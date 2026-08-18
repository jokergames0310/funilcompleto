<?php
header('Content-Type: application/json; charset=utf-8');

$token = 'e859d9233c2f556c6c0f4b2689cb93f0';
$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? '');

// 1. Validação básica de CPF para não processar lixo
if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'CPF inválido']);
    exit;
}

$url = "https://magmadatahub.com/api.php?token=$token&cpf=$cpf";

// --- TENTATIVA DE CONSULTA REAL ---
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10, // Aumentei um pouco o timeout
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/121.0.0.0",
        "Accept: application/json"
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Se houver erro de conexão
if ($curlError) {
    http_response_code(503);
    echo json_encode([
        'success' => false, 
        'erro' => 'Erro de conexão com a API',
        'detalhes' => $curlError
    ]);
    exit;
}

$data = json_decode($response, true);

// Verifica se a resposta é válida
if (!$data || !is_array($data)) {
    http_response_code(503);
    echo json_encode([
        'success' => false, 
        'erro' => 'Resposta inválida da API'
    ]);
    exit;
}

// 2. SE A API FUNCIONAR: Retorna os dados reais
if ($httpCode == 200 && isset($data['success']) && $data['success'] === true && !empty($data['nome'])) {
    echo json_encode([
        'success'    => true,
        'nome'       => $data['nome'],
        'cpf'        => $cpf,
        'nascimento' => $data['nascimento'] ?? '',
        'mae'        => $data['nome_mae'] ?? '',
        'sexo'       => $data['sexo'] ?? '',
        'origem'     => 'api_real'
    ]);
    exit;
}

// 3. FALHA DA API - Retorna erro honesto
http_response_code(404);
echo json_encode([
    'success' => false,
    'erro' => 'CPF não encontrado na base de dados',
    'http_code' => $httpCode,
    'detalhes' => $data['message'] ?? 'Dados não disponíveis'
]);
exit;
?>