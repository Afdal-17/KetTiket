<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

/**
 * Bot Assistant KetTiket — mesin jawaban berbasis aturan (rule-based).
 *
 * Seluruh jawaban diambil langsung dari data website ini (event, tiket, venue),
 * tanpa memanggil API AI eksternal. Alur: normalisasi teks -> deteksi intent
 * via kamus kata kunci -> susun jawaban dari database -> kirim kartu event +
 * saran pertanyaan lanjutan (chips) ke frontend.
 */
class ChatbotController extends Controller
{
    /** @var \Illuminate\Support\Collection|null cache event published */
    private $eventsCache = null;

    private array $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private array $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Kata umum pada judul event — bukan penanda event spesifik. */
    private array $genericWords = [
        'music', 'musik', 'fest', 'festival', 'konser', 'concert', 'live', 'show',
        'tour', 'party', 'demo', 'tahun', 'special', 'night', 'session', 'the', 'and',
    ];

    /**
     * Kamus intent. Urutan array = prioritas saat skor seri.
     * Kata kunci multi-kata (ada spasi) bernilai skor2, kata tunggal skor1.
     */
    private array $intents = [
        'greeting' => ['halo', 'hai', 'hi', 'hello', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'],
        'thanks' => ['terima kasih', 'makasih', 'thanks', 'thx'],
        'bye' => ['dadah', 'bye', 'sampai jumpa', 'see you', 'selamat tinggal'],
        'recommend' => ['rekomendasi', 'rekomendasikan', 'rekomendasiin', 'recommend', 'recommendation', 'saran konser', 'sarankan', 'yang bagus', 'yg bagus', 'terbaik', 'paling cocok', 'pilihkan'],
        'price' => ['harga', 'biaya', 'tarif', 'price', 'rupiah', 'murah', 'termurah', 'mahal', 'berapa harga', 'berapa harganya', 'how much', 'harga tiket', 'tiket berapa', 'price list'],
        'schedule' => ['jadwal', 'kapan', 'tanggal', 'jam berapa', 'waktu', 'dimulai', 'mulai jam', 'selesai jam', 'when', 'schedule', 'hari apa', 'bulan berapa', 'jam berapa', 'pukul berapa'],
        'venue' => ['dimana', 'di mana', 'lokasi', 'alamat', 'venue', 'tempat', 'gedung', 'where', 'address', 'kota', 'berada di'],
        'buy' => ['cara beli', 'cara membeli', 'cara pesan', 'beli tiket', 'pesan tiket', 'pemesanan', 'membeli', 'pembelian', 'checkout', 'cara order', 'beli', 'pesan', 'buy', 'purchase', 'dapat tiket', 'dapet tiket', 'titip beli'],
        'payment' => ['bayar', 'pembayaran', 'payment', 'transfer', 'qris', 'gopay', 'ovo', 'dana', 'midtrans', 'virtual account', 'kartu kredit', 'e-wallet', 'dompet digital', 'cara bayar', 'metode bayar'],
        'eticket' => ['e-tiket', 'etiket', 'e ticket', 'tiket digital', 'qr', 'barcode', 'bar code', 'kode tiket', 'tiket saya', 'unduh tiket', 'cetak tiket', 'eticket'],
        'checkin' => ['check in', 'checkin', 'check-in', 'gate', 'scanner', 'scan', 'pindai', 'masuk venue', 'validasi tiket', 'di scan', 'dipindai', 'pintu masuk'],
        'seats' => ['kursi', 'seat', 'seats', 'section', 'tribun', 'duduk', 'kapasitas', 'seat map', 'pilih kursi', 'denah'],
        'account' => ['akun', 'daftar akun', 'registrasi', 'register', 'login', 'lupa password', 'ganti password', 'buat akun', 'sign up', 'masuk akun', 'password', 'verifikasi email'],
        'organizer_info' => ['jadi organizer', 'penyelenggara', 'event organizer', 'organizer', 'bikin event', 'buat event', 'menyelenggarakan', 'daftar jadi organizer', 'panitia'],
        'refund' => ['refund', 'batal', 'dibatalkan', 'cancel', 'pembatalan', 'uang kembali'],
        'promo' => ['promo', 'diskon', 'voucher', 'potongan', 'kode promo', 'coupon', 'cashback'],
        'contact' => ['kontak', 'cs', 'customer service', 'hubungi', 'telepon', 'komplain', 'keluhan', 'lapor masalah'],
        'stats' => ['jumlah event', 'berapa event', 'berapa konser', 'total event', 'statistik', 'data event'],
        'help' => ['bantuan', 'help', 'panduan', 'cara pakai', 'fitur apa', 'bisa tanya apa', 'apa saja yang bisa', 'gimana cara', 'bagaimana cara', 'minta tolong', 'bisa bantu', 'kamu bisa apa', 'bisa ngapain'],
        'list_events' => ['event', 'konser', 'acara', 'ada apa', 'apa saja', 'lineup', 'gigs', 'jadwal acara', 'semua event', 'daftar event', 'event apa', 'tampilkan event'],
    ];

    /** Terima pertanyaan pengguna, kembalikan balasan terstruktur. */
    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $q = mb_strtolower(trim($request->input('message')));
        $q = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $q = trim(preg_replace('/\s+/', ' ', $q));

        $events = $this->publishedEvents();
        $padded = ' ' . $q . ' ';

        if ($q === '') {
            return response()->json($this->replyFallback());
        }

        //1) Judul event lengkap disebut -> detail event tersebut.
        foreach ($events as $e) {
            $title = mb_strtolower($e->title);
            if (mb_strlen($title) >= 4 && str_contains($q, $title)) {
                return response()->json($this->replyDetail($e));
            }
        }

        //2) Kata judul spesifik (mis. "coldplay") -> detail event, mengalahkan intent umum.
        foreach ($events as $e) {
            preg_match_all('/\p{L}{5,}/u', mb_strtolower($e->title), $m);
            foreach ($m[0] as $w) {
                if (in_array($w, $this->genericWords, true)) {
                    continue;
                }
                if (str_contains($padded, ' ' . $w . ' ')) {
                    return response()->json($this->replyDetail($e));
                }
            }
        }

        //3) Skoring intent via kamus kata kunci.
        $best = null;
        $score = 0;
        foreach ($this->intents as $intent => $keywords) {
            $s = 0;
            foreach ($keywords as $kw) {
                if (str_contains($padded, ' ' . $kw . ' ')) {
                    $s += str_contains($kw, ' ') ? 2 : 1;
                }
            }
            if ($s > $score) {
                $score = $s;
                $best = $intent;
            }
        }

        if ($best !== null && $score > 0) {
            return response()->json($this->dispatch($best));
        }

        //4) Hanya kata umum judul event yang disebut (mis. "music") -> tampilkan daftar.
        foreach ($events as $e) {
            foreach ($this->genericWords as $w) {
                if (str_contains($padded, ' ' . $w . ' ') && str_contains(mb_strtolower($e->title), $w)) {
                    return response()->json($this->replyList());
                }
            }
        }

        return response()->json($this->replyFallback());
    }

