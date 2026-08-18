<?php
/**
 * Webhook para notificacoes de pagamento Pix
 * Recebe confirmacao do gateway, registra e dispara conversao offline
 */

header('Content-Type: application/json; charset=utf-8');

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid JSON']);
  exit;
}

$logDir = __DIR__ . '/../data/logs';
if (!is_dir($logDir)) {
  @mkdir($logDir, 0755, true);
}

$paymentsFile = $logDir . '/payments.ndjson';

$payment = [
  'timestamp' => date('c'),
  'orderId' => $data['orderId'] ?? null,
  'sessionId' => $data['sessionId'] ?? null,
  'status' => $data['status'] ?? 'pending',
  'amount' => (float)($data['amount'] ?? 0),
  'currency' => $data['currency'] ?? 'BRL',
  'gateway' => $data['gateway'] ?? 'syncpay',
  'paymentId' => $data['paymentId'] ?? null,
  'paymentTime' => $data['paymentTime'] ?? date('c'),
  'source' => $data['source'] ?? 'webhook',
  'gclid' => $data['gclid'] ?? null,
  'gbraid' => $data['gbraid'] ?? null,
  'wbraid' => $data['wbraid'] ?? null
];

// Registra o pagamento
error_log(json_encode($payment, JSON_UNESCAPED_UNICODE) . "\n", 3, $paymentsFile);

// Se status eh CONFIRMADO e tem Google ID, registra conversao offline
if ($payment['status'] === 'confirmed' || $payment['status'] === 'paid') {
  if ($payment['gclid'] || $payment['gbraid'] || $payment['wbraid']) {
    $conversionData = [
      'orderId' => $payment['orderId'],
      'amount' => $payment['amount'],
      'paymentTime' => $payment['paymentTime'],
      'gclid' => $payment['gclid'],
      'gbraid' => $payment['gbraid'],
      'wbraid' => $payment['wbraid']
    ];

    // Chama o endpoint de conversao offline
    recordOfflineConversion($conversionData);
  }
}

http_response_code(200);
echo json_encode([
  'success' => true,
  'received' => true,
  'orderId' => $payment['orderId'],
  'status' => $payment['status']
]);

/**
 * Registra conversao offline via POST interno
 */
function recordOfflineConversion($data) {
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/api/offline-conversion.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}
?>

