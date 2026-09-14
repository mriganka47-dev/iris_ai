<?php
/* ============================================================
   Tiny .env loader — no Composer, no dependencies. Works on any
   shared PHP host (including InfinityFree). Used by both
   config.php and exa-proxy.php so there's only one copy of this
   logic.

   Reads simple KEY=VALUE lines from a .env file sitting next to
   it. Lines starting with # are treated as comments and skipped.
   ============================================================ */
function iris_load_env($path){
  $vars = [];
  if(!is_readable($path)) return $vars;
  foreach(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line){
    $line = trim($line);
    if($line === '' || $line[0] === '#') continue;
    if(strpos($line, '=') === false) continue;
    list($key, $value) = explode('=', $line, 2);
    $vars[trim($key)] = trim($value);
  }
  return $vars;
}
