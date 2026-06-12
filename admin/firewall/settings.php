<?php
session_start();
require_once '../../includes/config.php';

// Ensure superadmin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'superadmin') {
    header('Location: ../../login.php');
    exit;
}

require_once '../../vendor/autoload.php'; // for composer if needed

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Path to your local MaxMind DB
$maxmindDbPath = __DIR__ . '/GeoLite2-Country.mmdb';

// 7 Continents for the Blocked Continents card
$allContinents = [
    ['code' => 'AF', 'name' => 'Africa'],
    ['code' => 'AN', 'name' => 'Antarctica'],
    ['code' => 'AS', 'name' => 'Asia'],
    ['code' => 'EU', 'name' => 'Europe'],
    ['code' => 'NA', 'name' => 'North America'],
    ['code' => 'OC', 'name' => 'Oceania'],
    ['code' => 'SA', 'name' => 'South America'],
];

// JavaScript map: continent code to array of ISO country codes
$continentCountriesMap = [
    'AF' => ['DZ','AO','BJ','BW','BF','BI','CM','CV','CF','TD','KM','CD','CG','CI','DJ','EG','GQ','ER','ET','GA','GM','GH','GN','GW','KE','LS','LR','LY','MG','MW','ML','MA','MZ','NA','NE','NG','RW','ST','SN','SC','SL','SO','ZA','SS','SD','SZ','TZ','TG','TN','UG','ZM','ZW'],
    'AN' => ['AQ','BV','GS','HM'],
    'AS' => ['AF','AM','AZ','BH','BD','BT','BN','KH','CN','CX','GE','HK','IN','ID','IR','IQ','IL','JP','JO','KZ','KW','KG','LA','LB','MO','MY','MV','MN','MM','NP','OM','PK','PH','QA','SA','SG','KR','LK','SY','TW','TJ','TH','TL','TR','TM','AE','UZ','VN','YE','KP'],
    'EU' => ['AX','AL','AD','AT','BY','BE','BA','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GI','GR','GG','VA','HU','IS','IE','IM','IT','JE','LV','LI','LT','LU','MK','MT','MD','MC','ME','NL','NO','PL','PT','RO','RU','SM','RS','SK','SI','ES','SE','CH','UA','GB'],
    'NA' => ['AI','AG','AW','BS','BB','BZ','BM','BQ','CA','KY','CR','CU','CW','DM','DO','SV','GL','GD','GP','GT','HT','HN','JM','MQ','MS','MX','NI','PA','PR','BL','KN','LC','MF','PM','VC','SX','TT','TC','US','VG','VI'],
    'OC' => ['AS','AU','CK','FJ','PF','GU','KI','MH','FM','NR','NC','NZ','NU','MP','PW','PG','PN','WS','SB','TK','TO','TV','UM','VU','WF'],
    'SA' => ['AR','BO','BR','CL','CO','EC','FK','GF','GY','PY','PE','SR','UY','VE']
];

