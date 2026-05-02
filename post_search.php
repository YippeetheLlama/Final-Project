<?php
require_once __DIR__ . "/functions.php";

$keyword = $_GET["keyword"] ?? "";
$posts = null;

if ($keyword !== "") {
    $search = "%" . $keyword . "%";
    $where = "WHERE p.Title LIKE ? OR p.Content LIKE ?";
    $posts = db_query(posts_select_sql($where), [$search, $search]);
}

render_header("Search Posts");
?>
<section class="page-heading">
    <div>
        <h1>Search Blog Posts</h1>
        <p>Find posts by matching text in the title or content.</p>
    </div>
</section>

<section class="panel">
    <form class="form" action="post_search.php" method="get">
        <div class="form-row">
            <label for="keyword">Search keyword</label>
            <input type="text" id="keyword" name="keyword" value="<?php echo h($keyword); ?>" required>
        </div>
        <div>
            <input type="submit" value="Search">
        </div>
    </form>
</section>

<?php if ($keyword !== ""): ?>
    <section class="page-heading">
        <div>
            <h1>Results</h1>
            <p>Showing matches for "<?php echo h($keyword); ?>".</p>
        </div>
    </section>

    <?php if ($posts && $posts->num_rows > 0): ?>
        <div class="post-grid">
            <?php while ($post = $posts->fetch_assoc()): ?>
                <?php render_post_card($post); ?>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">No results found.</div>
    <?php endif; ?>
<?php endif; ?>

<?php
render_footer();
$conn->close();
?>
