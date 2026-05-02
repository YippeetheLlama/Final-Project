<?php
require_once __DIR__ . "/functions.php";

$post_id = $_GET["id"] ?? ($_POST["post_id"] ?? "");
$post = $post_id !== "" ? get_post_by_id($post_id) : null;
$categories = get_categories();
$tags = get_tags();

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
    set_flash("Log in as the post owner or an admin to edit this post.", "error");
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf("edit_post.php?id=" . urlencode($post["PostID"]));

    $title = $_POST["title"] ?? "";
    $content = $_POST["content"] ?? "";
    $categoryids = normalize_id_list($_POST["categoryid"] ?? []);
    $tagids = normalize_id_list($_POST["tagids"] ?? []);

    if ($title === "" || $content === "" || !$categoryids) {
        set_flash("Please fill out every field before saving.", "error");
    } else {
        $sql = "UPDATE Posts
                SET Title = ?, Content = ?, CategoryID = ?
                WHERE PostID = ?";

        if (db_query($sql, [$title, $content, $categoryids[0], (int) $post["PostID"]])) {
            sync_post_tags($post["PostID"], $tagids);
            set_flash("Post updated.", "success");
            header("Location: post.php?id=" . urlencode($post["PostID"]));
            exit;
        }

        set_flash("Post could not be updated.", "error");
    }
}

$form_title = $_POST["title"] ?? $post["Title"];
$form_content = $_POST["content"] ?? $post["Content"];
$form_categories = normalize_id_list($_POST["categoryid"] ?? get_post_category_ids($post["PostID"]));
$form_tags = normalize_id_list($_POST["tagids"] ?? get_post_tag_ids($post["PostID"]));

render_header("Edit Post");
?>
<section class="page-heading">
    <div>
        <h1>Edit Blog Post</h1>
        <p>Update the title, category, or content for this entry.</p>
    </div>
</section>

<section class="panel">
    <form class="form" action="edit_post.php" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="post_id" value="<?php echo h($post["PostID"]); ?>">

        <div class="form-row">
            <label for="categoryid">Category</label>
            <?php if ($categories): ?>
                <select id="categoryid" name="categoryid" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo h($category["CategoryID"]); ?>" <?php echo in_array($category["CategoryID"], $form_categories) ? "selected" : ""; ?>>
                            <?php echo h($category["CategoryName"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="number" id="categoryid" name="categoryid" value="<?php echo h($form_categories[0] ?? ""); ?>" required>
            <?php endif; ?>
        </div>

        <?php if ($tags): ?>
            <div class="form-row">
                <label for="tagids">Tags</label>
                <select id="tagids" name="tagids[]" multiple>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo h($tag["TagID"]); ?>" <?php echo in_array($tag["TagID"], $form_tags) ? "selected" : ""; ?>>
                            <?php echo h($tag["TagName"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?php echo h($form_title); ?>" required>
        </div>

        <div class="form-row">
            <label for="content">Content</label>
            <textarea id="content" name="content" required><?php echo h($form_content); ?></textarea>
        </div>

        <div class="actions">
            <input type="submit" value="Save Changes">
            <a class="button secondary" href="post.php?id=<?php echo h($post["PostID"]); ?>">Cancel</a>
        </div>
    </form>
</section>

<?php
render_footer();
$conn->close();
?>
