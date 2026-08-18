<?php
/**
 * Endpoint para registrar conversoes offline no Google Ads
 * Chamado quando o pagamento e confirmado (via webhook ou polling)
 */

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'Dados invalidos']);
    exit;
}

// Validacao minima
$orderId = $data['orderId'] ?? null;
$gclid = $data['gclid'] ?? null;
$gbraid = $data['gbraid'] ?? null;
$wbraid = $data['wbraid'] ?? null;
$amount = (float)($data['amount'] ?? 0);
$paymentTime = $data['paymentTime'] ?? date('c');

// Pelo menos um ID do Google deve estar presente
if (!$gclid && !$gbraid && !$wbraid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'Nenhum ID do Google fornecido']);
    exit;
}

// Arquivo de log pra conversoes offline
$logDir = __DIR__ . '/../data/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$today = date('Y-m-d');
$conversionFile = $logDir . '/conversions_offline_' . $today . '.ndjson';

// Registra a conversao
$conversion = [
    'timestamp' => date('c'),
    'orderId' => $orderId,
    'amount' => $amount,
    'currency' => 'BRL',
    'paymentTime' => $paymentTime,
    'gclid' => $gclid,
    'gbraid' => $gbraid,
    'wbraid' => $wbraid,
    'status' => 'pending_export', // Aguardando exportacao pro Google Ads
    'exported' => false,
    'exportTime' => null
];

error_log(json_encode($conversion, JSON_UNESCAPED_UNICODE) . "\n", 3, $conversionFile);

// Aqui voce pode integrar com Google Ads API para enviar direto
// Por enquanto, esta sendo logado pra exportacao manual (CSV 1x/semana)
// Futura melhor: implementar Google Ads API com developer token

// Resposta imediata
http_response_code(202);
echo json_encode([
    'success' => true,
    'message' => 'Conversao offline registrada para exportacao',
    'orderId' => $orderId,
    'logFile' => basename($conversionFile)
]);
?>
