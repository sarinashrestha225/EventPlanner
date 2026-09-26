<?php

session_start();

require_once __DIR__ . "/database.php";

ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);

$provider_id = isset($_GET["provider_id"]) ? (int)$_GET["provider_id"] : 0;

$is_logged_in = false;
$user_type = "";
$user_id = 0;

if (isset($_SESSION["customer_id"])) {
    $is_logged_in = true;
    $user_type = "customer";
    $user_id = (int)$_SESSION["customer_id"];
} elseif (isset($_SESSION["provider_id"])) {
    $is_logged_in = true;
    $user_type = "provider";
    $user_id = (int)$_SESSION["provider_id"];
} elseif (isset($_SESSION["admin_id"])) {
    $is_logged_in = true;
    $user_type = "admin";
    $user_id = (int)$_SESSION["admin_id"];
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function portfolioUrl($provider_id)
{
    if ($provider_id > 0) {
        return "portfolio.php?provider_id=" . $provider_id;
    }

    return "portfolio.php";
}

function userLabel($type, $id)
{
    if ($type === "admin") {
        return "Admin";
    }

    if ($type === "provider") {
        return "Provider #" . (int)$id;
    }

    if ($type === "customer") {
        return "Customer #" . (int)$id;
    }

    return "User";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $portfolio_item_id = (int)($_POST["portfolio_id"] ?? 0);
    $post_provider_id = (int)($_POST["provider_id"] ?? 0);

    if ($post_provider_id > 0) {
        $provider_id = $post_provider_id;
    }

    if (!$is_logged_in) {
        header("Location: login.php");
        exit;
    }

    if ($portfolio_item_id <= 0) {
        header("Location: " . portfolioUrl($provider_id));
        exit;
    }

    if ($action === "like") {

        $check = $conn->prepare(
            "SELECT id
             FROM portfolio_likes
             WHERE portfolio_id = ?
             AND user_type = ?
             AND user_id = ?
             LIMIT 1"
        );

        if (!$check) {
            die("Like query error: " . $conn->error);
        }

        $check->bind_param(
            "isi",
            $portfolio_item_id,
            $user_type,
            $user_id
        );

        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $like = $result->fetch_assoc();

            $delete = $conn->prepare(
                "DELETE FROM portfolio_likes WHERE id = ?"
            );

            if (!$delete) {
                die("Delete like error: " . $conn->error);
            }

            $like_id = (int)$like["id"];

            $delete->bind_param("i", $like_id);
            $delete->execute();
            $delete->close();

        } else {

            $insert = $conn->prepare(
                "INSERT INTO portfolio_likes
                (portfolio_id, user_type, user_id)
                VALUES (?, ?, ?)"
            );

            if (!$insert) {
                die("Insert like error: " . $conn->error);
            }

            $insert->bind_param(
                "isi",
                $portfolio_item_id,
                $user_type,
                $user_id
            );

            $insert->execute();
            $insert->close();
        }

        $check->close();

        header("Location: " . portfolioUrl($provider_id));
        exit;
    }

    if ($action === "comment") {

        $comment = trim($_POST["comment"] ?? "");

        if ($comment !== "") {

            $insert = $conn->prepare(
                "INSERT INTO portfolio_comments
                (portfolio_id, user_type, user_id, comment)
                VALUES (?, ?, ?, ?)"
            );

            if (!$insert) {
                die("Comment query error: " . $conn->error);
            }

            $insert->bind_param(
                "isis",
                $portfolio_item_id,
                $user_type,
                $user_id,
                $comment
            );

            $insert->execute();
            $insert->close();
        }

        header("Location: " . portfolioUrl($provider_id));
        exit;
    }
}

