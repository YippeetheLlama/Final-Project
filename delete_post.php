<?php
require_once __DIR__ . "/functions.php";

$post_id = $_GET["id"] ?? ($_POST["post_id"] ?? "");
$post = $post_id !== "" ? get_post_by_id($post_id) : null;

if (!$post) {
    render_header("Post Not Found");
    ?>
    <section class="empty-state">Post not found. <a href="index.php">Return to all posts</a>.</section>
    <?php
    render_footer();
    $conn->close();
    exit;
}

if (!can_manage_post($post)) {
    set_flash("Log in as the post owner or an admin to delete this post.", "error");
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf("delete_post.php?id=" . urlencode($post["PostID"]));
    $post_id_int = (int) $post["PostID"];

    if (table_exists("Comments")) {
        db_query("DELETE FROM Comments WHERE PostID = ?", [$post_id_int]);
    }

    if (table_exists("PostTags")) {
        db_query("DELETE FROM PostTags WHERE PostID = ?", [$post_id_int]);
    }

    if (db_query("DELETE FROM Posts WHERE PostID = ?", [$post_id_int])) {
        set_flash("Post deleted.", "success");
        header("Location: dashboard.php");
        exit;
    }

    set_flash("Post could not be deleted.", "error");
}

render_header("Delete Post");
?>
<section class="page-heading">
    <div>
        <h1>Delete Post</h1>
        <p>Confirm removal of this blog entry.</p>
    </div>
</section>

<section class="panel">
    <h2><?php echo h($post["Title"]); ?></h2>
    <p>This will remove the post from Posts and its related tag and comment rows.</p>
    <form class="inline-form" action="delete_post.php" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="post_id" value="<?php echo h($post["PostID"]); ?>">
        <input class="danger-submit" type="submit" value="Delete Post">
        <a class="button secondary" href="post.php?id=<?php echo h($post["PostID"]); ?>">Cancel</a>
    </form>
</section>

<?php
render_footer();
$conn->close();
?>
