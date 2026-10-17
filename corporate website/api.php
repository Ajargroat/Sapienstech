<?php
require_once __DIR__ . '/config.php';

// ─── CORS headers ─────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Pre-flight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Only accept POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ─── Parse body ───────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

$name   = trim($body['name']   ?? '');
$phone  = trim($body['phone']  ?? '');
$source = trim($body['source'] ?? 'modal');

// ─── Validate ─────────────────────────────────────────────────────────────
if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'name and phone are required']);
    exit;
}

// Sanitise source to known values only
if (!in_array($source, ['modal', 'footer'], true)) {
    $source = 'modal';
}

// ─── Connect ──────────────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// ─── Insert ───────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO leads (name, phone, source, created_at)
         VALUES (:name, :phone, :source, NOW())'
    );
    $stmt->execute([
        ':name'   => $name,
        ':phone'  => $phone,
        ':source' => $source,
    ]);

    $insertedId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id'      => $insertedId,
        'message' => 'Lead saved',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save data']);
}
