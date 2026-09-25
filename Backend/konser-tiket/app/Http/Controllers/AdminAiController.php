<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organizer;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\ScanLog;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAiController extends Controller
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * AI aggregates and analyzes platform performance.
     */
    public function analyzePlatform(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        // 1. User metrics
        $usersCount = User::count();
        $rolesBreakdown = User::select('role', DB::raw('count(*) as count'))->groupBy('role')->get()->pluck('count', 'role')->toArray();

        // 2. Organizer metrics
        $totalOrganizers = Organizer::count();
        $verifiedOrganizers = Organizer::where('verified', true)->count();

        // 3. Event metrics
        $totalEvents = Event::count();
        $eventStatusBreakdown = Event::select('status', DB::raw('count(*) as count'))->groupBy('status')->get()->pluck('count', 'status')->toArray();

        // 4. Sales metrics
        $completedOrders = Order::where('status', 'completed')->count();
        $totalSalesValue = Order::where('status', 'completed')->sum('total');

        // 5. Scan entry metrics
        $totalTickets = Ticket::count();
        $scannedTickets = Ticket::where('status', 'scanned')->count();

        $prompt = "Anda adalah CTO & Penasihat Bisnis KetTiket.\n" .
            "Berikut adalah kumpulan data platform KetTiket teragregasi terbaru dari database MySQL:\n\n" .
            "1. Pengguna Platform:\n" .
            "   - Total Pengguna: {$usersCount}\n" .
            "   - Pembagian Role: Customer: " . ($rolesBreakdown['customer'] ?? 0) . " | Organizer: " . ($rolesBreakdown['organizer'] ?? 0) . " | Super Admin: " . ($rolesBreakdown['admin'] ?? 0) . "\n" .
            "2. Data Penyelenggara (Organizer):\n" .
            "   - Total Organizer Terdaftar: {$totalOrganizers}\n" .
            "   - Organizer Terverifikasi: {$verifiedOrganizers} (" . ($totalOrganizers > 0 ? round(($verifiedOrganizers / $totalOrganizers) * 100, 1) : 0) . "%)\n" .
            "3. Data Konser (Event):\n" .
            "   - Total Konser Terdaftar: {$totalEvents}\n" .
            "   - Pembagian Status: Draft: " . ($eventStatusBreakdown['draft'] ?? 0) . " | Published: " . ($eventStatusBreakdown['published'] ?? 0) . "\n" .
            "4. Kinerja Keuangan & Tiket:\n" .
            "   - Total Transaksi Berhasil: {$completedOrders}\n" .
            "   - Total Nilai Penjualan Platform: Rp " . number_format($totalSalesValue, 0, ',', '.') . "\n" .
            "   - Total Tiket Terbit: {$totalTickets}\n" .
            "   - Tiket Berhasil Di-scan Masuk: {$scannedTickets} (" . ($totalTickets > 0 ? round(($scannedTickets / $totalTickets) * 100, 1) : 0) . "%)\n\n" .
            "Berikan analisis tingkat tinggi dalam format markdown mengenai kinerja bisnis platform KetTiket, pertumbuhan kemitraan organizer, kualitas konversi penjualan, serta saran optimasi kapasitas server atau strategi retensi user untuk triwulan berikutnya.";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'admin_analyze_platform');

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
     * AI detects suspicious platform activities and logs check anomalies.
     */
    public function detectSuspicious(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin only.'], 403);
        }

        // 1. Transaction failure rate (orders with status = 'failed')
        $failedOrders = Order::where('status', 'failed')->count();
        $completedOrders = Order::where('status', 'completed')->count();
        $totalOrders = Order::count();

        // 2. Scan failures (duplicate check-in logs or rejections)
        // Find scan logs that failed (verified = false)
        $failedScans = ScanLog::where('verified', false)->count();
        $totalScans = ScanLog::count();

        // 3. Speed booking anomalies (multiple bookings by the same user within short intervals)
        $rapidBookings = Order::select('user_id', DB::raw('count(*) as order_count'))
            ->groupBy('user_id')
            ->having('order_count', '>', 5)
            ->count();

        $prompt = "Anda adalah Direktur Keamanan Informasi (CISO) KetTiket.\n" .
            "Evaluasi data aktivitas keamanan platform berikut untuk mendeteksi ancaman bot, scalper (tengkulak tiket), atau kegagalan sistem gate:\n\n" .
            "1. Metrik Transaksi Pembayaran:\n" .
            "   - Total Transaksi: {$totalOrders}\n" .
            "   - Transaksi Berhasil: {$completedOrders}\n" .
            "   - Transaksi Gagal/Dibatalkan: {$failedOrders} (" . ($totalOrders > 0 ? round(($failedOrders / $totalOrders) * 100, 1) : 0) . "%)\n" .
            "2. Aktivitas Gate Check-in:\n" .
            "   - Total Scan Tiket: {$totalScans}\n" .
            "   - Percobaan Scan Gagal/Tiket Duplikat: {$failedScans} (" . ($totalScans > 0 ? round(($failedScans / $totalScans) * 100, 1) : 0) . "%)\n" .
            "3. Anomali Pembelian Cepat (Spamming):\n" .
            "   - Jumlah Pengguna Melakukan >5 Transaksi: {$rapidBookings} Akun\n\n" .
            "Tuliskan laporan penilaian risiko (Risk Assessment Report) dalam format markdown. Jelaskan apakah ada indikasi bot scalping aktif, seberapa aman proses check-in saat ini, dan berikan 3 rekomendasi peningkatan mitigasi keamanan siber (misalnya Captcha saat checkout, batasan IP, atau integrasi enkripsi OTP).";

        $aiResult = $this->aiService->generateResponse($prompt, $request->user()->id, 'admin_detect_suspicious');

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
}
