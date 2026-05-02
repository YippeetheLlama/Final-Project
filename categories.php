<?php
require_once __DIR__ . "/functions.php";

$categories = [];

if (table_exists("Categories")) {
    $sql = "SELECT c.CategoryID, c.Category AS CategoryName, COUNT(pc.PostID) AS PostCount
            FROM Categories c
            LEFT JOIN PostCategories pc ON c.CategoryID = pc.CategoryID
            GROUP BY c.CategoryID, c.Category
            ORDER BY c.Category";
    $result = db_query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

render_header("Categories");
?>
<section class="page-heading">
    <div>
        <h1>Categories</h1>
        <p>Browse posts by category.</p>
    </div>
</section>

<?php if (!table_exists("Categories")): ?>
    <section class="empty-state">No Categories table was found. Create categories in your database to use this page.</section>
<?php elseif ($categories): ?>
    <section class="list-panel">
        <?php foreach ($categories as $category): ?>
            <a class="list-row" href="category.php?id=<?php echo h($category["CategoryID"]); ?>">
                <span><?php echo h($category["CategoryName"]); ?></span>
                <span><?php echo h($category["PostCount"]); ?> posts</span>
            </a>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <section class="empty-state">No categories found.</section>
<?php endif; ?>

<?php
render_footer();
$conn->close();
?>
