<?php
/* ============================================================
   EXA SEARCH PROXY
   Exa's API is built for server-side use and does not send the
   CORS headers a browser needs to call it directly — that's why
   calling https://api.exa.ai/search straight from index.html's
   JavaScript fails once the site is live on a real domain (it
   isn't an InfinityFree-specific problem; it would fail on any
   host, since it's Exa's server that decides whether to allow
   cross-origin browser requests, not the site hosting the page).

   This script sits on your own server instead, so the browser
   calls THIS same-origin PHP file (no CORS issue at all), and
   this file makes the actual request to Exa from the server side,
   where CORS doesn't apply. Your Exa keys live in .env (never
   committed to GitHub) and are read here — they never get sent
   to the browser at all, unlike the Gemini key in config.php.

   SETUP: put your 2 Exa keys in .env (see .env.example), then
   upload this file + env.php + .env to the same folder as
   index.html on InfinityFree.
   ============================================================ */

require __DIR__ . '/env.php';
$env = iris_load_env(__DIR__ . '/.env');
$EXA_KEYS = [
  $env['EXA_KEY_1'] ?? '',
  $env['EXA_KEY_2'] ?? '',
];

header('Content-Type: application/json');
// Same-origin calls from your own index.html don't need this, but it's harmless
// to include in case you ever call this proxy from a different subdomain.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Use POST']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$query = isset($input['query']) ? trim($input['query']) : '';
if ($query === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Missing "query"']);
  exit;
}

// Site-wide round robin across visitors: which key gets tried FIRST rotates
// on every request (1st search -> key 1, 2nd -> key 2, 3rd -> key 1, ...),
// tracked in a small local file so it persists across requests/visitors.
// If the key that's "up" happens to fail, it still fails over to the other
// key right away — rotation never costs you a real answer.
$counterFile = __DIR__ . '/.exa_rotation';
$keyCount = count($EXA_KEYS);
$startIndex = 0;
if (is_readable($counterFile)) {
  $startIndex = ((int) file_get_contents($counterFile)) % $keyCount;
}
@file_put_contents($counterFile, ($startIndex + 1) % $keyCount);

$results = null;
$lastError = null;

for ($i = 0; $i < $keyCount; $i++) {
  $key = $EXA_KEYS[($startIndex + $i) % $keyCount];
  if (!$key) continue;

  $ch = curl_init('https://api.exa.ai/search');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
      'x-api-key: ' . $key,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
      'query' => $query,
      'numResults' => 5,
      'contents' => ['text' => ['maxCharacters' => 500]],
    ]),
  ]);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlErr = curl_error($ch);
  curl_close($ch);

  if ($curlErr) { $lastError = 'cURL error: ' . $curlErr; continue; }
  if ($httpCode < 200 || $httpCode >= 300) { $lastError = 'Exa HTTP ' . $httpCode . ': ' . $response; continue; }

  $data = json_decode($response, true);
  if (!empty($data['results'])) {
    $results = $data['results'];
    break;
  }
}

if ($results === null) {
  // No key worked (or none configured) — return empty results rather than an
  // error status, so the browser side falls back to Gemini's own search
  // grounding gracefully instead of showing a broken request.
  echo json_encode(['results' => [], 'debug' => $lastError]);
  exit;
}

echo json_encode(['results' => $results]);
