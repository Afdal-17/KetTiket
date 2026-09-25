<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KetTiket - @yield('title', 'Concert SaaS & AI Assistant')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Axios (Local Fallback) -->
    <script src="{{ asset('js/axios.min.js') }}"></script>
    
    <!-- Global Styles -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @stack('styles')
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <!-- Navigation -->
    <nav id="navbar" class="navbar">
        <div class="container">
            <a href="/" class="nav-brand">KetTiket</a>
            <div id="nav-right" class="nav-links">
                <!-- Injected via app.js based on auth state -->
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content container">
        @yield('content')
    </main>

    <!-- Global Loader -->
    <div id="global-loader" class="loader-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Global Scripts -->
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if(typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
