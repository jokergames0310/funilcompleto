<?php
header('Content-Type: application/json; charset=utf-8');

$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? '');

if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'CPF invalido']);
    exit;
}

if (preg_match('/^(\d)\1{10}$/', $cpf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'CPF invalido']);
    exit;
}

// MOCK - Retorna dados fake baseado no CPF
$nomes = ['João Silva', 'Maria Santos', 'Pedro Oliveira', 'Ana Costa', 'Bruno Alves'];
$maes = ['Carla Silva', 'Diane Santos', 'Fernanda Oliveira', 'Gisele Costa', 'Helena Alves'];

$hash = crc32($cpf);
$nome_idx = $hash % count($nomes);
$mae_idx = ($hash + 1) % count($maes);

echo json_encode([
    'success' => true,
    'nome' => $nomes[$nome_idx],
    'cpf' => $cpf,
    'nascimento' => '1990-' . str_pad(($hash % 12) + 1, 2, '0', STR_PAD_LEFT) . '-' . str_pad(($hash % 28) + 1, 2, '0', STR_PAD_LEFT),
    'mae' => $maes[$mae_idx],
    'sexo' => ($hash % 2) ? 'M' : 'F',
]);
?>
