<?php
// =============================================
// config.php — Database & App Configuration
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // ← change to your DB user
define('DB_PASS', '');              // ← change to your DB password
define('DB_NAME', 'earthquake_detector');
define('DB_CHARSET', 'utf8mb4');

// App config
define('APP_NAME', 'SeismoWatch');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/earthquake-detector');

// USGS Earthquake API endpoints
define('USGS_API_BASE', 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/');
define('USGS_GEOJSON_ALL_HOUR',  USGS_API_BASE . 'all_hour.geojson');
define('USGS_GEOJSON_ALL_DAY',   USGS_API_BASE . 'all_day.geojson');
define('USGS_GEOJSON_ALL_WEEK',  USGS_API_BASE . 'all_week.geojson');
define('USGS_GEOJSON_SIG_MONTH', USGS_API_BASE . 'significant_month.geojson');
define('USGS_GEOJSON_25_DAY',    USGS_API_BASE . '2.5_day.geojson');
define('USGS_GEOJSON_45_DAY',    USGS_API_BASE . '4.5_day.geojson');

// Philippines bounding box for regional focus
define('PH_LAT_MIN',  4.0);
define('PH_LAT_MAX', 21.5);
define('PH_LON_MIN', 116.0);
define('PH_LON_MAX', 127.0);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
