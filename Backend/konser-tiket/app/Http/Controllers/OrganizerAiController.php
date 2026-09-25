<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Ticket;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerAiController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Helper to verify if user owns the event or is an admin.
     */
    protected function authorizeEvent(Request $request, string $eventId)
    {
        $user = $request->user();
        $event = Event::with('organizer')->find($eventId);

        if (!$event) {
            abort(404, 'Event tidak ditemukan.');
        }

        if ($user->role === 'admin') {
            return $event;
        }

        if ($user->role !== 'organizer' || !$user->organizer || $event->organizer_id !== $user->organizer->id) {
            abort(403, 'Unauthorized. Anda hanya dapat menganalisis event milik Anda sendiri.');
        }

        return $event;
    }

    /**
     * Resolve event yang akan dianalisis: pakai event_id bila dikirim,
     * jika tidak — otomatis pilih event milik user yang paling dekat
     * (atau event terbaru bila semua sudah lewat).
     */
    protected function resolveEvent(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'organizer' && $user->role !== 'admin') {
            abort(403, 'Unauthorized. Organizer only.');
        }

        if ($request->filled('event_id')) {
            return $this->authorizeEvent($request, (string) $request->event_id);
        }

        $query = Event::query();
        if ($user->role === 'organizer') {
            $query->where('organizer_id', $user->organizer ? $user->organizer->id : 0);
        }

        $now = now();

        return (clone $query)->where('start_date', '>=', $now)->orderBy('start_date')->first()
            ?? (clone $query)->orderByDesc('start_date')->first();
    }

    /**
     * Respons ramah ketika organizer belum punya event sama sekali.
     */
    protected function noEventReply(string $key)
    {
        return response()->json([
            $key => "⚠️ **Belum ada event** pada akun Anda untuk dianalisis saat ini.\n\nSilakan **buat konser terlebih dahulu** melalui menu *Buat Konser Baru*, lalu jalankan kembali fitur ini agar AI dapat membaca data tiket Anda.",
            'metrics' => [],
        ]);
    }

    /**
     * AI generates event description based on title and genre.
     */
    public function generateDescription(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'organizer' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Organizer only.'], 403);
        }

        $request->validate([
            'title' => 'nullable|string|max:100',
            'genre' => 'nullable|string|max:50',
            'event_id' => 'nullable|exists:events,id',
        ]);

        $title = $request->input('title');
        $genre = $request->input('genre', 'Musik Umum');

        // Judul tidak dikirim → ambil dari event milik organizer (terdekat/terbaru)
        if (!$title) {
            $event = $this->resolveEvent($request);
            if (!$event) {
                return $this->noEventReply('description');
            }
            $title = $event->title;
        }

        $prompt = "Anda adalah Copywriter Pemasaran Profesional untuk KetTiket.\n" .
            "Buatkan deskripsi acara yang sangat menarik, premium, dan persuasif untuk konser musik berikut:\n" .
            "- Judul Konser: {$title}\n" .
            "- Genre Musik: {$genre}\n\n" .
            "Tulis deskripsi dalam format markdown yang rapi, mencakup:\n" .
            "1. Pengenalan singkat yang menggugah emosi/antusiasme.\n" .
            "2. Highlight utama mengapa penonton harus datang.\n" .
            "3. Himbauan agar penonton segera mengamankan tiket mereka.\n" .
            "Jaga bahasa tetap profesional, bersemangat, dan ramah (bahasa Indonesia).";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'organizer_generate_description');

        return response()->json([
            'description' => $aiResult['text'],
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ]);
    }

    /**
     * AI analyzes event sales.
     */
    public function analyzeSales(Request $request)
    {
        $request->validate(['event_id' => 'nullable|exists:events,id']);
        $event = $this->resolveEvent($request);
        if (!$event) {
            return $this->noEventReply('analysis');
        }

        // Fetch sales metrics from database
        $ticketTypes = $event->ticketTypes;
        $totalQuota = $ticketTypes->sum('quota');
        
        $salesDetails = OrderDetail::whereHas('order', function($q) use ($event) {
                $q->where('event_id', $event->id)->where('status', 'completed');
            })
            ->select('ticket_type_id', DB::raw('count(*) as sold_count'), DB::raw('sum(price) as earnings'))
            ->groupBy('ticket_type_id')
            ->get();

        $salesSummary = "";
        $totalEarned = 0;
        $totalSold = 0;

        foreach ($ticketTypes as $type) {
            $sold = $salesDetails->where('ticket_type_id', $type->id)->first();
            $soldCount = $sold ? $sold->sold_count : 0;
            $earnings = $sold ? $sold->earnings : 0;
            $totalEarned += $earnings;
            $totalSold += $soldCount;

            $salesSummary .= "- **{$type->name}**: Harga Rp " . number_format($type->price, 0, ',', '.') . " | Terjual: {$soldCount} / Kuota Asli: " . ($type->quota + $soldCount) . " (Sisa: {$type->quota})\n";
        }

        $prompt = "Anda adalah Analis Bisnis & Penjualan Konser untuk KetTiket.\n" .
            "Misi Anda adalah menganalisis kinerja penjualan dari konser berikut dan memberikan rekomendasi strategis:\n" .
            "Nama Konser: {$event->title}\n" .
            "Lokasi: {$event->venue->name}\n" .
            "Tanggal: {$event->start_date->format('d M Y')}\n\n" .
            "Kinerja Penjualan Tiket saat ini:\n" .
            $salesSummary .
            "- Total Terjual: {$totalSold} tiket\n" .
            "- Total Pendapatan Kotor: Rp " . number_format($totalEarned, 0, ',', '.') . "\n\n" .
            "Berikan ulasan singkat mengenai kecepatan penjualan, evaluasi kategori tiket mana yang paling sukses, dan rekomendasikan minimal 2 taktik promosi/marketing untuk mempercepat penjualan kategori tiket yang tersisa. Tuliskan dalam format markdown terstruktur.";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'organizer_analyze_sales');

        return response()->json([
            'analysis' => $aiResult['text'],
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ]);
    }

    /**
     * AI analyzes buyer demographics and transaction timing trends.
     */
    public function analyzeTrends(Request $request)
    {
        $request->validate(['event_id' => 'nullable|exists:events,id']);
        $event = $this->resolveEvent($request);
        if (!$event) {
            return $this->noEventReply('trends');
        }

        // Fetch user demographics (via email domain) & payment gateway details
        $orders = Order::with(['user', 'payment'])
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->get();

        $gateways = $orders->pluck('payment.gateway')->filter()->groupBy(function($g) { return $g; })->map->count();
        $emailDomains = $orders->pluck('user.email')->map(function($email) {
            $parts = explode('@', $email);
            return end($parts);
        })->groupBy(function($d) { return $d; })->map->count();

        // Count purchases by hour
        $purchaseHours = $orders->map(function($o) {
            return $o->created_at->format('H') . ':00';
        })->groupBy(function($h) { return $h; })->map->count();

        $gatewaySummary = "";
        foreach ($gateways as $gw => $count) {
            $gatewaySummary .= "- {$gw}: {$count} transaksi\n";
        }

        $domainSummary = "";
        foreach ($emailDomains as $dom => $count) {
            $domainSummary .= "- Domain @{$dom}: {$count} pembeli\n";
        }

        $hourSummary = "";
        foreach ($purchaseHours->take(5) as $hr => $count) {
            $hourSummary .= "- Jam {$hr}: {$count} transaksi\n";
        }

        $prompt = "Anda adalah Spesialis Riset Pemasaran untuk KetTiket.\n" .
            "Analisis data demografi dan tren pembelian tiket berikut untuk konser: \"{$event->title}\".\n\n" .
            "Data transaksi pembeli:\n" .
            "1. Distribusi Metode Pembayaran:\n" . ($gatewaySummary ?: "- Belum ada data\n") .
            "2. Profil Email Pembeli:\n" . ($domainSummary ?: "- Belum ada data\n") .
            "3. Puncak Waktu Pembelian (Top 5 Jam Terpadat):\n" . ($hourSummary ?: "- Belum ada data\n") . "\n" .
            "Buat analisis yang mengidentifikasi tipe pembeli mayoritas (misal: korporat/instansi vs perorangan/mahasiswa berdasarkan profil email), preferensi pembayaran mereka, serta rekomendasi waktu terbaik untuk mempublikasikan materi iklan media sosial berdasarkan jam transaksi terpadat. Buat dalam format markdown.";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'organizer_analyze_trends');

        return response()->json([
            'trends' => $aiResult['text'],
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ]);
    }

    /**
     * AI recommends ticket price distribution & campaigns.
     */
    public function ticketRecommendation(Request $request)
    {
        $request->validate(['event_id' => 'nullable|exists:events,id']);
        $event = $this->resolveEvent($request);
        if (!$event) {
            return $this->noEventReply('recommendations');
        }

        $ticketTypes = $event->ticketTypes;
        $ticketSummary = "";
        foreach ($ticketTypes as $type) {
            $ticketSummary .= "- Kategori: {$type->name} | Harga: Rp " . number_format($type->price, 0, ',', '.') . " | Sisa Stok: {$type->quota}\n";
        }

        $prompt = "Anda adalah Konsultan Manajemen Harga Tiket (Dynamic Pricing Consultant) KetTiket.\n" .
            "Silakan tinjau sisa alokasi tiket berikut untuk konser \"{$event->title}\":\n" .
            $ticketSummary . "\n" .
            "Berikan rekomendasi penyesuaian harga atau kampanye bundel (seperti tiket early bird, buy 2 get 1, diskon pelajar, dll) untuk memaksimalkan sisa kuota yang belum terjual. Fokuskan saran Anda pada taktik meningkatkan konversi tanpa menurunkan citra premium konser. Berikan jawaban dalam bentuk markdown terstruktur.";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'organizer_ticket_recommendation');

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
     * AI generates event performance executive report.
     */
    public function generateReport(Request $request)
    {
        $request->validate(['event_id' => 'nullable|exists:events,id']);
        $event = $this->resolveEvent($request);
        if (!$event) {
            return $this->noEventReply('report');
        }

        $orders = Order::where('event_id', $event->id)->where('status', 'completed')->get();
        $totalSold = $orders->count();
        $totalRevenue = $orders->sum('total');

        // Check gate entry / scan rates
        $scannedCount = Ticket::whereHas('orderDetail.order', function($q) use ($event) {
            $q->where('event_id', $event->id);
        })
        ->where('status', 'scanned')
        ->count();

        $activeCount = Ticket::whereHas('orderDetail.order', function($q) use ($event) {
            $q->where('event_id', $event->id);
        })
        ->where('status', 'active')
        ->count();

        $prompt = "Anda adalah COO KetTiket yang sedang menyusun laporan eksekutif pasca-event.\n" .
            "Buatkan ringkasan laporan formal (Executive Summary Report) untuk acara:\n" .
            "- Nama Konser: {$event->title}\n" .
            "- Penyelenggara: {$event->organizer->company_name}\n" .
            "- Total Tiket Terjual: {$totalSold}\n" .
            "- Pendapatan Kotor: Rp " . number_format($totalRevenue, 0, ',', '.') . "\n" .
            "- Jumlah Check-in Penonton di Gate: {$scannedCount} orang (Sisa tiket aktif belum masuk: {$activeCount})\n\n" .
            "Laporan harus ringkas, profesional, dan dalam format markdown. Cakup ringkasan finansial, efisiensi operasional pintu masuk (gate check-in), serta saran perbaikan infrastruktur tiket atau tata ruang area penonton untuk konser berikutnya.";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'organizer_generate_report');

        return response()->json([
            'report' => $aiResult['text'],
            'metrics' => [
                'model' => $aiResult['model'],
                'prompt_tokens' => $aiResult['prompt_tokens'],
                'completion_tokens' => $aiResult['completion_tokens'],
                'source' => $aiResult['source']
            ]
        ]);
    }
}
