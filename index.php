<?php
require_once __DIR__ . "/functions.php";

$result = db_query(posts_select_sql());

render_header("All Posts");
?>
<section class="page-heading">
    <div>
        <h1>All Blog Posts</h1>
        <p>Browse the newest entries in the blog database.</p>
    </div>
    <div class="actions">
        <a class="button" href="insert_post.php">New Post</a>
        <a class="button secondary" href="post_search.php">Search</a>
    </div>
</section>

<?php if ($result && $result->num_rows > 0): ?>
    <div class="post-grid">
        <?php while ($post = $result->fetch_assoc()): ?>
            <?php render_post_card($post); ?>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="empty-state">No posts found. Add your first blog post to get started.</div>
<?php endif; ?>

<?php
render_footer();
$conn->close();
?>
