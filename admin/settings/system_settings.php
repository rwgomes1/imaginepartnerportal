<?php
session_start();
require_once '../../includes/config.php';

// Only SuperAdmins can access System Settings
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /login.php');
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// -------------------------------------------------------------------
// Helper Functions to manage settings in system_settings table
// -------------------------------------------------------------------
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES (:k, :v, NOW())
        ON DUPLICATE KEY UPDATE setting_value = :v, updated_at = NOW()
    ");
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// -------------------------------------------------------------------
// Load existing settings from DB (or use defaults if not found)
// -------------------------------------------------------------------
$site_name         = getSetting($pdo, 'site_name', 'My Dev Site');
$site_url          = getSetting($pdo, 'site_url', 'https://rd6.imagineteam.solutions'); // New field
$send_mail         = getSetting($pdo, 'send_mail', 'yes'); // yes/no
$disable_mass_mail = getSetting($pdo, 'disable_mass_mail', 'no'); // yes/no
$from_email        = getSetting($pdo, 'from_email', 'webmaster@example.com');
$from_name         = getSetting($pdo, 'from_name', 'Imagine Team');
$reply_to_email    = getSetting($pdo, 'reply_to_email', '');
$reply_to_name     = getSetting($pdo, 'reply_to_name', '');
// Store a "test_email" in the DB so we can send a test mail to it.
$test_email        = getSetting($pdo, 'test_email', '');
$mailer            = getSetting($pdo, 'mailer', 'php'); // php/sendmail/smtp
$sendmail_path     = getSetting($pdo, 'sendmail_path', '/usr/sbin/sendmail');
$smtp_host         = getSetting($pdo, 'smtp_host', 'localhost');
$smtp_port         = getSetting($pdo, 'smtp_port', '25');
$smtp_security     = getSetting($pdo, 'smtp_security', 'none'); // none/ssl/tls
$smtp_auth         = getSetting($pdo, 'smtp_auth', 'yes'); // yes/no

$message = '';

