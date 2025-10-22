<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

// Configuración, Helpers y Tracking
$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

// --- Configuración SEO específica para esta página ---
$__seo = [
    'title'       => 'Política de Privacidad | Fox Publicidad (vallasled.com)',
    'description' => 'Conoce cómo Fox Publicidad protege y gestiona tus datos personales en vallasled.com de acuerdo con la legislación de República Dominicana.',
    'og_type'     => 'article',
];

// --- Función para inyectar el <head> dinámicamente ---
function __inject_head_legal(string $html, array $overrides): string {
    $head = '';
    $base_url = function_exists('base_url') ? base_url() : '/';
    if (function_exists('seo_page') && function_exists('seo_head')) {
        $head .= seo_head(seo_page($overrides));
    } else {
        $head .= '<title>' . h($overrides['title']) . '</title><meta name="description" content="' . h($overrides['description']) . '">';
    }
    $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    $head .= '<link rel="sitemap" type="application/xml" href="' . h($base_url) . '/sitemap.xml">' . "\n";
    $head .= <<<CSS
<style>
    body { font-family: Inter, system-ui, sans-serif; background-color: #0B1220; color: #cbd5e1; }
    .container{max-width:56rem;margin:0 auto;padding:2rem 1rem}
    .prose { color: #cbd5e1; line-height: 1.7; }
    .prose h1, .prose h2, .prose h3, .prose strong { color: #f8fafc; }
    .prose h1 { font-size: 2.25rem; font-weight: 800; }
    .prose h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2.5em; margin-bottom: 1em; border-bottom: 1px solid #334155; padding-bottom: 0.5rem;}
    .prose a { color: #38bdf8; text-decoration: none; font-weight: 600; }
    .prose a:hover { text-decoration: underline; }
    .prose ul { list-style: none; padding-left: 1.25rem; }
    .prose ul > li { position: relative; padding-left: 1.25rem; margin-bottom: 0.5rem; }
    .prose ul > li::before { content: ""; position: absolute; left: 0; top: 0.7em; width: 0.375em; height: 0.375em; border-radius: 50%; background-color: #38bdf8; }
    .lead{font-size: 1.25rem; color: #94a3b8;}
</style>
CSS;
    return preg_replace('~</head>~i', $head . '</head>', $html, 1) ?: ($head . $html);
}

// --- Inclusión del Header ---
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
    ob_start();
    include $__header;
    $hdr = ob_get_clean();
    echo __inject_head_legal($hdr, $__seo);
} else {
    echo "<!doctype html><html lang=\"es\"><head>";
    echo __inject_head_legal("</head>", $__seo);
    echo "<body>";
}

// --- Inclusión del Body Tracking ---
if (function_exists('tracking_body')) {
    tracking_body();
}
?>

<main class="container">
    <article class="prose max-w-none">
        <div class="text-center">
            <h1>Política de Privacidad de vallasled.com</h1>
            <p class="lead"><strong>Última actualización:</strong> 26 de Septiembre, 2025</p>
        </div>
        
        <hr class="my-12 border-slate-700">

        <section>
            <h2>1. Introducción y Responsable del Tratamiento</h2>
            <p>Bienvenido a vallasled.com (el "Sitio Web"), operado por <strong>Fox Publicidad</strong>. Su privacidad es de suma importancia para nosotros. Esta política de privacidad explica cómo recopilamos, usamos, protegemos y gestionamos su información personal, en cumplimiento con la <strong>Ley No. 172-13 sobre Protección de Datos de Carácter Personal de la República Dominicana</strong>.</p>
            <p>El responsable del tratamiento de sus datos personales es <strong>Fox Publicidad</strong>, con domicilio en Santo Domingo, República Dominicana. Para cualquier consulta, puede contactarnos a través de <a href="mailto:info@foxpublicidad.com">info@foxpublicidad.com</a>.</p>
        </section>
        
        <section>
            <h2>2. Información que Recopilamos</h2>
            <p>Podemos recopilar y procesar los siguientes tipos de información sobre usted a través de nuestro Sitio Web, vallasled.com:</p>
            <ul>
                <li><strong>Datos de Identificación y Contacto:</strong> Nombre, apellido, número de teléfono, dirección de correo electrónico, nombre de la empresa y RNC.</li>
                <li><strong>Datos de Transacción:</strong> Información sobre los servicios que solicita o contrata, así como datos de facturación necesarios para procesar los pagos (no almacenamos datos de tarjetas de crédito).</li>
                <li><strong>Datos de Navegación:</strong> Dirección IP, tipo de navegador, sistema operativo y otra información técnica sobre su visita a nuestro Sitio Web, recopilada a través de cookies y tecnologías similares.</li>
                <li><strong>Datos de Comunicación:</strong> Cualquier información que nos proporcione al contactar a nuestro equipo de soporte o al comunicarse con nosotros a través de formularios en nuestro Sitio Web.</li>
            </ul>
        </section>

        <section>
            <h2>3. Finalidad del Tratamiento de sus Datos</h2>
            <p>Utilizamos su información personal para las siguientes finalidades:</p>
            <ul>
                <li><strong>Proveer nuestros servicios:</strong> Para gestionar sus solicitudes de cotización, contratación de espacios publicitarios, y dar seguimiento a sus campañas.</li>
                <li><strong>Comunicación:</strong> Para responder a sus consultas, enviarle información sobre nuestros servicios, notificaciones administrativas y actualizaciones relevantes.</li>
                <li><strong>Facturación y Gestión Administrativa:</strong> Para procesar pagos, emitir facturas y cumplir con nuestras obligaciones fiscales y legales.</li>
                <li><strong>Marketing:</strong> Con su consentimiento, para enviarle comunicaciones comerciales, ofertas y noticias sobre nuestros servicios que puedan ser de su interés.</li>
                <li><strong>Mejora del Servicio:</strong> Para analizar el uso de nuestro Sitio Web y servicios con el fin de mejorar la experiencia del usuario, la funcionalidad y la oferta.</li>
            </ul>
        </section>
        
        <section>
            <h2>4. Legitimación y Conservación de Datos</h2>
            <p>La base legal para el tratamiento de sus datos es la ejecución de la relación contractual al solicitar nuestros servicios, nuestro interés legítimo en mejorar nuestra oferta, y su consentimiento explícito para las comunicaciones de marketing.</p>
            <p>Conservaremos sus datos personales durante el tiempo que sea necesario para cumplir con las finalidades para las que fueron recopilados, y para cumplir con las obligaciones legales aplicables.</p>
        </section>

        <section>
            <h2>5. Divulgación de sus Datos</h2>
            <p>No vendemos ni alquilamos su información personal. Solo compartimos sus datos con:</p>
            <ul>
                <li><strong>Proveedores de servicios:</strong> Terceros que nos ayudan a operar nuestro negocio, como procesadores de pago o servicios de analítica web, siempre bajo estrictos acuerdos de confidencialidad.</li>
                <li><strong>Autoridades competentes:</strong> Cuando sea requerido por ley o para responder a un proceso legal válido.</li>
            </ul>
        </section>

        <section>
            <h2>6. Sus Derechos (Derechos ARCO)</h2>
            <p>De acuerdo con la Ley No. 172-13, usted tiene derecho a:</p>
            <ul>
                <li><strong>Acceso:</strong> Solicitar y obtener información sobre sus datos personales que estamos tratando.</li>
                <li><strong>Rectificación:</strong> Solicitar la corrección de sus datos si son inexactos o incompletos.</li>
                <li><strong>Cancelación:</strong> Solicitar la eliminación de sus datos cuando ya no sean necesarios para los fines para los que fueron recogidos.</li>
                <li><strong>Oposición:</strong> Oponerse al tratamiento de sus datos por motivos legítimos.</li>
            </ul>
            <p>Para ejercer estos derechos, puede enviar una solicitud por escrito a <a href="mailto:privacidad@foxpublicidad.com">privacidad@foxpublicidad.com</a>, adjuntando una copia de su documento de identidad.</p>
        </section>

        <section>
            <h2>7. Seguridad de los Datos</h2>
            <p>Implementamos medidas de seguridad técnicas y organizativas adecuadas para proteger su información personal contra el acceso no autorizado, la alteración, la divulgación o la destrucción.</p>
        </section>
        
         <section>
            <h2>8. Uso de Cookies en vallasled.com</h2>
            <p>Nuestro sitio web, vallasled.com, utiliza cookies para mejorar la experiencia de navegación y recopilar datos estadísticos anónimos. Puede configurar su navegador para rechazar las cookies, aunque esto podría afectar la funcionalidad de algunas partes de nuestro sitio. Para más información, consulte nuestra Política de Cookies.</p>
        </section>

        <section>
            <h2>9. Cambios a esta Política</h2>
            <p>Nos reservamos el derecho de modificar esta política de privacidad en cualquier momento. Cualquier cambio será publicado en esta página y, si los cambios son significativos, se lo notificaremos por correo electrónico. Le recomendamos revisar esta página periódicamente.</p>
        </section>
    </article>
</main>

<?php
// --- Inclusión del Footer ---
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) {
    include $__footer;
}

// --- Conteo de Visita ---
if (function_exists('track_pageview')) {
    track_pageview($_SERVER['REQUEST_URI'] ?? '/es/privacidad');
}
?>
