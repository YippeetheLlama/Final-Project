<?php
require_once __DIR__ . "/functions.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf("register.php");

    $username = $_POST["username"] ?? "";
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    if ($username === "" || $email === "" || $password === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash("Please fill out every registration field.", "error");
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO Users (Username, PasswordHash, Email, Admin)
                VALUES (?, ?, ?, 0)";

        if (db_query($sql, [$username, $hashed_password, $email])) {
            set_flash("Account created. You can log in now.", "success");
            header("Location: login.php");
            exit;
        }

        set_flash("Account could not be created.", "error");
    }
}

render_header("Register");
?>
<section class="page-heading">
    <div>
        <h1>Create Account</h1>
        <p>Add a new user record to the blog database.</p>
    </div>
</section>

<section class="panel">
    <form class="form" action="register.php" method="post">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo h($_POST["username"] ?? ""); ?>" required>
        </div>

        <div class="form-row">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo h($_POST["email"] ?? ""); ?>" required>
        </div>

        <div class="form-row">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="actions">
            <input type="submit" value="Register">
            <a class="button secondary" href="login.php">Back to Login</a>
        </div>
    </form>
</section>

<?php
render_footer();
$conn->close();
?>
