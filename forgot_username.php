<?php
require_once __DIR__ . '/includes/bootstrap.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $msg = 'If an account exists for that email, the username has been sent.';

    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $msg = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = app_pdo();
            $stmt = $pdo->prepare('SELECT name, username, email FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $tplStmt = $pdo->prepare("SELECT template_subject, template_body FROM email_templates WHERE template_key = 'forgot_username' LIMIT 1");
                $tplStmt->execute();
                $tpl = $tplStmt->fetch();

                if ($tpl) {
                    $subject = str_replace(['{user}', '{username}'], [$user['name'], $user['username']], $tpl['template_subject']);
                    $body = str_replace(['{user}', '{username}'], [$user['name'], $user['username']], $tpl['template_body']);
                } else {
                    $subject = APP_NAME . ' username reminder';
                    $body = "Hello {$user['name']},\n\nYour username is: {$user['username']}";
                }

                $headers = "From: No-Reply <" . MAIL_FROM . ">\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                mail($user['email'], $subject, $body, $headers);
            }
        } catch (Throwable $exception) {
            error_log('Forgot username error: ' . $exception->getMessage());
        }
    }
}

$pageTitle = 'Forgot Username';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="container">
    <div class="card">
        <h2>Forgot Username</h2>
        <?php if ($msg !== ''): ?>
            <p><?php echo e($msg); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">

            <label for="email">Enter your email:</label>
            <input type="email" id="email" name="email" autocomplete="email" required>

            <button type="submit">Retrieve Username</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
