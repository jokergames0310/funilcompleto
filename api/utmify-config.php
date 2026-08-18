<?php
/**
 * Configuracao Utmify
 * API Token e endpoints
 */

// Credencial (em producao, usar variavel de ambiente)
define('UTMIFY_API_TOKEN', getenv('UTMIFY_API_TOKEN') ?: '');
define('UTMIFY_API_ENDPOINT', 'https://api.utmify.com.br/api-credentials/orders');

/**
 * Envia pedido para Utmify (rastreamento offline de conversao)
 * Chamado quando o PIX e gerado
 */
function sendToUtmify($orderData) {
  if (!UTMIFY_API_TOKEN) {
    error_log('⚠ UTMIFY_API_TOKEN nao configurado');
    return false;
  }

  $payload = [
    'platform_id' => $orderData['orderId'],
    'platform_name' => 'Funil-Completo',
    'email' => $orderData['customerEmail'],
    'phone' => $orderData['customerPhone'],
    'name' => $orderData['customerName'],
    'cpf' => preg_replace('/\D/', '', $orderData['customerCpf']),
    'country' => 'BR',
    'payment_method' => 'pix',
    'status' => 'pending',
    'total_price_cents' => (int)($orderData['amount'] * 100),
    'products' => [
      [
        'id' => $orderData['orderId'],
        'name' => 'Pedido ' . $orderData['orderId'],
        'quantity' => 1,
        'price_cents' => (int)($orderData['amount'] * 100)
      ]
    ],
    'utm_source' => $orderData['utm_source'] ?? '',
    'utm_medium' => $orderData['utm_medium'] ?? '',
    'utm_campaign' => $orderData['utm_campaign'] ?? '',
    'utm_content' => $orderData['utm_content'] ?? '',
    'utm_term' => $orderData['utm_term'] ?? '',
    'gclid' => $orderData['gclid'] ?? '',
    'isTest' => false
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => UTMIFY_API_ENDPOINT,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
      'x-api-token: ' . UTMIFY_API_TOKEN,
      'Content-Type: application/json',
      'User-Agent: Funil-Completo/1.0'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
  ]);

  $response = curl_exec($ch);
  $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlErrno = curl_errno($ch);
  curl_close($ch);

  if ($curlErrno !== 0) {
    error_log('❌ Utmify: erro de conexao - ' . $curlErrno);
    return false;
  }

  if ($httpCode !== 200 && $httpCode !== 201) {
    error_log('❌ Utmify: HTTP ' . $httpCode . ' - ' . $response);
    return false;
  }

  $decoded = json_decode($response, true);
  if ($decoded) {
    error_log('✓ Utmify: pedido enviado - ' . $orderData['orderId']);
    return true;
  }

  return false;
}

/**
 * Atualiza status do pedido em Utmify
 * Chamado quando pagamento e confirmado
 */
function updateUtmifyOrderStatus($orderId, $status) {
  if (!UTMIFY_API_TOKEN) {
    return false;
  }

  $payload = [
    'platform_id' => $orderId,
    'status' => $status // 'completed', 'pending', 'failed'
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => UTMIFY_API_ENDPOINT,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
      'x-api-token: ' . UTMIFY_API_TOKEN,
      'Content-Type: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
  ]);

  curl_exec($ch);
  $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return $httpCode === 200 || $httpCode === 201;
}
?>
