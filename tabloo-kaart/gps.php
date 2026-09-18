<?php
// Vahendab TLT GPS-voogu, sest transport.tallinn.ee/gps.txt ei luba
// brauseril teiselt domeenilt andmeid lugeda (CORS-päis puudub).
// Vastust hoitakse 3 sekundit puhvris, et mitu avatud tahvlit ei koormaks TLT serverit.

const GPS_URL = 'https://transport.tallinn.ee/gps.txt';
const CACHE_SECONDS = 3;

$cacheFile = sys_get_temp_dir() . '/viimsi_tabloo_gps.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (is_file($cacheFile) && time() - filemtime($cacheFile) < CACHE_SECONDS) {
    readfile($cacheFile);
    exit;
}

$body = false;
if (function_exists('curl_init')) {
    $ch = curl_init(GPS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'viimsi-tabloo',
    ]);
    $body = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $body = false;
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'viimsi-tabloo']]);
    $body = @file_get_contents(GPS_URL, false, $ctx);
}

if ($body === false) {
    // TLT ei vastanud: anna vanem puhver, kui see on olemas
    if (is_file($cacheFile)) {
        readfile($cacheFile);
        exit;
    }
    http_response_code(502);
    echo 'GPS-voog ei vasta';
    exit;
}

@file_put_contents($cacheFile, $body, LOCK_EX);
echo $body;
