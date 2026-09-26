<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$sql = "
    SELECT
        r.id,
        r.customer_id,
        r.provider_id,
        r.service_id,
        r.booking_id,
        r.rating,
        r.comment,
        r.status,
        r.created_at

    FROM reviews r

    ORDER BY r.id DESC
";

$result = $conn->query($sql);

if (!$result) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

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
        Reviews | Event Planner Admin
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

        .success {

            background: #e5f4d2;

            border: 1px solid #bddb94;

            color: #4e7528;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

        }

        .error {

            background: #ffe0e8;

            border: 1px solid #e5a5b7;

            color: #9a3655;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

        }

        .card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );

        }

        .table-container {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;

        }

        thead {

            background: #f6d568;

        }

        th {

            padding: 14px;

            text-align: left;

            color: #674300;

            font-size: 13px;

            white-space: nowrap;

        }

        td {

            padding: 13px;

            border-bottom:
                1px solid #f1dfb0;

            font-size: 13px;

            vertical-align: middle;

        }

        tbody tr:nth-child(even) {

            background: #fff3f6;

        }

        tbody tr:hover {

            background: #fff0b3;

        }

        .id {

            font-weight: bold;

            color: #8a6200;

        }

        .rating {

            color: #d49b00;

            font-size: 16px;

            white-space: nowrap;

        }

        .rating-number {

            color: #704800;

            font-size: 12px;

            font-weight: bold;

            margin-left: 5px;

        }

        .comment {

            max-width: 280px;

            line-height: 1.5;

            color: #6d5840;

        }

        .no-comment {

            color: #a18d70;

            font-style: italic;

        }

        .status {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 11px;

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

        .action {

            display: inline-block;

            text-decoration: none;

            padding: 7px 11px;

            border-radius: 7px;

            font-size: 11px;

            font-weight: bold;

            margin-right: 4px;

            margin-bottom: 4px;

        }

        .view {

            background: #fff0a6;

            color: #705000;

        }

        .delete {

            background: #f4caca;

            color: #9b2929;

        }

        .action:hover {

            opacity: 0.85;

        }

        .empty {

            text-align: center;

            padding: 50px;

            color: #8c7858;

            font-size: 14px;

        }

        @media (max-width: 900px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

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
            ⭐ Reviews
        </h1>

        <p>
            Manage customer reviews and ratings
        </p>

    </div>

    <?php if (isset($_GET["deleted"])): ?>

        <div class="success">

            ✅ Review deleted successfully.

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="error">

            ⚠️

            <?php

            if (
                $_GET["error"] === "invalid_id"
            ) {

                echo "Invalid review ID.";

            }

            elseif (
                $_GET["error"] === "not_found"
            ) {

                echo "Review not found.";

            }

            else {

                echo "Something went wrong.";

            }

            ?>

        </div>

    <?php endif; ?>

    <div class="card">

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Provider
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Booking
                        </th>

                        <th>
                            Rating
                        </th>

                        <th>
                            Review
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while (
                        $review =
                        $result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                <span class="id">

                                    #
                                    <?= (int)$review["id"] ?>

                                </span>

                            </td>

                            <td>

                                Customer #

                                <?= (int)$review["customer_id"] ?>

                            </td>

                            <td>

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

                            </td>

                            <td>

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

                            </td>

                            <td>

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

                            </td>

                            <td>

                                <div class="rating">

                                    <?php

                                    $rating =
                                        (int)$review["rating"];

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

                                        <?= $rating ?>/5

                                    </span>

                                </div>

                            </td>

                            <td>

                                <?php

                                $comment =
                                    trim(
                                        $review["comment"]
                                        ?? ""
                                    );

                                ?>

                                <?php if ($comment !== ""): ?>

                                    <div class="comment">

                                        <?php

                                        $short_comment =
                                            mb_strlen(
                                                $comment
                                            ) > 80

                                            ? mb_substr(
                                                $comment,
                                                0,
                                                80
                                            ) . "..."

                                            : $comment;

                                        echo htmlspecialchars(
                                            $short_comment
                                        );

                                        ?>

                                    </div>

                                <?php else: ?>

                                    <span class="no-comment">

                                        No comment

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span
                                    class="status
                                    <?= htmlspecialchars(
                                        $review["status"]
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $review["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $review["created_at"]
                                ) ?>

                            </td>

                            <td>

                                <a
                                    href="view.php?id=<?= (int)$review["id"] ?>"
                                    class="action view"
                                >

                                    👁 View

                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$review["id"] ?>"
                                    class="action delete"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this review?'
                                        );
                                    "
                                >

                                    🗑 Delete

                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="10"
                            class="empty"
                        >

                            ⭐ No reviews found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>