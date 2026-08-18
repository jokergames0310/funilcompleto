<?php
/**
 * Exporta conversoes offline em formato CSV para importacao no Google Ads
 * Endpoint: /api/export-conversions.php?date=2026-08-18&format=csv
 */

header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'json';

// Seguranca minima
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data invalida']);
    exit;
}

$logDir = __DIR__ . '/../data/logs';
$conversionFile = $logDir . '/conversions_offline_' . $date . '.ndjson';

if (!file_exists($conversionFile)) {
    echo json_encode([
        'success' => true,
        'date' => $date,
        'conversions' => [],
        'message' => 'Nenhuma conversao encontrada para esta data'
    ]);
    exit;
}

// Parse NDJSON
$lines = file($conversionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$conversions = [];

foreach ($lines as $line) {
    $decoded = json_decode($line, true);
    if ($decoded) {
        $conversions[] = $decoded;
    }
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=conversions_' . $date . '.csv');

    $output = fopen('php://output', 'w');

    // Header do CSV (formato Google Ads)
    fputcsv($output, [
        'Google Click ID',
        'Conversion Name',
        'Conversion Time',
        'Conversion Value',
        'Conversion Currency'
    ], ',');

    foreach ($conversions as $conv) {
        // Usa gclid se disponivel, senao gbraid
        $googleId = $conv['gclid'] ?? $conv['gbraid'] ?? null;

        if ($googleId) {
            fputcsv($output, [
                $googleId,
                'Purchase', // Nome exato da acao de conversao configurada no Google Ads
                $conv['paymentTime'],
                $conv['amount'],
                $conv['currency']
            ], ',');
        }
    }

    fclose($output);
    exit;
}

// JSON (padrao)
echo json_encode([
    'success' => true,
    'date' => $date,
    'conversions' => $conversions,
    'count' => count($conversions),
    'message' => 'Use ?format=csv para exportar em formato CSV'
]);
?>
