<?php
session_start();
require_once '../../includes/config.php';
require_once '../../vendor/autoload.php';

use OTPHP\TOTP;

// Ensure that the temporary user session exists for 2FA verification
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Retrieve user information using the temporary user ID
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['temp_user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user doesn't exist or does not have 2FA enabled, redirect
if (!$user || $user['twofa_enabled'] != 1) {
    header('Location: ../../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $otpCode = trim($_POST['otp_code']);
    
    // Create a TOTP object using the user's stored secret
    $totp = TOTP::create($user['twofa_secret'], 30, 'sha1', 6);
    $totp->setLabel($user['username']);
    $totp->setIssuer("YourSiteName"); // Update with your site’s name
    
    if ($totp->verify($otpCode)) {
        // Code is valid, log the successful 2FA event
        $logStmt = $pdo->prepare("
            INSERT INTO firewall_logs (event_type, ip_address, user_id, details)
            VALUES ('LOGIN_SUCCESS', :ip, :user_id, '2FA code verified successfully')
        ");
        $logStmt->execute([
            ':ip'      => $_SERVER['REMOTE_ADDR'],
            ':user_id' => $user['id']
        ]);
        
        // Update last_login for the user
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);
        
        // Set full session variables now that 2FA is verified
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'];
        
        // Remove temporary user info
        unset($_SESSION['temp_user_id']);
        
        // Redirect based on user role
        $redirects = [
            'partner'    => '/partners/dashboard.php',
            'admin'      => '/admin/dashboard.php',
            'superadmin' => '/admin/dashboard.php',
            'user'       => '/index.php'
        ];
        header('Location: ' . ($redirects[$user['role']] ?? '/index.php'));
        exit;
    } else {
        // Log failed 2FA attempt
        $logStmt = $pdo->prepare("
            INSERT INTO firewall_logs (event_type, ip_address, user_id, details)
            VALUES ('LOGIN_FAIL', :ip, :user_id, '2FA code invalid')
        ");
        $logStmt->execute([
            ':ip'      => $_SERVER['REMOTE_ADDR'],
            ':user_id' => $user['id']
        ]);
        
        $message = 'Invalid 2FA code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Two-Factor Authentication</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="container">
        <div class="card">
            <h2>Two-Factor Authentication</h2>
            <?php if (!empty($message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <p>Please enter the 6-digit code from your authenticator app.</p>
            <form method="POST">
                <label for="otp_code">OTP Code:</label>
                <input type="text" id="otp_code" name="otp_code" required style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                <button type="submit" class="button" style="margin-top:15px;">Verify</button>
            </form>
        </div>
    </div>
</main>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
