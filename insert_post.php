<?php
require_once __DIR__ . "/functions.php";

require_login("Log in to create a post.");

$categories = get_categories();
$tags = get_tags();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf("insert_post.php");

    $title = $_POST["title"] ?? "";
    $content = $_POST["content"] ?? "";
    $userid = current_user_id();
    $categoryids = normalize_id_list($_POST["categoryids"] ?? ($_POST["categoryid"] ?? []));
    $tagids = normalize_id_list($_POST["tagids"] ?? []);

    if ($title === "" || $content === "" || $userid === "" || !$categoryids) {
        set_flash("Please fill out every field before submitting the post.", "error");
    } else {
        $sql = "INSERT INTO Posts (UserID, Title, Content)
                VALUES (?, ?, ?)";

        if (db_query($sql, [(int) $userid, $title, $content])) {
            $new_post_id = $conn->insert_id;
            sync_post_categories($new_post_id, $categoryids);
            sync_post_tags($new_post_id, $tagids);
            set_flash("New post created.", "success");
            header("Location: index.php");
            exit;
        }

        set_flash("Post could not be created.", "error");
    }
}

render_header("Add New Post");
?>
<section class="page-heading">
    <div>
        <h1>Add New Blog Post</h1>
        <p>Create a post connected to a user and category in your database.</p>
    </div>
</section>

<section class="panel">
    <form class="form" action="insert_post.php" method="post">
        <?php echo csrf_field(); ?>

        <div class="form-row">
            <label for="categoryids">Categories</label>
            <?php if ($categories): ?>
                <select id="categoryids" name="categoryids[]" multiple required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo h($category["CategoryID"]); ?>" <?php echo in_array($category["CategoryID"], normalize_id_list($_POST["categoryids"] ?? ($_POST["categoryid"] ?? []))) ? "selected" : ""; ?>>
                            <?php echo h($category["CategoryName"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="number" id="categoryids" name="categoryid" value="<?php echo h($_POST["categoryid"] ?? ""); ?>" required>
            <?php endif; ?>
        </div>

        <?php if ($tags): ?>
            <div class="form-row">
                <label for="tagids">Tags</label>
                <select id="tagids" name="tagids[]" multiple>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo h($tag["TagID"]); ?>" <?php echo in_array($tag["TagID"], normalize_id_list($_POST["tagids"] ?? [])) ? "selected" : ""; ?>>
                            <?php echo h($tag["TagName"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

        <div class="form-row">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?php echo h($_POST["title"] ?? ""); ?>" required>
        </div>

        <div class="form-row">
            <label for="content">Content</label>
            <textarea id="content" name="content" required><?php echo h($_POST["content"] ?? ""); ?></textarea>
        </div>

        <div>
            <input type="submit" value="Publish Post">
        </div>
    </form>
</section>

<?php
render_footer();
$conn->close();
?>
