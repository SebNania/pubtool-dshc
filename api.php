<?php
/**
 * DSHC — API JSON
 * ----------------------------------------------------------------------------
 *   GET /dshc/api.php                    → état tarifaire maintenant (JSON)
 *   GET /dshc/api.php?format=text        → "offpeak" ou "peak" (texte brut)
 *   GET /dshc/api.php?at=2026-10-01T02:30Z   → état à un instant donné
 *   GET /dshc/api.php?action=month&month=2026-10
 *   GET /dshc/api.php?fields=state,next_change   (JSON réduit)
 *   GET /dshc/api.php?key=XXXX           → si DS_API_KEY est définie
 *
 * Lecture seule. Aucune écriture, aucune base de données.
 */
declare(strict_types=1);

require __DIR__ . '/dshc.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('X-Robots-Tag: noindex, nofollow');
header('X-DSHC: 1');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ---------- Sécurité : domaine, méthode, clé ---------- */
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, OPTIONS');
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (DS_API_KEY !== '') {
    $given = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!is_string($given) || !hash_equals(DS_API_KEY, $given)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden',
                          'hint' => 'Clé manquante ou invalide (?key=… ou en-tête X-API-Key).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ---------- Paramètres ---------- */
$at     = isset($_GET['at'])     && is_string($_GET['at'])     ? $_GET['at'] : null;
$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'state';
$format = isset($_GET['format']) && is_string($_GET['format']) ? strtolower($_GET['format']) : 'json';
$pretty = !isset($_GET['pretty']) || !in_array((string) $_GET['pretty'], ['0', 'false', 'no'], true);

$when = ds_parse_at($at);
if ($at !== null && $at !== '' && $when === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_at',
                      'hint' => 'Format attendu : 2026-10-01T02:30:00Z, 2026-10-01 02:30 ou 2026-10-01 (UTC).'], JSON_UNESCAPED_UNICODE);
    exit;
}
$now = $when ?? ds_now();

/* ---------- Cache ---------- */
header('Cache-Control: public, max-age=30, must-revalidate');
header('Vary: Accept-Encoding');

/* ---------- Réponse ---------- */
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
if ($pretty) {
    $flags |= JSON_PRETTY_PRINT;
}

if ($action === 'month') {
    $month = isset($_GET['month']) && is_string($_GET['month']) ? $_GET['month'] : $now->format('Y-m');
    $days  = ds_month($month);
    if (!$days) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_month', 'hint' => 'Format attendu : ?month=2026-10'], $flags);
        exit;
    }
    $p0 = ds_payload($now);
    echo json_encode([
        'ok'           => true,
        'schema'       => 1,
        'month'        => $month,
        'days'         => $days,
        'served_at_utc'=> $p0['now']['utc'],
        'peak_windows_utc' => DS_WINDOWS,
        'makeup_workdays_peak' => DS_MAKEUP_PEAK,
        'warnings'     => $p0['warnings'],
    ], $flags);
    exit;
}

$payload = ds_payload($now);

if ($format === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    echo $payload['off_peak'] ? "offpeak\n" : "peak\n";
    exit;
}

if ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre>' . htmlspecialchars(json_encode($payload, $flags), ENT_QUOTES) . '</pre>';
    exit;
}

/* Raccourci : ?fields=state,off_peak,next_change */
if (isset($_GET['fields']) && is_string($_GET['fields']) && $_GET['fields'] !== '') {
    $keep = array_filter(array_map('trim', explode(',', $_GET['fields'])), static fn ($k) => $k !== '');
    $out  = ['ok' => true];
    foreach ($keep as $k) {
        if (array_key_exists($k, $payload)) {
            $out[$k] = $payload[$k];
        }
    }
    echo json_encode($out, $flags);
    exit;
}

echo json_encode($payload, $flags);
