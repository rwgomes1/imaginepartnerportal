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
  <!-- <link rel="stylesheet" href="/assets/css/style2.css"> -->
  <style>
    /* Header styling with the original header image */
    .header {
      background: url('/assets/headers/header.png') no-repeat center;
      background-size: cover;
      height: 150px;
      display: flex;
      align-items: flex-end;
      padding: 20px;
    }
    .header .logo {
      height: 60px;
      margin-right: 20px;
    }
    .header .portal-title {
      font-size: 24px;
      font-weight: 700;
      color: #ffffff;
      flex-grow: 1;
      text-align: left;
      margin-bottom: 61px;
    }
  </style>
</head>
<body>
<header class="header">
  <img src="/assets/logo/logo@2x.webp" alt="Company Logo" class="logo">
  <h1 class="portal-title"><?php echo e(APP_NAME); ?></h1>
</header>
