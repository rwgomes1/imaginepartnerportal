<?php
session_start();
require_once 'includes/config.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Find user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(16));
            // Store the token in the password_resets table
            $resetStmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, reset_token, created_at)
                VALUES (:uid, :token, NOW())
                ON DUPLICATE KEY UPDATE reset_token = :token, created_at = NOW()
            ");
            $resetStmt->execute([
                ':uid'  => $user['id'],
                ':token'=> $token
            ]);

            // Build reset link using your domain
            $reset_link = "https://rd6.imagineteam.solutions/reset_password.php?token={$token}";

            // Fetch the forgot_password template from email_templates
            $tplStmt = $pdo->prepare("SELECT template_subject, template_body FROM email_templates WHERE template_key = 'forgot_password' LIMIT 1");
            $tplStmt->execute();
            $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);

            if ($tpl) {
                // Replace placeholders: {user} and {reset_link}
                $subject = str_replace(['{user}', '{reset_link}'], [$user['name'], $reset_link], $tpl['template_subject']);
                $body    = str_replace(['{user}', '{reset_link}'], [$user['name'], $reset_link], $tpl['template_body']);

                // Set proper From header using your domain
                $headers = "From: No-Reply <no-reply@rd6.imagineteam.solutions>\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($email, $subject, $body, $headers)) {
                    $msg = "Check your email for a reset link.";
                } else {
                    $msg = "Failed to send email. Please try again later.";
                }
            } else {
                $msg = "Forgot Password template not found!";
            }
        } else {
            $msg = "No user found with that email.";
        }
    } catch (PDOException $e) {
        error_log('DB error: ' . $e->getMessage());
        $msg = "DB error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <div class="container">
        <h2>Forgot Password</h2>
        <?php if (!empty($msg)): ?>
            <p><?php echo htmlspecialchars($msg); ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="email">Enter your email:</label>
            <input type="email" name="email" required>
            <button type="submit">Reset Password</button>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
