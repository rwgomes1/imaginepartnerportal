<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = app_pdo();
$message = '';
$success = false;
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$user = null;
$isTokenReset = $token !== '';

if ($isTokenReset) {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.name, u.role, u.partner_id, pr.reset_token
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.reset_token = :token
           AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();
} elseif (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, username, name, role, partner_id FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
}

if (!$user) {
    $message = $isTokenReset
        ? 'This reset link is invalid or expired. Please request a new one.'
        : 'Please log in to reset your password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please try again.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'UPDATE users
             SET password_hash = :hash, forced_password_reset = 0
             WHERE id = :id'
        );
        $stmt->execute([
            ':hash' => $hashed,
            ':id' => $user['id'],
        ]);

        if ($isTokenReset) {
            $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE reset_token = :token');
            $deleteStmt->execute([':token' => $token]);
            $success = true;
            $message = 'Your password has been reset. You can now log in.';
        } else {
            app_finish_login($user);
            app_redirect_for_role($user['role']);
        }
    }
}

$pageTitle = 'Reset Your Password';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="container">
    <div class="card">
        <h2>Reset Your Password</h2>
        <?php if ($message !== ''): ?>
            <p class="<?php echo $success ? 'success-message' : 'error-message'; ?>"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if ($user && !$success): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">
                <?php if ($isTokenReset): ?>
                    <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <?php endif; ?>

                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>

                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>

                <button type="submit">Reset Password</button>
            </form>
        <?php elseif ($success): ?>
            <p style="text-align:center;"><a href="/login.php">Return to login</a></p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
