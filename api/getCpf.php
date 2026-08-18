<?php
header('Content-Type: application/json; charset=utf-8');

$token = '96aee7799e0dc1d7d2afee93a376cd6ac04c274f578f0e0f4962be45f8a54d6d';
$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? '');

// Validacao basica
if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'CPF invalido']);
    exit;
}

if (preg_match('/^(\d)\1{10}$/', $cpf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'CPF invalido']);
    exit;
}

$url = "https://magmadatahub.com/api.php?token=" . urlencode($token) . "&cpf=" . urlencode($cpf);

$response = null;
$httpCode = 0;

// Tenta com cURL
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/121.0.0.0',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLOPT_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        http_response_code(503);
        echo json_encode(['success' => false, 'erro' => 'Erro de conexao']);
        exit;
    }
}

// Fallback: file_get_contents
if (empty($response) && ini_get('allow_url_fopen')) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $httpCode = $response ? 200 : 0;
}

if (empty($response)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'erro' => 'Servico indisponivel']);
    exit;
}

$data = json_decode($response, true);

if (!$data || !is_array($data)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'erro' => 'Resposta invalida']);
    exit;
}

if ($httpCode == 200 && !empty($data['success']) && $data['success'] === true && !empty($data['nome'])) {
    echo json_encode([
        'success' => true,
        'nome' => $data['nome'],
        'cpf' => $cpf,
        'nascimento' => $data['nascimento'] ?? '',
        'mae' => $data['nome_mae'] ?? '',
        'sexo' => $data['sexo'] ?? '',
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'erro' => 'CPF nao encontrado']);
exit;
?>
