<?php
require_once __DIR__ . '/includes/bootstrap.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $msg = 'If an account exists for that email, a reset link has been sent.';

    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $msg = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = app_pdo();
            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(16));
                $resetStmt = $pdo->prepare(
                    'INSERT INTO password_resets (user_id, reset_token, created_at)
                     VALUES (:uid, :token, NOW())
                     ON DUPLICATE KEY UPDATE reset_token = :token, created_at = NOW()'
                );
                $resetStmt->execute([
                    ':uid' => $user['id'],
                    ':token' => $token,
                ]);

                $resetLink = APP_URL . "/reset_password.php?token={$token}";
                $tplStmt = $pdo->prepare("SELECT template_subject, template_body FROM email_templates WHERE template_key = 'forgot_password' LIMIT 1");
                $tplStmt->execute();
                $tpl = $tplStmt->fetch();

                if ($tpl) {
                    $subject = str_replace(['{user}', '{reset_link}'], [$user['name'], $resetLink], $tpl['template_subject']);
                    $body = str_replace(['{user}', '{reset_link}'], [$user['name'], $resetLink], $tpl['template_body']);
                } else {
                    $subject = APP_NAME . ' password reset';
                    $body = "Hello {$user['name']},\n\nUse this link to reset your password: {$resetLink}";
                }

                $headers = "From: No-Reply <" . MAIL_FROM . ">\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                mail($user['email'], $subject, $body, $headers);
            }
        } catch (Throwable $exception) {
            error_log('Forgot password error: ' . $exception->getMessage());
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="container">
    <div class="card">
        <h2>Forgot Password</h2>
        <?php if ($msg !== ''): ?>
            <p><?php echo e($msg); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">

            <label for="email">Enter your email:</label>
            <input type="email" id="email" name="email" autocomplete="email" required>

            <button type="submit">Reset Password</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
