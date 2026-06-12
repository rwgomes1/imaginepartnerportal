<?php
// Copy this file to includes/config.local.php and fill in deployment-specific values.
// includes/config.local.php is ignored by git so secrets stay out of the repo.

return [
    'APP_NAME' => 'Imagine Partner Portal',
    'APP_URL' => 'https://your-domain.example',
    'MAIL_FROM' => 'no-reply@your-domain.example',
    'DB_DSN' => 'mysql:host=localhost;dbname=your_database;charset=utf8mb4',
    'DB_USER' => 'your_database_user',
    'DB_PASS' => 'your_database_password',
    'OPENWEATHER_API_KEY' => '',
];