    private function dispatch(string $intent): array
    {
        return match ($intent) {
            'greeting' => $this->replyGreeting(),
            'thanks' => $this->replyThanks(),
            'bye' => $this->replyBye(),
            'recommend' => $this->replyRecommend(),
            'price' => $this->replyPrice(),
            'schedule' => $this->replySchedule(),
            'venue' => $this->replyVenue(),
            'buy' => $this->replyBuy(),
            'payment' => $this->replyPayment(),
            'eticket' => $this->replyEticket(),
            'checkin' => $this->replyCheckin(),
            'seats' => $this->replySeats(),
            'account' => $this->replyAccount(),
            'organizer_info' => $this->replyOrganizer(),
            'refund' => $this->replyRefund(),
            'promo' => $this->replyPromo(),
            'contact' => $this->replyContact(),
            'stats' => $this->replyStats(),
            'help' => $this->replyHelp(),
            'list_events' => $this->replyList(),
            default => $this->replyFallback(),
        };
    }

    // ------------------------------------------------------------------ DATA

    private function publishedEvents()
    {
        if ($this->eventsCache === null) {
            $this->eventsCache = Event::with(['venue', 'ticketTypes'])
                ->where('status', 'published')
                ->orderBy('start_date')
                ->get();
        }
        return $this->eventsCache;
    }

