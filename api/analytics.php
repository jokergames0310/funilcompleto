<?php
header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../data/logs';
$period = $_GET['period'] ?? 'today';
$action = $_GET['action'] ?? 'summary';

function getDateRange($period) {
  $today = new DateTime();
  switch ($period) {
    case 'today':
      return [$today->format('Y-m-d'), $today->format('Y-m-d')];
    case 'week':
      $week = new DateTime();
      $week->sub(new DateInterval('P7D'));
      return [$week->format('Y-m-d'), $today->format('Y-m-d')];
    case 'month':
      $month = new DateTime();
      $month->sub(new DateInterval('P30D'));
      return [$month->format('Y-m-d'), $today->format('Y-m-d')];
    default:
      return [$today->format('Y-m-d'), $today->format('Y-m-d')];
  }
}

function parseNdjson($file) {
  if (!file_exists($file)) return [];
  $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $result = [];
  foreach ($lines as $line) {
    $decoded = json_decode($line, true);
    if ($decoded) $result[] = $decoded;
  }
  return $result;
}

function getFunnelSummary($logDir, $period) {
  [$startDate, $endDate] = getDateRange($period);

  $allEvents = [];
  $logDir = dirname($logDir);

  if (is_dir($logDir)) {
    for ($i = 0; $i < 30; $i++) {
      $date = new DateTime($startDate);
      $date->add(new DateInterval('P' . $i . 'D'));
      if ($date->format('Y-m-d') > $endDate) break;

      $file = $logDir . '/logs/funnel_' . $date->format('Y-m-d') . '.ndjson';
      $events = parseNdjson($file);
      $allEvents = array_merge($allEvents, $events);
    }
  }

  $stages = [
    'landing' => 'Landing',
    'cpf_analysis' => 'Análise CPF',
    'qualification_1' => 'Qualificação 1',
    'registration' => 'Cadastro',
    'billing_date' => 'Data de Vencimento',
    'payment_pix' => 'Pagamento Pix',
    'approved' => 'Aprovado'
  ];

  $stageMetrics = array_fill_keys(array_keys($stages), [
    'entries' => 0,
    'exits' => 0,
    'conversions' => 0,
    'errors' => 0,
    'avgTimeOnStage' => 0
  ]);

  $sessions = [];
  $totalTime = [];

  foreach ($allEvents as $event) {
    $stage = $event['stage'] ?? null;
    $sid = $event['sessionId'] ?? null;

    if (!isset($sessions[$sid])) {
      $sessions[$sid] = ['stages' => [], 'events' => []];
    }

    $sessions[$sid]['events'][] = $event;

    if (isset($stageMetrics[$stage])) {
      if ($event['event'] === 'stage_entry') {
        $stageMetrics[$stage]['entries']++;
      } elseif ($event['event'] === 'stage_exit') {
        $stageMetrics[$stage]['exits']++;
        if (isset($event['data']['timeOnStage'])) {
          if (!isset($totalTime[$stage])) $totalTime[$stage] = [];
          $totalTime[$stage][] = $event['data']['timeOnStage'];
        }
      } elseif ($event['event'] === 'stage_conversion') {
        $stageMetrics[$stage]['conversions']++;
      } elseif ($event['event'] === 'form_error' || $event['event'] === 'validation_error') {
        $stageMetrics[$stage]['errors']++;
      }
    }
  }

  foreach ($totalTime as $stage => $times) {
    if (!empty($times)) {
      $stageMetrics[$stage]['avgTimeOnStage'] = round(array_sum($times) / count($times));
    }
  }

  $funnel = [];
  foreach ($stages as $key => $label) {
    $funnel[] = [
      'stage' => $label,
      'stageKey' => $key,
      'entries' => $stageMetrics[$key]['entries'],
      'conversions' => $stageMetrics[$key]['conversions'],
      'errors' => $stageMetrics[$key]['errors'],
      'avgTimeOnStage' => $stageMetrics[$key]['avgTimeOnStage']
    ];
  }

  return [
    'funnel' => $funnel,
    'totalSessions' => count($sessions),
    'conversions' => count(array_filter($sessions, function($s) {
      return collect($s['events'], 'event', 'stage_conversion') > 0;
    }))
  ];
}

function collect($events, $key, $value) {
  $count = 0;
  foreach ($events as $e) {
    if (($e[$key] ?? null) === $value) $count++;
  }
  return $count;
}

if ($action === 'summary') {
  echo json_encode(getFunnelSummary($logDir, $period));
} else {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid action']);
}
?>
