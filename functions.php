<?php
require_once __DIR__ . "/security.php";
enforce_not_blocked();

if (session_status() === PHP_SESSION_NONE) {
    $secure_cookie = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $secure_cookie,
        "httponly" => true,
        "samesite" => "Lax"
    ]);
    session_start();
}

require_once __DIR__ . "/db_connect.php";

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function db_param_types($params) {
    $types = "";
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= "i";
        } elseif (is_float($param)) {
            $types .= "d";
        } else {
            $types .= "s";
        }
    }
    return $types;
}

function db_query($sql, $params = [], $types = "") {
    global $conn;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    if ($params) {
        $types = $types ?: db_param_types($params);
        $bind_values = [$types];
        foreach ($params as $key => $value) {
            $bind_values[] = &$params[$key];
        }

        if (!call_user_func_array([$stmt, "bind_param"], $bind_values)) {
            error_log("Bind failed: " . $stmt->error);
            return false;
        }
    }

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }

    $result = $stmt->get_result();
    return $result !== false ? $result : $stmt;
}

function is_logged_in() {
    return isset($_SESSION["user_id"]);
}

function require_login($message = "Please log in to continue.") {
    if (!is_logged_in()) {
        set_flash($message, "error");
        header("Location: login.php");
        exit;
    }
}

function require_admin() {
    require_login("Log in as an admin to view that page.");
    if (!is_admin()) {
        http_response_code(403);
        set_flash("You do not have permission to view that page.", "error");
        header("Location: index.php");
        exit;
    }
}

function current_user_id() {
    return $_SESSION["user_id"] ?? null;
}

function current_username() {
    return $_SESSION["username"] ?? "Guest";
}

function is_admin() {
    return isset($_SESSION["is_admin"]) && normalize_bit($_SESSION["is_admin"]);
}

function normalize_bit($value) {
    if ($value === true || $value === 1 || $value === "1") {
        return true;
    }

    return is_string($value) && strlen($value) > 0 && ord($value[0]) === 1;
}

function can_manage_post($post) {
    if (!is_logged_in() || !$post) {
        return false;
    }

    return is_admin() || (string) ($post["UserID"] ?? "") === (string) current_user_id();
}

function set_flash($message, $type = "info") {
    $_SESSION["flash"] = [
        "message" => $message,
        "type" => $type
    ];
}

function get_flash() {
    if (!isset($_SESSION["flash"])) {
        return null;
    }

    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}

function csrf_token() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function valid_csrf() {
    $token = $_POST["csrf_token"] ?? "";
    return is_string($token) && hash_equals($_SESSION["csrf_token"] ?? "", $token);
}

function require_valid_csrf($redirect = "index.php") {
    if (!valid_csrf()) {
        set_flash("The form expired. Please try again.", "error");
        header("Location: " . $redirect);
        exit;
    }
}

function login_throttle_seconds_remaining() {
    $lock_until = $_SESSION["login_lock_until"] ?? 0;
    $ip_lock_until = login_attempt_record()["lock_until"] ?? 0;
    return max(0, $lock_until - time(), $ip_lock_until - time());
}

function record_failed_login() {
    $_SESSION["login_failures"] = ($_SESSION["login_failures"] ?? 0) + 1;

    if ($_SESSION["login_failures"] >= 5) {
        $_SESSION["login_lock_until"] = time() + 300;
    }

    $attempts = read_login_attempts();
    $ip = client_ip();
    if ($ip !== "unknown") {
        $record = $attempts[$ip] ?? ["failures" => 0, "lock_until" => 0];
        $record["failures"] = ($record["failures"] ?? 0) + 1;
        $record["last_failure"] = time();

        if ($record["failures"] >= 5) {
            $record["lock_until"] = time() + 300;
        }

        $attempts[$ip] = $record;
        write_login_attempts($attempts);
    }
}

function clear_failed_login() {
    unset($_SESSION["login_failures"], $_SESSION["login_lock_until"]);

    $ip = client_ip();
    if ($ip !== "unknown") {
        $attempts = read_login_attempts();
        unset($attempts[$ip]);
        write_login_attempts($attempts);
    }
}

