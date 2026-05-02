<?php
require_once __DIR__ . "/functions.php";

require_login("Log in to view your dashboard.");

$user_id = (int) current_user_id();
$posts = db_query(posts_select_sql("WHERE p.UserID = ?"), [$user_id]);
$post_count = 0;
$comment_count = 0;

$post_count_result = db_query("SELECT COUNT(*) AS TotalPosts FROM Posts WHERE UserID = ?", [$user_id]);
if ($post_count_result) {
    $post_count_row = $post_count_result->fetch_assoc();
    $post_count = $post_count_row["TotalPosts"] ?? 0;
}

if (table_exists("Comments")) {
    $comment_count_result = db_query("SELECT COUNT(*) AS TotalComments FROM Comments WHERE UserID = ?", [$user_id]);
    if ($comment_count_result) {
        $comment_count_row = $comment_count_result->fetch_assoc();
        $comment_count = $comment_count_row["TotalComments"] ?? 0;
    }
}

render_header("Dashboard");
?>
<section class="page-heading">
    <div>
        <h1>Dashboard</h1>
        <p>Manage posts for <?php echo h(current_username()); ?>.</p>
    </div>
    <div class="actions">
        <a class="button" href="insert_post.php">New Post</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat">
        <span class="stat-value"><?php echo h($post_count); ?></span>
        <span class="stat-label">Posts</span>
    </div>
    <?php if (table_exists("Comments")): ?>
        <div class="stat">
            <span class="stat-value"><?php echo h($comment_count); ?></span>
            <span class="stat-label">Comments</span>
        </div>
    <?php endif; ?>
    <div class="stat">
        <span class="stat-value"><?php echo is_admin() ? "Yes" : "No"; ?></span>
        <span class="stat-label">Admin</span>
    </div>
</section>

<section class="section-block">
    <div class="page-heading compact">
        <div>
            <h1>Your Posts</h1>
        </div>
    </div>

    <?php if ($posts && $posts->num_rows > 0): ?>
        <div class="post-grid">
            <?php while ($post = $posts->fetch_assoc()): ?>
                <?php render_post_card($post); ?>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">You have not added any posts yet.</div>
    <?php endif; ?>
</section>

<?php
render_footer();
$conn->close();
?>
