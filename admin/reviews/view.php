<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($id <= 0) {

    header("Location: index.php?error=invalid_id");

    exit();

}

$sql = "
    SELECT
        id,
        customer_id,
        provider_id,
        service_id,
        booking_id,
        rating,
        comment,
        status,
        created_at,
        updated_at
    FROM reviews
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php?error=not_found");

    exit();

}

$review = $result->fetch_assoc();

$stmt->close();

$rating =
    (int)$review["rating"];

$status =
    $review["status"];

$comment =
    trim($review["comment"] ?? "");

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        View Review | Event Planner Admin
    </title>

    <style>

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }

        body {

            background: #fffaf0;

            color: #5a4630;

        }

        .main-content {

            margin-left: 250px;

            padding: 30px;

            min-height: 100vh;

        }

        .page-header {

            background:
                linear-gradient(
                    135deg,
                    #fff2a8,
                    #ffd75e,
                    #f8c1d4
                );

            padding: 25px 30px;

            border-radius: 20px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.15
                );

        }

        .page-header h1 {

            color: #704800;

            font-size: 30px;

            margin-bottom: 7px;

        }

        .page-header p {

            color: #765d40;

            font-size: 14px;

        }

        .card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );

            max-width: 1000px;

        }

        .top-info {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

            padding-bottom: 20px;

            border-bottom:
                1px solid #f1dfb0;

        }

        .review-id {

            color: #704800;

            font-size: 18px;

            font-weight: bold;

        }

        .status {

            display: inline-block;

            padding: 7px 14px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }

        .published {

            background: #dff3c4;

            color: #4e7528;

        }

        .hidden {

            background: #ffd2df;

            color: #a23d5d;

        }

        .details-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }

        .detail-box {

            background: #fffaf0;

            border: 1px solid #f0d88a;

            border-radius: 12px;

            padding: 17px;

        }

        .detail-box.full {

            grid-column: 1 / -1;

        }

        .detail-label {

            color: #927750;

            font-size: 11px;

            font-weight: bold;

            text-transform: uppercase;

            margin-bottom: 7px;

        }

        .detail-value {

            color: #5a4630;

            font-size: 14px;

            font-weight: bold;

        }

        .rating {

            color: #d49b00;

            font-size: 25px;

            letter-spacing: 2px;

        }

        .rating-number {

            color: #704800;

            font-size: 13px;

            margin-left: 8px;

            letter-spacing: 0;

        }

        .comment-box {

            background: #fff3f6;

            border: 1px solid #f0c7d4;

            border-radius: 14px;

            padding: 20px;

            min-height: 120px;

            color: #6d5840;

            font-size: 14px;

            line-height: 1.7;

            white-space: pre-wrap;

        }

        .no-comment {

            color: #a18d70;

            font-style: italic;

        }

        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 25px;

        }

        .btn {

            display: inline-block;

            text-decoration: none;

            padding: 12px 20px;

            border-radius: 10px;

            font-size: 13px;

            font-weight: bold;

        }

        .back {

            background: #f6d568;

            color: #674300;

        }

        .back:hover {

            background: #eac84f;

        }

        .delete {

            background: #f4caca;

            color: #9b2929;

        }

        .delete:hover {

            background: #edb8b8;

        }

        @media (max-width: 800px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

            .details-grid {

                grid-template-columns: 1fr;

            }

            .detail-box.full {

                grid-column: auto;

            }

            .top-info {

                align-items: flex-start;

                gap: 15px;

                flex-direction: column;

            }

        }

    </style>

</head>

<body>

<?php

include "../includes/sidebar.php";

?>

<div class="main-content">

    <div class="page-header">

        <h1>
            ⭐ Review Details
        </h1>

        <p>
            View complete customer review information
        </p>

    </div>

    <div class="card">

        <div class="top-info">

            <div class="review-id">

                Review #

                <?= (int)$review["id"] ?>

            </div>

            <span
                class="status
                <?= htmlspecialchars($status) ?>"
            >

                <?= htmlspecialchars(
                    ucfirst($status)
                ) ?>

            </span>

        </div>

        <div class="details-grid">

            <div class="detail-box">

                <div class="detail-label">

                    Customer ID

                </div>

                <div class="detail-value">

                    Customer #

                    <?= (int)$review["customer_id"] ?>

                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">

                    Provider ID

                </div>

                <div class="detail-value">

                    <?php

                    if (
                        $review["provider_id"]
                        !== null
                    ) {

                        echo "Provider #" .
                            (int)$review["provider_id"];

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">

                    Service ID

                </div>

                <div class="detail-value">

                    <?php

                    if (
                        $review["service_id"]
                        !== null
                    ) {

                        echo "Service #" .
                            (int)$review["service_id"];

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">

                    Booking ID

                </div>

                <div class="detail-value">

                    <?php

                    if (
                        $review["booking_id"]
                        !== null
                    ) {

                        echo "#" .
                            (int)$review["booking_id"];

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="detail-box full">

                <div class="detail-label">

                    Rating

                </div>

                <div class="rating">

                    <?php

                    for (
                        $i = 1;
                        $i <= 5;
                        $i++
                    ) {

                        if (
                            $i <= $rating
                        ) {

                            echo "★";

                        } else {

                            echo "☆";

                        }

                    }

                    ?>

                    <span
                        class="rating-number"
                    >

                        <?= $rating ?> / 5

                    </span>

                </div>

            </div>

            <div class="detail-box full">

                <div class="detail-label">

                    Customer Review

                </div>

                <div class="comment-box">

                    <?php if ($comment !== ""): ?>

                        <?= htmlspecialchars(
                            $comment
                        ) ?>

                    <?php else: ?>

                        <span class="no-comment">

                            Customer did not write a comment.

                        </span>

                    <?php endif; ?>

                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">

                    Created At

                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $review["created_at"]
                    ) ?>

                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">

                    Last Updated

                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $review["updated_at"]
                    ) ?>

                </div>

            </div>

        </div>

        <div class="buttons">

            <a
                href="index.php"
                class="btn back"
            >

                ← Back to Reviews

            </a>

            <a
                href="delete.php?id=<?= (int)$review["id"] ?>"
                class="btn delete"
                onclick="
                    return confirm(
                        'Are you sure you want to delete this review?'
                    );
                "
            >

                🗑 Delete Review

            </a>

        </div>

    </div>

</div>

</body>

</html>