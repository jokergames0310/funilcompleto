<?php
/**
 * Configuracao ZuckPay
 * Credenciais e constantes da API
 */

// Credenciais (em producao, usar variaveis de ambiente)
define('ZUCKPAY_CLIENT_ID', getenv('ZUCKPAY_CLIENT_ID') ?: 'tropadofafa06_7488061169');
define('ZUCKPAY_CLIENT_SECRET', getenv('ZUCKPAY_CLIENT_SECRET') ?: 'f6ba2efc879d6f6a387747bb111facbdbb9952c3fae58761b1400327b138d228');
define('ZUCKPAY_WEBHOOK_SECRET', getenv('ZUCKPAY_WEBHOOK_SECRET') ?: ''); // Gera no painel ZuckPay

// Endpoints
define('ZUCKPAY_API_BASE', 'https://zuckpay.com.br/conta/v3');
define('ZUCKPAY_PIX_ENDPOINT', ZUCKPAY_API_BASE . '/pix/qrcode');

/**
 * Credenciais em base64 para Basic Auth
 */
function getZuckPayAuthHeader() {
  $credentials = ZUCKPAY_CLIENT_ID . ':' . ZUCKPAY_CLIENT_SECRET;
  return 'Basic ' . base64_encode($credentials);
}

/**
 * Valida assinatura do webhook ZuckPay
 * Formato: "t=<timestamp>,v1=<hmac_sha256>"
 */
function validateZuckPaySignature($payload, $signature) {
  if (!ZUCKPAY_WEBHOOK_SECRET) {
    // Sem webhook secret configurado, pula validacao
    error_log('⚠ ZUCKPAY_WEBHOOK_SECRET nao configurado - webhooks sem validacao');
    return true;
  }

  $parts = [];
  parse_str(strtr($signature, ',', '&'), $parts);
  $ts = $parts['t'] ?? '';
  $v1 = $parts['v1'] ?? '';

  // Anti-replay: timestamp nao pode ter mais de 5 minutos
  if (abs(time() - (int)$ts) > 300) {
    error_log('❌ Webhook expirado: ' . $ts);
    return false;
  }

  // Calcula HMAC esperado
  $expected = hash_hmac('sha256', $ts . '.' . $payload, ZUCKPAY_WEBHOOK_SECRET);

  // Compara com hash_equals (previne timing attacks)
  if (!hash_equals($expected, $v1)) {
    error_log('❌ Assinatura invalida - esperado: ' . $expected . ', recebido: ' . $v1);
    return false;
  }

  return true;
}
?>
