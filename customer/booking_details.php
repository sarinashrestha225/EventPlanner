<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;

}

$customer_id = (int) $_SESSION["user_id"];

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header("Location: my_bookings.php");
    exit;

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
        b.updated_at,

        s.service_name,
        s.description,
        s.unit,
        s.min_price,
        s.max_price,

        e.event_name

    FROM bookings b

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN events e
        ON b.event_type_id = e.id

    WHERE b.id = ?
    AND b.customer_id = ?

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
    "ii",
    $booking_id,
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    die("Booking not found.");

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
    Booking Details - Event Planner
</title>

<style>

* {

    box-sizing: border-box;

}

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #fff8f2;

    color: #555;

}

.header {

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f3d36a
        );

    padding: 20px 40px;

    color: white;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

.header h2 {

    margin: 0;

}

.back-btn {

    text-decoration: none;

    background: #b8860b;

    color: white;

    padding: 10px 18px;

    border-radius: 8px;

    font-weight: bold;

}

.back-btn:hover {

    background: #8f6908;

}

.container {

    width: 92%;

    max-width: 1000px;

    margin: 40px auto;

}

.title {

    text-align: center;

    margin-bottom: 30px;

}

.title h1 {

    color: #b8860b;

    margin-bottom: 8px;

}

.title p {

    color: #777;

}

.card {

    background: white;

    border-radius: 18px;

    padding: 30px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,0.08);

}

.service-card {

    background: #fff8e1;

    border-left: 5px solid #d4af37;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 25px;

}

.service-card h2 {

    margin-top: 0;

    margin-bottom: 10px;

    color: #444;

}

.event {

    color: #b8860b;

    font-weight: bold;

}

.description {

    margin-top: 12px;

    line-height: 1.6;

    color: #666;

}

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;

}

.info {

    background: #fff8e1;

    padding: 17px;

    border-radius: 10px;

}

.label {

    font-size: 13px;

    color: #888;

    margin-bottom: 7px;

}

.value {

    color: #555;

    font-weight: bold;

    font-size: 16px;

}

.amount {

    color: #b8860b;

    font-size: 22px;

    font-weight: bold;

}

.status {

    display: inline-block;

    padding: 8px 15px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

    text-transform: capitalize;

}

.status-pending {

    background: #fff1c7;

    color: #a06b00;

}

.status-confirmed {

    background: #dff5df;

    color: #287a28;

}

.status-completed {

    background: #d9ecff;

    color: #1769aa;

}

.status-cancelled {

    background: #ffe1e1;

    color: #b00020;

}

.payment {

    display: inline-block;

    padding: 8px 15px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

    text-transform: capitalize;

}

.payment-unpaid {

    background: #ffe1e1;

    color: #b00020;

}

.payment-paid {

    background: #dff5df;

    color: #287a28;

}

.payment-partial {

    background: #fff1c7;

    color: #a06b00;

}

.payment-refunded {

    background: #e5e5e5;

    color: #555;

}

.note {

    background: #fafafa;

    border-left: 4px solid #d4af37;

    padding: 15px;

    margin-top: 25px;

    border-radius: 7px;

    line-height: 1.6;

}

.actions {

    display: flex;

    gap: 12px;

    margin-top: 25px;

    flex-wrap: wrap;

}

.btn {

    text-decoration: none;

    padding: 12px 20px;

    border-radius: 8px;

    font-weight: bold;

    text-align: center;

}

.back {

    background: #d4af37;

    color: white;

}

.back:hover {

    background: #b8860b;

}

.pay {

    background: #28a745;

    color: white;

}

.pay:hover {

    background: #218838;

}

.service-btn {

    background: #f3c84b;

    color: #6b4b00;

}

.service-btn:hover {

    background: #d4af37;

    color: white;

}

@media (max-width: 700px) {

    .header {

        padding: 18px 20px;

    }

    .container {

        width: 94%;

        margin: 25px auto;

    }

    .card {

        padding: 22px;

    }

    .info-grid {

        grid-template-columns: 1fr;

    }

    .actions {

        flex-direction: column;

    }

    .btn {

        width: 100%;

    }

}

</style>

</head>

<body>

<div class="header">

    <h2>
        Event Planner
    </h2>

    <a
        href="my_bookings.php"
        class="back-btn"
    >

        ← My Bookings

    </a>