    private function endOf($e)
    {
        return $e->end_date ?? $e->start_date;
    }

    private function isUpcoming($e): bool
    {
        $end = $this->endOf($e);
        return !$end || !$end->isPast();
    }

    private function upcoming()
    {
        return $this->publishedEvents()->filter(fn ($e) => $this->isUpcoming($e))->values();
    }

    private function past()
    {
        return $this->publishedEvents()->filter(fn ($e) => !$this->isUpcoming($e))->values();
    }

    private function wib($d)
    {
        return $d ? $d->copy()->setTimezone('Asia/Jakarta') : null;
    }

    private function fmtDay($d): string
    {
        $d = $this->wib($d);
        if (!$d) {
            return 'jadwal menyusul';
        }
        return $this->days[(int) $d->format('w')] . ', ' . $d->format('j') . ' '
            . $this->months[(int) $d->format('n')] . ' ' . $d->format('Y');
    }

    private function fmtTime($d): string
    {
        $d = $this->wib($d);
        return $d ? $d->format('H:i') : '--:--';
    }

    private function fmtRange($e): string
    {
        $start = $e->start_date;
        $end = $this->endOf($e);
        if (!$start) {
            return 'Jadwal menyusul';
        }
        $line = $this->fmtDay($start) . ' pukul ' . $this->fmtTime($start) . ' WIB';
        if ($end) {
            if ($this->fmtDay($end) === $this->fmtDay($start)) {
                $line .= '–' . $this->fmtTime($end) . ' WIB';
            } else {
                $line .= ' s/d ' . $this->fmtDay($end) . ' ' . $this->fmtTime($end) . ' WIB';
            }
        }
        return $line;
    }

    private function rp($n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }

    private function venueLine($e): string
    {
        if ($e->venue) {
            $parts = [$e->venue->name];
            if ($e->venue->address) {
                $parts[] = $e->venue->address;
            }
            if ($e->venue->city && !str_contains((string) $e->venue->address, $e->venue->city)) {
                $parts[] = $e->venue->city;
            }
            return implode(', ', $parts);
        }
        return $e->location_name ?: 'Lokasi menyusul';
    }

    private function priceFrom($e): ?float
    {
        $prices = $e->ticketTypes->pluck('price')->filter(fn ($p) => $p !== null);
        return $prices->count() ? (float) $prices->min() : null;
    }

    /** Daftar kategori tiket satu baris per event. */
    private function ticketLines($e): string
    {
        if ($e->ticketTypes->isEmpty()) {
            return 'Kategori tiket belum ditentukan.';
        }
        $out = [];
        foreach ($e->ticketTypes->sortBy('price') as $tt) {
            $line = $tt->name . ': ' . $this->rp($tt->price);
            if ($tt->quota !== null) {
                $line .= ' (kuota ' . number_format($tt->quota) . ')';
            }
            $out[] = $out === [] ? $line : '   • ' . $line;
        }
        return implode("\n", $out);
    }

    private function ticketInline($e): string
    {
        if ($e->ticketTypes->isEmpty()) {
            return 'menyusul';
        }
        $names = [];
        foreach ($e->ticketTypes->sortBy('price') as $tt) {
            $names[] = $tt->name . ' ' . $this->rp($tt->price);
        }
        return implode(', ', $names);
    }

    /** Kartu event untuk frontend. */
    private function card($e): array
    {
        $from = $this->priceFrom($e);
        return [
            'id' => $e->id,
            'title' => $e->title,
            'image' => $e->image ?: null,
            'date_label' => $this->fmtRange($e),
            'venue' => $e->venue ? $e->venue->name : ($e->location_name ?: null),
            'price_label' => $from !== null ? 'Mulai ' . $this->rp($from) : 'Harga menyusul',
        ];
    }