function login_attempts_file() {
    return __DIR__ . "/login_attempts.json";
}

function read_login_attempts() {
    $file = login_attempts_file();
    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_login_attempts($attempts) {
    $handle = fopen(login_attempts_file(), "c+");
    if (!$handle) {
        error_log("Unable to open login attempts file.");
        return false;
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($attempts, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}

function login_attempt_record() {
    $ip = client_ip();
    if ($ip === "unknown") {
        return [];
    }

    $attempts = read_login_attempts();
    $record = $attempts[$ip] ?? [];

    if (($record["lock_until"] ?? 0) > 0 && $record["lock_until"] <= time()) {
        unset($attempts[$ip]);
        write_login_attempts($attempts);
        return [];
    }

    return $record;
}

function table_exists($table) {
    global $dbname;
    static $table_cache = [];

    if (isset($table_cache[$table])) {
        return $table_cache[$table];
    }

    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1";
    $result = db_query($sql, [$dbname, $table]);
    $table_cache[$table] = $result && $result->num_rows > 0;
    return $table_cache[$table];
}

function get_categories() {
    if (!table_exists("Categories")) {
        return [];
    }

    $categories = [];
    $result = db_query("SELECT CategoryID, Category AS CategoryName FROM Categories ORDER BY Category");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}

function get_tags() {
    if (!table_exists("Tags")) {
        return [];
    }

    $tags = [];
    $result = db_query("SELECT TagID, Tag AS TagName FROM Tags ORDER BY Tag");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
    }

    return $tags;
}

function get_post_by_id($post_id) {
    if (!ctype_digit((string) $post_id)) {
        return null;
    }

    $result = db_query(posts_select_sql("WHERE p.PostID = ?"), [(int) $post_id]);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

function normalize_id_list($value) {
    if (!is_array($value)) {
        $value = $value === "" || $value === null ? [] : [$value];
    }

    $ids = [];
    foreach ($value as $id) {
        if (ctype_digit((string) $id)) {
            $ids[] = (int) $id;
        }
    }

    return array_values(array_unique($ids));
}

function get_post_category_ids($post_id) {
    if (!ctype_digit((string) $post_id)) {
        return [];
    }

    $ids = [];
    $result = db_query("SELECT CategoryID FROM Posts WHERE PostID = ?", [(int) $post_id]);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ids[] = $row["CategoryID"];
        }
    }

    return $ids;
}

function get_post_tag_ids($post_id) {
    if (!table_exists("PostTags") || !ctype_digit((string) $post_id)) {
        return [];
    }

    $ids = [];
    $result = db_query("SELECT TagID FROM PostTags WHERE PostID = ?", [(int) $post_id]);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ids[] = $row["TagID"];
        }
    }

    return $ids;
}

function sync_post_categories($post_id, $category_ids) {
    if (!ctype_digit((string) $post_id)) {
        return;
    }

    $category_ids = normalize_id_list($category_ids);
    if (!$category_ids) {
        return;
    }

    $post_id = (int) $post_id;
    db_query("UPDATE Posts SET CategoryID = ? WHERE PostID = ?", [$category_ids[0], $post_id]);
}

function sync_post_tags($post_id, $tag_ids) {
    if (!table_exists("PostTags") || !ctype_digit((string) $post_id)) {
        return;
    }

    $post_id = (int) $post_id;
    db_query("DELETE FROM PostTags WHERE PostID = ?", [$post_id]);

    foreach (normalize_id_list($tag_ids) as $tag_id) {
        db_query("INSERT INTO PostTags (TagID, PostID) VALUES (?, ?)", [$tag_id, $post_id]);
    }
}

