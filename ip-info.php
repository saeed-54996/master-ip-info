<?php

// Include the Simple HTML DOM Parser library
require_once "simple_html_dom.php";

// Cache directory
$cacheDir = 'cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Cleanup function to remove old cache files
function cleanOldCache($cacheDir) {
    foreach (new DirectoryIterator($cacheDir) as $file) {
        if ($file->isFile() && (time() - $file->getMTime()) > 2592000) { // Files older than 30 days
            unlink($file->getRealPath());
        }
    }
}

// Helper to normalize whitespace and trim
function clean($str) {
    // replace all whitespace sequences with a single space, then trim ends
    return trim(preg_replace('/\s+/', ' ', $str));
}

// Execute cleanup on script start
cleanOldCache($cacheDir);

$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.150 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Safari/605.1.15',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (iPad; CPU OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 10; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.152 Mobile Safari/537.36',
    'Mozilla/5.0 (Linux; Android 10; SM-T865) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.152 Safari/537.36',
    'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:85.0) Gecko/20100101 Firefox/85.0',
    'Mozilla/5.0 (Windows NT 10.0; rv:85.0) Gecko/20100101 Firefox/85.0',
    'Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14',
    'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.22 (KHTML, like Gecko) Ubuntu Chromium/25.0.1364.160 Chrome/25.0.1364.160 Safari/537.22'
];

// Check for IP parameter
if (!isset($_GET['ip']) || empty($_GET['ip'])) {
    echo json_encode(['ok' => false, 'description' => 'Not detected any IP input']);
    exit;
}

$ip = $_GET['ip'];
$cacheFile = "{$cacheDir}/cache_" . md5($ip) . ".html";
$cacheLifetime = 604800; // 7 days

// Serve the cached file if it exists and is fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $data = file_get_contents($cacheFile);
} else {
    $url = "https://check-host.net/ip-info?host=" . $ip;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_USERAGENT, $userAgents[array_rand($userAgents)]);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $data = curl_exec($curl);
    if ($data === false) {
        echo json_encode(['ok' => false, 'description' => 'Curl error: ' . curl_error($curl)]);
        curl_close($curl);
        exit;
    }
    // Save to cache
    file_put_contents($cacheFile, $data);
    curl_close($curl);
}

// Process the HTML content
$html = str_get_html($data);
if (!$html) {
    echo json_encode(['ok' => false, 'description' => 'Failed to parse HTML']);
    exit;
}

// Parse and clean specific data
$parsedData = [];
foreach ($html->find('.ipinfo-item.mb-3') as $table) {
    $parsedData[] = [
        'Name'        => clean($table->find('strong', 0)->plaintext),
        'ip-range'    => clean($table->find('.break-all', 2)->plaintext),
        'isp'         => clean($table->find('.break-all', 3)->plaintext),
        'org'         => clean($table->find('.break-all', 4)->plaintext),
        'country'     => clean($table->find('.break-words', 0)->plaintext),
        'region'      => clean($table->find('.break-all', 5)->plaintext),
        'city'        => clean($table->find('.break-all', 6)->plaintext),
        'time-zone'   => clean($table->find('.break-all', 7)->plaintext),
        'local-time'  => clean($table->find('.break-words', 1)->plaintext),
        'postal-code' => clean($table->find('.break-all', 8)->plaintext),
    ];
}

echo json_encode(['ok' => true, 'data' => $parsedData]);
