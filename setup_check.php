<?php
require_once __DIR__ . "/functions.php";

require_admin();

$expected_tables = [
    "Users" => ["UserID", "Username", "PasswordHash", "Email", "Admin"],
    "Categories" => ["CategoryID", "Category"],
    "Posts" => ["PostID", "Title", "Content", "UserID", "CategoryID"],
    "Comments" => ["CommentID", "Comment", "PostID", "UserID"],
    "Tags" => ["TagID", "Tag"],
    "PostTags" => ["PostTagID", "TagID", "PostID"]
];

function column_exists($table, $column) {
    global $dbname;

    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
            LIMIT 1";
    $result = db_query($sql, [$dbname, $table, $column]);

    return $result && $result->num_rows > 0;
}

render_header("Setup Check");
?>
<section class="page-heading">
    <div>
        <h1>Setup Check</h1>
        <p>Quick read-only check for the table and column names the PHP pages expect.</p>
    </div>
</section>

<section class="panel">
    <div class="setup-grid">
        <?php foreach ($expected_tables as $table => $columns): ?>
            <div class="setup-card">
                <h2><?php echo h($table); ?></h2>
                <?php if (table_exists($table)): ?>
                    <p class="status-ok">Table found</p>
                    <ul class="check-list">
                        <?php foreach ($columns as $column): ?>
                            <li>
                                <span><?php echo h($column); ?></span>
                                <strong><?php echo column_exists($table, $column) ? "Found" : "Missing"; ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="status-bad">Table missing</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php
render_footer();
$conn->close();
?>
