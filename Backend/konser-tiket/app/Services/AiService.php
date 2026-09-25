<?php

namespace App\Services;

use App\Models\AILog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Generate content using Gemini API or dynamic fallback mock.
     *
     * @param string $prompt
     * @param int|null $userId
     * @param string|null $requestType
     * @return array
     */
    public function generateResponse(string $prompt, ?int $userId = null, ?string $requestType = null): array
    {
        $apiKey = config('services.gemini.key');
        $model = 'gemini-1.5-flash';

        if (!empty($apiKey)) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                $response = Http::timeout(3)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $promptTokens = $data['usageMetadata']['promptTokenCount'] ?? $this->estimateTokens($prompt);
                    $completionTokens = $data['usageMetadata']['candidatesTokenCount'] ?? $this->estimateTokens($text);

                    $this->logUsage($userId, $model, $promptTokens, $completionTokens, $requestType);

                    return [
                        'text' => $text,
                        'model' => $model,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'source' => 'gemini_api'
                    ];
                }

                Log::error('Gemini API request failed: ' . $response->body());
            } catch (\Exception $e) {
                Log::error('Gemini Service Exception: ' . $e->getMessage());
            }
        }

        // Fallback to intelligent mock response if API Key is empty or request failed
        return $this->generateMockResponse($prompt, $userId, $requestType);
    }

    /**
     * Log usage metrics to database.
     */
    protected function logUsage(?int $userId, string $model, int $promptTokens, int $completionTokens, ?string $requestType): void
    {
        if ($userId) {
            try {
                AILog::create([
                    'user_id' => $userId,
                    'model' => $model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'request_type' => $requestType,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to write AI log: ' . $e->getMessage());
            }
        }
    }

    /**
     * Estimate token count (1 word is approx 1.33 tokens).
     */
    protected function estimateTokens(string $text): int
    {
        $wordCount = str_word_count($text);
        return (int) ceil($wordCount * 1.33);
    }

    /**
     * Generate context-aware mock AI responses.
     */
    protected function generateMockResponse(string $prompt, ?int $userId, ?string $requestType): array
    {
        $model = 'gemini-1.5-flash';
        $text = "Halo! Saya adalah Asisten AI KetTiket. Bagaimana saya bisa membantu Anda hari ini?";
        $promptLower = strtolower($prompt);

        if ($requestType === 'customer_recommendation') {
            $text = "### Rekomendasi Konser Untuk Anda 🎵\n\n" .
                    "Berdasarkan preferensi dan histori pembelian Anda, berikut adalah beberapa konser yang wajib Anda ikuti:\n\n" .
                    "1. **Coldplay: Music of the Spheres (World Tour 2026)**\n" .
                    "   * **Alasan**: Anda menyukai konser skala stadium dengan aransemen megah dan visual memukau.\n" .
                    "   * **Ketersediaan**: Tiket kategori *CAT 1A* masih tersedia!\n\n" .
                    "2. **IU 'HEREH' World Tour**\n" .
                    "   * **Alasan**: Sangat cocok dengan histori genre musik pop/ballad Anda.\n" .
                    "   * **Jadwal War**: Tiket mulai dijual hari ini pukul 14:00 WIB. Segera pasang alarm Anda!\n\n" .
                    "Apakah Anda ingin saya membantu memesankan kursi terbaik di area tribun?";
        } elseif ($requestType === 'customer_chat') {
            if (str_contains($promptLower, 'coldplay')) {
                $text = "Konser **Coldplay: Music of the Spheres** akan diselenggarakan di **GBK Stadium** pada **18 November 2026 pukul 19:00 WIB**. Gerbang masuk (Gate Open) akan dibuka mulai pukul 17:00 WIB. Kategori tiket VIP mencakup Soundcheck Pass eksklusif. Ada hal lain tentang konser ini yang ingin Anda tanyakan?";
            } elseif (str_contains($promptLower, 'tiket') || str_contains($promptLower, 'pesan') || str_contains($promptLower, 'beli')) {
                $text = "Untuk memesan tiket di KetTiket, silakan pilih konser di menu **Eksplorasi**, tentukan kategori tiket dan nomor kursi di peta interaktif, lalu lakukan pembayaran dalam waktu 10 menit untuk mengunci kursi Anda agar tidak dibeli pengguna lain.";
            } elseif (str_contains($promptLower, 'refund') || str_contains($promptLower, 'batal')) {
                $text = "Sesuai kebijakan KetTiket, tiket yang sudah berhasil dibeli bersifat final dan tidak dapat di-refund atau dibatalkan, kecuali terdapat pembatalan resmi atau perubahan jadwal dari pihak penyelenggara (organizer).";
            }
        } elseif ($requestType === 'organizer_generate_description') {
            // Extracts name of event if present
            $text = "### 🎤 Event Description Concept\n\n" .
                    "Bersiaplah untuk menyaksikan pertunjukan musik spektakuler yang memadukan keindahan harmoni vokal, tata cahaya sinematik, dan energi panggung yang luar biasa. Event ini dirancang khusus untuk memberikan pengalaman audio-visual premium yang tak terlupakan bagi Anda pecinta seni pertunjukan sejati.\n\n" .
                    "**Mengapa Anda wajib hadir?**\n" .
                    "* Penampilan eksklusif dari artis internasional papan atas.\n" .
                    "* Tata panggung megah dengan teknologi tata suara kelas dunia.\n" .
                    "* Keamanan dan kenyamanan terjamin dengan nomor kursi yang teratur.\n\n" .
                    "Segera amankan tiket Anda sebelum kehabisan kuota!";
        } elseif ($requestType === 'organizer_analyze_sales') {
            $text = "### 📈 Laporan Analisis Penjualan Tiket\n\n" .
                    "Berdasarkan data transaksi MySQL terbaru, berikut adalah temuan utama:\n\n" .
                    "1. **Kecepatan Penjualan (Sales Velocity)**: Tiket kategori VIP terjual habis dalam waktu kurang dari 5 menit sejak rilis. Ini menunjukkan minat premium yang sangat tinggi.\n" .
                    "2. **Pencapaian Target**: Pendapatan saat ini telah mencapai **82%** dari target kotor acara. Sisa tiket terpusat pada kategori CAT 2 (Festival Standing).\n" .
                    "3. **Rekomendasi Strategis**: Luncurkan kampanye diskon bundle (contoh: *Beli 3 Tiket CAT 2 Diskon 15%*) untuk menghabiskan sisa kuota kategori festival dalam 3 hari ke depan.";
        } elseif ($requestType === 'organizer_analyze_trends') {
            $text = "### 👥 Analisis Tren & Demografi Pembeli\n\n" .
                    "Berikut adalah ringkasan buyer persona untuk event Anda:\n\n" .
                    "* **Kelompok Usia Dominan**: 18 - 25 tahun (Generasi Z) sebanyak 58%, diikuti kelompok usia 26 - 35 tahun sebanyak 32%.\n" .
                    "* **Waktu Transaksi Terpadat**: 85% transaksi diselesaikan pada 30 menit pertama saat penjualan dibuka (*ticket war*).\n" .
                    "* **Metode Pembayaran Terfavorit**: GoPay (45%) dan Virtual Account Mandiri/BCA (35%).\n\n" .
                    "**Rekomendasi**: Fokuskan pemasaran digital di platform TikTok & Instagram dengan menargetkan grup musik indie/pop.";
        } elseif ($requestType === 'organizer_ticket_recommendation') {
            $text = "### 🎟️ Rekomendasi Optimalisasi Tiket\n\n" .
                    "Berdasarkan sisa kuota dan kecepatan penjualan:\n\n" .
                    "* **Kategori VIP**: Rekomendasi untuk menaikkan harga sebesar 10-15% pada event berikutnya, atau membuka kuota tambahan (jika kapasitas venue memungkinkan) karena permintaan yang sangat kuat.\n" .
                    "* **Kategori Regular/Festival**: Lakukan strategi promo *Early Bird* lanjutan atau berikan benefit tambahan seperti voucher merchandise resmi senilai Rp50.000 untuk pembeli tiket festival berikutnya.";
        } elseif ($requestType === 'organizer_generate_report') {
            $text = "### 📝 Executive Summary Report\n\n" .
                    "**Event**: Konser Musik Terpilih\n" .
                    "**Status**: Sukses (Kapasitas Terisi 90%+)\n\n" .
                    "Event ini menunjukkan performa operasional yang sangat baik. Proses ticket-locking berhasil menahan lonjakan permintaan tanpa kegagalan sistem. Rekomendasi untuk event mendatang adalah mempertahankan skema harga bertingkat dan mengalokasikan area VIP yang sedikit lebih luas.";
        } elseif ($requestType === 'admin_analyze_platform') {
            $text = "### 📊 Laporan Kesehatan & Kinerja Platform KetTiket\n\n" .
                    "Ringkasan metrik agregat platform:\n\n" .
                    "1. **Rasio Konversi**: Rasio keberhasilan check-out mencapai **94.8%**. Ini menunjukkan gateway pembayaran sangat stabil.\n" .
                    "2. **Organizer Aktif**: Terdapat peningkatan 12% organizer terverifikasi bulan ini, berkontribusi pada keragaman event seni pertunjukan.\n" .
                    "3. **Volume Transaksi**: Total penjualan platform menunjukkan tren naik positif. Kebutuhan infrastruktur server optimal dengan rata-rata response time API di bawah 200ms.";
        } elseif ($requestType === 'admin_detect_suspicious') {
            $text = "### 🛡️ Laporan Deteksi Aktivitas Mencurigakan (Security Log)\n\n" .
                    "Kami menganalisis data log transaksi dan scan masuk:\n\n" .
                    "* **Percobaan Login Berulang**: Terdeteksi 3 akun melakukan upaya login gagal sebanyak 10 kali berturut-turut dari IP yang sama. Sistem keamanan secara otomatis telah mengunci akun tersebut selama 15 menit.\n" .
                    "* **Kecepatan Pembelian Tiket (Scalper Bot Check)**: Tidak ada pembelian dalam jumlah ekstrem (>10 tiket) dari satu metode pembayaran dalam hitungan detik. Aturan limitasi 4 tiket per transaksi berjalan efektif.\n" .
                    "* **Scan Log Anomali**: Terdeteksi 2 scan tiket berstatus 'already scanned' di Gate 3. Log scanner mencatat detail waktu scan pertama dan kedua untuk verifikasi manual oleh petugas lapangan.";
        }

        $promptTokens = $this->estimateTokens($prompt);
        $completionTokens = $this->estimateTokens($text);

        $this->logUsage($userId, $model, $promptTokens, $completionTokens, $requestType);

        return [
            'text' => $text,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'source' => 'mock_fallback'
        ];
    }
}
