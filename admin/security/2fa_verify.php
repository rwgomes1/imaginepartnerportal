<?php
use OTPHP\TOTP;

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['temp_user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = app_pdo();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $_SESSION['temp_user_id']]);
$user = $stmt->fetch();

if (!$user || (int) $user['twofa_enabled'] !== 1) {
    header('Location: ../../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please try again.';
    } else {
        $otpCode = trim($_POST['otp_code']);

        $totp = TOTP::create($user['twofa_secret'], 30, 'sha1', 6);
        $totp->setLabel($user['username']);
        $totp->setIssuer(APP_NAME);

        if ($totp->verify($otpCode)) {
            app_log_firewall('LOGIN_SUCCESS', '2FA code verified successfully', (int) $user['id']);

            $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            $updateStmt->execute([':id' => $user['id']]);

            app_finish_login($user);
            if ((int) ($user['forced_password_reset'] ?? 0) === 1) {
                header('Location: /reset_password.php');
                exit;
            }

            app_redirect_for_role($user['role']);
        }

        app_log_firewall('LOGIN_FAIL', '2FA code invalid', (int) $user['id']);
        $message = 'Invalid 2FA code. Please try again.';
    }
}

$pageTitle = 'Two-Factor Authentication';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="container">
    <div class="card">
        <h2>Two-Factor Authentication</h2>
        <?php if ($message !== ''): ?>
            <p class="error-message"><?php echo e($message); ?></p>
        <?php endif; ?>
        <p>Please enter the 6-digit code from your authenticator app.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">

            <label for="otp_code">OTP Code:</label>
            <input type="text" id="otp_code" name="otp_code" inputmode="numeric" autocomplete="one-time-code" required>

            <button type="submit" class="button">Verify</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