function posts_select_sql($where = "") {
    $select = "p.PostID, p.UserID, p.Title, p.Content, p.CategoryID";
    $from = "Posts p";
    $joins = "";
    $group_by = " GROUP BY p.PostID, p.UserID, p.Title, p.Content, p.CategoryID";

    if (table_exists("Users")) {
        $select .= ", u.Username";
        $joins .= " LEFT JOIN Users u ON p.UserID = u.UserID";
        $group_by .= ", u.Username";
    }

    if (table_exists("Categories")) {
        $select .= ", c.Category AS CategoryName";
        $joins .= " LEFT JOIN Categories c ON p.CategoryID = c.CategoryID";
        $group_by .= ", c.Category";
    } else {
        $select .= ", NULL AS CategoryName";
    }

    if (table_exists("Tags") && table_exists("PostTags")) {
        $select .= ", GROUP_CONCAT(DISTINCT t.Tag ORDER BY t.Tag SEPARATOR ', ') AS TagNames";
        $joins .= " LEFT JOIN PostTags pt ON p.PostID = pt.PostID";
        $joins .= " LEFT JOIN Tags t ON pt.TagID = t.TagID";
    } else {
        $select .= ", NULL AS TagNames";
    }

    if (table_exists("Comments")) {
        $select .= ", (SELECT COUNT(*) FROM Comments cm WHERE cm.PostID = p.PostID) AS CommentCount";
    }

    return "SELECT $select FROM $from$joins $where$group_by ORDER BY p.PostID DESC";
}

function excerpt($content, $length = 260) {
    $content = trim((string) $content);
    if (strlen($content) <= $length) {
        return $content;
    }

    return substr($content, 0, $length) . "...";
}

function render_header($title = "Blog Platform") {
    $flash = get_flash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($title); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="index.php">Blog Platform</a>
            <nav class="nav-links" aria-label="Primary navigation">
                <a href="index.php">Posts</a>
                <a href="categories.php">Categories</a>
                <a href="post_search.php">Search</a>
                <a href="insert_post.php">New Post</a>
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <span class="nav-user"><?php echo h(current_username()); ?></span>
                    <form class="nav-form" action="logout.php" method="post">
                        <?php echo csrf_field(); ?>
                        <button class="nav-button" type="submit">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container page">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo h($flash["type"]); ?>"><?php echo h($flash["message"]); ?></div>
        <?php endif; ?>
    <?php
}

function render_footer() {
    ?>
    </main>
    <footer class="site-footer">
        <div class="container">PHP and MySQL blogging platform for the LAMP assignment.</div>
    </footer>
</body>
</html>
    <?php
}

function render_post_card($post) {
    $author = $post["Username"] ?? ("User #" . ($post["UserID"] ?? "Unknown"));
    $category = $post["CategoryName"] ?? "Uncategorized";
    $tags = $post["TagNames"] ?? "";
    $comment_count = $post["CommentCount"] ?? null;
    ?>
    <article class="post-card">
        <div class="post-meta">
            <a href="user.php?id=<?php echo h($post["UserID"] ?? ""); ?>"><?php echo h($author); ?></a>
            <?php if (isset($post["CategoryID"]) && $post["CategoryID"] !== ""): ?>
                <a href="category.php?id=<?php echo h($post["CategoryID"]); ?>"><?php echo h($category); ?></a>
            <?php else: ?>
                <span><?php echo h($category); ?></span>
            <?php endif; ?>
            <?php if ($tags !== ""): ?>
                <span><?php echo h($tags); ?></span>
            <?php endif; ?>
            <?php if ($comment_count !== null): ?>
                <span><?php echo h($comment_count); ?> comments</span>
            <?php endif; ?>
        </div>
        <h2><a href="post.php?id=<?php echo h($post["PostID"]); ?>"><?php echo h($post["Title"]); ?></a></h2>
        <p><?php echo nl2br(h(excerpt($post["Content"]))); ?></p>
        <div class="card-actions">
            <a class="text-link" href="post.php?id=<?php echo h($post["PostID"]); ?>">Read post</a>
            <?php if (can_manage_post($post)): ?>
                <a class="text-link" href="edit_post.php?id=<?php echo h($post["PostID"]); ?>">Edit</a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
?>
