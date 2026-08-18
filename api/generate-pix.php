<?php
/**
 * Gera QR Code PIX via ZuckPay
 * POST /api/generate-pix.php
 *
 * Body:
 * {
 *   "orderId": "PED-123456",
 *   "amount": 100.00,
 *   "customerName": "João Silva",
 *   "customerCpf": "12345678900",
 *   "customerEmail": "joao@email.com",
 *   "customerPhone": "11999998888",
 *   "sessionId": "abc-def-ghi",
 *   "gclid": "CjwKCAjwxuan..."
 * }
 */

require_once __DIR__ . '/zuckpay-config.php';
require_once __DIR__ . '/utmify-config.php';

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Dados invalidos']);
  exit;
}

$orderId = $data['orderId'] ?? null;
$amount = (float)($data['amount'] ?? 0);
$customerName = $data['customerName'] ?? 'Cliente';
$customerCpf = preg_replace('/\D/', '', $data['customerCpf'] ?? '');
$customerEmail = $data['customerEmail'] ?? '';
$customerPhone = preg_replace('/\D/', '', $data['customerPhone'] ?? '');
$sessionId = $data['sessionId'] ?? null;
$gclid = $data['gclid'] ?? null;

// Validacoes basicas
if (!$orderId || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'orderId e amount sao obrigatorios']);
  exit;
}

if (!$customerCpf || strlen($customerCpf) !== 11) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'CPF invalido']);
  exit;
}

// Chama ZuckPay API
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => ZUCKPAY_PIX_ENDPOINT,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode([
    'valor' => $amount,
    'nome' => substr($customerName, 0, 150),
    'cpf' => $customerCpf,
    'email' => $customerEmail,
    'telefone' => $customerPhone
  ]),
  CURLOPT_HTTPHEADER => [
    'Authorization: ' . getZuckPayAuthHeader(),
    'Content-Type: application/json',
    'User-Agent: Funil-Completo/1.0'
  ],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
  CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
curl_close($ch);

// Erro de conexao
if ($curlErrno !== 0) {
  http_response_code(503);
  echo json_encode(['success' => false, 'error' => 'Erro ao conectar com ZuckPay']);
  exit;
}

// Parse resposta ZuckPay
$apiResponse = json_decode($response, true);

if ($httpCode !== 200 || !$apiResponse) {
  http_response_code($httpCode ?: 503);
  echo json_encode([
    'success' => false,
    'error' => 'Falha ao gerar PIX',
    'details' => $apiResponse ?? 'Resposta invalida'
  ]);
  exit;
}

// Log do pedido com Google IDs
$logDir = __DIR__ . '/../data/logs';
if (!is_dir($logDir)) {
  @mkdir($logDir, 0755, true);
}

$ordersFile = $logDir . '/orders.ndjson';
$order = [
  'timestamp' => date('c'),
  'orderId' => $orderId,
  'sessionId' => $sessionId,
  'amount' => $amount,
  'currency' => 'BRL',
  'customerName' => $customerName,
  'customerCpf' => $customerCpf,
  'customerEmail' => $customerEmail,
  'customerPhone' => $customerPhone,
  'gclid' => $gclid,
  'pixId' => $apiResponse['id'] ?? null,
  'pixQrCode' => $apiResponse['qr_code'] ?? null,
  'pixCopiaColaSemPunctuation' => $apiResponse['copia_cola_sem_punctuation'] ?? null,
  'status' => 'pending',
  'gateway' => 'zuckpay'
];

error_log(json_encode($order, JSON_UNESCAPED_UNICODE) . "\n", 3, $ordersFile);

// Envia para Utmify (rastreamento offline)
sendToUtmify([
  'orderId' => $orderId,
  'amount' => $amount,
  'customerName' => $customerName,
  'customerCpf' => $customerCpf,
  'customerEmail' => $customerEmail,
  'customerPhone' => $customerPhone,
  'gclid' => $gclid,
  'utm_source' => $data['utm_source'] ?? '',
  'utm_medium' => $data['utm_medium'] ?? '',
  'utm_campaign' => $data['utm_campaign'] ?? '',
  'utm_content' => $data['utm_content'] ?? '',
  'utm_term' => $data['utm_term'] ?? ''
]);

// Retorna dados pro cliente
http_response_code(200);
echo json_encode([
  'success' => true,
  'orderId' => $orderId,
  'pixId' => $apiResponse['id'] ?? null,
  'pixQrCode' => $apiResponse['qr_code'] ?? null,
  'pixCopiaColaSemPunctuation' => $apiResponse['copia_cola_sem_punctuation'] ?? null,
  'amount' => $amount,
  'expiresIn' => $apiResponse['time_to_expiration'] ?? null
]);
?>
