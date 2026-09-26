<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: events.php");
    exit;
}

$booking_id = (int) $_GET['id'];

$customer_id = (int) $_SESSION['user_id'];

$sql = "
    SELECT
        b.id,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status,
        b.payment_status,
        s.service_name,
        e.event_name
    FROM bookings b

    INNER JOIN services s
        ON b.service_id = s.id

    INNER JOIN events e
        ON b.event_type_id = e.id

    WHERE b.id = ?
      AND b.customer_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param(
    "ii",
    $booking_id,
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
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
    Booking Successful - Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #fffaf5;

    color: #444;

}

.header {

    background:
        linear-gradient(
            135deg,
            #f8c8dc,
            #f6d365
        );

    padding: 18px 6%;

    box-shadow:
        0 2px 10px
        rgba(0, 0, 0, 0.08);

}

.logo {

    font-size: 25px;

    font-weight: bold;

    color: #805d00;

}

.container {

    width: 92%;

    max-width: 700px;

    margin: 60px auto;

}

.card {

    background: white;

    padding: 40px;

    border-radius: 18px;

    text-align: center;

    box-shadow:
        0 5px 25px
        rgba(0, 0, 0, 0.08);

    border-top:
        5px solid #d4af37;

}

.success-icon {

    font-size: 60px;

    margin-bottom: 15px;

}

h1 {

    color: #8b6508;

    margin-bottom: 10px;

}

.message {

    color: #777;

    margin-bottom: 25px;

    line-height: 1.6;

}

.details {

    text-align: left;

    background: #fffaf0;

    border:
        1px solid
        #ead58b;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 28px;

}

.row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    padding: 10px 0;

    border-bottom:
        1px solid
        #eee;

}

.row:last-child {

    border-bottom: none;

}

.label {

    color: #888;

}

.value {

    font-weight: bold;

    color: #805d00;

    text-align: right;

}

.back-btn {

    display: inline-block;

    text-decoration: none;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #b8860b
        );

    color: white;

    padding: 13px 25px;

    border-radius: 10px;

    font-weight: bold;

    font-size: 15px;

    border: none;

    box-shadow:
        0 4px 12px
        rgba(184, 134, 11, 0.25);

    transition:
        0.3s ease;

}

.back-btn:hover {

    background:
        linear-gradient(
            135deg,
            #b8860b,
            #d4af37
        );

    transform:
        translateY(-2px);

    box-shadow:
        0 7px 18px
        rgba(184, 134, 11, 0.35);

}

@media(max-width:600px) {

    .container {

        width: 94%;

        margin:
            30px auto 50px;

    }

    .card {

        padding:
            25px 18px;

    }

    .row {

        flex-direction: column;

        align-items: flex-start;

        gap: 4px;

    }

    .value {

        text-align: left;

    }

    .back-btn {

        width: 100%;

        text-align: center;

    }

}

</style>

</head>

<body>

<header class="header">

    <div class="logo">
        🎉 Event Planner
    </div>

</header>

<div class="container">

    <div class="card">

        <div class="success-icon">
            ✅
        </div>

        <h1>
            Booking Submitted!
        </h1>

        <p class="message">

            Your service booking has been
            successfully submitted.

        </p>

        <div class="details">

            <div class="row">

                <span class="label">
                    Booking ID
                </span>

                <span class="value">
                    #<?= (int)$booking['id'] ?>
                </span>

            </div>

            <div class="row">

                <span class="label">
                    Event
                </span>

                <span class="value">

                    <?= htmlspecialchars(
                        $booking['event_name']
                    ) ?>

                </span>

            </div>

            <div class="row">

                <span class="label">
                    Service
                </span>

                <span class="value">

                    <?= htmlspecialchars(
                        $booking['service_name']
                    ) ?>

                </span>

            </div>

            <div class="row">

                <span class="label">
                    Date
                </span>

                <span class="value">

                    <?= htmlspecialchars(
                        $booking['booking_date']
                    ) ?>

                </span>

            </div>

            <div class="row">

                <span class="label">
                    Time
                </span>

                <span class="value">

                    <?= htmlspecialchars(
                        $booking['booking_time']
                    ) ?>

                </span>

            </div>

            <div class="row">

                <span class="label">
                    Amount
                </span>

                <span class="value">

                    Rs.
                    <?= number_format(
                        (float)$booking['amount'],
                        2
                    ) ?>

                </span>

            </div>

            <div class="row">

                <span class="label">
                    Booking Status
                </span>

                <span class="value">

                    <?= htmlspecialchars(
                        ucfirst(
                            $booking['status']
                        )
                    ) ?>

                </span>

            </div>

            <div class="row">

                <span class="label">
                    Payment Status
                </span>

                <span class="value">

                    <?= htmlspecialchars(
                        ucfirst(
                            $booking['payment_status']
                        )
                    ) ?>

                </span>

            </div>

        </div>

        <a
            href="/EventPlanner/customer/dashboard.php"
            class="back-btn"
        >
            ← Back to Dashboard
        </a>

    </div>

</div>

</body>

</html>