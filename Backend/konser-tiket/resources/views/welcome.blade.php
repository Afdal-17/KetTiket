<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KetTiket - Platform Tiket Konser #1</title>
    <!-- Meta Config for Frontend -->
    <meta name="midtrans-client-key" content="{{ config('services.midtrans.client_key') }}">
    <meta name="midtrans-is-production" content="{{ config('services.midtrans.is_production') ? 'true' : 'false' }}">

    <!-- Axios (Local) -->
    <script src="{{ asset('js/axios.min.js') }}"></script>
    
    <!-- Lucide Icons (Local) -->
    <script src="{{ asset('js/lucide.min.js') }}" defer></script>

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ time() }}">
</head>
<body>
    <!-- Global Loader -->
    <div id="global-loader" class="loader-overlay">
        <div class="spinner"></div>
    </div>

    <!-- SPA Mount Point -->
    <div id="app"></div>

    <!-- Midtrans Snap: dimuat lazy oleh customer.js HANYA saat pembayaran dibuka,
         agar halaman tidak pernah menunggu resource internet (tetap jalan offline). -->
    @unless(config('services.midtrans.client_key'))
    <script>
        window.snap = { pay: function() { alert('Midtrans gateway not configured.'); } };
    </script>
    @endunless

    <!-- Initialize Lucide Icons -->
    <script>
        // Render ikon hanya jika masih ada placeholder <i data-lucide> yang belum dikonversi.
        // CATATAN PENTING: lucide.createIcons() menghasilkan <svg data-lucide> (atribut tetap ada),
        // jadi kita HARUS membatasi selector ke tag <i> saja. Jika memakai selector [data-lucide]
        // atau memanggil createIcons() tanpa syarat dari MutationObserver, terjadi infinite loop:
        // observer -> createIcons -> DOM berubah -> observer -> ... -> browser freeze (tidak merespon).
        const renderLucideIcons = () => {
            if (typeof lucide === 'undefined') return;
            if (document.querySelector('i[data-lucide]')) lucide.createIcons();
        };
        document.addEventListener('DOMContentLoaded', renderLucideIcons);
        // Re-initialize ikon saat SPA memasang HTML baru yang memuat <i data-lucide>
        const observer = new MutationObserver(renderLucideIcons);
        observer.observe(document.body, { childList: true, subtree: true });
    </script>

    <!-- SPA Scripts -->
    <script src="{{ asset('js/app.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/customer.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/dashboard.js') }}?v={{ time() }}"></script>
</body>
</html>
