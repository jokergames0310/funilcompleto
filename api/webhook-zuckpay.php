<?php
/**
 * Webhook ZuckPay - Recebe notificacoes de confirmacao de pagamento
 * Configure em ZuckPay > Integrações > Webhooks
 * URL: https://seu-site.com/api/webhook-zuckpay.php
 */

require_once __DIR__ . '/zuckpay-config.php';
require_once __DIR__ . '/utmify-config.php';

header('Content-Type: application/json; charset=utf-8');

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ZUCKPAY_SIGNATURE'] ?? '';

// Valida assinatura
if (!validateZuckPaySignature($payload, $signature)) {
  http_response_code(401);
  echo json_encode(['error' => 'Assinatura invalida']);
  exit;
}

$data = json_decode($payload, true);

if (!$data) {
  http_response_code(400);
  echo json_encode(['error' => 'JSON invalido']);
  exit;
}

// Log de todos os webhooks recebidos
$logDir = __DIR__ . '/../data/logs';
if (!is_dir($logDir)) {
  @mkdir($logDir, 0755, true);
}

$today = date('Y-m-d');
$webhooksFile = $logDir . '/webhooks_zuckpay_' . $today . '.ndjson';

$webhook = [
  'timestamp' => date('c'),
  'event' => $data['type'] ?? 'unknown',
  'pixId' => $data['id'] ?? null,
  'status' => $data['status'] ?? null,
  'amount' => $data['valor'] ?? null,
  'orderId' => $data['referencia'] ?? null,
  'customerCpf' => $data['cpf'] ?? null,
  'paymentTime' => $data['timestamp_pagamento'] ?? null,
  'raw' => $data
];

error_log(json_encode($webhook, JSON_UNESCAPED_UNICODE) . "\n", 3, $webhooksFile);

// Se pagamento foi confirmado
if (($data['status'] === 'confirmed' || $data['status'] === 'pago' || $data['type'] === 'pix.received') && !empty($data['referencia'])) {

  // Busca o pedido para pegar sessionId e Google IDs
  $ordersFile = $logDir . '/orders.ndjson';
  $orderId = $data['referencia'];
  $sessionId = null;
  $gclid = null;

  if (file_exists($ordersFile)) {
    $lines = file($ordersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $order = json_decode($line, true);
      if ($order && $order['orderId'] === $orderId) {
        $sessionId = $order['sessionId'];
        $gclid = $order['gclid'];
        break;
      }
    }
  }

  // Registra o pagamento
  $paymentsFile = $logDir . '/payments.ndjson';
  $payment = [
    'timestamp' => date('c'),
    'orderId' => $orderId,
    'sessionId' => $sessionId,
    'pixId' => $data['id'] ?? null,
    'status' => 'confirmed',
    'amount' => (float)($data['valor'] ?? 0),
    'currency' => 'BRL',
    'gateway' => 'zuckpay',
    'paymentTime' => $data['timestamp_pagamento'] ?? date('c'),
    'gclid' => $gclid,
    'source' => 'webhook'
  ];

  error_log(json_encode($payment, JSON_UNESCAPED_UNICODE) . "\n", 3, $paymentsFile);

  // Dispara conversao offline
  recordOfflineConversion([
    'orderId' => $orderId,
    'amount' => (float)($data['valor'] ?? 0),
    'paymentTime' => $data['timestamp_pagamento'] ?? date('c'),
    'sessionId' => $sessionId,
    'gclid' => $gclid
  ]);

  // Atualiza status em Utmify
  updateUtmifyOrderStatus($orderId, 'completed');
}

http_response_code(200);
echo json_encode([
  'success' => true,
  'received' => true,
  'event' => $data['type'] ?? 'unknown'
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

  curl_exec($ch);
  curl_close($ch);
}
?>
