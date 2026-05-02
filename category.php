<?php
require_once __DIR__ . "/functions.php";

$category_id = $_GET["id"] ?? "";
$category = null;
$posts = null;

if ($category_id !== "") {
    if (ctype_digit((string) $category_id) && table_exists("Categories")) {
        $category_result = db_query("SELECT CategoryID, Category AS CategoryName FROM Categories WHERE CategoryID = ? LIMIT 1", [(int) $category_id]);
        if ($category_result && $category_result->num_rows > 0) {
            $category = $category_result->fetch_assoc();
        }

        $posts = db_query(posts_select_sql("WHERE EXISTS (
            SELECT 1 FROM PostCategories pc_filter
            WHERE pc_filter.PostID = p.PostID
            AND pc_filter.CategoryID = ?
        )"), [(int) $category_id]);
    }
}

$title = $category["CategoryName"] ?? ($category_id !== "" ? "Category #" . $category_id : "Category");

render_header($title);
?>
<section class="page-heading">
    <div>
        <h1><?php echo h($title); ?></h1>
        <p>Posts filed under this category.</p>
    </div>
    <div class="actions">
        <a class="button secondary" href="categories.php">All Categories</a>
    </div>
</section>

<?php if ($category_id === ""): ?>
    <section class="empty-state">No category selected. <a href="categories.php">Browse categories</a>.</section>
<?php elseif ($posts && $posts->num_rows > 0): ?>
    <div class="post-grid">
        <?php while ($post = $posts->fetch_assoc()): ?>
            <?php render_post_card($post); ?>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <section class="empty-state">No posts found in this category.</section>
<?php endif; ?>

<?php
render_footer();
$conn->close();
?>
