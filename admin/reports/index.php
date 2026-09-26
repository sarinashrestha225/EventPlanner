<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$total_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $total_bookings =
        (int)$row["total"];

}

$pending_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'pending'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $pending_bookings =
        (int)$row["total"];

}

$confirmed_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'confirmed'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $confirmed_bookings =
        (int)$row["total"];

}

$completed_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'completed'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $completed_bookings =
        (int)$row["total"];

}

$cancelled_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'cancelled'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $cancelled_bookings =
        (int)$row["total"];

}

$total_revenue = 0;

$sql = "
    SELECT
        COALESCE(
            SUM(amount),
            0
        ) AS total
    FROM bookings
    WHERE status != 'cancelled'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $total_revenue =
        (float)$row["total"];

}

$paid_amount = 0;

$sql = "
    SELECT
        COALESCE(
            SUM(amount),
            0
        ) AS total
    FROM bookings
    WHERE payment_status = 'paid'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $paid_amount =
        (float)$row["total"];

}

$unpaid_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE payment_status = 'unpaid'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $unpaid_bookings =
        (int)$row["total"];

}

$partial_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE payment_status = 'partial'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $partial_bookings =
        (int)$row["total"];

}

$refunded_bookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE payment_status = 'refunded'
";

$result = $conn->query($sql);

if ($result) {

    $row = $result->fetch_assoc();

    $refunded_bookings =
        (int)$row["total"];

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
        Reports | Event Planner Admin
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

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 30px;

        }

        .stat-card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 18px;

            padding: 22px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );

        }

        .stat-title {

            color: #927750;

            font-size: 13px;

            margin-bottom: 10px;

        }

        .stat-value {

            color: #704800;

            font-size: 28px;

            font-weight: bold;

        }

        .stat-icon {

            font-size: 28px;

            margin-bottom: 10px;

        }

        .section {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );

        }

        .section h2 {

            color: #704800;

            font-size: 20px;

            margin-bottom: 20px;

        }

        .report-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

        }

        .report-box {

            padding: 20px;

            border-radius: 14px;

            text-align: center;

            background: #fffaf0;

            border: 1px solid #f0d88a;

        }

        .report-box h3 {

            font-size: 13px;

            color: #765d40;

            margin-bottom: 10px;

        }

        .report-number {

            font-size: 24px;

            font-weight: bold;

            color: #704800;

        }

        .revenue-box {

            background:
                linear-gradient(
                    135deg,
                    #fff2a8,
                    #f8c1d4
                );

            border-radius: 15px;

            padding: 25px;

            margin-bottom: 15px;

        }

        .revenue-box h3 {

            color: #704800;

            font-size: 14px;

            margin-bottom: 8px;

        }

        .revenue {

            font-size: 30px;

            font-weight: bold;

            color: #674300;

        }

        @media (max-width: 1100px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

            .report-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }

        @media (max-width: 900px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

        }

        @media (max-width: 600px) {

            .stats-grid {

                grid-template-columns: 1fr;

            }

            .report-grid {

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

        <h1>
            📊 Reports
        </h1>

        <p>
            View booking, payment and revenue reports
        </p>

    </div>

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-icon">
                📋
            </div>

            <div class="stat-title">
                Total Bookings
            </div>

            <div class="stat-value">

                <?= $total_bookings ?>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                ✅
            </div>

            <div class="stat-title">
                Completed Bookings
            </div>

            <div class="stat-value">

                <?= $completed_bookings ?>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-title">
                Total Revenue
            </div>

            <div class="stat-value">

                Rs.
                <?= number_format(
                    $total_revenue,
                    2
                ) ?>

            </div>

        </div>

    </div>

    <div class="section">

        <h2>
            📋 Booking Report
        </h2>

        <div class="report-grid">

            <div class="report-box">

                <h3>
                    Pending
                </h3>

                <div class="report-number">

                    <?= $pending_bookings ?>

                </div>

            </div>

            <div class="report-box">

                <h3>
                    Confirmed
                </h3>

                <div class="report-number">

                    <?= $confirmed_bookings ?>

                </div>

            </div>

            <div class="report-box">

                <h3>
                    Completed
                </h3>

                <div class="report-number">

                    <?= $completed_bookings ?>

                </div>

            </div>

            <div class="report-box">

                <h3>
                    Cancelled
                </h3>

                <div class="report-number">

                    <?= $cancelled_bookings ?>

                </div>

            </div>

        </div>

    </div>

    <div class="section">

        <h2>
            💳 Payment Report
        </h2>

        <div class="report-grid">

            <div class="report-box">

                <h3>
                    Paid Amount
                </h3>

                <div class="report-number">

                    Rs.
                    <?= number_format(
                        $paid_amount,
                        2
                    ) ?>

                </div>

            </div>

            <div class="report-box">

                <h3>
                    Unpaid
                </h3>

                <div class="report-number">

                    <?= $unpaid_bookings ?>

                </div>

            </div>

            <div class="report-box">

                <h3>
                    Partial
                </h3>

                <div class="report-number">

                    <?= $partial_bookings ?>

                </div>

            </div>

            <div class="report-box">

                <h3>
                    Refunded
                </h3>

                <div class="report-number">

                    <?= $refunded_bookings ?>

                </div>

            </div>

        </div>

    </div>

    <div class="section">

        <h2>
            💰 Revenue Report
        </h2>

        <div class="revenue-box">

            <h3>
                Total Booking Revenue
            </h3>

            <div class="revenue">

                Rs.
                <?= number_format(
                    $total_revenue,
                    2
                ) ?>

            </div>

        </div>

        <div class="revenue-box">

            <h3>
                Paid Revenue
            </h3>

            <div class="revenue">

                Rs.
                <?= number_format(
                    $paid_amount,
                    2
                ) ?>

            </div>

        </div>

    </div>

</div>

</body>

</html>