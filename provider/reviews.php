<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["provider_id"];

$total_reviews = 0;
$average_rating = 0;

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_reviews,
        AVG(rating) AS average_rating
    FROM reviews
    WHERE provider_id = ?
    AND status = 'published'
");

if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$rating_result = $stmt->get_result();
$rating_data = $rating_result->fetch_assoc();

$total_reviews = (int) ($rating_data["total_reviews"] ?? 0);
$average_rating = (float) ($rating_data["average_rating"] ?? 0);

$stmt->close();

$stmt = $conn->prepare("
    SELECT
        r.id,
        r.customer_id,
        r.service_id,
        r.booking_id,
        r.rating,
        r.comment,
        r.status,
        r.created_at,
        c.name AS customer_name
    FROM reviews r
    LEFT JOIN users c
        ON r.customer_id = c.id
    WHERE r.provider_id = ?
    AND r.status = 'published'
    ORDER BY r.id DESC
");

if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();

$reviews = [];

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Reviews</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8f2;
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
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-shadow: 0 3px 15px rgba(100, 70, 80, 0.12);
}

.header h1 {
    color: #8a5a00;
    font-size: 27px;
}

.dashboard-btn {
    text-decoration: none;
    background: #b8860b;
    color: white;
    padding: 11px 20px;
    border-radius: 10px;
    font-weight: bold;
    transition: 0.3s;
}

.dashboard-btn:hover {
    background: #946f08;
}

.container {
    width: 92%;
    max-width: 950px;
    margin: 35px auto;
}

.rating-summary {
    background: white;
    border-radius: 18px;
    padding: 30px;
    text-align: center;
    margin-bottom: 28px;
    box-shadow: 0 6px 20px rgba(100, 70, 80, 0.08);
    border: 1px solid #f1e1e6;
}

.rating-title {
    font-size: 18px;
    color: #5d4148;
    font-weight: bold;
    margin-bottom: 10px;
}

.rating-number {
    font-size: 52px;
    font-weight: bold;
    color: #b8860b;
}

.stars {
    color: #d4af37;
    font-size: 30px;
    letter-spacing: 2px;
    margin: 8px 0;
}

.review-count {
    color: #777;
    font-size: 14px;
}

.review-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 18px;
    box-shadow: 0 5px 18px rgba(100, 70, 80, 0.08);
    border: 1px solid #f1e1e6;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.customer-name {
    font-size: 18px;
    font-weight: bold;
    color: #5d4148;
}

.review-stars {
    color: #d4af37;
    font-size: 19px;
    white-space: nowrap;
}

.review-comment {
    background: #fff8f2;
    border-radius: 12px;
    padding: 16px;
    color: #666;
    line-height: 1.7;
    font-size: 15px;
}

.review-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    gap: 10px;
    color: #999;
    font-size: 13px;
}

.status {
    background: #e7f7e9;
    color: #2d7a39;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.empty {
    background: white;
    border-radius: 18px;
    padding: 65px 25px;
    text-align: center;
    box-shadow: 0 6px 20px rgba(100, 70, 80, 0.08);
    border: 1px solid #f1e1e6;
}

.empty-icon {
    font-size: 60px;
    margin-bottom: 15px;
}

.empty h2 {
    color: #5d4148;
    margin-bottom: 10px;
}

.empty p {
    color: #888;
    font-size: 14px;
}

@media (max-width: 600px) {

    .header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
    }

    .header h1 {
        font-size: 23px;
    }

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .review-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .review-info {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<div class="header">

    <h1>
        ⭐ My Reviews & Ratings
    </h1>

    <a
        href="dashboard.php"
        class="dashboard-btn"
    >
        ← Dashboard
    </a>

</div>


<div class="container">


    <div class="rating-summary">

        <div class="rating-title">
            Your Average Rating
        </div>

        <div class="rating-number">

            <?= number_format($average_rating, 1) ?>

        </div>

        <div class="stars">

            <?php

            $rounded_rating = (int) round($average_rating);

            for ($i = 1; $i <= 5; $i++) {

                if ($i <= $rounded_rating) {
                    echo "★";
                } else {
                    echo "☆";
                }

            }

            ?>

        </div>

        <div class="review-count">

            Based on
            <?= $total_reviews ?>
            review<?= $total_reviews == 1 ? "" : "s" ?>

        </div>

    </div>


    <?php if (empty($reviews)): ?>

        <div class="empty">

            <div class="empty-icon">
                ⭐
            </div>

            <h2>
                No Reviews Yet
            </h2>

            <p>
                Customer reviews will appear here after customers submit their reviews.
            </p>

        </div>

    <?php else: ?>


        <?php foreach ($reviews as $review): ?>

            <div class="review-card">

                <div class="review-header">

                    <div class="customer-name">

                        👤
                        <?= htmlspecialchars(
                            $review["customer_name"] ?? "Customer",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>


                    <div class="review-stars">

                        <?php

                        $rating = (int) $review["rating"];

                        for ($i = 1; $i <= 5; $i++) {

                            if ($i <= $rating) {
                                echo "★";
                            } else {
                                echo "☆";
                            }

                        }

                        ?>

                        <?= $rating ?>/5

                    </div>

                </div>


                <?php if (!empty($review["comment"])): ?>

                    <div class="review-comment">

                        <?= nl2br(
                            htmlspecialchars(
                                $review["comment"],
                                ENT_QUOTES,
                                "UTF-8"
                            )
                        ) ?>

                    </div>

                <?php else: ?>

                    <div class="review-comment">

                        Customer did not leave a comment.

                    </div>

                <?php endif; ?>


                <div class="review-info">

                    <span>
                        📅
                        <?= date(
                            "d M Y, h:i A",
                            strtotime($review["created_at"])
                        ) ?>
                    </span>

                    <span class="status">
                        Published
                    </span>

                </div>

            </div>

        <?php endforeach; ?>


    <?php endif; ?>


</div>

</body>

</html>