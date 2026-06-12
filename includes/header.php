<?php
// /includes/header.php
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($pageTitle); ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php foreach (($pageStylesheets ?? []) as $stylesheet): ?>
    <link rel="stylesheet" href="<?php echo e($stylesheet); ?>">
  <?php endforeach; ?>
</head>
<body>
<header class="header">
  <div class="header-inner">
    <a class="header-logo" href="/index.php" aria-label="<?php echo e(APP_NAME); ?> home">
      <img src="/assets/logo/logo@2x.webp" alt="ImagineSoftware">
    </a>
    <div class="header-copy">
      <span class="header-kicker">Partner Ecosystem</span>
      <h1 class="portal-title"><?php echo e(APP_NAME); ?></h1>
    </div>
  </div>
</header>
