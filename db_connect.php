<?php
$local_config_path = __DIR__ . "/config.local.php";
$local_config = file_exists($local_config_path) ? require $local_config_path : [];

function db_setting($key, $default = "") {
    global $local_config;

    $env_value = getenv($key);
    if ($env_value !== false && $env_value !== "") {
        return $env_value;
    }

    return $local_config[$key] ?? $default;
}

$servername = db_setting("BLOG_DB_HOST", "127.0.0.1");
$port = (int) db_setting("BLOG_DB_PORT", "3306");
$username = db_setting("BLOG_DB_USER");
$password = db_setting("BLOG_DB_PASS");
$dbname = db_setting("BLOG_DB_NAME", "blog_s011");

$conn = @new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed. Check the server configuration.");
}

$conn->set_charset("utf8mb4");
?>
