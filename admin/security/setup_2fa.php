<?php
use OTPHP\TOTP;

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = app_pdo();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../../login.php');
    exit;
}

if ((int) $user['twofa_enabled'] === 1) {
    app_redirect_for_role($user['role']);
}

if (empty($_SESSION['temp_2fa_secret'])) {
    $_SESSION['temp_2fa_secret'] = TOTP::create()->getSecret();
}

$secret = $_SESSION['temp_2fa_secret'];
$totp = TOTP::create($secret, 30, 'sha1', 6);
$totp->setLabel($user['username']);
$totp->setIssuer(APP_NAME);

$provisioningUri = $totp->getProvisioningUri();
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($provisioningUri) . '&size=200x200';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please try again.';
    } elseif ($totp->verify(trim($_POST['otp_code']))) {
        $updateStmt = $pdo->prepare(
            'UPDATE users
             SET twofa_enabled = 1, twofa_secret = :secret, force_2fa_setup = 0
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':secret' => $secret,
            ':id' => $user['id'],
        ]);

        unset($_SESSION['temp_2fa_secret']);
        app_finish_login($user);
        if ((int) ($user['forced_password_reset'] ?? 0) === 1) {
            header('Location: /reset_password.php');
            exit;
        }

        app_redirect_for_role($user['role']);
    } else {
        $message = 'Invalid 2FA code. Please try again.';
    }
}

$pageTitle = 'Set Up Two-Factor Authentication';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="container">
    <div class="card">
        <h2>Set Up Two-Factor Authentication</h2>
        <?php if ($message !== ''): ?>
            <p class="error-message"><?php echo e($message); ?></p>
        <?php endif; ?>
        <p>Scan the QR code below with Google Authenticator or a similar TOTP app, or enter the secret manually.</p>
        <div style="text-align:center; margin:15px 0;">
            <img src="<?php echo e($qrCodeUrl); ?>" alt="QR Code for 2FA">
        </div>
        <p style="text-align:center;">Secret: <strong><?php echo e($secret); ?></strong></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">

            <label for="otp_code">Enter 6-digit code:</label>
            <input type="text" id="otp_code" name="otp_code" inputmode="numeric" autocomplete="one-time-code" required style="padding:10px; border:1px solid #ccc; border-radius:4px;">

            <button type="submit" class="button" style="margin-top:15px;">Verify</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
