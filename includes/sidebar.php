<?php
// /includes/sidebar.php

require_once __DIR__ . '/bootstrap.php';

// Optionally fetch or define $user_role, $user_name, etc.
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['user_name'] ?? 'Guest';

// If you need a pending partner count for admins/superadmins:
$pendingCount = 0;
try {
    $pdo = app_pdo();
    $stmt = $pdo->query("SELECT COUNT(*) AS pending_count 
                         FROM partners 
                         WHERE application_status = 'Pending Application'");
    $pendingData = $stmt->fetch();
    $pendingCount = $pendingData ? $pendingData['pending_count'] : 0;
} catch (PDOException $e) {
    // handle error or keep $pendingCount = 0
}
?>
<?php $layoutHasSidebar = true; ?>
<div class="dashboard-wrapper">
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h2>Menu</h2>
    </div>

    <ul class="sidebar-menu">

      <?php if ($user_role === 'partner'): ?>
        <!-- PARTNER -->
        <li class="menu-section collapsible">
          <span>Partner Dashboard</span>
          <ul class="submenu">
            <li><a href="/partners/dashboard.php">Home</a></li>
            <li><a href="/partners/leads/submit_lead.php">Submit Lead</a></li>
            <li><a href="/partners/resources/index.php">Resources</a></li>
          </ul>
        </li>

      <?php elseif ($user_role === 'admin' || $user_role === 'superadmin'): ?>

        <?php if ($user_role === 'superadmin'): ?>
          <li class="menu-section collapsible">
            <span>SuperAdmin Controls</span>
            <ul class="submenu">
              <li><a href="/admin/users/create_user.php">Add New User</a></li>
              <li><a href="/admin/users/list_users.php">Edit Users</a></li>
              <li><a href="/admin/settings/system_settings.php">Manage System Settings</a></li>
              <li><a href="/admin/settings/email_templates.php">Email Templates</a></li>              
            </ul>
          </li>
        <?php endif; ?>

        <li class="menu-section collapsible">
          <span>Admin Tools</span>
          <ul class="submenu">
            <li><a href="/admin/partners/create_partner.php">Create Partner Account</a></li>
            <li><a href="/admin/resources/manage_resources.php">Resource Library</a></li>
          </ul>
        </li>
                <li class="menu-section collapsible">
          <span>Firewall</span>
          <ul class="submenu">
            <li><a href="/admin/firewall/dashboard.php">Dashboard</a></li>  
            <li><a href="/admin/firewall/settings.php">Firewall Settings</a></li>
            <li><a href="/admin/firewall/whitelist-blacklist.php">Whitelist/Blacklist</a></li>            
            <li><a href="/admin/firewall/logs.php">Logs</a></li>
          </ul>
        </li>

        <li class="menu-section collapsible">
          <span>Partners</span>
          <ul class="submenu">
            <li><a href="/admin/partners/list_partners.php">All Partners</a></li>
            <li><a href="/admin/partners/pending.php">Pending (<?php echo $pendingCount; ?>)</a></li>
          </ul>
        </li>

        <li class="menu-section collapsible">
          <span>Leads</span>
          <ul class="submenu">
            <li><a href="/admin/leads/manage_leads.php">Manage Leads</a></li>
          </ul>
        </li>

      <?php else: ?>
        <!-- If some other role, or user not recognized, etc. -->
        <li><a href="/login.php">Login</a></li>
      <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
      <?php if ($user_role === 'guest'): ?>
        <a href="/login.php">Sign in</a>
      <?php else: ?>
        Logged in as: <?php echo e($user_name); ?><br>
        <a href="/logout.php">Logout</a>
      <?php endif; ?>
    </div>
  </nav>

  <!-- main-content open here, will close in footer.php -->
  <main class="main-content">
