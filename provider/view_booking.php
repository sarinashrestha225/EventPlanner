<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["provider_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];

$booking_id = 0;

if (isset($_GET["id"])) {
    $booking_id = (int) $_GET["id"];
}

if ($booking_id <= 0 && isset($_GET["booking_id"])) {
    $booking_id = (int) $_GET["booking_id"];
}

if ($booking_id <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking ID Missing</title>

        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #fff8f0;
                color: #4b3621;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .box {
                width: 90%;
                max-width: 500px;
                background: white;
                padding: 35px;
                border-radius: 15px;
                text-align: center;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #6b4f2a;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
            }
        </style>
    </head>

    <body>

        <div class="box">
            <h2>❌ Booking ID Missing</h2>

            <p>
                Please open the booking from My Bookings.
            </p>

            <p>
                Current URL does not contain a valid booking ID.
            </p>

            <a href="bookings.php">
                ← Back to Bookings
            </a>
        </div>

    </body>
    </html>
    <?php
    exit;
}

$sql = "
    SELECT
        b.id,
        b.customer_id,
        b.provider_id,
        b.service_id,
        b.package_id,
        b.event_type_id,
        b.guests,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status,
        b.payment_status,
        b.customer_note,

        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,

        s.service_name,

        p.package_name AS package_name,

        e.event_name

    FROM bookings b

    LEFT JOIN users u
        ON b.customer_id = u.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN packages p
        ON b.package_id = p.id

    LEFT JOIN event_types e
        ON b.event_type_id = e.id

    WHERE
        b.id = ?
        AND b.provider_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "SQL Error: " .
        htmlspecialchars(
            $conn->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$stmt->bind_param(
    "ii",
    $booking_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Booking Not Found</title>

        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #fff8f0;
                color: #4b3621;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .box {
                width: 90%;
                max-width: 500px;
                background: white;
                padding: 35px;
                border-radius: 15px;
                text-align: center;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #6b4f2a;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
            }
        </style>
    </head>

    <body>

        <div class="box">

            <h2>❌ Booking Not Found</h2>

            <p>
                Booking #<?= $booking_id ?>
                does not belong to your provider account.
            </p>

            <a href="bookings.php">
                ← Back to Bookings
            </a>

        </div>

    </body>
    </html>
    <?php

    $stmt->close();

    exit;
}

$booking = $result->fetch_assoc();

$stmt->close();

$status = strtolower(
    trim(
        $booking["status"] ?? "pending"
    )
);

$payment_status = strtolower(
    trim(
        $booking["payment_status"] ?? "unpaid"
    )
);

$customer_name =
    $booking["customer_name"]
    ?? "Not provided";

$customer_email =
    $booking["customer_email"]
    ?? "Not provided";

$customer_phone =
    trim(
        $booking["customer_phone"] ?? ""
    );

$service_name =
    trim(
        $booking["service_name"] ?? ""
    );

$package_name =
    trim(
        $booking["package_name"] ?? ""
    );

$event_name =
    $booking["event_name"]
    ?? "Not specified";

$booking_date =
    $booking["booking_date"]
    ?? "Not specified";

$booking_time =
    $booking["booking_time"]
    ?? "Not specified";

$amount =
    (float) (
        $booking["amount"]
        ?? 0
    );

$guests =
    isset($booking["guests"])
        ? (int) $booking["guests"]
        : 0;

$is_package_booking =
    !empty($booking["package_id"]);

$is_service_booking =
    !empty($booking["service_id"]);

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
    Booking #<?= (int) $booking_id ?> - Event Planner
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f0;
    color: #4b3621;
}

.container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 25px;
    margin-bottom: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

h1 {
    margin: 0;
    color: #4b3621;
}

h2 {
    color: #b8860b;
    margin-top: 0;
}

.info {
    margin-bottom: 15px;
    padding: 14px;
    background: #fffaf5;
    border-radius: 10px;
}

.info:last-child {
    margin-bottom: 0;
}

.label {
    display: block;
    color: #888;
    font-size: 13px;
    margin-bottom: 5px;
}

.value {
    font-weight: bold;
    font-size: 16px;
}

