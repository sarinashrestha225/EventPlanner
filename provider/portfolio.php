<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["provider_id"];

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        description,
        image,
        media_type,
        created_at
    FROM portfolio
    WHERE provider_id = ?
    ORDER BY id DESC
");

if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();

$portfolio = [];

while ($row = $result->fetch_assoc()) {
    $portfolio[] = $row;
}

$stmt->close();

$provider_name = $_SESSION["provider_name"] ?? "Provider";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Portfolio - Provider</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #fff7f0;
    color: #4b3621;
    min-height: 100vh;
}

.header {
    background: linear-gradient(
        135deg,
        #f7c6d9,
        #fff0c7
    );
    padding: 22px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    box-shadow: 0 3px 15px rgba(120, 80, 90, 0.12);
}

.header-left h1 {
    color: #8a5a00;
    font-size: 27px;
    margin-bottom: 5px;
}

.header-left p {
    color: #765d64;
    font-size: 14px;
}

.dashboard-btn {
    text-decoration: none;
    background: #b8860b;
    color: white;
    padding: 11px 18px;
    border-radius: 10px;
    font-weight: bold;
    transition: 0.3s;
}

.dashboard-btn:hover {
    background: #946f08;
    transform: translateY(-2px);
}

.container {
    width: 92%;
    max-width: 1150px;
    margin: 35px auto;
}

.top-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.top-section h2 {
    color: #5d4148;
    font-size: 24px;
    margin-bottom: 6px;
}

.top-section p {
    color: #777;
    font-size: 14px;
}

.add-btn {
    display: inline-block;
    text-decoration: none;
    background: #d4af37;
    color: white;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: bold;
    transition: 0.3s;
}

.add-btn:hover {
    background: #b8941f;
    transform: translateY(-2px);
}

.grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fit,
        minmax(280px, 1fr)
    );
    gap: 22px;
}

.card {
    background: white;
    border-radius: 17px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(100, 70, 80, 0.09);
    border: 1px solid #f1e1e6;
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(100, 70, 80, 0.14);
}

.media-container {
    width: 100%;
    height: 250px;
    background: #f8efe7;
    overflow: hidden;
    position: relative;
}

.media-container img,
.media-container video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.media-type {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(0, 0, 0, 0.65);
    color: white;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    z-index: 2;
}

.no-media {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 55px;
}

.content {
    padding: 19px;
}

.title {
    font-size: 20px;
    font-weight: bold;
    color: #5d4148;
    margin-bottom: 9px;
}

.description {
    color: #6f6265;
    line-height: 1.6;
    font-size: 14px;
    margin-bottom: 12px;
}

.date {
    color: #999;
    font-size: 12px;
    margin-bottom: 16px;
}

.card-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.edit-btn {
    display: inline-block;
    text-decoration: none;
    background: #fff1c9;
    color: #946f08;
    border: 1px solid #e0c25c;
    padding: 9px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: bold;
}

.edit-btn:hover {
    background: #ffe7a1;
}

.delete-btn {
    display: inline-block;
    text-decoration: none;
    background: #dc3545;
    color: white;
    padding: 9px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: bold;
}

.delete-btn:hover {
    background: #b92b38;
}

.empty {
    background: white;
    padding: 65px 25px;
    text-align: center;
    border-radius: 18px;
    box-shadow: 0 6px 20px rgba(100, 70, 80, 0.08);
}

.empty-icon {
    font-size: 60px;
    margin-bottom: 18px;
}

.empty h3 {
    color: #5d4148;
    font-size: 23px;
    margin-bottom: 8px;
}

.empty p {
    color: #888;
    margin-bottom: 22px;
}

.success {
    background: #e7f8e9;
    color: #28743b;
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-weight: bold;
}

@media (max-width: 700px) {

    .header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
    }

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .top-section {
        flex-direction: column;
        align-items: flex-start;
    }

    .add-btn {
        width: 100%;
        text-align: center;
    }

    .media-container {
        height: 220px;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="header-left">

        <h1>
            📸 My Portfolio
        </h1>

        <p>
            Welcome,
            <?= htmlspecialchars(
                $provider_name,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>

    </div>

    <a
        href="dashboard.php"
        class="dashboard-btn"
    >
        ← Dashboard
    </a>

</header>

<div class="container">

    <?php if (isset($_GET["success"])): ?>

        <?php if ($_GET["success"] === "added"): ?>

            <div class="success">
                ✅ Portfolio media uploaded successfully.
            </div>

        <?php elseif ($_GET["success"] === "deleted"): ?>

            <div class="success">
                ✅ Portfolio media deleted successfully.
            </div>

        <?php endif; ?>

    <?php endif; ?>

    <div class="top-section">

        <div>

            <h2>
                My Work Portfolio
            </h2>

            <p>
                Upload and manage photos and videos of your previous work.
            </p>

        </div>

        <a
            href="add_portfolio.php"
            class="add-btn"
        >
            ➕ Add Photo / Video
        </a>

    </div>

    <?php if (empty($portfolio)): ?>

        <div class="empty">

            <div class="empty-icon">
                📸
            </div>

            <h3>
                No Portfolio Media
            </h3>

            <p>
                You haven't uploaded any portfolio photos or videos yet.
            </p>

            <a
                href="add_portfolio.php"
                class="add-btn"
            >
                ➕ Add Your First Photo / Video
            </a>

        </div>

    <?php else: ?>

        <div class="grid">

            <?php foreach ($portfolio as $item): ?>

                <?php

                $media_file =
                    __DIR__ .
                    "/uploads/portfolio/" .
                    $item["image"];

                $media_url =
                    "uploads/portfolio/" .
                    rawurlencode($item["image"]);

                $media_type =
                    $item["media_type"] ?? "image";

                ?>

                <div class="card">

                    <div class="media-container">

                        <?php if (
                            !empty($item["image"]) &&
                            file_exists($media_file)
                        ): ?>

                            <?php if ($media_type === "video"): ?>

                                <span class="media-type">
                                    🎥 Video
                                </span>

                                <video
                                    controls
                                    preload="metadata"
                                >
                                    <source
                                        src="<?= htmlspecialchars(
                                            $media_url,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >
                                    Your browser does not support video playback.
                                </video>

                            <?php else: ?>

                                <span class="media-type">
                                    📸 Photo
                                </span>

                                <img
                                    src="<?= htmlspecialchars(
                                        $media_url,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $item["title"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                            <?php endif; ?>

                        <?php else: ?>

                            <div class="no-media">
                                📁
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="content">

                        <div class="title">

                            <?= htmlspecialchars(
                                $item["title"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>

                        <?php if (!empty($item["description"])): ?>

                            <div class="description">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $item["description"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                        <div class="date">

                            Added:
                            <?= htmlspecialchars(
                                $item["created_at"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>

                        <div class="card-actions">

                            <a
                                href="edit_portfolio.php?id=<?= (int) $item["id"] ?>"
                                class="edit-btn"
                            >
                                ✏️ Edit
                            </a>

                            <a
                                href="delete_portfolio.php?id=<?= (int) $item["id"] ?>"
                                class="delete-btn"
                                onclick="return confirm('Are you sure you want to delete this portfolio media?');"
                            >
                                🗑️ Delete
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>