    private function cards($events, int $limit = 5): array
    {
        return $events->take($limit)->map(fn ($e) => $this->card($e))->values()->all();
    }

    private function defaultChips(): array
    {
        return ['Event apa saja yang ada?', 'Berapa harga tiketnya?', 'Gimana cara beli tiket?'];
    }

    private function ok(string $reply, array $cards = [], ?array $chips = null): array
    {
        return [
            'reply' => $reply,
            'cards' => $cards,
            'chips' => $chips ?? $this->defaultChips(),
        ];
    }

    // ---------------------------------------------------------------- BALASAN

    private function replyGreeting(): array
    {
        return $this->ok(
            "Halo! Saya **Bot Assistant KetTiket**.\nSemua jawaban saya diambil langsung dari data website ini — daftar event, harga tiket, jadwal, venue, cara beli, e-tiket, sampai check-in gate.\nMau tahu soal apa?",
            [],
            ['Event apa saja yang ada?', 'Rekomendasi konser', 'Cara beli tiket gimana?']
        );
    }

    private function replyThanks(): array
    {
        return $this->ok(
            "Sama-sama! Senang bisa membantu. Kalau ada pertanyaan lain seputar event atau tiket, langsung saja tanya ke saya.",
            [],
            ['Event apa saja yang ada?', 'Gimana cara check-in di gate?']
        );
    }

    private function replyBye(): array
    {
        return $this->ok(
            "Sampai jumpa! Jangan lupa cek menu **Tiket Saya** sebelum hari-H ya. Selamat menikmati konser!",
            [],
            ['Event apa saja yang ada?', 'Cara beli tiket gimana?']
        );
    }

    private function replyList(): array
    {
        $up = $this->upcoming();

        if ($up->isEmpty()) {
            $past = $this->past();
            if ($past->isEmpty()) {
                return $this->ok(
                    "Belum ada event yang tayang di KetTiket saat ini. Pantau terus halaman Eksplor ya — begitu ada konser baru, langsung muncul di sini!",
                    [],
                    ['Rekomendasi konser', 'Cara daftar jadi penyelenggara']
                );
            }
            return $this->ok(
                "Belum ada event mendatang. Berikut event yang sudah selesai:\n"
                . $this->numberedList($past)
                . "\nKlik kartu di bawah untuk detailnya.",
                $this->cards($past),
                ['Event mendatang kapan?', 'Cara beli tiket gimana?']
            );
        }

        $lines = $this->numberedList($up);
        $extra = $up->count() > 6 ? "\n… dan " . ($up->count() - 6) . " event lainnya — klik kartu di bawah untuk detail lengkap." : '';

        return $this->ok(
            "Tentu! Saat ini ada **{$up->count()} event** tayang di KetTiket:\n"
            . $lines . $extra,
            $this->cards($up),
            ['Berapa harga tiketnya?', 'Rekomendasi konser yang mana?', 'Gimana cara beli tiket?']
        );
    }

    private function numberedList($events, int $limit = 6): string
    {
        $out = [];
        $i = 1;
        foreach ($events->take($limit) as $e) {
            $out[] = "**{$i}. {$e->title}**\n   Jadwal: " . $this->fmtRange($e)
                . "\n   Lokasi: " . $this->venueLine($e)
                . "\n   Tiket: " . $this->ticketInline($e);
            $i++;
        }
        return implode("\n", $out);
    }

