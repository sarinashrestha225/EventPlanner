<?php

require_once "../../database.php";

require_once "../includes/auth.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php");
    exit();

}

$booking_id = (int) $_GET["id"];

$sql = "
    SELECT
        b.id,
        b.customer_id,
        b.event_type_id,
        b.service_id,
        b.provider_id,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status,
        b.payment_status,
        b.customer_note,
        b.created_at,
        b.updated_at
    FROM bookings b
    WHERE b.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "i",
    $booking_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: index.php");
    exit();

}

$booking = $result->fetch_assoc();

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

    <title>
        View Booking | Event Planner Admin
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

        .booking-card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 5px 20px
                rgba(218, 165, 32, 0.10);

        }

        .booking-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            padding-bottom: 20px;

            margin-bottom: 25px;

            border-bottom:
                1px solid #f0d88a;

        }

        .booking-number {

            font-size: 22px;

            font-weight: bold;

            color: #704800;

        }

        .status {

            display: inline-block;

            padding: 8px 16px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }

        .pending {

            background: #ffe5a0;

            color: #8a6200;

        }

        .confirmed {

            background: #fff0a6;

            color: #705000;

        }

        .completed {

            background: #dff3c4;

            color: #4e7528;

        }

        .cancelled {

            background: #ffd2df;

            color: #a23d5d;

        }

        .section-title {

            color: #8b5e00;

            font-size: 17px;

            margin-bottom: 15px;

            padding-bottom: 8px;

            border-bottom:
                2px solid #f5d875;

        }

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 30px;

        }

        .info-box {

            background: #fff8dc;

            border: 1px solid #f0d88a;

            border-radius: 12px;

            padding: 16px;

        }

        .info-label {

            color: #96784f;

            font-size: 11px;

            font-weight: bold;

            text-transform: uppercase;

            margin-bottom: 7px;

        }

        .info-value {

            color: #5a4630;

            font-size: 15px;

            font-weight: bold;

            word-break: break-word;

        }

        .payment-box {

            background:
                linear-gradient(
                    135deg,
                    #fff8d8,
                    #ffe7ef
                );

            border: 1px solid #efd48a;

            border-radius: 15px;

            padding: 20px;

            margin-bottom: 30px;

        }

        .payment-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;

        }

        .paid {

            color: #4e7528;

        }

        .unpaid {

            color: #a23d5d;

        }

        .partial {

            color: #8a6200;

        }

        .refunded {

            color: #7040a0;

        }

        .note-box {

            background: #fff3f6;

            border: 1px solid #f0cbd6;

            border-radius: 12px;

            padding: 18px;

            margin-bottom: 30px;

        }

        .note-text {

            color: #765d65;

            line-height: 1.6;

            white-space: pre-wrap;

        }

        .buttons {

            display: flex;

            gap: 10px;

            padding-top: 20px;

            border-top:
                1px solid #f0d88a;

        }

        .btn {

            display: inline-block;

            padding: 11px 18px;

            border-radius: 9px;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;

        }

        .back-btn {

            background: #fff0a6;

            color: #705000;

        }

        .back-btn:hover {

            background: #ffd75e;

        }

        .edit-btn {

            background: #ffd2df;

            color: #a23d5d;

        }

        .edit-btn:hover {

            background: #f3abc2;

        }

        @media (max-width: 1000px) {

            .info-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }

        @media (max-width: 700px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

            .booking-top {

                flex-direction: column;

                align-items: flex-start;

            }

            .info-grid {

                grid-template-columns: 1fr;

            }

            .payment-grid {

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
            👁 View Booking
        </h1>

        <p>
            View complete booking information
        </p>

    </div>

    <div class="booking-card">

        <div class="booking-top">

            <div class="booking-number">

                📋 Booking #<?= (int)$booking["id"] ?>

            </div>

            <div>

                <span
                    class="status
                    <?= htmlspecialchars(
                        $booking["status"]
                    ) ?>"
                >

                    <?= htmlspecialchars(
                        ucfirst(
                            $booking["status"]
                        )
                    ) ?>

                </span>

            </div>

        </div>

        <h2 class="section-title">

            📌 Booking Information

        </h2>

        <div class="info-grid">

            <div class="info-box">

                <div class="info-label">

                    Customer ID

                </div>

                <div class="info-value">

                    #<?= (int)$booking["customer_id"] ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Event Type ID

                </div>

                <div class="info-value">

                    #<?= (int)$booking["event_type_id"] ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Service ID

                </div>

                <div class="info-value">

                    <?php

                    if (
                        $booking["service_id"] !== null
                        &&
                        $booking["service_id"] !== ""
                    ) {

                        echo "#"
                            . (int)$booking["service_id"];

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Provider ID

                </div>

                <div class="info-value">

                    <?php

                    if (
                        $booking["provider_id"] !== null
                        &&
                        $booking["provider_id"] !== ""
                    ) {

                        echo "#"
                            . (int)$booking["provider_id"];

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Booking Date

                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $booking["booking_date"]
                    ) ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Booking Time

                </div>

                <div class="info-value">

                    <?php

                    if (
                        !empty(
                            $booking["booking_time"]
                        )
                    ) {

                        echo htmlspecialchars(
                            $booking["booking_time"]
                        );

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Booking Amount

                </div>

                <div class="info-value">

                    Rs.
                    <?= number_format(
                        (float)$booking["amount"],
                        2
                    ) ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Created At

                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $booking["created_at"]
                    ) ?>

                </div>

            </div>

            <div class="info-box">

                <div class="info-label">

                    Last Updated

                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $booking["updated_at"]
                    ) ?>

                </div>

            </div>

        </div>

        <h2 class="section-title">

            💰 Payment Information

        </h2>

        <div class="payment-box">

            <div class="payment-grid">

                <div>

                    <div class="info-label">

                        Payment Status

                    </div>

                    <div
                        class="info-value
                        <?= htmlspecialchars(
                            $booking["payment_status"]
                        ) ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst(
                                $booking["payment_status"]
                            )
                        ) ?>

                    </div>

                </div>

                <div>

                    <div class="info-label">

                        Amount

                    </div>

                    <div class="info-value">

                        Rs.
                        <?= number_format(
                            (float)$booking["amount"],
                            2
                        ) ?>

                    </div>

                </div>

            </div>

        </div>

        <?php if (
            !empty(
                trim(
                    (string)$booking["customer_note"]
                )
            )
        ): ?>

            <h2 class="section-title">

                📝 Customer Note

            </h2>

            <div class="note-box">

                <div class="note-text">

                    <?= htmlspecialchars(
                        $booking["customer_note"]
                    ) ?>

                </div>

            </div>

        <?php endif; ?>

        <div class="buttons">

            <a
                href="index.php"
                class="btn back-btn"
            >

                ← Back to Bookings

            </a>

            <a
                href="edit.php?id=<?= (int)$booking["id"] ?>"
                class="btn edit-btn"
            >

                ✏ Edit Booking

            </a>

        </div>

    </div>

</div>

</body>

</html>