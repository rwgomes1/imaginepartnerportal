<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../../includes/config.php';
require_once '../../vendor/autoload.php';

use OTPHP\TOTP;

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Fetch current user from the database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If the user doesn't exist or already has 2FA enabled, redirect them
if (!$user) {
    header('Location: ../../login.php');
    exit;
}
if ($user['twofa_enabled'] == 1) {
    header('Location: ../../index.php'); // or your dashboard page
    exit;
}

// If a temporary 2FA secret has not been generated for this session, create one
if (empty($_SESSION['temp_2fa_secret'])) {
    // Generate a TOTP secret using OT PHP
    $tempTotp = TOTP::create();
    $_SESSION['temp_2fa_secret'] = $tempTotp->getSecret();
}
$secret = $_SESSION['temp_2fa_secret'];

// Create the TOTP object using the secret
$totp = TOTP::create($secret, 30, 'sha1', 6);
$totp->setLabel($user['username']);
$issuerName = "YourSiteName"; // Change this to your site’s name
$totp->setIssuer($issuerName);

// Generate the provisioning URI for the TOTP (to be encoded in a QR code)
$provisioningUri = $totp->getProvisioningUri();

// Use a free QR code API to generate the QR image URL
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($provisioningUri) . '&size=200x200';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $otpCode = trim($_POST['otp_code']);
    
    // Verify the code using the TOTP object
    if ($totp->verify($otpCode)) {
        // Code is correct - update the user's record to enable 2FA
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET twofa_enabled = 1, twofa_secret = :secret, force_2fa_setup = 0 
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':secret' => $secret,
            ':id'     => $user['id']
        ]);
        // Clear the temporary secret from the session
        unset($_SESSION['temp_2fa_secret']);
        header('Location: ../../index.php'); // Redirect to dashboard or homepage
        exit;
    } else {
        $message = 'Invalid 2FA code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Set Up Two-Factor Authentication</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="container">
        <div class="card">
            <h2>Set Up Two-Factor Authentication</h2>
            <?php if (!empty($message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <p>Scan the QR code below with Google Authenticator (or a similar TOTP app), or enter the secret manually.</p>
            <div style="text-align:center; margin:15px 0;">
                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code for 2FA">
            </div>
            <p style="text-align:center;">Secret: <strong><?php echo htmlspecialchars($secret); ?></strong></p>
            <form method="POST">
                <label for="otp_code">Enter 6-digit code:</label>
                <input type="text" id="otp_code" name="otp_code" required style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                <button type="submit" class="button" style="margin-top:15px;">Verify</button>
            </form>
        </div>
    </div>
</main>
<?php include '../../includes/footer.php'; ?>
</body>
</html>
