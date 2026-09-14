<?php
/* ============================================================
   CONFIG BRIDGE
   index.html loads this file as a <script src="config.php"> tag.
   It reads .env server-side (never committed to GitHub) and
   writes out a small window.IRIS_CONFIG object with the values
   the browser-side app needs.

   IMPORTANT — read this before assuming everything here is
   "hidden": keeping .env out of GitHub only keeps these values
   out of your public SOURCE CODE. Once this script runs, the
   Gemini key and Client ID below ARE sent to and visible in the
   browser (view-source, or the Network tab) for anyone who
   visits your live site — because index.html calls Gemini
   directly from client-side JavaScript, the browser needs the
   real key to do that. That's a different, separate concern
   from "is it on GitHub".

   Exa keys do NOT go through this file at all, and are never
   sent to the browser — they stay entirely server-side inside
   exa-proxy.php, which is the more airtight pattern. If you want
   that same level of protection for Gemini too, that means
   routing Gemini calls through a PHP proxy as well instead of
   calling it directly from the browser — ask and it can be built
   the same way exa-proxy.php was.
   ============================================================ */

require __DIR__ . '/env.php';
$env = iris_load_env(__DIR__ . '/.env');

header('Content-Type: application/javascript');
// Prevent this from being cached somewhere stale after you update .env
header('Cache-Control: no-store');

$config = [
  'geminiKeys' => [
    $env['GEMINI_KEY_1'] ?? '',
    $env['GEMINI_KEY_2'] ?? '',
  ],
  'googleClientId' => $env['GOOGLE_CLIENT_ID'] ?? '',
];

echo 'window.IRIS_CONFIG = ' . json_encode($config) . ';';
