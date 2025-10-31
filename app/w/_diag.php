<?php
// Minimal diagnostics endpoint (no MediaWiki bootstrap)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
 * Note that this snippet could be removed if using the a dotenv extension
 * which may also offer some small performance advantages.
 */
foreach (parse_ini_file('/home/doomwiki/.env') as $key => $value) {
  if (!getenv($key)) {
    putenv("$key=$value");
  }
}

// Show PHP and mysqli modes to help debug mysqli_sql_exception issues
$info = [
    'php_version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'mysqli.report_mode_ini' => ini_get('mysqli.report_mode'),
    'env' => [
        'MYSQL_HOSTNAME' => getenv('MYSQL_HOSTNAME') ?: null,
        'MYSQL_DATABASE' => getenv('MYSQL_DATABASE') ?: null,
        'MYSQL_USERNAME' => getenv('MYSQL_USERNAME') ?: null,
        // Never print password values
        'MYSQL_PASSWORD_set' => getenv('MYSQL_PASSWORD') !== false,
    ],
    'script' => __FILE__,
];

header('Content-Type: application/json');
echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
