<?php
define("BLOCKLIST_FILE", __DIR__ . "/blocked_ips.json");

function local_security_setting($key, $default = "") {
    static $local_config = null;

    if ($local_config === null) {
        $config_path = __DIR__ . "/config.local.php";
        $local_config = file_exists($config_path) ? require $config_path : [];
        if (!is_array($local_config)) {
            $local_config = [];
        }
    }

    return $local_config[$key] ?? $default;
}

function client_ip() {
    return $_SERVER["REMOTE_ADDR"] ?? "unknown";
}

function honeypot_allowlist() {
    $raw = getenv("BLOG_HONEYPOT_ALLOW_IPS");
    if (!$raw) {
        $raw = local_security_setting("BLOG_HONEYPOT_ALLOW_IPS");
    }

    if (!$raw) {
        return [];
    }

    return array_filter(array_map("trim", explode(",", $raw)));
}

function is_allowlisted_ip($ip) {
    return in_array($ip, honeypot_allowlist(), true);
}

function read_blocklist() {
    if (!file_exists(BLOCKLIST_FILE)) {
        return [];
    }

    $json = file_get_contents(BLOCKLIST_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function write_blocklist($data) {
    $handle = fopen(BLOCKLIST_FILE, "c+");
    if (!$handle) {
        error_log("Unable to open blocklist file.");
        return false;
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}

function is_blocked_ip($ip = null) {
    $ip = $ip ?? client_ip();
    if ($ip === "unknown" || is_allowlisted_ip($ip)) {
        return false;
    }

    $blocklist = read_blocklist();
    return isset($blocklist[$ip]);
}

function block_current_ip($reason = "honeypot") {
    $ip = client_ip();
    if ($ip === "unknown" || is_allowlisted_ip($ip)) {
        return false;
    }

    $blocklist = read_blocklist();
    $blocklist[$ip] = [
        "reason" => $reason,
        "blocked_at" => gmdate("c"),
        "path" => $_SERVER["REQUEST_URI"] ?? "",
        "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? ""
    ];

    return write_blocklist($blocklist);
}

function enforce_not_blocked() {
    if (!is_blocked_ip()) {
        return;
    }

    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    exit("Access denied.");
}
?>
