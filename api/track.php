<?php
header('Content-Type: application/json; charset=utf-8');

$events = [];
$input = file_get_contents('php://input');

if ($input) {
  $decoded = json_decode($input, true);
  if (is_array($decoded)) {
    $events = $decoded;
  }
}

$logDir = __DIR__ . '/../data/logs';
if (!is_dir($logDir)) {
  @mkdir($logDir, 0755, true);
}

$today = date('Y-m-d');
$logFile = $logDir . '/funnel_' . $today . '.ndjson';

foreach ($events as $event) {
  if (!is_array($event)) continue;

  $line = json_encode([
    'timestamp' => $event['timestamp'] ?? date('c'),
    'sessionId' => $event['sessionId'] ?? null,
    'event' => $event['event'] ?? null,
    'stage' => $event['stage'] ?? null,
    'data' => $event['data'] ?? [],
    'device' => $event['device'] ?? []
  ], JSON_UNESCAPED_UNICODE);

  error_log($line . "\n", 3, $logFile);
}

http_response_code(202);
echo json_encode(['success' => true, 'count' => count($events)]);
?>