// Full array of ISO 3166-1 alpha-2 country codes and names
$allCountries = [
    ['code' => 'AD', 'name' => 'Andorra'],
    ['code' => 'AE', 'name' => 'United Arab Emirates'],
    ['code' => 'AF', 'name' => 'Afghanistan'],
    ['code' => 'AG', 'name' => 'Antigua and Barbuda'],
    ['code' => 'AI', 'name' => 'Anguilla'],
    ['code' => 'AL', 'name' => 'Albania'],
    ['code' => 'AM', 'name' => 'Armenia'],
    ['code' => 'AO', 'name' => 'Angola'],
    ['code' => 'AQ', 'name' => 'Antarctica'],
    ['code' => 'AR', 'name' => 'Argentina'],
    ['code' => 'AS', 'name' => 'American Samoa'],
    ['code' => 'AT', 'name' => 'Austria'],
    ['code' => 'AU', 'name' => 'Australia'],
    ['code' => 'AW', 'name' => 'Aruba'],
    ['code' => 'AX', 'name' => 'Åland Islands'],
    ['code' => 'AZ', 'name' => 'Azerbaijan'],
    ['code' => 'BA', 'name' => 'Bosnia and Herzegovina'],
    ['code' => 'BB', 'name' => 'Barbados'],
    ['code' => 'BD', 'name' => 'Bangladesh'],
    ['code' => 'BE', 'name' => 'Belgium'],
    ['code' => 'BF', 'name' => 'Burkina Faso'],
    ['code' => 'BG', 'name' => 'Bulgaria'],
    ['code' => 'BH', 'name' => 'Bahrain'],
    ['code' => 'BI', 'name' => 'Burundi'],
    ['code' => 'BJ', 'name' => 'Benin'],
    ['code' => 'BL', 'name' => 'Saint Barthélemy'],
    ['code' => 'BM', 'name' => 'Bermuda'],
    ['code' => 'BN', 'name' => 'Brunei Darussalam'],
    ['code' => 'BO', 'name' => 'Bolivia (Plurinational State of)'],
    ['code' => 'BQ', 'name' => 'Bonaire, Sint Eustatius and Saba'],
    ['code' => 'BR', 'name' => 'Brazil'],
    ['code' => 'BS', 'name' => 'Bahamas'],
    ['code' => 'BT', 'name' => 'Bhutan'],
    ['code' => 'BV', 'name' => 'Bouvet Island'],
    ['code' => 'BW', 'name' => 'Botswana'],
    ['code' => 'BY', 'name' => 'Belarus'],
    ['code' => 'BZ', 'name' => 'Belize'],
    ['code' => 'CA', 'name' => 'Canada'],
    ['code' => 'CC', 'name' => 'Cocos (Keeling) Islands'],
    ['code' => 'CD', 'name' => 'Congo (the Democratic Republic of the)'],
    ['code' => 'CF', 'name' => 'Central African Republic'],
    ['code' => 'CG', 'name' => 'Congo'],
    ['code' => 'CH', 'name' => 'Switzerland'],
    ['code' => 'CI', 'name' => "Côte d'Ivoire"],
    ['code' => 'CK', 'name' => 'Cook Islands'],
    ['code' => 'CL', 'name' => 'Chile'],
    ['code' => 'CM', 'name' => 'Cameroon'],
    ['code' => 'CN', 'name' => 'China'],
    ['code' => 'CO', 'name' => 'Colombia'],
    ['code' => 'CR', 'name' => 'Costa Rica'],
    ['code' => 'CU', 'name' => 'Cuba'],
    ['code' => 'CV', 'name' => 'Cabo Verde'],
    ['code' => 'CW', 'name' => 'Curaçao'],
    ['code' => 'CX', 'name' => 'Christmas Island'],
    ['code' => 'CY', 'name' => 'Cyprus'],
    ['code' => 'CZ', 'name' => 'Czechia'],
    ['code' => 'DE', 'name' => 'Germany'],
    ['code' => 'DJ', 'name' => 'Djibouti'],
    ['code' => 'DK', 'name' => 'Denmark'],
    ['code' => 'DM', 'name' => 'Dominica'],
    ['code' => 'DO', 'name' => 'Dominican Republic'],
    ['code' => 'DZ', 'name' => 'Algeria'],
    ['code' => 'EC', 'name' => 'Ecuador'],
    ['code' => 'EE', 'name' => 'Estonia'],
    ['code' => 'EG', 'name' => 'Egypt'],
    ['code' => 'EH', 'name' => 'Western Sahara'],
    ['code' => 'ER', 'name' => 'Eritrea'],
    ['code' => 'ES', 'name' => 'Spain'],
    ['code' => 'ET', 'name' => 'Ethiopia'],
    ['code' => 'FI', 'name' => 'Finland'],
    ['code' => 'FJ', 'name' => 'Fiji'],
    ['code' => 'FK', 'name' => 'Falkland Islands (Malvinas)'],
    ['code' => 'FM', 'name' => 'Micronesia (Federated States of)'],
    ['code' => 'FO', 'name' => 'Faroe Islands'],
    ['code' => 'FR', 'name' => 'France'],
    ['code' => 'GA', 'name' => 'Gabon'],
    ['code' => 'GB', 'name' => 'United Kingdom'],
    ['code' => 'GD', 'name' => 'Grenada'],
    ['code' => 'GE', 'name' => 'Georgia'],
    ['code' => 'GF', 'name' => 'French Guiana'],
    ['code' => 'GG', 'name' => 'Guernsey'],
    ['code' => 'GH', 'name' => 'Ghana'],
    ['code' => 'GI', 'name' => 'Gibraltar'],
    ['code' => 'GL', 'name' => 'Greenland'],
    ['code' => 'GM', 'name' => 'Gambia'],
    ['code' => 'GN', 'name' => 'Guinea'],
    ['code' => 'GP', 'name' => 'Guadeloupe'],
    ['code' => 'GQ', 'name' => 'Equatorial Guinea'],
    ['code' => 'GR', 'name' => 'Greece'],
    ['code' => 'GS', 'name' => 'South Georgia and the South Sandwich Islands'],
    ['code' => 'GT', 'name' => 'Guatemala'],
    ['code' => 'GU', 'name' => 'Guam'],
    ['code' => 'GW', 'name' => 'Guinea-Bissau'],
    ['code' => 'GY', 'name' => 'Guyana'],
    ['code' => 'HK', 'name' => 'Hong Kong'],
    ['code' => 'HM', 'name' => 'Heard Island and McDonald Islands'],
    ['code' => 'HN', 'name' => 'Honduras'],
    ['code' => 'HR', 'name' => 'Croatia'],
    ['code' => 'HT', 'name' => 'Haiti'],
    ['code' => 'HU', 'name' => 'Hungary'],
    ['code' => 'ID', 'name' => 'Indonesia'],
    ['code' => 'IE', 'name' => 'Ireland'],
    ['code' => 'IL', 'name' => 'Israel'],
    ['code' => 'IM', 'name' => 'Isle of Man'],
    ['code' => 'IN', 'name' => 'India'],
    ['code' => 'IO', 'name' => 'British Indian Ocean Territory'],
    ['code' => 'IQ', 'name' => 'Iraq'],
    ['code' => 'IR', 'name' => 'Iran (Islamic Republic of)'],
    ['code' => 'IS', 'name' => 'Iceland'],
    ['code' => 'IT', 'name' => 'Italy'],
    ['code' => 'JE', 'name' => 'Jersey'],
    ['code' => 'JM', 'name' => 'Jamaica'],
    ['code' => 'JO', 'name' => 'Jordan'],
    ['code' => 'JP', 'name' => 'Japan'],
    ['code' => 'KE', 'name' => 'Kenya'],
    ['code' => 'KG', 'name' => 'Kyrgyzstan'],
    ['code' => 'KH', 'name' => 'Cambodia'],
    ['code' => 'KI', 'name' => 'Kiribati'],
    ['code' => 'KM', 'name' => 'Comoros'],
    ['code' => 'KN', 'name' => 'Saint Kitts and Nevis'],
    ['code' => 'KP', 'name' => "Korea (the Democratic People's Republic of)"],
    ['code' => 'KR', 'name' => 'Korea (the Republic of)'],
    ['code' => 'KW', 'name' => 'Kuwait'],
    ['code' => 'KY', 'name' => 'Cayman Islands'],
    ['code' => 'KZ', 'name' => 'Kazakhstan'],
    ['code' => 'LA', 'name' => "Lao People's Democratic Republic"],
    ['code' => 'LB', 'name' => 'Lebanon'],
    ['code' => 'LC', 'name' => 'Saint Lucia'],
    ['code' => 'LI', 'name' => 'Liechtenstein'],
    ['code' => 'LK', 'name' => 'Sri Lanka'],
    ['code' => 'LR', 'name' => 'Liberia'],
    ['code' => 'LS', 'name' => 'Lesotho'],
    ['code' => 'LT', 'name' => 'Lithuania'],
    ['code' => 'LU', 'name' => 'Luxembourg'],
    ['code' => 'LV', 'name' => 'Latvia'],
    ['code' => 'LY', 'name' => 'Libya'],
    ['code' => 'MA', 'name' => 'Morocco'],
    ['code' => 'MC', 'name' => 'Monaco'],
    ['code' => 'MD', 'name' => 'Moldova (the Republic of)'],
    ['code' => 'ME', 'name' => 'Montenegro'],
    ['code' => 'MF', 'name' => 'Saint Martin (French part)'],
    ['code' => 'MG', 'name' => 'Madagascar'],
    ['code' => 'MH', 'name' => 'Marshall Islands'],
    ['code' => 'MK', 'name' => 'North Macedonia'],
    ['code' => 'ML', 'name' => 'Mali'],
    ['code' => 'MM', 'name' => 'Myanmar'],
    ['code' => 'MN', 'name' => 'Mongolia'],
    ['code' => 'MO', 'name' => 'Macao'],
    ['code' => 'MP', 'name' => 'Northern Mariana Islands'],
    ['code' => 'MQ', 'name' => 'Martinique'],
    ['code' => 'MR', 'name' => 'Mauritania'],
    ['code' => 'MS', 'name' => 'Montserrat'],
    ['code' => 'MT', 'name' => 'Malta'],
    ['code' => 'MU', 'name' => 'Mauritius'],
    ['code' => 'MV', 'name' => 'Maldives'],
    ['code' => 'MW', 'name' => 'Malawi'],
    ['code' => 'MX', 'name' => 'Mexico'],
    ['code' => 'MY', 'name' => 'Malaysia'],
    ['code' => 'MZ', 'name' => 'Mozambique'],
    ['code' => 'NA', 'name' => 'Namibia'],
    ['code' => 'NC', 'name' => 'New Caledonia'],
    ['code' => 'NE', 'name' => 'Niger'],
    ['code' => 'NF', 'name' => 'Norfolk Island'],
    ['code' => 'NG', 'name' => 'Nigeria'],
    ['code' => 'NI', 'name' => 'Nicaragua'],
    ['code' => 'NL', 'name' => 'Netherlands'],
    ['code' => 'NO', 'name' => 'Norway'],
    ['code' => 'NP', 'name' => 'Nepal'],
    ['code' => 'NR', 'name' => 'Nauru'],
    ['code' => 'NU', 'name' => 'Niue'],
    ['code' => 'NZ', 'name' => 'New Zealand'],
    ['code' => 'OM', 'name' => 'Oman'],
    ['code' => 'PA', 'name' => 'Panama'],
    ['code' => 'PE', 'name' => 'Peru'],
    ['code' => 'PF', 'name' => 'French Polynesia'],
    ['code' => 'PG', 'name' => 'Papua New Guinea'],
    ['code' => 'PH', 'name' => 'Philippines'],
    ['code' => 'PK', 'name' => 'Pakistan'],
    ['code' => 'PL', 'name' => 'Poland'],
    ['code' => 'PM', 'name' => 'Saint Pierre and Miquelon'],
    ['code' => 'PN', 'name' => 'Pitcairn'],
    ['code' => 'PR', 'name' => 'Puerto Rico'],
    ['code' => 'PS', 'name' => 'Palestine, State of'],
    ['code' => 'PT', 'name' => 'Portugal'],
    ['code' => 'PW', 'name' => 'Palau'],
    ['code' => 'PY', 'name' => 'Paraguay'],
    ['code' => 'QA', 'name' => 'Qatar'],
    ['code' => 'RE', 'name' => 'Réunion'],
    ['code' => 'RO', 'name' => 'Romania'],
    ['code' => 'RS', 'name' => 'Serbia'],
    ['code' => 'RU', 'name' => 'Russian Federation'],
    ['code' => 'RW', 'name' => 'Rwanda'],
    ['code' => 'SA', 'name' => 'Saudi Arabia'],
    ['code' => 'SB', 'name' => 'Solomon Islands'],
    ['code' => 'SC', 'name' => 'Seychelles'],
    ['code' => 'SD', 'name' => 'Sudan'],
    ['code' => 'SE', 'name' => 'Sweden'],
    ['code' => 'SG', 'name' => 'Singapore'],
    ['code' => 'SH', 'name' => 'Saint Helena, Ascension and Tristan da Cunha'],
    ['code' => 'SI', 'name' => 'Slovenia'],
    ['code' => 'SJ', 'name' => 'Svalbard and Jan Mayen'],
    ['code' => 'SK', 'name' => 'Slovakia'],
    ['code' => 'SL', 'name' => 'Sierra Leone'],
    ['code' => 'SM', 'name' => 'San Marino'],
    ['code' => 'SN', 'name' => 'Senegal'],
    ['code' => 'SO', 'name' => 'Somalia'],
    ['code' => 'SR', 'name' => 'Suriname'],
    ['code' => 'SS', 'name' => 'South Sudan'],
    ['code' => 'ST', 'name' => 'Sao Tome and Principe'],
    ['code' => 'SV', 'name' => 'El Salvador'],
    ['code' => 'SX', 'name' => 'Sint Maarten (Dutch part)'],
    ['code' => 'SY', 'name' => 'Syrian Arab Republic'],
    ['code' => 'SZ', 'name' => 'Eswatini'],
    ['code' => 'TC', 'name' => 'Turks and Caicos Islands'],
    ['code' => 'TD', 'name' => 'Chad'],
    ['code' => 'TF', 'name' => 'French Southern Territories'],
    ['code' => 'TG', 'name' => 'Togo'],
    ['code' => 'TH', 'name' => 'Thailand'],
    ['code' => 'TJ', 'name' => 'Tajikistan'],
    ['code' => 'TK', 'name' => 'Tokelau'],
    ['code' => 'TL', 'name' => 'Timor-Leste'],
    ['code' => 'TM', 'name' => 'Turkmenistan'],
    ['code' => 'TN', 'name' => 'Tunisia'],
    ['code' => 'TO', 'name' => 'Tonga'],
    ['code' => 'TR', 'name' => 'Türkiye'],
    ['code' => 'TT', 'name' => 'Trinidad and Tobago'],
    ['code' => 'TV', 'name' => 'Tuvalu'],
    ['code' => 'TW', 'name' => 'Taiwan'],
    ['code' => 'TZ', 'name' => 'Tanzania, United Republic of'],
    ['code' => 'UA', 'name' => 'Ukraine'],
    ['code' => 'UG', 'name' => 'Uganda'],
    ['code' => 'UM', 'name' => 'United States Minor Outlying Islands'],
    ['code' => 'US', 'name' => 'United States'],
    ['code' => 'UY', 'name' => 'Uruguay'],
    ['code' => 'UZ', 'name' => 'Uzbekistan'],
    ['code' => 'VA', 'name' => 'Holy See'],
    ['code' => 'VC', 'name' => 'Saint Vincent and the Grenadines'],
    ['code' => 'VE', 'name' => 'Venezuela (Bolivarian Republic of)'],
    ['code' => 'VG', 'name' => 'Virgin Islands (British)'],
    ['code' => 'VI', 'name' => 'Virgin Islands (U.S.)'],
    ['code' => 'VN', 'name' => 'Viet Nam'],
    ['code' => 'VU', 'name' => 'Vanuatu'],
    ['code' => 'WF', 'name' => 'Wallis and Futuna'],
    ['code' => 'WS', 'name' => 'Samoa'],
    ['code' => 'YE', 'name' => 'Yemen'],
    ['code' => 'YT', 'name' => 'Mayotte'],
    ['code' => 'ZA', 'name' => 'South Africa'],
    ['code' => 'ZM', 'name' => 'Zambia'],
    ['code' => 'ZW', 'name' => 'Zimbabwe'],
];

