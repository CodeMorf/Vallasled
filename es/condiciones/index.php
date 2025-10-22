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
    'title'       => 'Términos y Condiciones | Fox Publicidad (vallasled.com)',
    'description' => 'Términos y condiciones de uso para los servicios de publicidad en vallas ofrecidos por Fox Publicidad a través de vallasled.com.',
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
            <h1>Términos y Condiciones de Uso</h1>
            <p class="lead"><strong>Última actualización:</strong> 26 de Septiembre, 2025</p>
        </div>
        
        <hr class="my-12 border-slate-700">

        <section>
            <h2>1. Aceptación de los Términos</h2>
            <p>Bienvenido a vallasled.com (el "Sitio Web"), una plataforma operada por <strong>Fox Publicidad</strong> ("Nosotros", "la Empresa"). Los siguientes términos y condiciones ("Términos") rigen su acceso y uso de nuestro Sitio Web y de todos los servicios ofrecidos en él. Al acceder, navegar o utilizar este Sitio Web, usted ("el Usuario") reconoce haber leído, entendido y aceptado estar sujeto a estos Términos. Si no está de acuerdo con estos Términos, no debe utilizar este Sitio Web.</p>
        </section>
        
        <section>
            <h2>2. Descripción del Servicio</h2>
            <p>vallasled.com es una plataforma en línea que permite a los usuarios buscar, cotizar y contratar espacios publicitarios en diferentes formatos de vallas (LED, impresas, móviles, etc.) disponibles en la República Dominicana. La Empresa actúa como intermediario y gestor de dichos espacios.</p>
        </section>

        <section>
            <h2>3. Cuentas de Usuario y Registro</h2>
            <p>Para acceder a ciertas funciones, como la contratación de servicios, es posible que deba registrarse y crear una cuenta. Usted se compromete a:</p>
            <ul>
                <li>Proporcionar información veraz, precisa, actual y completa durante el proceso de registro.</li>
                <li>Mantener la confidencialidad de su contraseña y ser el único responsable de todas las actividades que ocurran bajo su cuenta.</li>
                <li>Notificarnos inmediatamente sobre cualquier uso no autorizado de su cuenta o cualquier otra violación de seguridad.</li>
            </ul>
        </section>
        
        <section>
            <h2>4. Proceso de Contratación y Pagos</h2>
            <p>El proceso de contratación de un espacio publicitario se formalizará a través de las cotizaciones y acuerdos generados a través de la plataforma o con nuestros representantes.</p>
            <ul>
                <li><strong>Precios y Moneda:</strong> Todos los precios se muestran en Pesos Dominicanos (DOP), a menos que se indique lo contrario. Los precios no incluyen el ITBIS, el cual será desglosado en la factura final.</li>
                <li><strong>Pago:</strong> El pago de los servicios deberá realizarse según las condiciones acordadas en la cotización, a través de los métodos de pago disponibles en la plataforma.</li>
                <li><strong>Cancelación:</strong> Las políticas de cancelación y reembolso estarán sujetas a los términos específicos de cada contratación y serán comunicadas antes de formalizar el acuerdo.</li>
            </ul>
        </section>

        <section>
            <h2>5. Contenido del Anunciante</h2>
            <p>El Usuario es el único responsable del contenido (imágenes, videos, textos) que proporciona para ser exhibido en las vallas ("Contenido del Anunciante"). Al proporcionarnos su contenido, usted garantiza que:</p>
            <ul>
                <li>Posee todos los derechos de propiedad intelectual, licencias y permisos necesarios para el uso de dicho contenido.</li>
                <li>El contenido no viola ninguna ley aplicable en la República Dominicana, incluyendo, pero no limitándose a, leyes sobre difamación, obscenidad, propiedad intelectual y publicidad engañosa.</li>
                <li>El contenido no promueve actividades ilegales, violencia, discriminación ni contiene material ofensivo.</li>
            </ul>
            <p><strong>Fox Publicidad</strong> se reserva el derecho de rechazar, a su entera discreción, cualquier contenido que considere inapropiado o que viole estos Términos.</p>
        </section>

        <section>
            <h2>6. Obligaciones de Fox Publicidad</h2>
            <p>Nos comprometemos a gestionar la correcta exhibición de su publicidad según los términos contratados, asegurando que el espacio publicitario esté operativo y su contenido se muestre en los tiempos y formatos acordados. No nos hacemos responsables de interrupciones del servicio por causas de fuerza mayor (fallos eléctricos, desastres naturales, etc.).</p>
        </section>

         <section>
            <h2>7. Limitación de Responsabilidad</h2>
            <p>En la máxima medida permitida por la ley, Fox Publicidad no será responsable por daños directos, indirectos, incidentales o consecuentes que resulten del uso o la imposibilidad de uso de nuestros servicios, incluyendo la pérdida de beneficios o interrupción del negocio.</p>
        </section>

        <section>
            <h2>8. Propiedad Intelectual</h2>
            <p>Todo el contenido presente en vallasled.com, incluyendo textos, gráficos, logos, iconos, imágenes y software, es propiedad de Fox Publicidad o de sus proveedores de contenido y está protegido por las leyes de propiedad intelectual. No se permite la reproducción, modificación o distribución de dicho contenido sin nuestro consentimiento previo por escrito.</p>
        </section>

        <section>
            <h2>9. Modificación de los Términos</h2>
            <p>Nos reservamos el derecho de modificar estos Términos en cualquier momento. Las modificaciones entrarán en vigor inmediatamente después de su publicación en el Sitio Web. El uso continuado del Sitio Web después de cualquier cambio constituirá su aceptación de los nuevos Términos.</p>
        </section>

        <section>
            <h2>10. Ley Aplicable y Jurisdicción</h2>
            <p>Estos Términos se regirán e interpretarán de acuerdo con las leyes de la República Dominicana. Cualquier disputa que surja en relación con estos Términos será sometida a la jurisdicción exclusiva de los tribunales de Santo Domingo, Distrito Nacional.</p>
        </section>

         <section>
            <h2>11. Contacto</h2>
            <p>Si tiene alguna pregunta sobre estos Términos y Condiciones, puede contactarnos en:</p>
            <p><strong>Fox Publicidad</strong><br>
            Correo Electrónico: <a href="mailto:legal@foxpublicidad.com">legal@foxpublicidad.com</a><br>
            Santo Domingo, República Dominicana.</p>
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
    track_pageview($_SERVER['REQUEST_URI'] ?? '/es/terminos');
}
?>

