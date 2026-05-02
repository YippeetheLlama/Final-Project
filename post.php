<?php
require_once __DIR__ . "/functions.php";

$post_id = $_GET["id"] ?? "";
$post = null;
$comments = [];

if ($post_id !== "") {
    $post = get_post_by_id($post_id);
}

if ($post && $_SERVER["REQUEST_METHOD"] === "POST" && table_exists("Comments")) {
    require_login("Log in to add a comment.");
    require_valid_csrf("post.php?id=" . urlencode($post["PostID"]));

    $comment = $_POST["comment"] ?? "";

    if ($comment !== "") {
        $sql = "INSERT INTO Comments (PostID, UserID, Comment) VALUES (?, ?, ?)";
        if (db_query($sql, [(int) $post["PostID"], (int) current_user_id(), $comment])) {
            set_flash("Comment added.", "success");
        } else {
            set_flash("Comment could not be added.", "error");
        }
        header("Location: post.php?id=" . urlencode($post["PostID"]));
        exit;
    }
}

if ($post && table_exists("Comments")) {
    $comment_sql = "SELECT c.CommentID, c.Comment, c.UserID, u.Username
        FROM Comments c
        LEFT JOIN Users u ON c.UserID = u.UserID
        WHERE c.PostID = ?
        ORDER BY c.CommentID DESC";
    $comment_result = db_query($comment_sql, [(int) $post["PostID"]]);
    if ($comment_result) {
        while ($row = $comment_result->fetch_assoc()) {
            $comments[] = $row;
        }
    }
}

render_header($post ? $post["Title"] : "Post Not Found");
?>

<?php if (!$post): ?>
    <section class="empty-state">
        Post not found. <a href="index.php">Return to all posts</a>.
    </section>
<?php else: ?>
    <article class="panel">
        <div class="post-meta">
            <a href="user.php?id=<?php echo h($post["UserID"]); ?>"><?php echo h($post["Username"] ?? ("User #" . $post["UserID"])); ?></a>
            <?php if ($post["CategoryID"]): ?>
                <a href="category.php?id=<?php echo h($post["CategoryID"]); ?>"><?php echo h($post["CategoryName"]); ?></a>
            <?php endif; ?>
            <?php if ($post["TagNames"]): ?>
                <span><?php echo h($post["TagNames"]); ?></span>
            <?php endif; ?>
        </div>
        <div class="detail-title-row">
            <h1><?php echo h($post["Title"]); ?></h1>
            <?php if (can_manage_post($post)): ?>
                <div class="actions">
                    <a class="button secondary" href="edit_post.php?id=<?php echo h($post["PostID"]); ?>">Edit</a>
                    <a class="button danger" href="delete_post.php?id=<?php echo h($post["PostID"]); ?>">Delete</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="post-body"><?php echo nl2br(h($post["Content"])); ?></div>
    </article>

    <?php if (table_exists("Comments")): ?>
        <section class="panel">
            <h2>Comments</h2>
            <?php if ($comments): ?>
                <div class="comment-list">
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <strong><?php echo h($comment["Username"] ?? ("User #" . $comment["UserID"])); ?></strong>
                            <p><?php echo nl2br(h($comment["Comment"])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No comments yet.</p>
            <?php endif; ?>

            <?php if (is_logged_in()): ?>
                <form class="form" action="post.php?id=<?php echo h($post["PostID"]); ?>" method="post">
                    <?php echo csrf_field(); ?>
                    <div class="form-row">
                        <label for="comment">Add a comment</label>
                        <textarea id="comment" name="comment" required></textarea>
                    </div>
                    <div>
                        <input type="submit" value="Post Comment">
                    </div>
                </form>
            <?php else: ?>
                <p><a href="login.php">Log in</a> to add a comment.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php
render_footer();
$conn->close();
?>