.status {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.confirmed,
.accepted {
    background: #d4edda;
    color: #155724;
}

.completed {
    background: #cfe2ff;
    color: #084298;
}

.rejected,
.cancelled {
    background: #f8d7da;
    color: #721c24;
}

.payment {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 15px;
    font-size: 14px;
    font-weight: bold;
}

.payment-paid {
    background: #d4edda;
    color: #155724;
}

.payment-pending {
    background: #fff3cd;
    color: #856404;
}

.payment-unpaid {
    background: #f8d7da;
    color: #721c24;
}

.payment-partial {
    background: #cfe2ff;
    color: #084298;
}

.payment-refunded {
    background: #e2e3e5;
    color: #41464b;
}

.contact-box {
    margin-top: 20px;
    padding: 20px;
    background: #fffaf0;
    border: 1px solid #ead7a1;
    border-radius: 12px;
}

.contact-title {
    font-size: 18px;
    font-weight: bold;
    color: #6b4f2a;
    margin-bottom: 10px;
}

.phone-number {
    font-size: 20px;
    font-weight: bold;
    color: #4b3621;
    margin-bottom: 15px;
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.btn {
    display: inline-block;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    font-weight: bold;
    border: none;
    cursor: pointer;
}

.back {
    background: #6b4f2a;
}

.back:hover {
    background: #4b3621;
}

.accept {
    background: #28a745;
}

.accept:hover {
    background: #218838;
}

.reject {
    background: #dc3545;
}

.reject:hover {
    background: #b02a37;
}

.message {
    background: #3498db;
}

.message:hover {
    background: #217dbb;
}

.call {
    background: #28a745;
}

.call:hover {
    background: #218838;
}

.emergency {
    background: #dc3545;
}

.emergency:hover {
    background: #b02a37;
}

.amount {
    font-size: 22px;
    color: #b8860b;
    font-weight: bold;
}

.package-box {
    background: #fffaf0;
    border: 1px solid #ead7a1;
}

.package-name {
    font-size: 20px;
    font-weight: bold;
    color: #6b4f2a;
}

.guests {
    font-size: 18px;
    font-weight: bold;
    color: #4b3621;
}

.note {
    white-space: pre-wrap;
    line-height: 1.6;
}

.emergency-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 15px;
}

.emergency-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 14px;
    background: #fff8f8;
    border: 1px solid #f0d5d5;
    border-radius: 10px;
}

.emergency-name {
    font-weight: bold;
}

.emergency-number {
    color: #777;
    font-size: 14px;
    margin-top: 3px;
}