    private function replyDetail($e): array
    {
        $status = $this->isUpcoming($e) ? 'Akan berlangsung — tiket masih dijual' : 'Event sudah selesai';
        $from = $this->priceFrom($e);

        $txt = "**{$e->title}**\n"
            . "• Jadwal: " . $this->fmtRange($e) . "\n"
            . "• Lokasi: " . $this->venueLine($e) . "\n"
            . "• Tempat duduk: " . ($e->has_seating ? 'tersedia seat map (pilih section/kursi)' : 'tanpa seat map (area bebas)') . "\n"
            . "• Status: {$status}\n";

        if ($e->description) {
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($e->description)));
            if ($desc !== '') {
                $txt .= "• Tentang: " . (mb_strlen($desc) > 180 ? mb_substr($desc, 0, 180) . '…' : $desc) . "\n";
            }
        }

        $txt .= "\n**Harga tiket:**\n" . $this->ticketLines($e);
        if ($from !== null) {
            $txt .= "\n\nHarga mulai dari **" . $this->rp($from) . "**.";
        }
        if ($this->isUpcoming($e)) {
            $txt .= "\nIngin beli? Buka menu **Eksplor** → pilih event ini → pilih kategori → checkout.";
        }

        return $this->ok(
            $txt,
            [$this->card($e)],
            ['Gimana cara beli tiketnya?', 'Gimana cara check-in di gate?', 'Daftar semua event']
        );
    }

    private function replyRecommend(): array
    {
        $up = $this->upcoming();

        if ($up->isEmpty()) {
            return $this->ok(
                "Belum ada event mendatang saat ini, jadi saya belum bisa memberi rekomendasi. Nanti begitu ada konser baru tayang, tanya saya lagi ya!",
                [],
                ['Daftar semua event', 'Cara daftar jadi penyelenggara']
            );
        }

        $top = $up->take(3);
        $txt = "Dengan senang hati! Berdasarkan jadwal terdekat dan harga, ini rekomendasi saya:\n";
        $i = 1;
        foreach ($top as $e) {
            $from = $this->priceFrom($e);
            $priceTxt = $from !== null ? 'mulai ' . $this->rp($from) : 'harga menyusul';
            $txt .= "**{$i}. {$e->title}**\n   " . $this->fmtRange($e)
                . "\n   " . $this->venueLine($e)
                . "\n   Tiket {$priceTxt}\n";
            $i++;
        }
        if ($up->count() === 1) {
            $txt .= "\nBaru ada satu event tayang — cocok banget buat yang mau serius berburu tiket!";
        } else {
            $txt .= "\nKlik kartu untuk langsung menuju detail & pemesanan.";
        }

        return $this->ok(
            $txt,
            $this->cards($top),
            ['Berapa harga tiketnya?', 'Gimana cara beli tiket?', 'Daftar semua event']
        );
    }

    private function replyPrice(): array
    {
        $up = $this->upcoming();

        if ($up->isEmpty()) {
            return $this->ok(
                "Belum ada event tayang, jadi belum ada harga tiket yang bisa saya tampilkan. Cek lagi nanti ya!",
                [],
                ['Daftar semua event']
            );
        }

        $txt = "Berikut harga tiket yang sedang berlaku di KetTiket:\n";
        foreach ($up->take(6) as $e) {
            $from = $this->priceFrom($e);
            $txt .= "**{$e->title}** — " . ($from !== null ? 'mulai **' . $this->rp($from) . '**' : 'harga menyusul') . "\n";
            $txt .= "   " . $this->ticketInline($e) . "\n";
        }
        $txt .= "\nHarga final dan ketersediaan terlihat di halaman masing-masing event (klik kartu di bawah).";

        return $this->ok(
            $txt,
            $this->cards($up),
            ['Gimana cara beli tiket?', 'Ada promo atau diskon?', 'Rekomendasi konser']
        );
    }

    private function replySchedule(): array
    {
        $up = $this->upcoming();

        if ($up->isEmpty()) {
            return $this->ok(
                "Belum ada event mendatang. Saat jadwal baru diumumkan, langsung tampil di menu Eksplor dan bisa ditanyakan ke saya.",
                [],
                ['Daftar semua event']
            );
        }

        $txt = "Jadwal event mendatang (waktu Indonesia Barat):\n";
        foreach ($up->take(6) as $e) {
            $txt .= "**{$e->title}**\n   " . $this->fmtRange($e) . "\n   " . $this->venueLine($e) . "\n";
        }

        return $this->ok(
            $txt,
            $this->cards($up),
            ['Dimana lokasinya?', 'Berapa harga tiketnya?', 'Daftar semua event']
        );
    }

    private function replyVenue(): array
    {
        $up = $this->upcoming();

        if ($up->isEmpty()) {
            return $this->ok(
                "Belum ada event yang memiliki jadwal tayang, jadi daftar venue kosong. Cek lagi nanti ya!",
                [],
                ['Daftar semua event']
            );
        }

        $seen = [];
        $txt = "Berikut venue yang akan dipakai event KetTiket:\n";
        foreach ($up as $e) {
            $key = $e->venue ? 'v' . $e->venue->id : 'l' . $e->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $txt .= "**" . ($e->venue ? $e->venue->name : ($e->location_name ?: 'Lokasi menyusul')) . "**";
            if ($e->venue) {
                $addr = trim(($e->venue->address ?? '') . ($e->venue->city && !str_contains((string) $e->venue->address, $e->venue->city) ? ', ' . $e->venue->city : ''));
                if ($addr !== '') {
                    $txt .= "\n   " . $addr;
                }
                if ($e->venue->capacity) {
                    $txt .= "\n   Kapasitas: " . number_format((int) $e->venue->capacity) . " orang";
                }
            }
            $txt .= "\n   Dipakai oleh: {$e->title}\n";
        }
        $txt .= "\nDetail peta Google Maps ada di halaman event (klik kartu di bawah).";

        return $this->ok(
            $txt,
            $this->cards($up),
            ['Jam berapa mulainya?', 'Berapa harga tiketnya?', 'Daftar semua event']
        );
    }

    private function replyBuy(): array
    {
        return $this->ok(
            "Cara beli tiket di KetTiket, lima langkah mudah:\n"
            . "**1.** Daftar atau masuk akun — akun baru otomatis menjadi **Customer** (penonton).\n"
            . "**2.** Buka menu **Eksplor**, pilih event yang kamu mau.\n"
            . "**3.** Pilih kategori tiket — dan section/kursi bila event punya seat map.\n"
            . "**4.** Checkout lalu bayar lewat payment gateway Midtrans (opsi metode tampil di halaman checkout).\n"
            . "**5.** Setelah pembayaran terkonfirmasi, e-tiket otomatis muncul di menu **Tiket Saya** — siap ditunjukkan ke petugas gate.",
            [],
            ['Metode bayar apa saja?', 'Gimana cara check-in di gate?', 'Event apa saja yang ada?']
        );
    }

    private function replyPayment(): array
    {
        return $this->ok(
            "Pembayaran di KetTiket diproses lewat payment gateway **Midtrans**. Metode yang tersedia tampil di halaman checkout, umumnya:\n"
            . "• Transfer bank / Virtual Account\n"
            . "• E-wallet (GoPay, OVO, DANA, dan lainnya)\n"
            . "• QRIS\n"
            . "• Kartu kredit/debit (bila diaktifkan panitia)\n\n"
            . "Setelah pembayaran terkonfirmasi, e-tiket terbit otomatis di menu **Tiket Saya**. Simpan bukti pembayaran sampai tiket terbit ya.",
            [],
            ['Gimana cara dapat e-tiketnya?', 'Cara beli tiket gimana?', 'Ada promo atau diskon?']
        );
    }

    private function replyEticket(): array
    {
        return $this->ok(
            "E-tiketmu berupa **barcode unik** (QR Code & barcode garis Code128) — satu tiket satu kode:\n"
            . "• Buka menu **Tiket Saya** → kartu tiket → tombol **Ke Petugas**.\n"
            . "• Barcode tampil terkunci selama **35 detik** per kali dibuka (waktu tampil ke petugas) dan bisa dibuka ulang kapan saja.\n"
            . "• **Masa berlaku tiket tetap sampai waktu selesai event**, bukan35 detik tampil — jadi tiket aman sampai hari-H.\n"
            . "• Tersedia tombol **Unduh Gambar Barcode** untuk disimpan offline.\n"
            . "• Jangan bagikan layar barcode ke orang lain — satu kode hanya berlaku untuk satu tiket.",
            [],
            ['Gimana cara check-in di gate?', 'Gimana cara beli tiket?', 'Kapan event dimulai?']
        );
    }

    private function replyCheckin(): array
    {
        return $this->ok(
            "Check-in di gate KetTiket, ikuti langkah ini:\n"
            . "**1.** Buka **Tiket Saya** → tombol **Ke Petugas** saat sudah sampai di depan scanner.\n"
            . "**2.** Arahkan barcode ke kamera scanner petugas — QR maupun barcode garis (Code128) sama-sama terbaca.\n"
            . "**3.** Layar scanner menampilkan **SELALU BERLAKU** bila tiket valid (berlaku s/d akhir event), atau status lain seperti sudah dipakai / kedaluwarsa.\n"
            . "**4.** Satu tiket hanya bisa discan **sekali** — jangan tunjukkan ke beberapa orang.\n"
            . "**5.** Datang lebih awal dan bawa identitas bila diminta panitia.",
            [],
            ['Gimana cara lihat barcode saya?', 'Kapan event dimulai?', 'Daftar semua event']
        );
    }

    private function replySeats(): array
    {
        $seating = $this->upcoming()->filter(fn ($e) => $e->has_seating)->values();

        if ($seating->isEmpty()) {
            $bebas = $this->upcoming();
            $txt = "Belum ada event dengan seat map saat ini.";
            if ($bebas->isNotEmpty()) {
                $txt .= " Event yang tayang sekarang memakai sistem area bebas (berdiri/keliling), jadi pilih kategori tiket saja tanpa menentukan kursi.";
            }
            return $this->ok($txt, $this->cards($bebas->take(3)), ['Daftar semua event', 'Berapa harga tiketnya?']);
        }

        $txt = "Event berikut **punya seat map** (kamu memilih section/kursi saat checkout):\n";
        foreach ($seating as $e) {
            $cap = $e->venue && $e->venue->capacity ? ' — kapasitas ' . number_format((int) $e->venue->capacity) . ' orang' : '';
            $txt .= "**{$e->title}**" . $cap . "\n   " . $this->venueLine($e) . "\n";
        }
        $txt .= "\nCara pilih: buka halaman event → bagian **Pilih Tempat Duduk / Section** → klik kursi yang diinginkan → lanjut checkout. Kursi yang sudah dipilih orang lain otomatis terkunci.";

        return $this->ok($txt, $this->cards($seating), ['Berapa harga tiketnya?', 'Gimana cara beli tiket?', 'Daftar semua event']);
    }

    private function replyAccount(): array
    {
        return $this->ok(
            "Tentang akun KetTiket:\n"
            . "• **Daftar**: menu Daftar (isi nama, email, password) — semua akun baru otomatis berrole **Customer** (penonton).\n"
            . "• **Masuk**: menu Masuk dengan email + password (atau login telepon/Google bila tersedia).\n"
            . "• **Lupa password**: hubungi tim KetTiket untuk reset — sertakan email akunmu.\n"
            . "• Peran lain (**Organizer, Scanner, Admin**) tidak dibuat lewat pendaftaran publik — dibuat khusus oleh tim admin platform.",
            [],
            ['Gimana cara jadi penyelenggara?', 'Event apa saja yang ada?', 'Cara beli tiket gimana?']
        );
    }

    private function replyOrganizer(): array
    {
        return $this->ok(
            "Ingin menjadi penyelenggara event di KetTiket? Begini ketentuannya:\n"
            . "• Pendaftaran publik saat ini hanya menghasilkan akun **Customer** (penonton).\n"
            . "• Akun **Event Organizer** dibuat khusus oleh tim admin KetTiket — silakan ajukan diri lewat kontak resmi platform.\n"
            . "• Setelah jadi organizer, semua tools tersedia di dashboard: terbitkan event, kelola kategori & kuota tiket, seat map, laporan penjualan, sampai analisis AI penjualan.\n"
            . "• Mau tanya-tanya dulu seputar fitur organizer? Saya bisa jelaskan.",
            [],
            ['Fitur apa saja untuk organizer?', 'Daftar semua event', 'Cara beli tiket gimana?']
        );
    }

    private function replyRefund(): array
    {
        return $this->ok(
            "Soal pembatalan & refund:\n"
            . "• Kebijakan pembatalan mengikuti ketentuan masing-masing **penyelenggara event** — baca deskripsi event sebelum membeli.\n"
            . "• Untuk pengajuan refund, hubungi panitia lewat kontak yang tercantum di halaman event terkait, sertakan bukti pembayaran & ID pesanan.\n"
            . "• Tiket yang sudah discan/digunakan tidak dapat ditukar atau dipakai ulang.",
            [],
            ['Cara hubungi panitia event?', 'Event apa saja yang ada?', 'Cara beli tiket gimana?']
        );
    }

    private function replyPromo(): array
    {
        return $this->ok(
            "Saat ini KetTiket **belum memiliki fitur kode promo/voucher diskon** — harga yang tertera di halaman event adalah harga resmi terkini.\n"
            . "Bila kelak ada promo dari panitia, informasinya akan muncul langsung di halaman event dan pengumuman resmi platform.",
            [],
            ['Berapa harga tiketnya?', 'Rekomendasi konser', 'Event apa saja yang ada?']
        );
    }

    private function replyContact(): array
    {
        return $this->ok(
            "Cara menghubungi pihak terkait:\n"
            . "• **Pertanyaan per event** (tiket, lokasi, perubahan jadwal): hubungi panitia penyelenggara lewat kontak yang tercantum di halaman event tersebut.\n"
            . "• **Kendala akun/pembayaran di platform**: siapkan email akun dan ID pesananmu saat menghubungi tim KetTiket agar cepat ditangani.\n"
            . "• Status pesanan & e-tiket bisa dicek sendiri kapan saja di menu pesanan dan **Tiket Saya**.",
            [],
            ['Event apa saja yang ada?', 'Cara beli tiket gimana?', 'Gimana cara dapat e-tiketnya?']
        );
    }

    private function replyStats(): array
    {
        $events = $this->publishedEvents();
        $up = $this->upcoming();
        $past = $this->past();
        $tt = $events->sum(fn ($e) => $e->ticketTypes->count());
        $venues = $events->pluck('venue_id')->filter()->unique()->count();

        return $this->ok(
            "Data KetTiket saat ini:\n"
            . "• **{$events->count()} event** tayang ({$up->count()} mendatang, {$past->count()} selesai)\n"
            . "• **{$tt} kategori tiket** terdaftar\n"
            . "• **{$venues} venue** dipakai event\n\n"
            . "Rincian per event bisa dilihat lewat kartu di bawah.",
            $this->cards($up->count() ? $up : $past),
            ['Daftar semua event', 'Berapa harga tiketnya?', 'Rekomendasi konser']
        );
    }

    private function replyHelp(): array
    {
        return $this->ok(
            "Saya **Bot Assistant KetTiket** — jawaban saya murni dari data website ini (bukan AI umum). Saya bisa bantu soal:\n"
            . "• Daftar & detail event\n"
            . "• Harga dan kategori tiket\n"
            . "• Jadwal serta lokasi venue\n"
            . "• Cara beli, pembayaran, e-tiket & check-in gate\n"
            . "• Akun, penyelenggara, promo, refund\n\n"
            . "Ketik salah satunya atau ketuk saran di bawah ya.",
            [],
            ['Event apa saja yang ada?', 'Berapa harga tiketnya?', 'Gimana cara check-in di gate?']
        );
    }

    private function replyFallback(): array
    {
        return $this->ok(
            "Maaf, saya belum menangkap maksud pertanyaannya. Saya bot berbasis data KetTiket dan bisa bantu soal:\n"
            . "• Daftar & detail event • Harga tiket • Jadwal • Lokasi venue\n"
            . "• Cara beli & bayar • E-tiket & check-in • Akun, organizer, promo, refund\n\n"
            . 'Coba ketik contoh: "event apa saja yang ada?" atau ketuk saran di bawah.',
            [],
            ['Event apa saja yang ada?', 'Berapa harga tiketnya?', 'Cara beli tiket gimana?']
        );
    }
}
