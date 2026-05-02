<?php
require_once __DIR__ . "/functions.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf("login.php");

    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $wait = login_throttle_seconds_remaining();

    if ($wait > 0) {
        set_flash("Too many failed attempts. Try again in " . $wait . " seconds.", "error");
    } else {
        $sql = "SELECT * FROM Users WHERE Username = ? LIMIT 1";
        $result = db_query($sql, [$username]);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stored_password = $user["PasswordHash"] ?? "";
            $php_bcrypt_password = str_replace('$2b$', '$2y$', $stored_password);
            $password_matches = password_verify($password, $stored_password)
                || password_verify($password, $php_bcrypt_password);

            if ($password_matches) {
                session_regenerate_id(true);
                $_SESSION["user_id"] = $user["UserID"];
                $_SESSION["username"] = $user["Username"];
                $_SESSION["is_admin"] = normalize_bit($user["Admin"] ?? 0) ? 1 : 0;
                clear_failed_login();
                set_flash("Welcome back, " . $user["Username"] . ".", "success");
                header("Location: index.php");
                exit;
            }
        }

        record_failed_login();
        set_flash("Invalid username or password.", "error");
    }
}

render_header("Login");
?>
<section class="page-heading">
    <div>
        <h1>User Login</h1>
        <p>Sign in to post as your blog user.</p>
    </div>
</section>

<section class="panel">
    <form class="form" action="login.php" method="post">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo h($_POST["username"] ?? ""); ?>" required>
        </div>

        <div class="form-row">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="actions">
            <input type="submit" value="Login">
            <a class="button secondary" href="register.php">Create Account</a>
        </div>
    </form>
</section>

<?php
render_footer();
$conn->close();
?>