</div>

<div class="container">

    <div class="title">

        <h1>
            📋 Booking Details
        </h1>

        <p>
            View complete information about your booking
        </p>

    </div>

    <div class="card">

        <div class="service-card">

            <h2>

                <?= htmlspecialchars(
                    $booking["service_name"]
                    ?? "Service"
                ) ?>

            </h2>

            <div class="event">

                Event:

                <?= htmlspecialchars(
                    $booking["event_name"]
                    ?? "N/A"
                ) ?>

            </div>

            <?php if (
                !empty(
                    $booking["description"]
                )
            ): ?>

                <div class="description">

                    <?= nl2br(
                        htmlspecialchars(
                            $booking["description"]
                        )
                    ) ?>

                </div>

            <?php endif; ?>

        </div>

        <div class="info-grid">

            <div class="info">

                <div class="label">
                    Booking ID
                </div>

                <div class="value">

                    #<?= (int)$booking["id"] ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Service ID
                </div>

                <div class="value">

                    <?php

                    if (
                        $booking["service_id"]
                        !== null
                    ) {

                        echo "#"
                            . (int)
                            $booking["service_id"];

                    } else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Provider
                </div>

                <div class="value">

                    <?php

                    if (
                        $booking["provider_id"]
                        !== null
                    ) {

                        echo "Provider #"
                            . (int)
                            $booking["provider_id"];

                    } else {

                        echo "Not Assigned Yet";

                    }

                    ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Event Type
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $booking["event_name"]
                        ?? "N/A"
                    ) ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Booking Date
                </div>

                <div class="value">

                    <?= date(
                        "d M Y",
                        strtotime(
                            $booking["booking_date"]
                        )
                    ) ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Booking Time
                </div>

                <div class="value">

                    <?php

                    if (
                        !empty(
                            $booking["booking_time"]
                        )
                    ) {

                        echo date(
                            "h:i A",
                            strtotime(
                                $booking["booking_time"]
                            )
                        );

                    } else {

                        echo "Not specified";

                    }

                    ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Booking Amount
                </div>

                <div class="amount">

                    Rs.
                    <?= number_format(
                        (float)
                        $booking["amount"],
                        2
                    ) ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Booking Status
                </div>

                <span
                    class="status status-<?= htmlspecialchars(
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

            <div class="info">

                <div class="label">
                    Payment Status
                </div>

                <span
                    class="payment payment-<?= htmlspecialchars(
                        $booking["payment_status"]
                    ) ?>"
                >

                    <?= htmlspecialchars(
                        ucfirst(
                            $booking["payment_status"]
                        )
                    ) ?>

                </span>

            </div>

            <div class="info">

                <div class="label">
                    Booking Created
                </div>

                <div class="value">

                    <?= date(
                        "d M Y, h:i A",
                        strtotime(
                            $booking["created_at"]
                        )
                    ) ?>

                </div>

            </div>

            <div class="info">

                <div class="label">
                    Last Updated
                </div>

                <div class="value">

                    <?= date(
                        "d M Y, h:i A",
                        strtotime(
                            $booking["updated_at"]
                        )
                    ) ?>

                </div>

            </div>

        </div>

        <?php if (
            !empty(
                $booking["customer_note"]
            )
        ): ?>

            <div class="note">

                <strong>
                    Your Note:
                </strong>

                <br><br>

                <?= nl2br(
                    htmlspecialchars(
                        $booking["customer_note"]
                    )
                ) ?>

            </div>

        <?php endif; ?>

        <div class="actions">

            <a
                href="my_bookings.php"
                class="btn back"
            >

                ← Back to My Bookings

            </a>

            <?php if (
                !empty(
                    $booking["service_id"]
                )
            ): ?>

                <a
                    href="service_details.php?id=<?= (int)$booking["service_id"] ?>"
                    class="btn service-btn"
                >

                    👁 View Service

                </a>

            <?php endif; ?>

            <?php if (
                $booking["status"] === "confirmed"
                &&
                $booking["payment_status"] !== "paid"
            ): ?>

                <a
                    href="payment.php?booking_id=<?= (int)$booking["id"] ?>"
                    class="btn pay"
                >

                    💳 Pay Now

                </a>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>