<?php
// Basic DB connectivity diagnostics. Remove after troubleshooting.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load env like LocalSettings (without bootstrapping MediaWiki)
if (is_readable('/home/doomwiki/.env')) {
    foreach (parse_ini_file('/home/doomwiki/.env') as $k => $v) {
        if (!getenv($k)) putenv("$k=$v");
    }
}

$host = getenv('MYSQL_HOSTNAME');
$user = getenv('MYSQL_USERNAME');
$db   = getenv('MYSQL_DATABASE');
$port = getenv('MYSQL_PORT');
$hasPwd = getenv('MYSQL_PASSWORD') !== false;

$res = [
    'host' => $host.":".$port,
    'db' => $db,
    'port' => $port,
    'php_version' => PHP_VERSION,
    'resolve' => [
        'gethostbyname' => $host ? @gethostbyname($host) : null,
        'gethostbynamel' => $host ? @gethostbynamel($host) : null,
    ],
    'tcp' => null,
    'mysqli_connect' => null,
];

// 1) Raw TCP connect test
if ($host) {
    $t0 = microtime(true);
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 5.0);
    $dt = microtime(true) - $t0;
    if ($fp) {
        fclose($fp);
        $res['tcp'] = [ 'ok' => true, 'ms' => (int)round($dt*1000) ];
    } else {
        $res['tcp'] = [ 'ok' => false, 'ms' => (int)round($dt*1000), 'errno' => $errno, 'error' => $errstr ];
    }
}

// 2) Mysqli connect (optional) — only if password is present in env
if ($host && $user && $hasPwd) {
    $mysqli = mysqli_init();
    // Keep timeouts tight for diagnostics
    mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $t0 = microtime(true);
    $ok = @mysqli_real_connect($mysqli, $host.":".$port, getenv('MYSQL_USERNAME'), getenv('MYSQL_PASSWORD'), $db);
    $dt = microtime(true) - $t0;
    if ($ok) {
        $res['mysqli_connect'] = [ 'ok' => true, 'ms' => (int)round($dt*1000) ];
        mysqli_close($mysqli);
    } else {
        $res['mysqli_connect'] = [ 'ok' => false, 'ms' => (int)round($dt*1000), 'errno' => mysqli_connect_errno(), 'error' => mysqli_connect_error() ];
    }
}

header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), "\n";