.emergency-call {
    background: #dc3545;
    color: white;
    padding: 8px 13px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.emergency-call:hover {
    background: #b02a37;
}

.no-phone {
    color: #888;
    font-size: 14px;
}

@media(max-width: 700px) {

    .container {
        margin: 20px auto;
        padding: 15px;
    }

    .card {
        padding: 20px;
    }

    .emergency-list {
        grid-template-columns: 1fr;
    }

    .emergency-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .emergency-call {
        width: 100%;
        text-align: center;
    }

    .btn {
        width: 100%;
        text-align: center;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="header">

            <h1>
                📋 Booking #<?= (int) $booking_id ?>
            </h1>

            <span
                class="status <?= htmlspecialchars(
                    $status,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >
                <?= htmlspecialchars(
                    ucfirst($status),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </span>

        </div>

    </div>


    <div class="card">

        <h2>
            👤 Customer Information
        </h2>

        <div class="info">

            <span class="label">
                Name
            </span>

            <div class="value">
                <?= htmlspecialchars(
                    $customer_name,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        </div>

        <div class="info">

            <span class="label">
                Email
            </span>

            <div class="value">
                <?= htmlspecialchars(
                    $customer_email,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        </div>

        <div class="contact-box">

            <div class="contact-title">
                📞 Customer Phone
            </div>

            <?php if ($customer_phone !== ""): ?>

                <div class="phone-number">
                    <?= htmlspecialchars(
                        $customer_phone,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

                <a
                    href="tel:<?= htmlspecialchars(
                        $customer_phone,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    class="btn call"
                >
                    📞 Call Customer
                </a>

            <?php else: ?>

                <div class="no-phone">
                    Customer phone number is not available.
                </div>

            <?php endif; ?>

        </div>

    </div>


    <div class="card">

        <h2>
            🎉 Event Information
        </h2>

        <div class="info">

            <span class="label">
                Event
            </span>

            <div class="value">
                <?= htmlspecialchars(
                    $event_name,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        </div>


        <?php if ($is_package_booking): ?>

            <div class="info package-box">

                <span class="label">
                    Package
                </span>

                <div class="package-name">

                    <?= htmlspecialchars(
                        $package_name !== ""
                            ? $package_name
                            : "Package",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            </div>


            <div class="info">

                <span class="label">
                    Guests
                </span>

                <div class="guests">

                    <?php if ($guests > 0): ?>

                        <?= number_format($guests) ?>
                        Guests

                    <?php else: ?>

                        Guest number not recorded.

                    <?php endif; ?>

                </div>

            </div>

        <?php elseif ($is_service_booking): ?>

            <div class="info">

                <span class="label">
                    Service
                </span>

                <div class="value">

                    <?= htmlspecialchars(
                        $service_name !== ""
                            ? $service_name
                            : "Not specified",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            </div>

        <?php else: ?>

            <div class="info">

                <span class="label">
                    Booking Type
                </span>

                <div class="value">
                    General Booking
                </div>

            </div>

        <?php endif; ?>


        <div class="info">

            <span class="label">
                Booking Date
            </span>

            <div class="value">

                <?= htmlspecialchars(
                    $booking_date,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        </div>


        <div class="info">

            <span class="label">
                Booking Time
            </span>

            <div class="value">

                <?= htmlspecialchars(
                    $booking_time,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        </div>


        <div class="info">

            <span class="label">
                Amount
            </span>

            <div class="amount">

                Rs.
                <?= number_format(
                    $amount,
                    2
                ) ?>

            </div>

        </div>


        <div class="info">

            <span class="label">
                Payment Status
            </span>

            <div>

                <span
                    class="payment payment-<?= htmlspecialchars(
                        $payment_status,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                    <?= htmlspecialchars(
                        ucfirst($payment_status),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </span>

            </div>

        </div>

    </div>


    <?php if (trim($booking["customer_note"] ?? "") !== ""): ?>

        <div class="card">

            <h2>
                📝 Customer Note
            </h2>

            <div class="info note">

                <?= htmlspecialchars(
                    $booking["customer_note"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <div class="card">

        <h2>
            📌 Booking Action
        </h2>

        <?php if ($status === "pending"): ?>

            <p>
                This booking is waiting for your response.
            </p>

            <div class="actions">

                <a
                    href="update_booking.php?id=<?= (int) $booking_id ?>&action=accept"
                    class="btn accept"
                >
                    ✅ Accept Booking
                </a>

                <a
                    href="update_booking.php?id=<?= (int) $booking_id ?>&action=reject"
                    class="btn reject"
                >
                    ❌ Reject Booking
                </a>

            </div>

        <?php elseif (
            $status === "confirmed" ||
            $status === "accepted"
        ): ?>

            <p>
                ✅ This booking has already been accepted.
            </p>

        <?php elseif ($status === "rejected"): ?>

            <p>
                ❌ This booking has already been rejected.
            </p>

        <?php elseif ($status === "cancelled"): ?>

            <p>
                ❌ This booking has been cancelled.
            </p>

        <?php elseif ($status === "completed"): ?>

            <p>
                ✅ This booking has been completed.
            </p>

        <?php else: ?>

            <p>

                Current status:

                <strong>
                    <?= htmlspecialchars(
                        ucfirst($status),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </strong>

            </p>

        <?php endif; ?>

    </div>


    <div class="card">

        <h2>
            💬 Customer Communication
        </h2>

        <div class="actions">

            <?php if (!empty($booking["customer_id"])): ?>

                <a
                    href="messages.php?booking_id=<?= (int) $booking_id ?>"
                    class="btn message"
                >
                    💬 Message Customer
                </a>

            <?php endif; ?>


            <?php if ($customer_phone !== ""): ?>

                <a
                    href="tel:<?= htmlspecialchars(
                        $customer_phone,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    class="btn call"
                >
                    📞 Call Customer
                </a>

            <?php endif; ?>

        </div>

    </div>


    <div class="card">

        <h2>
            🚨 Emergency Contacts
        </h2>

        <p>
            Use these numbers for emergency situations.
        </p>

        <div class="emergency-list">


            <div class="emergency-item">

                <div>
                    <div class="emergency-name">
                        🚔 Nepal Police
                    </div>

                    <div class="emergency-number">
                        100
                    </div>
                </div>

                <a
                    href="tel:100"
                    class="emergency-call"
                >
                    📞 Call
                </a>

            </div>


            <div class="emergency-item">

                <div>
                    <div class="emergency-name">
                        🔥 Fire Brigade
                    </div>

                    <div class="emergency-number">
                        101
                    </div>
                </div>

                <a
                    href="tel:101"
                    class="emergency-call"
                >
                    📞 Call
                </a>

            </div>


            <div class="emergency-item">

                <div>
                    <div class="emergency-name">
                        🚑 Ambulance
                    </div>

                    <div class="emergency-number">
                        102
                    </div>
                </div>

                <a
                    href="tel:102"
                    class="emergency-call"
                >
                    📞 Call
                </a>

            </div>


            <div class="emergency-item">

                <div>
                    <div class="emergency-name">
                        🚦 Traffic Police
                    </div>

                    <div class="emergency-number">
                        103
                    </div>
                </div>

                <a
                    href="tel:103"
                    class="emergency-call"
                >
                    📞 Call
                </a>

            </div>


            <div class="emergency-item">

                <div>
                    <div class="emergency-name">
                        👶 Child Helpline
                    </div>

                    <div class="emergency-number">
                        1098
                    </div>
                </div>

                <a
                    href="tel:1098"
                    class="emergency-call"
                >
                    📞 Call
                </a>

            </div>


            <div class="emergency-item">

                <div>
                    <div class="emergency-name">
                        👩 Women Helpline
                    </div>

                    <div class="emergency-number">
                        1145
                    </div>
                </div>

                <a
                    href="tel:1145"
                    class="emergency-call"
                >
                    📞 Call
                </a>

            </div>


        </div>

    </div>


    <div class="actions">

        <a
            href="bookings.php"
            class="btn back"
        >
            ← Back to Bookings
        </a>

    </div>

</div>

</body>

</html>

