<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php'; // Debe exponer $conn (mysqli)

/**
 * Lee la Google Maps API Key desde config_global.google_maps_api_key (activo=1).
 * Fallback: variable de entorno GMAPS_API_KEY si no hay registro.
 */
function cm_get_google_maps_api_key(mysqli $conn): string {
    $sql = "SELECT valor FROM config_global
            WHERE clave='google_maps_api_key' AND activo=1
            ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $key = '';
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $key = (string)($row['valor'] ?? '');
    }
    if ($key === '') {
        $env = getenv('GMAPS_API_KEY');
        if ($env) $key = $env;
    }
    return trim($key);
}

$GOOGLE_MAPS_API_KEY = cm_get_google_maps_api_key($conn);
if ($GOOGLE_MAPS_API_KEY === '') {
    error_log('[mapas.php] google_maps_api_key vacío. Configura tabla config_global o ENV GMAPS_API_KEY.');
}
