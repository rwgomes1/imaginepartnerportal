<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        try {
            $pdo = app_pdo();

            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                if ((int) ($user['force_2fa_setup'] ?? 0) === 1) {
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_name'] = $user['name'] ?: $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: /admin/security/setup_2fa.php');
                    exit;
                }

                if ((int) ($user['twofa_enabled'] ?? 0) === 1) {
                    $_SESSION['temp_user_id'] = (int) $user['id'];
                    $_SESSION['temp_user_role'] = $user['role'];
                    header('Location: /admin/security/2fa_verify.php');
                    exit;
                }

                app_log_firewall('LOGIN_SUCCESS', 'User logged in successfully', (int) $user['id']);

                try {
                    $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
                    $updateStmt->execute([':id' => $user['id']]);
                } catch (PDOException $exception) {
                    error_log('Unable to update last_login: ' . $exception->getMessage());
                }

                app_finish_login($user);
                if ((int) ($user['forced_password_reset'] ?? 0) === 1) {
                    header('Location: /reset_password.php');
                    exit;
                }

                app_redirect_for_role($user['role']);
            }

            app_log_firewall('LOGIN_FAIL', 'Unsuccessful login attempt for username: ' . $username);
            $error = 'Invalid username or password.';
        } catch (PDOException $exception) {
            error_log('DB error during login: ' . $exception->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="container">
    <div class="card">
        <h2>Login</h2>
        <?php if ($error !== ''): ?>
            <p class="error-message"><?php echo e($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">

            <label for="username">Username:</label>
            <input type="text" id="username" name="username" autocomplete="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Login</button>
        </form>
    </div>
</div>

<div class="auth-links">
    <a href="forgot_username.php">Forgot Username</a> |
    <a href="forgot_password.php">Forgot Password</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