// -------------------------------------------------------------------
// Handle Form Submission
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    // Grab posted values (null coalesce + trim + htmlspecialchars to avoid null issues)
    $site_name_post         = htmlspecialchars(trim($_POST['site_name'] ?? ''));
    $site_url_post          = htmlspecialchars(trim($_POST['site_url'] ?? '')); // New field
    $send_mail_post         = htmlspecialchars(trim($_POST['send_mail'] ?? 'no'));
    $disable_mass_mail_post = htmlspecialchars(trim($_POST['disable_mass_mail'] ?? 'no'));
    $from_email_post        = htmlspecialchars(trim($_POST['from_email'] ?? ''));
    $from_name_post         = htmlspecialchars(trim($_POST['from_name'] ?? ''));
    $reply_to_email_post    = htmlspecialchars(trim($_POST['reply_to_email'] ?? ''));
    $reply_to_name_post     = htmlspecialchars(trim($_POST['reply_to_name'] ?? ''));
    // We'll retrieve the test_email for the actual test
    $test_email_post        = htmlspecialchars(trim($_POST['test_email'] ?? ''));
    $mailer_post            = htmlspecialchars(trim($_POST['mailer'] ?? 'php'));
    $sendmail_path_post     = htmlspecialchars(trim($_POST['sendmail_path'] ?? ''));
    $smtp_host_post         = htmlspecialchars(trim($_POST['smtp_host'] ?? ''));
    $smtp_port_post         = htmlspecialchars(trim($_POST['smtp_port'] ?? ''));
    $smtp_security_post     = htmlspecialchars(trim($_POST['smtp_security'] ?? 'none'));
    $smtp_auth_post         = htmlspecialchars(trim($_POST['smtp_auth'] ?? 'no'));

    // Check if "Send Test Mail" was clicked
    if (isset($_POST['send_test_mail'])) {
        // We'll do a real test only if mailer is "php"
        if ($mailer_post !== 'php') {
            $message = "Test mail can only be sent using PHP Mail in this demo.";
        } else {
            // We'll use the test_email if provided, otherwise fallback to from_email
            $to = !empty($test_email_post) ? $test_email_post : $from_email_post;

            // Build the headers
            $subject = "Test Email from System Settings";
            $body    = "This is a real test email sent from your system using PHP mail().\n\n";
            $body   .= "Mailer: PHP Mail\n";
            $headers = "From: {$from_name_post} <{$from_email_post}>\r\n";
            $headers .= "Reply-To: {$reply_to_name_post} <{$reply_to_email_post}>\r\n";
            // You can add more headers if needed (e.g. CC, BCC)

            // Attempt to send using mail()
            if (mail($to, $subject, $body, $headers)) {
                $message = "Test mail sent successfully to {$to}. Check your inbox.";
            } else {
                $message = "Failed to send test mail. Please check your PHP mail configuration.";
            }
        }
    } else {
        // Update DB with new settings
        setSetting($pdo, 'site_name', $site_name_post);
        setSetting($pdo, 'site_url', $site_url_post); // Update the site URL
        setSetting($pdo, 'send_mail', $send_mail_post);
        setSetting($pdo, 'disable_mass_mail', $disable_mass_mail_post);
        setSetting($pdo, 'from_email', $from_email_post);
        setSetting($pdo, 'from_name', $from_name_post);
        setSetting($pdo, 'reply_to_email', $reply_to_email_post);
        setSetting($pdo, 'reply_to_name', $reply_to_name_post);
        setSetting($pdo, 'test_email', $test_email_post); // store the test email as well
        setSetting($pdo, 'mailer', $mailer_post);
        setSetting($pdo, 'sendmail_path', $sendmail_path_post);
        setSetting($pdo, 'smtp_host', $smtp_host_post);
        setSetting($pdo, 'smtp_port', $smtp_port_post);
        setSetting($pdo, 'smtp_security', $smtp_security_post);
        setSetting($pdo, 'smtp_auth', $smtp_auth_post);

        $message = "Settings updated successfully!";

        // Reload variables from DB so the form shows updated values
        $site_name         = getSetting($pdo, 'site_name', 'My Dev Site');
        $site_url          = getSetting($pdo, 'site_url', 'https://rd6.imagineteam.solutions');
        $send_mail         = getSetting($pdo, 'send_mail', 'yes');
        $disable_mass_mail = getSetting($pdo, 'disable_mass_mail', 'no');
        $from_email        = getSetting($pdo, 'from_email', 'webmaster@example.com');
        $from_name         = getSetting($pdo, 'from_name', 'Imagine Team');
        $reply_to_email    = getSetting($pdo, 'reply_to_email', '');
        $reply_to_name     = getSetting($pdo, 'reply_to_name', '');
        $test_email        = getSetting($pdo, 'test_email', '');
        $mailer            = getSetting($pdo, 'mailer', 'php');
        $sendmail_path     = getSetting($pdo, 'sendmail_path', '/usr/sbin/sendmail');
        $smtp_host         = getSetting($pdo, 'smtp_host', 'localhost');
        $smtp_port         = getSetting($pdo, 'smtp_port', '25');
        $smtp_security     = getSetting($pdo, 'smtp_security', 'none');
        $smtp_auth         = getSetting($pdo, 'smtp_auth', 'yes');
    }
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
    <div class="container">

        <?php if (!empty($message)): ?>
            <div class="card" style="margin-bottom:20px;">
                <p class="success-message" style="padding:10px;">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Card 1: General Site Settings -->
            <div class="card" style="margin-bottom:20px; text-align:left; padding:20px;">
                <h2 style="margin-top:0;">General Site Settings</h2>
                <div style="margin-bottom:20px;">
                    <label for="site_name" style="font-weight:bold; display:block; margin-bottom:5px;">
                        Site Name:
                    </label>
                    <input type="text" id="site_name" name="site_name" 
                           value="<?php echo htmlspecialchars($site_name ?? ''); ?>"
                           style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="margin-bottom:20px;">
                    <label for="site_url" style="font-weight:bold; display:block; margin-bottom:5px;">
                        Site URL:
                    </label>
                    <input type="text" id="site_url" name="site_url"
                           value="<?php echo htmlspecialchars($site_url ?? ''); ?>"
                           style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
            </div>

            <!-- Card 2: Mail Settings (two-column grid) -->
            <div class="card" style="margin-bottom:20px; text-align:left; padding:20px;">
                <h2 style="margin-top:0;">Mail Settings</h2>

                <!-- We'll use a two-column grid for these fields -->
                <div style="
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 20px;
                ">

                    <!-- Column 1: Send Mail toggle, From Email, From Name, etc. -->
                    <div>
                        <label style="font-weight:bold; margin-bottom:5px; display:block;">
                            Send Mail:
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:5px;">
                            <input type="radio" name="send_mail" value="yes"
                                   <?php echo ($send_mail === 'yes') ? 'checked' : ''; ?>>
                            Yes
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:5px;">
                            <input type="radio" name="send_mail" value="no"
                                   <?php echo ($send_mail === 'no') ? 'checked' : ''; ?>>
                            No
                        </label>

                        <label style="font-weight:bold; margin-top:15px; display:block;">
                            Disable Mass Mail:
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:5px;">
                            <input type="radio" name="disable_mass_mail" value="yes"
                                   <?php echo ($disable_mass_mail === 'yes') ? 'checked' : ''; ?>>
                            Yes
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:5px;">
                            <input type="radio" name="disable_mass_mail" value="no"
                                   <?php echo ($disable_mass_mail === 'no') ? 'checked' : ''; ?>>
                            No
                        </label>

                        <label for="from_email" style="font-weight:bold; margin-top:15px; display:block;">
                            From Email:
                        </label>
                        <input type="email" id="from_email" name="from_email"
                               value="<?php echo htmlspecialchars($from_email ?? ''); ?>"
                               style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">

                        <label for="from_name" style="font-weight:bold; margin-top:15px; display:block;">
                            From Name:
                        </label>
                        <input type="text" id="from_name" name="from_name"
                               value="<?php echo htmlspecialchars($from_name ?? ''); ?>"
                               style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <!-- Column 2: Reply-To, Test Email, Mailer, etc. -->
                    <div>
                        <label for="reply_to_email" style="font-weight:bold; margin-bottom:5px; display:block;">
                            Reply-To Email:
                        </label>
                        <input type="email" id="reply_to_email" name="reply_to_email"
                               value="<?php echo htmlspecialchars($reply_to_email ?? ''); ?>"
                               style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">

                        <label for="reply_to_name" style="font-weight:bold; margin-top:15px; display:block;">
                            Reply-To Name:
                        </label>
                        <input type="text" id="reply_to_name" name="reply_to_name"
                               value="<?php echo htmlspecialchars($reply_to_name ?? ''); ?>"
                               style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">

                        <!-- Test Email field for sending real test mail -->
                        <label for="test_email" style="font-weight:bold; margin-top:15px; display:block;">
                            Test Email (for sending test mail):
                        </label>
                        <input type="email" id="test_email" name="test_email"
                               value="<?php echo htmlspecialchars($test_email ?? ''); ?>"
                               style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">

                        <label for="mailer" style="font-weight:bold; margin-top:15px; display:block;">
                            Mailer:
                        </label>
                        <select id="mailer" name="mailer" onchange="toggleMailerOptions()"
                                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            <option value="php" <?php echo ($mailer === 'php') ? 'selected' : ''; ?>>PHP Mail</option>
                            <option value="sendmail" <?php echo ($mailer === 'sendmail') ? 'selected' : ''; ?>>Sendmail</option>
                            <option value="smtp" <?php echo ($mailer === 'smtp') ? 'selected' : ''; ?>>SMTP</option>
                        </select>
                    </div>
                </div> <!-- End two-column grid -->

                <!-- Sendmail Path (only visible if mailer=sendmail) -->
                <div id="sendmail-path-section" style="margin-top:15px; display:none;">
                    <label for="sendmail_path" style="font-weight:bold; display:block; margin-bottom:5px;">
                        Sendmail Path:
                    </label>
                    <input type="text" id="sendmail_path" name="sendmail_path"
                           value="<?php echo htmlspecialchars($sendmail_path ?? ''); ?>"
                           style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <!-- SMTP Fields (only visible if mailer=smtp) -->
                <div id="smtp-section" style="margin-top:15px; display:none;">
                    <label for="smtp_host" style="font-weight:bold; display:block; margin-bottom:5px;">
                        SMTP Host:
                    </label>
                    <input type="text" id="smtp_host" name="smtp_host"
                           value="<?php echo htmlspecialchars($smtp_host ?? ''); ?>"
                           style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">

                    <label for="smtp_port" style="font-weight:bold; display:block; margin-bottom:5px; margin-top:15px;">
                        SMTP Port:
                    </label>
                    <input type="text" id="smtp_port" name="smtp_port"
                           value="<?php echo htmlspecialchars($smtp_port ?? ''); ?>"
                           style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">

                    <label for="smtp_security" style="font-weight:bold; display:block; margin-bottom:5px; margin-top:15px;">
                        SMTP Security:
                    </label>
                    <select id="smtp_security" name="smtp_security"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                        <option value="none" <?php echo ($smtp_security === 'none') ? 'selected' : ''; ?>>None</option>
                        <option value="ssl" <?php echo ($smtp_security === 'ssl') ? 'selected' : ''; ?>>SSL/TLS</option>
                        <option value="tls" <?php echo ($smtp_security === 'tls') ? 'selected' : ''; ?>>STARTTLS</option>
                    </select>

                    <label style="display:block; margin-top:15px; font-weight:bold;">
                        SMTP Authentication:
                    </label>
                    <div style="display:flex; gap:10px;">
                        <label style="display:flex; align-items:center; gap:5px;">
                            <input type="radio" name="smtp_auth" value="yes" <?php echo ($smtp_auth === 'yes') ? 'checked' : ''; ?>>
                            Yes
                        </label>
                        <label style="display:flex; align-items:center; gap:5px;">
                            <input type="radio" name="smtp_auth" value="no" <?php echo ($smtp_auth === 'no') ? 'checked' : ''; ?>>
                            No
                        </label>
                    </div>
                </div>

                <!-- Send Test Mail + Save Settings Buttons -->
                <div style="margin-top:20px;">
                    <button type="submit" name="send_test_mail" class="button" style="margin-right:10px;">
                        Send Test Mail
                    </button>
                    <button type="submit" class="button">
                        Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script src="../../assets/js/settings-mailer-script.js"></script>
