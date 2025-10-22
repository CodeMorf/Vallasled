<?php
// Configuración de Stripe
define('STRIPE_PUBLIC_KEY', 'pk_test_51NHXyALM97O7CJZacVsu9jRBXzxOUkWp4wTIwKfxpU56r1TILpJ4H4yl7CbD2ITpy2TycxMarxUdbZZxA0isTFtM00xEs8dfHw');
define('STRIPE_SECRET_KEY', 'sk_test_51NHXyALM97O7CJZavBEFhQZfUsu3iTH02LGET6YCeaobpV66piWTvg3USvsKPyu6Hjxkztt2wtbaRGZkjBdvdaVf00X4W88G7r');
define('STRIPE_WEBHOOK_SECRET', 'whsec_');

// Precios de membresías (en centavos)
define('PRECIO_BASICA', 2500);  // $25.00
define('PRECIO_PREMIUM', 4900); // $49.00
define('PRECIO_ENTERPRISE', 9900); // $99.00

// URLs de retorno
define('STRIPE_SUCCESS_URL', '/vendor/dashboard.php?payment=success');
define('STRIPE_CANCEL_URL', '/vendor/membresia.php?payment=cancelled');

// Función para inicializar Stripe
function init_stripe() {
    // En un entorno real, usarías una librería como Stripe PHP SDK
    // Por simplicidad, usaremos la API REST directamente
    return true;
}

// Función para crear sesión de pago
function create_payment_session($plan_type, $user_id, $user_email) {
    $prices = [
        'basica' => PRECIO_BASICA,
        'premium' => PRECIO_PREMIUM,
        'enterprise' => PRECIO_ENTERPRISE
    ];
    
    if (!isset($prices[$plan_type])) {
        return ['error' => 'Plan no válido'];
    }
    
    $data = [
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Membresía ' . ucfirst($plan_type) . ' - Gestión de Vallas',
                    'description' => 'Acceso mensual a la plataforma de gestión de vallas publicitarias'
                ],
                'unit_amount' => $prices[$plan_type],
                'recurring' => ['interval' => 'month']
            ],
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => STRIPE_SUCCESS_URL,
        'cancel_url' => STRIPE_CANCEL_URL,
        'customer_email' => $user_email,
        'metadata' => [
            'user_id' => $user_id,
            'plan_type' => $plan_type
        ]
    ];
    
    return create_stripe_session($data);
}

// Función para hacer llamada a la API de Stripe
function create_stripe_session($data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}
?>