// Process form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Update MaxMind DB
    if (isset($_POST['update_maxmind'])) {
        $licenseKey = 'Dl7nu7TBnxJlYJ6F'; // your MaxMind license key
        $url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key={$licenseKey}&suffix=tar.gz";
        
        // Download and extract .mmdb
        $tmpFile = __DIR__ . '/geolite2.tar.gz';
        if (@file_put_contents($tmpFile, fopen($url, 'r'))) {
            $phar = new PharData($tmpFile);
            $phar->extractTo(__DIR__, null, true);
            foreach (scandir(__DIR__) as $item) {
                if (strpos($item, 'GeoLite2-Country_') === 0 && is_dir(__DIR__ . '/' . $item)) {
                    $dbPath = __DIR__ . '/' . $item . '/GeoLite2-Country.mmdb';
                    if (file_exists($dbPath)) {
                        @rename($dbPath, $maxmindDbPath); // overwrite existing
                    }
                    deleteDir(__DIR__ . '/' . $item);
                }
            }
            @unlink($tmpFile);
            $message .= "MaxMind database updated successfully.<br>";
        } else {
            $message .= "Failed to download MaxMind database.<br>";
        }
    }
    
    // 2) Update the blocked countries from the checkbox grid
    if (isset($_POST['update_blocked_countries'])) {
        $checkedCountries = isset($_POST['blocked_countries']) ? $_POST['blocked_countries'] : [];
        
        // Remove all entries where code_type='country'
        $pdo->exec("DELETE FROM firewall_country_list WHERE code_type='country'");
        
        // Insert the newly checked countries
        if (!empty($checkedCountries)) {
            $stmt = $pdo->prepare("INSERT INTO firewall_country_list (code, code_type) VALUES (:code, 'country')");
            foreach ($checkedCountries as $cc) {
                $stmt->execute([':code' => $cc]);
            }
        }
        $message .= "Blocked countries updated.<br>";
    }
}

