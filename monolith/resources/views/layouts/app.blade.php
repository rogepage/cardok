<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Cardok — Consulta de Débitos Veiculares' }}</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    @livewireStyles
</head>
<body class="min-h-full font-sans antialiased text-slate-800 bg-slate-50 selection:bg-brand-500 selection:text-white">
    <!-- Header -->
    <header class="border-b border-slate-200 bg-white/80 backdrop-blur sticky top-0 z-50 shadow-xs">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-brand-600 flex items-center justify-center text-white font-extrabold text-lg shadow-sm">
                    C
                </div>
                <div>
                    <span class="font-extrabold text-xl tracking-tight text-slate-900">CARDOK</span>
                    <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 border border-brand-200">Veículos</span>
                </div>
            </div>
            <div class="text-xs text-slate-500 hidden sm:block">
                Consulta & Simulação de Pagamento
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="py-10 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="mt-auto border-t border-slate-200 py-6 text-center text-xs text-slate-400">
        <p>&copy; 2026 Cardok. Demonstração técnica — Home Test.</p>
    </footer>

    @livewireScripts
</body>
</html>
