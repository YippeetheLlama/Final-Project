<?php
require_once __DIR__ . "/functions.php";

$user_id = $_GET["id"] ?? "";
$user = null;
$posts = null;

if ($user_id !== "") {
    if (ctype_digit((string) $user_id) && table_exists("Users")) {
        $user_result = db_query("SELECT UserID, Username, Email, Admin FROM Users WHERE UserID = ? LIMIT 1", [(int) $user_id]);
        if ($user_result && $user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
        }

        $posts = db_query(posts_select_sql("WHERE p.UserID = ?"), [(int) $user_id]);
    }
}

$title = $user["Username"] ?? ($user_id !== "" ? "User #" . $user_id : "User");

render_header($title);
?>
<section class="page-heading">
    <div>
        <h1><?php echo h($title); ?></h1>
        <?php if ($user && (is_admin() || (string) current_user_id() === (string) $user["UserID"])): ?>
            <p><?php echo h($user["Email"]); ?><?php echo normalize_bit($user["Admin"]) ? " | Admin user" : ""; ?></p>
        <?php else: ?>
            <p>Posts from this user.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($user_id === ""): ?>
    <section class="empty-state">No user selected.</section>
<?php elseif ($posts && $posts->num_rows > 0): ?>
    <div class="post-grid">
        <?php while ($post = $posts->fetch_assoc()): ?>
            <?php render_post_card($post); ?>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <section class="empty-state">No posts found for this user.</section>
<?php endif; ?>

<?php
render_footer();
$conn->close();
?>
