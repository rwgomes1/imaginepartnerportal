<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../../includes/config.php';

// Only SuperAdmins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /login.php');
    exit;
}

// Connect DB
$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1) Seed default templates if not present
function seedDefaultTemplates($pdo) {
    $defaults = [
        [
            'template_key'    => 'forgot_username',
            'template_name'   => 'Forgot Username Email',
            'template_subject'=> 'Your Username Request',
            'template_body'   => 'Hello {user}, your username is: {username}'
        ],
        [
            'template_key'    => 'forgot_password',
            'template_name'   => 'Forgot Password Email',
            'template_subject'=> 'Reset Your Password',
            'template_body'   => 'Hello {user}, click here to reset your password: {reset_link}'
        ]
    ];
    foreach ($defaults as $def) {
        // Insert or ignore if key already exists
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO email_templates (template_key, template_name, template_subject, template_body)
            VALUES (:tk, :tn, :ts, :tb)
        ");
        $stmt->execute([
            ':tk' => $def['template_key'],
            ':tn' => $def['template_name'],
            ':ts' => $def['template_subject'],
            ':tb' => $def['template_body']
        ]);
    }
}
seedDefaultTemplates($pdo);

// 2) Handle form submissions (Create/Update template)
$message = '';
$action  = $_GET['action'] ?? '';
$id      = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    // Save or update template
    if (isset($_POST['template_key'], $_POST['template_name'], $_POST['template_subject'], $_POST['template_body'])) {
        $template_key     = trim($_POST['template_key']);
        $template_name    = trim($_POST['template_name']);
        $template_subject = trim($_POST['template_subject']);
        $template_body    = trim($_POST['template_body']);
        $id_post          = intval($_POST['template_id'] ?? 0);

        if ($id_post > 0) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE email_templates
                SET template_key = :tk,
                    template_name = :tn,
                    template_subject = :ts,
                    template_body = :tb,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':tk' => $template_key,
                ':tn' => $template_name,
                ':ts' => $template_subject,
                ':tb' => $template_body,
                ':id' => $id_post
            ]);
            $message = "Template updated successfully!";
        } else {
            // Insert new
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (template_key, template_name, template_subject, template_body, updated_at)
                VALUES (:tk, :tn, :ts, :tb, NOW())
            ");
            $stmt->execute([
                ':tk' => $template_key,
                ':tn' => $template_name,
                ':ts' => $template_subject,
                ':tb' => $template_body
            ]);
            $message = "New template created successfully!";
        }
    }
    header('Location: email_templates.php?msg='.urlencode($message));
    exit;
}

// If there's a message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Fetch all templates for listing
$stmt = $pdo->query("SELECT * FROM email_templates ORDER BY template_name ASC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If editing
$editTemplate = null;
if ($action === 'edit' && $id > 0) {
    $stmt2 = $pdo->prepare("SELECT * FROM email_templates WHERE id = :id LIMIT 1");
    $stmt2->execute([':id' => $id]);
    $editTemplate = $stmt2->fetch(PDO::FETCH_ASSOC);
}

// Include layout
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="container">
    <h2>Email Templates</h2>
    <?php if (!empty($message)): ?>
      <div class="card" style="padding:10px; margin-bottom:20px;">
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
      </div>
    <?php endif; ?>

    <div style="display:flex; gap:20px;">
      <!-- Left Column: Template Listing or Edit Form -->
      <div style="flex: 2;">
        <?php if ($action === 'edit'): ?>
          <!-- Edit / Create Form -->
          <div class="card" style="padding:20px;">
            <?php if ($editTemplate): ?>
              <h3>Edit Template</h3>
            <?php else: ?>
              <h3>Add New Template</h3>
            <?php endif; ?>
            <form method="POST" style="margin-top:10px;">
              <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
              <input type="hidden" name="template_id" value="<?php echo $editTemplate['id'] ?? 0; ?>">

              <label for="template_key" style="font-weight:bold; display:block; margin-bottom:5px;">Template Key (unique):</label>
              <input type="text" id="template_key" name="template_key"
                     value="<?php echo htmlspecialchars($editTemplate['template_key'] ?? ''); ?>"
                     style="width:100%; padding:8px; margin-bottom:10px;">

              <label for="template_name" style="font-weight:bold; display:block; margin-bottom:5px;">Template Name:</label>
              <input type="text" id="template_name" name="template_name"
                     value="<?php echo htmlspecialchars($editTemplate['template_name'] ?? ''); ?>"
                     style="width:100%; padding:8px; margin-bottom:10px;">

              <label for="template_subject" style="font-weight:bold; display:block; margin-bottom:5px;">Subject:</label>
              <input type="text" id="template_subject" name="template_subject"
                     value="<?php echo htmlspecialchars($editTemplate['template_subject'] ?? ''); ?>"
                     style="width:100%; padding:8px; margin-bottom:10px;">

              <label for="template_body" style="font-weight:bold; display:block; margin-bottom:5px;">Body:</label>
              <textarea id="template_body" name="template_body" rows="6"
                        style="width:100%; padding:8px;"><?php echo htmlspecialchars($editTemplate['template_body'] ?? ''); ?></textarea>

              <button type="submit" class="button" style="margin-top:15px;">Save Template</button>
            </form>
          </div>
        <?php else: ?>
          <!-- Listing all templates -->
          <div class="card" style="padding:20px;">
            <h3>All Templates</h3>
            <?php if (count($templates) === 0): ?>
              <p>No templates found. Click "Add New Template" to create one.</p>
            <?php else: ?>
              <table style="width:100%; border-collapse:collapse;">
                <thead>
                  <tr style="background:#f5f5f5;">
                    <th style="border:1px solid #ddd; padding:8px;">Template Name</th>
                    <th style="border:1px solid #ddd; padding:8px;">Key</th>
                    <th style="border:1px solid #ddd; padding:8px;">Subject</th>
                    <th style="border:1px solid #ddd; padding:8px;">Updated</th>
                    <th style="border:1px solid #ddd; padding:8px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($templates as $t): ?>
                    <tr>
                      <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo htmlspecialchars($t['template_name']); ?>
                      </td>
                      <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo htmlspecialchars($t['template_key']); ?>
                      </td>
                      <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo htmlspecialchars($t['template_subject']); ?>
                      </td>
                      <td style="border:1px solid #ddd; padding:8px;">
                        <?php echo $t['updated_at']; ?>
                      </td>
                      <td style="border:1px solid #ddd; padding:8px;">
                        <a href="email_templates.php?action=edit&id=<?php echo $t['id']; ?>"
                           style="color:#007bff; text-decoration:none;">Edit</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
            <div style="margin-top:10px;">
              <a href="email_templates.php?action=edit&id=0" class="button">Add New Template</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <!-- Right Column: Placeholder Reference -->
      <div style="flex: 1;">
        <div class="card" style="padding:20px;">
          <h3>Placeholder Reference</h3>
          <p>Use these placeholders in your templates:</p>
          <ul style="list-style:none; padding-left:0;">
            <li><strong>{user}</strong> &mdash; The full name of the user</li>
            <li><strong>{username}</strong> &mdash; The user’s actual username</li>
            <li><strong>{reset_link}</strong> &mdash; A URL to reset the user’s password</li>
            <li><strong>{partner}</strong> &mdash; The partner company name</li>
            <!-- Add more placeholders as needed -->
          </ul>
          <p>They will be replaced dynamically when emails are sent.</p>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
