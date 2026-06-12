<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use GeoIp2\Database\Reader;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Firewall DB connection error");
}

// Path to your local MaxMind DB (e.g., /admin/firewall/GeoLite2-Country.mmdb)
$maxmindDbPath = __DIR__ . '/GeoLite2-Country.mmdb';

// 1) Gather request info
$userIp     = $_SERVER['REMOTE_ADDR'];
$requestUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$referer    = $_SERVER['HTTP_REFERER'] ?? 'No referer';
$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$userId     = $_SESSION['user_id'] ?? null; // If logged in

// 2) Whitelist Check
$isWhitelisted = checkIpList($pdo, $userIp, 'whitelist');
if ($isWhitelisted) {
    logEvent($pdo, 'ALLOW', $userIp, $requestUrl, $referer, $userAgent, $userId, 'Whitelisted IP');
    return; // Allow request, skip further checks
}

// 3) Blacklist Check
$isBlacklisted = checkIpList($pdo, $userIp, 'blacklist');
if ($isBlacklisted) {
    logEvent($pdo, 'BLOCK', $userIp, $requestUrl, $referer, $userAgent, $userId, 'Blacklisted IP');
    blockRequest("Your IP address is blocked.");
}

// 4) Country/Continent Check (always enabled)
if (file_exists($maxmindDbPath)) {
    try {
        $reader = new Reader($maxmindDbPath);
        $record = $reader->country($userIp);

        $countryCode   = strtoupper($record->country->isoCode);    // e.g. "US"
        $continentCode = strtoupper($record->continent->code);       // e.g. "NA"

        // Always check country blocking
        if (isBlockedCountry($pdo, $countryCode)) {
            logEvent($pdo, 'BLOCK', $userIp, $requestUrl, $referer, $userAgent, $userId, "Blocked country: $countryCode");
            blockRequest("Access from your country ($countryCode) is blocked.");
        }

        // Always check continent blocking
        if (isBlockedContinent($pdo, $continentCode)) {
            logEvent($pdo, 'BLOCK', $userIp, $requestUrl, $referer, $userAgent, $userId, "Blocked continent: $continentCode");
            blockRequest("Access from your continent ($continentCode) is blocked.");
        }
    } catch (Exception $e) {
        // If MaxMind fails, we can log the error (optional) and allow the request.
        logEvent($pdo, 'ERROR', $userIp, $requestUrl, $referer, $userAgent, $userId, "MaxMind error: " . $e->getMessage());
    }
}

// 5) Allow the request
logEvent($pdo, 'ALLOW', $userIp, $requestUrl, $referer, $userAgent, $userId, 'Allowed request');

// Helper Functions
function checkIpList($pdo, $ip, $listType) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM firewall_ip_list WHERE ip_address = :ip AND list_type = :lt");
    $stmt->execute([':ip' => $ip, ':lt' => $listType]);
    return $stmt->fetchColumn() > 0;
}

function blockRequest($message) {
    header('HTTP/1.1 403 Forbidden');
    echo "<div style='padding:20px; text-align:center; font-family:Arial;'>
            <h1>403 Forbidden</h1>
            <p>$message</p>
          </div>";
    exit;
}

function logEvent($pdo, $eventType, $ip, $url, $referer, $userAgent, $userId, $details) {
    $stmt = $pdo->prepare("
        INSERT INTO firewall_logs (event_type, ip_address, request_url, referer, user_agent, user_id, details)
        VALUES (:et, :ip, :url, :ref, :ua, :uid, :det)
    ");
    $stmt->execute([
        ':et'   => $eventType,
        ':ip'   => $ip,
        ':url'  => $url,
        ':ref'  => $referer,
        ':ua'   => $userAgent,
        ':uid'  => $userId,
        ':det'  => $details
    ]);
}

function getSetting($pdo, $key, $default='') {
    // This function is no longer used in our current design.
    $stmt = $pdo->prepare("SELECT setting_value FROM firewall_settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $val = $stmt->fetchColumn();
    return ($val !== false) ? $val : $default;
}

function isBlockedCountry($pdo, $code) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM firewall_country_list WHERE code = :code AND code_type='country'");
    $stmt->execute([':code' => $code]);
    return $stmt->fetchColumn() > 0;
}

function isBlockedContinent($pdo, $code) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM firewall_country_list WHERE code = :code AND code_type='continent'");
    $stmt->execute([':code' => $code]);
    return $stmt->fetchColumn() > 0;
}
