<?php
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
  'sessionId' => $data['sessionId'] ?? null,
  'status' => $data['status'] ?? 'pending',
  'amount' => (float)($data['amount'] ?? 0),
  'currency' => $data['currency'] ?? 'BRL',
  'gateway' => $data['gateway'] ?? 'unknown',
  'paymentId' => $data['paymentId'] ?? null,
  'paymentTime' => $data['paymentTime'] ?? null,
  'source' => $data['source'] ?? 'webhook'
];

error_log(json_encode($payment, JSON_UNESCAPED_UNICODE) . "\n", 3, $paymentsFile);

http_response_code(200);
echo json_encode(['success' => true, 'received' => true]);
?>
