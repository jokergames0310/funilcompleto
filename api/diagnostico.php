<?php
header('Content-Type: application/json; charset=utf-8');

$diagnostico = [
    'php_version' => phpversion(),
    'curl_enabled' => function_exists('curl_init'),
    'allow_url_fopen' => ini_get('allow_url_fopen'),
    'openssl_enabled' => extension_loaded('openssl'),
    'https_test' => null,
    'api_test' => null,
];

// Testa conexão HTTPS básica
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.google.com',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $diagnostico['https_test'] = [
        'status' => $error ?: 'OK',
        'http_code' => $httpCode,
    ];
}

// Testa API magmadatahub
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://magmadatahub.com/api.php?token=96aee7799e0dc1d7d2afee93a376cd6ac04c274f578f0e0f4962be45f8a54d6d&cpf=14312572658',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLOPT_HTTP_CODE);
    curl_close($ch);

    $diagnostico['api_test'] = [
        'status' => $error ?: 'OK',
        'http_code' => $httpCode,
        'response_length' => strlen($response),
    ];
}

echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