if ($provider_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            id,
            provider_id,
            title,
            description,
            image,
            media_type,
            created_at
         FROM portfolio
         WHERE provider_id = ?
         ORDER BY created_at DESC"
    );

    if (!$stmt) {
        die("Portfolio query error: " . $conn->error);
    }

    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $portfolio_result = $stmt->get_result();

} else {

    $portfolio_result = $conn->query(
        "SELECT
            id,
            provider_id,
            title,
            description,
            image,
            media_type,
            created_at
         FROM portfolio
         ORDER BY created_at DESC"
    );

    if (!$portfolio_result) {
        die("Portfolio query error: " . $conn->error);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Portfolio - EventPlanner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f6fa;
    color: #222;
}

.navbar {
    background: #ffffff;
    border-bottom: 1px solid #ddd;
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    color: #6c2bd9;
}

.nav-links {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.nav-links a {
    text-decoration: none;
    color: #333;
    font-weight: 600;
}

.nav-links a:hover {
    color: #6c2bd9;
}

.container {
    width: 94%;
    max-width: 1200px;
    margin: 35px auto;
}

.page-title {
    text-align: center;
    margin-bottom: 8px;
    font-size: 32px;
}

.page-description {
    text-align: center;
    color: #666;
    margin-bottom: 35px;
}

.section-title {
    font-size: 25px;
    margin-bottom: 8px;
}

.section-description {
    color: #666;
    margin-bottom: 25px;
}

.portfolio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
    gap: 25px;
}

.portfolio-card {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.media-area {
    background: #111;
    position: relative;
}

.portfolio-image {
    width: 100%;
    height: 260px;
    display: block;
    object-fit: cover;
    cursor: zoom-in;
}

.portfolio-video {
    width: 100%;
    height: 260px;
    display: block;
    object-fit: cover;
    background: #000;
}

.media-button {
    width: 100%;
    border: none;
    background: #6c2bd9;
    color: white;
    padding: 12px;
    cursor: pointer;
    font-size: 15px;
    font-weight: bold;
}

.media-button:hover {
    background: #5520ad;
}

.card-content {
    padding: 18px;
}

.card-title {
    font-size: 21px;
    font-weight: bold;
    margin-bottom: 8px;
}

.card-description {
    color: #555;
    line-height: 1.5;
    min-height: 45px;
}

.provider-info {
    margin-top: 12px;
    color: #666;
    font-size: 14px;
}

.action-row {
    display: flex;
    gap: 10px;
    margin-top: 18px;
    flex-wrap: wrap;
}

.action-form {
    margin: 0;
}

.like-button {
    border: 1px solid #ddd;
    background: white;
    padding: 9px 14px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
}

.like-button:hover {
    background: #f5f0ff;
}

.like-button.liked {
    color: #e11d48;
    border-color: #e11d48;
}

.comment-count {
    padding: 9px 14px;
    border-radius: 20px;
    background: #f1f1f1;
    font-size: 14px;
}

.comments-section {
    margin-top: 20px;
    border-top: 1px solid #eee;
    padding-top: 18px;
}

.comments-title {
    font-size: 17px;
    font-weight: bold;
    margin-bottom: 12px;
}

.comment-list {
    max-height: 250px;
    overflow-y: auto;
}

.comment-item {
    background: #f7f7f7;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
}

.comment-user {
    font-size: 13px;
    font-weight: bold;
    color: #6c2bd9;
    margin-bottom: 4px;
}

.comment-text {
    font-size: 14px;
    color: #333;
    word-break: break-word;
}

.comment-date {
    font-size: 11px;
    color: #888;
    margin-top: 5px;
}

.comment-form {
    margin-top: 14px;
}

.comment-input {
    width: 100%;
    min-height: 75px;
    resize: vertical;
    border: 1px solid #ccc;
    border-radius: 10px;
    padding: 11px;
    font-family: inherit;
    font-size: 14px;
}

.send-button {
    margin-top: 8px;
    border: none;
    background: #6c2bd9;
    color: white;
    padding: 10px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.send-button:hover {
    background: #5520ad;
}

.login-message {
    background: #f7f3ff;
    border-radius: 10px;
    padding: 12px;
    color: #5a22b3;
    font-size: 14px;
    margin-top: 15px;
}

.login-message a {
    color: #5a22b3;
    font-weight: bold;
    text-decoration: none;
}

.empty {
    background: white;
    padding: 50px;
    text-align: center;
    border-radius: 14px;
    color: #777;
}

.lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.94);
    align-items: center;
    justify-content: center;
    padding: 30px;
}

.lightbox.active {
    display: flex;
}

.lightbox-content {
    max-width: 95vw;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-image {
    max-width: 95vw;
    max-height: 90vh;
    object-fit: contain;
    cursor: zoom-in;
    border-radius: 5px;
}

.lightbox-video {
    max-width: 95vw;
    max-height: 90vh;
    width: auto;
    height: auto;
    background: #000;
}

.close-lightbox {
    position: fixed;
    top: 20px;
    right: 25px;
    width: 45px;
    height: 45px;
    border: none;
    border-radius: 50%;
    background: white;
    color: #111;
    font-size: 28px;
    cursor: pointer;
    z-index: 10000;
}

.lightbox-title {
    position: fixed;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    background: rgba(0, 0, 0, 0.6);
    padding: 8px 16px;
    border-radius: 20px;
    max-width: 80%;
    text-align: center;
}

@media (max-width: 600px) {

    .navbar {
        padding: 15px;
    }

    .nav-links {
        gap: 12px;
    }

    .container {
        width: 92%;
        margin: 25px auto;
    }

    .page-title {
        font-size: 26px;
    }

    .portfolio-grid {
        grid-template-columns: 1fr;
    }

    .portfolio-image,
    .portfolio-video {
        height: 230px;
    }
}

</style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        EventPlanner
    </div>

    <div class="nav-links">

        <a href="index.php">Home</a>

        <a href="services.php">Services</a>

        <a href="portfolio.php">Portfolio</a>

        <?php if ($is_logged_in): ?>

            <?php if ($user_type === "customer"): ?>
                <a href="customer/dashboard.php">Dashboard</a>
            <?php elseif ($user_type === "provider"): ?>
                <a href="provider/dashboard.php">Dashboard</a>
            <?php elseif ($user_type === "admin"): ?>
                <a href="admin/dashboard.php">Dashboard</a>
            <?php endif; ?>

        <?php else: ?>

            <a href="login.php">Login</a>
            <a href="register.php">Register</a>

        <?php endif; ?>

    </div>

</nav>

<div class="container">

    <h1 class="page-title">
        Provider Portfolios
    </h1>

    <p class="page-description">
        Explore photos and videos of work uploaded by our event providers.
    </p>

    <?php if ($provider_id > 0): ?>

        <h2 class="section-title">
            Provider Portfolio
        </h2>

    <?php else: ?>

        <h2 class="section-title">
            All Provider Work
        </h2>

        <p class="section-description">
            Browse photos and videos uploaded by event providers.
        </p>

    <?php endif; ?>

    <?php if ($portfolio_result->num_rows > 0): ?>

        <div class="portfolio-grid">

            <?php while ($item = $portfolio_result->fetch_assoc()): ?>

                <?php

                $portfolio_item_id = (int)$item["id"];
                $item_provider_id = (int)$item["provider_id"];

                $title = $item["title"] ?? "";
                $description = $item["description"] ?? "";
                $image_name = $item["image"] ?? "";
                $media_type = strtolower($item["media_type"] ?? "image");

                $media_url = "provider/uploads/portfolio/" . rawurlencode($image_name);

                $like_count = 0;
                $comment_count = 0;
                $user_liked = false;

                $like_count_stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM portfolio_likes
                     WHERE portfolio_id = ?"
                );

                if ($like_count_stmt) {

                    $like_count_stmt->bind_param(
                        "i",
                        $portfolio_item_id
                    );

                    $like_count_stmt->execute();

                    $like_result = $like_count_stmt->get_result();
                    $like_data = $like_result->fetch_assoc();

                    $like_count = (int)($like_data["total"] ?? 0);

                    $like_count_stmt->close();
                }

                $comment_count_stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM portfolio_comments
                     WHERE portfolio_id = ?"
                );

                if ($comment_count_stmt) {

                    $comment_count_stmt->bind_param(
                        "i",
                        $portfolio_item_id
                    );

                    $comment_count_stmt->execute();

                    $comment_result = $comment_count_stmt->get_result();
                    $comment_data = $comment_result->fetch_assoc();

                    $comment_count = (int)($comment_data["total"] ?? 0);

                    $comment_count_stmt->close();
                }

                if ($is_logged_in) {

                    $user_like_stmt = $conn->prepare(
                        "SELECT id
                         FROM portfolio_likes
                         WHERE portfolio_id = ?
                         AND user_type = ?
                         AND user_id = ?
                         LIMIT 1"
                    );

                    if ($user_like_stmt) {

                        $user_like_stmt->bind_param(
                            "isi",
                            $portfolio_item_id,
                            $user_type,
                            $user_id
                        );

                        $user_like_stmt->execute();

                        $user_like_result = $user_like_stmt->get_result();

                        if ($user_like_result->num_rows > 0) {
                            $user_liked = true;
                        }

                        $user_like_stmt->close();
                    }
                }

                ?>

                <div class="portfolio-card">

                    <div class="media-area">

                        <?php if (
                            $media_type === "video" ||
                            in_array(
                                strtolower(pathinfo($image_name, PATHINFO_EXTENSION)),
                                ["mp4", "webm", "mov"]
                            )
                        ): ?>

                            <video
                                class="portfolio-video"
                                controls
                                preload="metadata"
                            >
                                <source src="<?= e($media_url) ?>">
                                Your browser does not support video.
                            </video>

                            <button
                                type="button"
                                class="media-button"
                                onclick="openLightbox(
                                    '<?= e($media_url) ?>',
                                    '<?= e($title) ?>',
                                    'video'
                                )"
                            >
                                ⛶ Full View
                            </button>

                        <?php else: ?>

                            <img
                                src="<?= e($media_url) ?>"
                                alt="<?= e($title) ?>"
                                class="portfolio-image"
                                onclick="openLightbox(
                                    '<?= e($media_url) ?>',
                                    '<?= e($title) ?>',
                                    'image'
                                )"
                                onerror="this.style.display='none';"
                            >

                            <button
                                type="button"
                                class="media-button"
                                onclick="openLightbox(
                                    '<?= e($media_url) ?>',
                                    '<?= e($title) ?>',
                                    'image'
                                )"
                            >
                                ⛶ Full View
                            </button>

                        <?php endif; ?>

                    </div>

                    <div class="card-content">

                        <div class="card-title">
                            <?= e($title) ?>
                        </div>

                        <div class="card-description">
                            <?= nl2br(e($description)) ?>
                        </div>

                        <div class="provider-info">
                            Provider ID:
                            <?= $item_provider_id ?>
                            <br>
                            Added:
                            <?= e($item["created_at"]) ?>
                        </div>

                        <div class="action-row">

                            <?php if ($is_logged_in): ?>

                                <form
                                    method="POST"
                                    class="action-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="like"
                                    >

                                    <input
                                        type="hidden"
                                        name="portfolio_id"
                                        value="<?= $portfolio_item_id ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="provider_id"
                                        value="<?= $provider_id ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="like-button <?= $user_liked ? "liked" : "" ?>"
                                    >
                                        <?= $user_liked ? "❤️ Unlike" : "♡ Like" ?>
                                        <?= $like_count ?>
                                    </button>

                                </form>

                            <?php else: ?>

                                <button
                                    type="button"
                                    class="like-button"
                                    onclick="window.location.href='login.php'"
                                >
                                    ♡ Like <?= $like_count ?>
                                </button>

                            <?php endif; ?>

                            <div class="comment-count">
                                💬 <?= $comment_count ?> Comments
                            </div>

                        </div>

                        <div class="comments-section">

                            <div class="comments-title">
                                💬 Comments
                            </div>

                            <?php if ($is_logged_in): ?>

                                <?php

                                $comments_stmt = $conn->prepare(
                                    "SELECT
                                        user_type,
                                        user_id,
                                        comment,
                                        created_at
                                     FROM portfolio_comments
                                     WHERE portfolio_id = ?
                                     ORDER BY created_at DESC"
                                );

                                ?>

                                <?php if ($comments_stmt): ?>

                                    <?php

                                    $comments_stmt->bind_param(
                                        "i",
                                        $portfolio_item_id
                                    );

                                    $comments_stmt->execute();

                                    $comments_result = $comments_stmt->get_result();

                                    ?>

                                    <?php if ($comments_result->num_rows > 0): ?>

                                        <div class="comment-list">

                                            <?php while ($comment_row = $comments_result->fetch_assoc()): ?>

                                                <div class="comment-item">

                                                    <div class="comment-user">
                                                        <?= e(
                                                            userLabel(
                                                                $comment_row["user_type"],
                                                                $comment_row["user_id"]
                                                            )
                                                        ) ?>
                                                    </div>

                                                    <div class="comment-text">
                                                        <?= nl2br(e($comment_row["comment"])) ?>
                                                    </div>

                                                    <div class="comment-date">
                                                        <?= e($comment_row["created_at"]) ?>
                                                    </div>

                                                </div>

                                            <?php endwhile; ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="comment-item">
                                            No comments yet. Be the first to comment.
                                        </div>

                                    <?php endif; ?>

                                    <?php $comments_stmt->close(); ?>

                                <?php endif; ?>

                                <form
                                    method="POST"
                                    class="comment-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="comment"
                                    >

                                    <input
                                        type="hidden"
                                        name="portfolio_id"
                                        value="<?= $portfolio_item_id ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="provider_id"
                                        value="<?= $provider_id ?>"
                                    >

                                    <textarea
                                        name="comment"
                                        class="comment-input"
                                        placeholder="Write a comment..."
                                        required
                                    ></textarea>

                                    <button
                                        type="submit"
                                        class="send-button"
                                    >
                                        Send
                                    </button>

                                </form>

                            <?php else: ?>

                                <div class="login-message">

                                    🔐 Like/comment गर्न
                                    <a href="login.php">Login</a>
                                    गर्नुहोस्।

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty">
            No portfolio items available yet.
        </div>

    <?php endif; ?>

