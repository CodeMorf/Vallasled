<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

$page_title = 'Acceso Restringido | Vallas.com';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #0B1220;
            color: #e2e8f0;
        }
        .main-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            .sidebar {
                display: none; /* Oculto en móvil para este layout específico */
            }
        }
    </style>
</head>
<body class="antialiased">

    <div class="main-grid">
        <!-- Sidebar -->
        <aside class="sidebar bg-slate-900 p-8 flex flex-col justify-between">
            <div>
                <a href="/" class="block mb-12">
                    <img src="https://placehold.co/150x40/0B1220/FFFFFF?text=VALLAS.COM" alt="Vallas.com Logo">
                </a>
                <nav class="flex flex-col space-y-4">
                    <a href="/tipos" class="text-slate-300 hover:text-white text-lg font-semibold">Tipos</a>
                    <a href="/mapa" class="text-slate-300 hover:text-white text-lg font-semibold">Mapa</a>
                    <a href="/catalogo" class="text-slate-300 hover:text-white text-lg font-semibold">Catálogo</a>
                    <a href="/acceder" class="text-slate-300 hover:text-white text-lg font-semibold">Acceder</a>
                </nav>
            </div>
            <a href="/registro" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-center font-bold py-3 px-6 rounded-lg transition-colors">
                Registra tu Valla
            </a>
        </aside>

        <!-- Main Content -->
        <main class="w-full flex flex-col">
            <div class="flex-grow flex items-center justify-center">
                <div class="text-center max-w-lg mx-auto p-4">
                    <div class="flex justify-center mb-8">
                        <div class="w-20 h-20 flex items-center justify-center bg-yellow-500/10 rounded-full">
                            <svg class="w-10 h-10 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                            </svg>
                        </div>
                    </div>
                    <h1 class="text-5xl font-extrabold text-white mb-4">Acceso Prohibido</h1>
                    <p class="text-slate-400 text-lg mb-2">Esta sección se encuentra en mantenimiento para mejorar nuestros servicios.</p>
                    <p class="text-slate-500 text-lg mb-8">Disculpe las molestias.</p>
                    <a href="/" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-8 rounded-full transition-colors text-lg">
                        Volver a la Página Principal
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <footer class="p-8 text-slate-400">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                    <div>
                        <h4 class="font-bold text-white mb-4">VALLASLED.COM</h4>
                        <p class="text-sm">Descubre y alquila vallas LED y estáticas. También imprenta, publicidad móvil y mochilas.</p>
                    </div>
                    <div>
                        <h4 class="font-bold text-white mb-4">SERVICIOS</h4>
                        <nav class="flex flex-col space-y-2 text-sm">
                            <a href="/alquiler" class="hover:text-white">Alquiler de vallas LED</a>
                            <a href="/impresas" class="hover:text-white">Imprenta y vallas impresas</a>
                            <a href="/movil" class="hover:text-white">Publicidad móvil (LED)</a>
                            <a href="/mochilas" class="hover:text-white">Mochilas publicitarias</a>
                            <a href="/marketing" class="hover:text-white">Marketing y campañas</a>
                        </nav>
                    </div>
                    <div>
                        <h4 class="font-bold text-white mb-4">RECURSOS</h4>
                        <nav class="flex flex-col space-y-2 text-sm">
                            <a href="/blog" class="hover:text-white">Blog</a>
                            <a href="/noticias" class="hover:text-white">Noticias</a>
                            <a href="/analisis" class="hover:text-white">Análisis</a>
                            <a href="/mapa-ubicaciones" class="hover:text-white">Mapa de ubicaciones</a>
                            <a href="/inicio" class="hover:text-white">Inicio</a>
                        </nav>
                    </div>
                    <div>
                        <h4 class="font-bold text-white mb-4">ACCESOS</h4>
                        <nav class="flex flex-col space-y-2 text-sm">
                            <a href="/login/cliente" class="hover:text-white">Login Cliente</a>
                            <a href="/login/gubernamental" class="hover:text-white">Login Gubernamental</a>
                            <a href="/login/proveedor" class="hover:text-white">Login Proveedor</a>
                            <a href="/login/admin" class="hover:text-white">Login Admin</a>
                            <a href="/login/prestador" class="hover:text-white">Login Prestador</a>
                            <a href="/login/moviles" class="hover:text-white">Móviles</a>
                        </nav>
                    </div>
                    <div>
                        <h4 class="font-bold text-white mb-4">LEGAL Y CONTACTO</h4>
                        <nav class="flex flex-col space-y-2 text-sm">
                            <a href="/politica-privacidad" class="hover:text-white">Política de privacidad</a>
                            <a href="/terminos-condiciones" class="hover:text-white">Términos y condiciones</a>
                            <a href="/sitemap" class="hover:text-white">Sitemap</a>
                            <p>Tel: 18091234567</p>
                            <p>Dirección: Santo Domingo, RD</p>
                            <p>Email: soporte@vallasled.com</p>
                             <a href="#" class="mt-2 inline-block w-full bg-green-500 hover:bg-green-400 text-white font-bold py-2 px-4 rounded-lg text-center">WhatsApp</a>
                        </nav>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-slate-800 text-center text-sm flex justify-between">
                    <p>&copy; 2025 Vallasled.com. Todos los derechos reservados.</p>
                    <p>by Vallasled</p>
                </div>
            </footer>
        </main>
    </div>

</body>
</html>

