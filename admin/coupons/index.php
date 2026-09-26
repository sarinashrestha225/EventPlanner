<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$sql = "
    SELECT
        id,
        code,
        name,
        description,
        discount_type,
        discount_value,
        minimum_amount,
        maximum_discount,
        usage_limit,
        used_count,
        start_date,
        end_date,
        status,
        created_at
    FROM coupons
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$total_coupons = 0;
$active_coupons = 0;
$inactive_coupons = 0;
$total_used = 0;

$summary_sql = "
    SELECT

        COUNT(*) AS total_coupons,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'active'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS active_coupons,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'inactive'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS inactive_coupons,

        COALESCE(
            SUM(used_count),
            0
        ) AS total_used

    FROM coupons
";

$summary_result = $conn->query($summary_sql);

if ($summary_result) {

    $summary =
        $summary_result->fetch_assoc();

    $total_coupons =
        (int)$summary["total_coupons"];

    $active_coupons =
        (int)$summary["active_coupons"];

    $inactive_coupons =
        (int)$summary["inactive_coupons"];

    $total_used =
        (int)$summary["total_used"];
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
        Coupons | Event Planner Admin
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
                rgba(218, 165, 32, 0.15);

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

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

        .add-btn {

            text-decoration: none;

            background: #704800;

            color: #fffdf7;

            padding: 12px 18px;

            border-radius: 10px;

            font-size: 13px;

            font-weight: bold;

            white-space: nowrap;

        }

        .add-btn:hover {

            background: #8a6200;

        }

        .summary {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 25px;

        }

        .summary-card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 16px;

            padding: 20px;

            box-shadow:
                0 5px 20px
                rgba(218, 165, 32, 0.08);

        }

        .summary-title {

            color: #927750;

            font-size: 12px;

            font-weight: bold;

            margin-bottom: 8px;

        }

        .summary-value {

            color: #704800;

            font-size: 22px;

            font-weight: bold;

        }

        .card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 5px 20px
                rgba(218, 165, 32, 0.10);

        }

        .table-container {

            width: 100%;

            overflow-x: auto;

        }

        table {

            width: 100%;

            min-width: 1250px;

            border-collapse: collapse;

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

            white-space: nowrap;

        }

        tbody tr:nth-child(even) {

            background: #fff3f6;

        }

        tbody tr:hover {

            background: #fff0b3;

        }

        .coupon-code {

            display: inline-block;

            background: #fff0a6;

            color: #704800;

            border: 1px dashed #d8ad2f;

            padding: 7px 10px;

            border-radius: 8px;

            font-weight: bold;

            font-size: 12px;

        }

        .discount {

            background: #f8c1d4;

            color: #8a3f5d;

            padding: 6px 10px;

            border-radius: 20px;

            font-weight: bold;

            font-size: 11px;

        }

        .status {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

        }

        .active {

            background: #dff3c4;

            color: #4e7528;

        }

        .inactive {

            background: #ffd2df;

            color: #a23d5d;

        }

        .action {

            display: inline-block;

            text-decoration: none;

            padding: 7px 10px;

            border-radius: 7px;

            font-size: 11px;

            font-weight: bold;

            margin-right: 4px;

        }

        .edit {

            background: #fff0a6;

            color: #705000;

        }

        .delete {

            background: #ffd2df;

            color: #a23d5d;

        }

        .action:hover {

            opacity: 0.8;

        }

        .empty {

            text-align: center;

            padding: 50px;

            color: #8c7858;

        }

        @media (max-width: 1100px) {

            .summary {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }

        @media (max-width: 700px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

            .page-header {

                flex-direction: column;

                align-items: flex-start;

            }

            .summary {

                grid-template-columns: 1fr;

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

        <div>

            <h1>
                🎟️ Coupons
            </h1>

            <p>
                Manage discount coupons for customers
            </p>

        </div>

        <a
            href="add.php"
            class="add-btn"
        >

            ➕ Add Coupon

        </a>

    </div>

    <div class="summary">

        <div class="summary-card">

            <div class="summary-title">

                Total Coupons

            </div>

            <div class="summary-value">

                <?= $total_coupons ?>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-title">

                Active Coupons

            </div>

            <div class="summary-value">

                <?= $active_coupons ?>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-title">

                Inactive Coupons

            </div>

            <div class="summary-value">

                <?= $inactive_coupons ?>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-title">

                Total Used

            </div>

            <div class="summary-value">

                <?= $total_used ?>

            </div>

        </div>

    </div>

    <div class="card">

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Code
                        </th>

                        <th>
                            Name
                        </th>

                        <th>
                            Discount
                        </th>

                        <th>
                            Minimum
                        </th>

                        <th>
                            Maximum
                        </th>

                        <th>
                            Usage
                        </th>

                        <th>
                            Valid From
                        </th>

                        <th>
                            Valid Until
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while (
                        $coupon =
                        $result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                #<?= (int)$coupon["id"] ?>

                            </td>

                            <td>

                                <span class="coupon-code">

                                    <?= htmlspecialchars(
                                        $coupon["code"]
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $coupon["name"]
                                ) ?>

                            </td>

                            <td>

                                <span class="discount">

                                    <?php

                                    if (
                                        $coupon[
                                            "discount_type"
                                        ]
                                        === "percentage"
                                    ) {

                                        echo number_format(
                                            (float)
                                            $coupon[
                                                "discount_value"
                                            ],
                                            2
                                        ) . "%";

                                    } else {

                                        echo "Rs. "
                                            . number_format(
                                                (float)
                                                $coupon[
                                                    "discount_value"
                                                ],
                                                2
                                            );

                                    }

                                    ?>

                                </span>

                            </td>

                            <td>

                                Rs.
                                <?= number_format(
                                    (float)
                                    $coupon[
                                        "minimum_amount"
                                    ],
                                    2
                                ) ?>

                            </td>

                            <td>

                                <?php

                                if (
                                    $coupon[
                                        "maximum_discount"
                                    ] !== null
                                ) {

                                    echo "Rs. "
                                        . number_format(
                                            (float)
                                            $coupon[
                                                "maximum_discount"
                                            ],
                                            2
                                        );

                                } else {

                                    echo "No Limit";

                                }

                                ?>

                            </td>

                            <td>

                                <?= (int)
                                    $coupon["used_count"] ?>

                                /

                                <?php

                                if (
                                    $coupon[
                                        "usage_limit"
                                    ] !== null
                                ) {

                                    echo (int)
                                        $coupon[
                                            "usage_limit"
                                        ];

                                } else {

                                    echo "∞";

                                }

                                ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $coupon["start_date"]
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $coupon["end_date"]
                                ) ?>

                            </td>

                            <td>

                                <span
                                    class="status
                                    <?= htmlspecialchars(
                                        $coupon["status"]
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $coupon["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <a
                                    href="edit.php?id=<?= (int)$coupon["id"] ?>"
                                    class="action edit"
                                >

                                    ✏ Edit

                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$coupon["id"] ?>"
                                    class="action delete"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this coupon?'
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
                            colspan="11"
                            class="empty"
                        >

                            🎟️

                            <br><br>

                            No coupons found.

                            <br><br>

                            Click
                            <strong>
                                Add Coupon
                            </strong>
                            to create your first coupon.

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