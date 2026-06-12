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
            // Fetch the forgot_username template from email_templates table
            $tplStmt = $pdo->prepare("SELECT template_subject, template_body FROM email_templates WHERE template_key = 'forgot_username' LIMIT 1");
            $tplStmt->execute();
            $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);

            if ($tpl) {
                // Replace placeholders: {user} and {username}
                $subject = str_replace(['{user}', '{username}'], [$user['name'], $user['username']], $tpl['template_subject']);
                $body    = str_replace(['{user}', '{username}'], [$user['name'], $user['username']], $tpl['template_body']);

                // Set the From header using your domain
                $headers = "From: No-Reply <no-reply@rd6.imagineteam.solutions>\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($email, $subject, $body, $headers)) {
                    $msg = "Your username has been emailed to you.";
                } else {
                    $msg = "Failed to send email. Please try again later.";
                }
            } else {
                $msg = "Forgot Username template not found!";
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
    <title>Forgot Username</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <div class="container">
        <div class="card">
            <h2>Forgot Username</h2>
            <?php if (!empty($msg)): ?>
                <p><?php echo htmlspecialchars($msg); ?></p>
            <?php endif; ?>
            <form method="POST">
                <label for="email">Enter your email:</label>
                <input type="email" name="email" required>
                <button type="submit">Retrieve Username</button>
            </form>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