</div>

<div
    id="lightbox"
    class="lightbox"
    onclick="closeLightbox(event)"
>

    <button
        type="button"
        class="close-lightbox"
        onclick="closeLightbox()"
    >
        ×
    </button>

    <div class="lightbox-content">

        <img
            id="lightboxImage"
            class="lightbox-image"
            style="display:none;"
            alt=""
        >

        <video
            id="lightboxVideo"
            class="lightbox-video"
            style="display:none;"
            controls
        >
        </video>

    </div>

    <div
        id="lightboxTitle"
        class="lightbox-title"
    ></div>

</div>

<script>

function openLightbox(src, title, type)
{
    const lightbox = document.getElementById("lightbox");
    const image = document.getElementById("lightboxImage");
    const video = document.getElementById("lightboxVideo");
    const titleBox = document.getElementById("lightboxTitle");

    lightbox.classList.add("active");

    titleBox.textContent = title || "";

    image.style.display = "none";
    video.style.display = "none";

    video.pause();
    video.removeAttribute("src");
    video.load();

    if (type === "video") {

        video.style.display = "block";
        video.src = src;
        video.load();

    } else {

        image.style.display = "block";
        image.src = src;
        image.alt = title || "Portfolio image";

    }

    document.body.style.overflow = "hidden";
}

function closeLightbox(event)
{
    const lightbox = document.getElementById("lightbox");

    if (
        event &&
        event.target !== lightbox &&
        !event.target.classList.contains("close-lightbox")
    ) {
        return;
    }

    lightbox.classList.remove("active");

    const image = document.getElementById("lightboxImage");
    const video = document.getElementById("lightboxVideo");

    image.src = "";

    video.pause();
    video.removeAttribute("src");
    video.load();

    document.body.style.overflow = "";
}

document.addEventListener(
    "keydown",
    function(event)
    {
        if (event.key === "Escape") {
            closeLightbox();
        }
    }
);

</script>

</body>
</html>