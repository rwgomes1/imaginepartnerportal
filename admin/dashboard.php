<?php
session_start();
require_once '../includes/config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch user details
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare('SELECT username, name, role FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user && $user['name'] ? $user['name'] : $user['username'];
    $user_role = $user['role'];
} catch (PDOException $e) {
    $user_name = 'User';
    $user_role = 'user';
}

// Fetch pending partner count for sidebar
$pendingCount = 0;
try {
    $pendingStmt = $pdo->query("SELECT COUNT(*) AS pending_count FROM partners WHERE application_status = 'Pending Application'");
    $pendingData = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $pendingData ? $pendingData['pending_count'] : 0;
} catch (PDOException $e) {
    // Use default 0
}
$pageTitle = "Admin Dashboard";

// ---------------------------------------------
// Corporate Locations Weather
// ---------------------------------------------
$weather_api_key = OPENWEATHER_API_KEY;
$locations = [
    [
        'name' => 'San Diego, CA',
        'lat'  => 32.7157,
        'lon'  => -117.1611
    ],
    [
        'name' => 'Charlotte, NC (HQ)',
        'lat'  => 35.2271,
        'lon'  => -80.8431
    ],
    [
        'name' => 'Delmar, NY',
        'lat'  => 42.6226,
        'lon'  => -73.8323
    ]
];

// We'll fetch data for each location using the One Call API
// We'll store results in $weatherResults, keyed by the location's name
$weatherResults = [];

foreach ($locations as $loc) {
    $lat = $loc['lat'];
    $lon = $loc['lon'];
    
    // Use imperial if you want Fahrenheit; use metric if you want Celsius
    $units = 'imperial'; // or 'metric'
    
    if ($weather_api_key !== '') {
        $weather_url = "https://api.openweathermap.org/data/2.5/onecall?lat={$lat}&lon={$lon}&exclude=minutely,hourly,alerts&appid={$weather_api_key}&units={$units}";

        // Use @file_get_contents (or cURL if needed)
        $weather_json = @file_get_contents($weather_url);
        $weather_data = json_decode($weather_json, true);
    } else {
        $weather_data = null;
    }

    $weatherResults[$loc['name']] = $weather_data;
}

?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<!-- Main Content Area -->
<main class="main-content">
    <div class="welcome-banner">
        <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p>You are logged in as <strong><?php echo htmlspecialchars($user_role); ?></strong>.</p>
    </div>
    
    <!-- 3-Column Weather Card -->
    <div class="card weather-card">
        <div class="card-header">
            Our Corporate Locations - Weather
        </div>
        <div class="card-body weather-grid">
            <?php foreach ($locations as $loc): 
                $locName = $loc['name'];
                $data = $weatherResults[$locName];
            ?>
            <div class="weather-location">
                <h4><?php echo htmlspecialchars($locName); ?></h4>
                
                <?php if ($data && isset($data['current'])): ?>
                    <!-- Current Weather -->
                    <?php 
                        // Example: 'weather' => [ [ 'description' => 'clear sky' ] ], 'temp' => 75.2
                        $currWeather = $data['current'];
                        $currDesc = ucfirst($currWeather['weather'][0]['description']);
                        $currTemp = $currWeather['temp'];
                    ?>
                    <p><strong>Current:</strong> <?php echo $currDesc; ?> - <?php echo $currTemp; ?>°</p>
                    
                    <hr>
                    
                    <!-- 2-Day Forecast -->
                    <h5>2-Day Forecast</h5>
                    <?php
                        // daily[0] is "today", daily[1] is "tomorrow", etc.
                        // We'll skip daily[0] since that's basically current day
                        // Show daily[1] and daily[2]
                        for ($i = 1; $i <= 2; $i++):
                            if (!isset($data['daily'][$i])) {
                                continue;
                            }
                            $day = $data['daily'][$i];
                            $dayTemp = $day['temp']['day'];
                            $dayDesc = ucfirst($day['weather'][0]['description']);
                            $dayDate = date('Y-m-d', $day['dt']);
                    ?>
                        <p>
                            <strong><?php echo $dayDate; ?>:</strong>
                            <?php echo $dayDesc; ?>, <?php echo $dayTemp; ?>°
                        </p>
                    <?php endfor; ?>
                <?php else: ?>
                    <p>Weather data is not available at the moment.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
