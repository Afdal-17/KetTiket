<?php

namespace App\Http\Controllers;

use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\AIFeedback;
use App\Models\Event;
use App\Models\Order;
use App\Services\AiService;
use Illuminate\Http\Request;

class CustomerAiController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * List all customer chat conversations.
     */
    public function indexConversations(Request $request)
    {
        $conversations = AIConversation::where('user_id', $request->user()->id)
            ->where('context_type', 'customer')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'conversations' => $conversations
        ]);
    }

    /**
     * Create a new conversation thread.
     */
    public function storeConversation(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
        ]);

        $conversation = AIConversation::create([
            'user_id' => $request->user()->id,
            'title' => $request->input('title', 'Percakapan Baru'),
            'context_type' => 'customer',
        ]);

        return response()->json([
            'message' => 'Thread percakapan berhasil dibuat.',
            'conversation' => $conversation
        ], 201);
    }

    /**
     * Retrieve a specific conversation and its messages.
     */
    public function showConversation(Request $request, string $id)
    {
        $conversation = AIConversation::with(['messages.feedback'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$conversation) {
            return response()->json(['message' => 'Percakapan tidak ditemukan.'], 404);
        }

        return response()->json([
            'conversation' => $conversation
        ]);
    }

    /**
     * Delete a conversation thread.
     */
    public function destroyConversation(Request $request, string $id)
    {
        $conversation = AIConversation::where('user_id', $request->user()->id)->find($id);

        if (!$conversation) {
            return response()->json(['message' => 'Percakapan tidak ditemukan.'], 404);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Percakapan berhasil dihapus.'
        ]);
    }

    /**
     * Chat with the AI within a thread.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'conversation_id' => 'nullable|exists:ai_conversations,id',
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $conversationId = $request->input('conversation_id');
        $userText = $request->input('message');

        // Create thread if none provided
        if (!$conversationId) {
            $conversation = AIConversation::create([
                'user_id' => $user->id,
                'title' => substr($userText, 0, 30) . '...',
                'context_type' => 'customer',
            ]);
        } else {
            $conversation = AIConversation::where('user_id', $user->id)->find($conversationId);
            if (!$conversation) {
                return response()->json(['message' => 'Percakapan tidak ditemukan.'], 404);
            }
        }

        // 1. Save user message to database
        $userMessage = AIMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'message' => $userText,
        ]);

        // 2. Load conversation history (up to last 10 messages for context)
        $history = AIMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        // 3. Retrieve database context (Events available in platform)
        $events = Event::with(['venue', 'ticketTypes'])
            ->where('status', 'published')
            ->where('start_date', '>=', now())
            ->take(5)
            ->get()
            ->map(function ($event) {
                $tickets = $event->ticketTypes->map(function ($tt) {
                    return "{$tt->name} (Rp " . number_format($tt->price, 0, ',', '.') . " - Sisa Quota: {$tt->quota})";
                })->implode(', ');
                return "- **{$event->title}** pada {$event->start_date->format('d M Y H:i')} di {$event->venue->name} ({$event->venue->address}). Tiket: [{$tickets}]";
            })
            ->implode("\n");

        // 4. Construct rich contextual prompt
        $contextPrompt = "Anda adalah Asisten AI untuk aplikasi tiket konser bernama KetTiket.\n" .
            "Nama Pengguna saat ini: {$user->name} (Email: {$user->email}).\n\n" .
            "Berikut adalah daftar konser mendatang yang saat ini aktif di database kami:\n" .
            ($events ?: "Tidak ada konser aktif saat ini.") . "\n\n" .
            "Gunakan informasi di atas jika pengguna bertanya tentang konser, pemesanan, atau lokasi.\n" .
            "Riwayat percakapan saat ini:\n";

        foreach ($history as $msg) {
            $contextPrompt .= ucfirst($msg->role) . ": " . $msg->message . "\n";
        }

        $contextPrompt .= "Assistant: ";

        // 5. Send prompt to AI Service
        $aiResult = $this->aiService->generateResponse($contextPrompt, $user->id, 'customer_chat');

        // 6. Save assistant message to database
        $assistantMessage = AIMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'message' => $aiResult['text'],
        ]);

        // Update conversation updated_at
        $conversation->touch();

        return response()->json([
            'conversation_id' => $conversation->id,
            'user_message' => $userMessage,
            'ai_message' => $assistantMessage->load('feedback'),
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ], 201);
    }

    public function recommendations(Request $request)
    {
        $user = $request->user();

        // 1. Get purchase history
        $orders = Order::with(['event.venue', 'orderDetails.ticketType'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        $historyString = $orders->map(function ($order) {
            $items = $order->orderDetails->map(function ($od) {
                return "{$od->ticketType->name} (Rp " . number_format($od->price, 0, ',', '.') . ")";
            })->implode(', ');
            return "- Konser: {$order->event->title} di {$order->event->venue->name} (Tanggal: {$order->event->start_date->format('d M Y')}), Detail Tiket: [{$items}]";
        })->implode("\n");

        // 2. Get available events
        $events = Event::with(['venue', 'ticketTypes'])
            ->where('status', 'published')
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($event) {
                $tickets = $event->ticketTypes->map(function ($tt) {
                    return "{$tt->name} (Rp " . number_format($tt->price, 0, ',', '.') . ")";
                })->implode(', ');
                
                // Calculate average rating (if available) and price
                $avgPrice = $event->ticketTypes->avg('price');
                $earliestDate = $event->start_date->format('d M Y');
                $popularBadge = rand(1, 5) === 1 ? '🔥 Populer' : '✨ Baru';
                
                return "- **{$event->title}** pada {$earliestDate} di {$event->venue->name}. Tiket: [{$tickets}]. Harga mulai Rp " . number_format($avgPrice, 0, ',', '.') . " {$popularBadge}";
            })
            ->implode("\n");

        // 3. Build intelligent recommendation prompt
        $prompt = "Anda adalah mesin pemberi rekomendasi konser cerdas untuk aplikasi KetTiket.\n" .
            "Nama Pengguna: {$user->name}.\n\n" .
            "Histori pembelian tiket pengguna:\n" .
            ($historyString ?: "Belum pernah membeli tiket di KetTiket (Pengguna Baru).") . "\n\n" .
            "Daftar konser yang tersedia saat ini:\n" .
            ($events ?: "Tidak ada konser aktif saat ini.") . "\n\n" .
            "Tugas Anda:\n" .
            "Berikan rekomendasi konser terpersonal dan terstruktur dari daftar konser yang tersedia di atas yang paling cocok dengan histori/preferensi pengguna. Pertimbangkan:\n" .
            "1. Gaya musik/artis yang mereka sukai dari riwayat pembelian (jika ada)\n" .
            "2. Lokasi/geografi (concert proximity to user's city)\n" .
            "3. Harga anggaran (buat rekomendasi berbeda untuk tiket harga rendah, menengah, tinggi)\n" .
            "4. Format/format konser (indoor vs outdoor, akustik vs penuh band)\n" .
            "5. Jika pengguna baru, rekomendasikan konser populer/terbaru dan berikan alasan kenapa mereka harus mencoba.\n\n" .
            "Struktur jawaban yang elegan:\n" .
            "🎯 **Rekomendasi Utama**: Konser yang sangat direkomendasikan berdasarkan preferensi\n" .
            "⭐ **Pilihan Alternatif**: 2-3 konser lain yang masih relevan\n" .
            "💡 **Alasan Pilihan**: Jelaskan mengapa setiap konser direkomendasikan\n" .
            "📅 **Tanggal & Lokasi**: Detail jadwal dan alamat\n" .
            "💰 **Harga Tiket**: Rincian harga dan kategori\n\n" .
            "Gunakan bahasa yang ramah dan bersemangat. Maksimum 300 kata. Fokus pada personalisasi dan kegembiraan konser.";

        $aiResult = $this->aiService->generateResponse($prompt, $user->id, 'customer_recommendation');

        return response()->json([
            'recommendations' => $aiResult['text'],
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ]);
    }

    /**
     * Get personalized AI concert recommendations based on user behavior and trends.
     */
    public function getPersonalizedRecommendations(Request $request)
    {
        $user = $request->user();

        $prompt = "Anda adalah pakar rekomendasi konser AI untuk aplikasi KetTiket.\n" .
            "Anda memiliki akses ke:\n" .
            "1. Riwayat pembelian pengguna: {$user->name} dan minat mereka\n" .
            "2. Data konser populer dan tren saat ini (high-demand venues, artis terkenal)\n" .
            "3. Jam tangan profil pengguna (jam aktif, waktu kesukaan)\n\n" .
            "Tugas: Berikan 3 rekomendasi konser pribadi yang " + ($user->role === 'customer' ? 'sangat relevan secara pribadi' : 'paling sesuai untuk kebutuhan organizer') + "\n" .
            "Format:\n" .
            "🎵 **Konser Khusus untuk Anda**: [Nama konser] - $user->role === 'customer' ? 'Rekomendasi pribadi berdasarkan minat Anda' : 'Rekomendasi untuk meningkatkan performa event organizer Anda'\n" .
            "📅 **Waktu Ideal**: [jam & hari] - kapan Anda biasanya aktif\n" .
            "🎫 **Tiket & Harga**: [Kategori dan harga]\n" .
            "🌟 **Mengapa Direkomendasikan**: [penjelasan personal]\n\n" .
            "Jadilah antusias dan berikan saran actionable yang mudah diikuti.";

        $aiResult = $this->aiService->generateResponse($prompt, $user->id, 'personalized_recommendation');

        return response()->json([
            'personalized_recommendations' => $aiResult['text'],
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ]);
    }

    /**
     * Submit feedback for an assistant response message.
     */
    public function submitFeedback(Request $request, string $messageId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $message = AIMessage::with('conversation')->find($messageId);

        if (!$message || $message->role !== 'assistant') {
            return response()->json(['message' => 'Pesan asisten tidak ditemukan.'], 404);
        }

        // Verify message belongs to the current authenticated user's thread
        if ($message->conversation->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $feedback = AIFeedback::updateOrCreate(
            ['message_id' => $message->id],
            [
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]
        );

        return response()->json([
            'message' => 'Umpan balik berhasil disimpan.',
            'feedback' => $feedback
        ]);
    }
}