// Fetch the currently blocked countries (code_type='country')
$stmt = $pdo->query("SELECT code FROM firewall_country_list WHERE code_type='country'");
$blockedCountriesRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
$blockedCountries = $blockedCountriesRows ?: [];

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return;
    $files = scandir($dirPath);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $fullPath = $dirPath . '/' . $file;
        if (is_dir($fullPath)) {
            deleteDir($fullPath);
        } else {
            @unlink($fullPath);
        }
    }
    @rmdir($dirPath);
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="container">
        <h2>Firewall Settings</h2>
        <?php if (!empty($message)): ?>
            <div style="background:#f2f2f2; padding:10px; margin-bottom:10px; border:1px solid #ccc;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Update MaxMind Database Card -->
        <div class="card" style="text-align:left;">
            <h3>Update MaxMind Database</h3>
            <form method="POST">
                <p>Click to download and update the local GeoLite2-Country.mmdb file using your MaxMind license.</p>
                <button type="submit" name="update_maxmind" class="button" style="max-width:200px;">
                    Update MaxMind DB
                </button>
            </form>
        </div>

        <!-- Blocked Continents Card (5-column grid) -->
        <div class="card" style="text-align:left;">
            <h3>Blocked Continents</h3>
            <p style="font-size:12px;">Click a continent to automatically select all its countries below.</p>
            <div style="
                display: grid; 
                grid-template-columns: repeat(5, 1fr);
                gap: 5px;
                margin-top: 10px;
            ">
                <?php foreach ($allContinents as $cont): ?>
                    <label style="display:flex; align-items:center; gap:3px; background:#f9f9f9; border:1px solid #ddd; padding:4px; border-radius:4px; cursor:pointer; font-size:12px;">
                        <input type="checkbox" onclick="toggleContinent('<?php echo $cont['code']; ?>', this.checked)" style="transform: scale(0.8);">
                        <?php echo htmlspecialchars($cont['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Blocked Countries Card (5-column grid) -->
        <div class="card" style="text-align:left;">
            <h3>Blocked Countries</h3>
            <p style="font-size:12px;">Select the countries you want to block. Continents above will auto-check their countries.</p>
            <form method="POST">
                <div style="
                     display: grid; 
                     grid-template-columns: repeat(5, 1fr); 
                     gap: 5px; 
                     margin-top: 10px;
                ">
                    <?php foreach ($allCountries as $c): 
                        $code = $c['code'];
                        $name = $c['name'];
                        // If the name contains '(', truncate it to avoid word wrap.
                        $shortName = (strpos($name, '(') !== false) ? trim(substr($name, 0, strpos($name, '('))) : $name;
                        $isChecked = in_array($code, $blockedCountries);
                    ?>
                    <label style="display:flex; align-items:center; gap:3px; background:#f9f9f9; border:1px solid #ddd; padding:4px; border-radius:4px; cursor:pointer; font-size:12px;">
                        <input 
                            type="checkbox" 
                            name="blocked_countries[]" 
                            value="<?php echo htmlspecialchars($code); ?>"
                            <?php echo $isChecked ? 'checked' : ''; ?>
                            id="country_<?php echo htmlspecialchars($code); ?>"
                            style="transform: scale(0.8);"
                        >
                        <?php echo htmlspecialchars($shortName); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="update_blocked_countries" class="button" style="margin-top:10px; max-width:200px;">
                    Save Blocked Countries
                </button>
            </form>
        </div>
    </div>
</main>

<script>
// JavaScript object mapping each continent code to its array of country codes.
const continentMap = <?php echo json_encode($continentCountriesMap); ?>;

function toggleContinent(continentCode, isChecked) {
    if (!continentMap[continentCode]) return;
    const countries = continentMap[continentCode];
    countries.forEach(cc => {
        const cb = document.getElementById('country_' + cc);
        if (cb) {
            cb.checked = isChecked;
        }
    });
}
</script>
<?php include '../../includes/footer.php'; ?>
