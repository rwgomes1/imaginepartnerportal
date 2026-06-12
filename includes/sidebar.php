<?php
// /includes/sidebar.php

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optionally fetch or define $user_role, $user_name, etc.
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['user_name'] ?? 'Guest';

// If you need a pending partner count for admins/superadmins:
$pendingCount = 0;
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->query("SELECT COUNT(*) AS pending_count 
                         FROM partners 
                         WHERE application_status = 'Pending Application'");
    $pendingData = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $pendingData ? $pendingData['pending_count'] : 0;
} catch (PDOException $e) {
    // handle error or keep $pendingCount = 0
}
?>
<style>
.dashboard-wrapper {
    display: flex;
    min-height: calc(100vh - 150px); /* Subtract header height (150px) */
}

/* SIDEBAR BASE STYLES */
.sidebar {
    background-color: #011d3b;
    width: 250px;
    height: calc(100vh - 150px); /* fill below the header */
    transition: width 0.3s ease;
    color: #fff;
    display: flex;
    flex-direction: column;
    padding-bottom: 40px; /* ensures footer text is visible above pinned page footer */
}

/* We no longer have .collapsed or #sidebarToggle logic */

/* The top area inside the sidebar (if you want a heading, etc.) */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    background: #011d3b;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.2rem;
}

/* The main UL in the sidebar */
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
}

/* 
   Collapsible logic: 
   Each parent is .menu-section.collapsible.
   The <span> is the clickable heading. 
   The .submenu is hidden unless .expanded is applied.
*/
.menu-section.collapsible {
    margin-bottom: 15px; /* spacing between sections */
}

/* The clickable heading */
.menu-section.collapsible > span {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-size: 1rem;
    font-weight: bold;
    padding: 5px 10px;
    background-color: #02254e;
    border-radius: 4px;
}

/* Add a small arrow (▼) that rotates on expand */
.menu-section.collapsible > span::after {
    content: "▼";
    font-size: 0.9rem;
    transition: transform 0.3s;
    margin-left: 5px;
}

/* If expanded, rotate arrow 180° */
.menu-section.collapsible.expanded > span::after {
    transform: rotate(180deg);
}

/* Submenu hidden by default */
.menu-section.collapsible .submenu {
    display: none;
    margin-left: 10px; /* small indent */
    margin-top: 5px;
}

/* Show submenu if parent has .expanded */
.menu-section.collapsible.expanded .submenu {
    display: block;
}

/* Submenu links */
.submenu li {
    margin-bottom: 5px;
    padding: 0 15px; /* same as your old .sidebar-menu li */
}
.submenu li a {
    display: block;
    color: #fff;
    text-decoration: none;
    font-size: 0.95rem;
    padding: 5px 0;
}
.submenu li a:hover {
    text-decoration: underline;
}

/* If you still have single items that are not collapsible, e.g. a plain li */
.sidebar-menu li {
    padding: 5px 15px;
}

/* The sidebar footer for user info */
.sidebar-footer {
    padding: 10px;
    font-size: 0.8rem;
    text-align: center;
    border-top: 1px solid #444;
    margin-top: auto;
}

/* MAIN CONTENT */
.main-content {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: #f5f7fa;
}
</style>

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
      Logged in as: <?php echo htmlspecialchars($user_name); ?><br>
      <a href="/logout.php" style="color:#fff; text-decoration:underline; font-size:0.9rem;">Logout</a>
    </div>
  </nav>

  <!-- main-content open here, will close in footer.php -->
  <main class="main-content">
