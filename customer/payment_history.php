<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;

}

$customer_id = (int) $_SESSION["user_id"];

$sql = "
    SELECT
        p.id,
        p.booking_id,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.payment_status,
        p.payment_note,
        p.payment_date,
        p.created_at,

        b.booking_date,
        b.booking_time,
        b.status AS booking_status,

        s.service_name,

        e.event_name

    FROM payments p

    INNER JOIN bookings b
        ON p.booking_id = b.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN events e
        ON b.event_type_id = e.id

    WHERE p.customer_id = ?

    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database Error: "
        . htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "i",
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

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
    Payment History - Event Planner
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

    width: 94%;

    max-width: 1200px;

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

.payment-card {

    background: white;

    border-radius: 15px;

    padding: 25px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.08);
}

.payment-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    border-bottom: 1px solid #eee;

    padding-bottom: 15px;

    margin-bottom: 20px;
}

.payment-top h2 {

    margin: 0;

    color: #444;
}

.payment-id {

    color: #888;

    font-size: 14px;

    margin-top: 5px;
}

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;
}

.info {

    background: #fff8e1;

    padding: 15px;

    border-radius: 9px;
}

.info-label {

    font-size: 13px;

    color: #888;

    margin-bottom: 6px;
}

.info-value {

    font-weight: bold;

    color: #555;

    word-break: break-word;
}

.amount {

    color: #b8860b;

    font-size: 20px;

    font-weight: bold;
}

.status {

    display: inline-block;

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

    text-transform: capitalize;
}

.status-pending {

    background: #fff1c7;

    color: #a06b00;
}

.status-paid {

    background: #dff5df;

    color: #287a28;
}

.status-failed {

    background: #ffe1e1;

    color: #b00020;
}

.status-refunded {

    background: #e5e5e5;

    color: #555;
}

.note {

    margin-top: 20px;

    background: #fafafa;

    border-left:
        4px solid #d4af37;

    padding: 12px 15px;

    border-radius: 5px;

    color: #666;
}

.booking-status {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    text-transform: capitalize;
}

.empty {

    background: white;

    padding: 60px 30px;

    text-align: center;

    border-radius: 15px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.08);
}

.empty-icon {

    font-size: 60px;

    margin-bottom: 15px;
}

.empty h2 {

    color: #b8860b;
}

.empty p {

    color: #777;
}

@media (max-width: 850px) {

    .info-grid {

        grid-template-columns:
            1fr 1fr;
    }

}

@media (max-width: 600px) {

    .header {

        padding: 18px 20px;
    }

    .container {

        width: 94%;
    }

    .payment-top {

        flex-direction: column;

        align-items: flex-start;
    }

    .info-grid {

        grid-template-columns: 1fr;
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
            💳 Payment History
        </h1>

        <p>
            View all your booking payments
        </p>

    </div>

<?php if ($result->num_rows > 0): ?>

    <?php while ($payment = $result->fetch_assoc()): ?>

        <div class="payment-card">

            <div class="payment-top">

                <div>

                    <h2>

                        <?= htmlspecialchars(
                            $payment["service_name"]
                            ?? "Service"
                        ) ?>

                    </h2>

                    <div class="payment-id">

                        Payment #<?= $payment["id"] ?>

                        &nbsp; | &nbsp;

                        Booking #<?= $payment["booking_id"] ?>

                    </div>

                </div>

                <span
                    class="status status-<?= htmlspecialchars(
                        $payment["payment_status"]
                    ) ?>"
                >

                    <?= htmlspecialchars(
                        $payment["payment_status"]
                    ) ?>

                </span>

            </div>

            <div class="info-grid">

                <div class="info">

                    <div class="info-label">
                        Event
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $payment["event_name"]
                            ?? "N/A"
                        ) ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        Amount
                    </div>

                    <div class="amount">

                        Rs.
                        <?= number_format(
                            $payment["amount"],
                            2
                        ) ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        Payment Method
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            ucfirst(
                                str_replace(
                                    "_",
                                    " ",
                                    $payment[
                                        "payment_method"
                                    ]
                                )
                            )
                        ) ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        Transaction ID
                    </div>

                    <div class="info-value">

                        <?php if (
                            !empty(
                                $payment[
                                    "transaction_id"
                                ]
                            )
                        ): ?>

                            <?= htmlspecialchars(
                                $payment[
                                    "transaction_id"
                                ]
                            ) ?>

                        <?php else: ?>

                            Not Available

                        <?php endif; ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        Payment Date
                    </div>

                    <div class="info-value">

                        <?php if (
                            !empty(
                                $payment[
                                    "payment_date"
                                ]
                            )
                        ): ?>

                            <?= date(
                                "d M Y, h:i A",
                                strtotime(
                                    $payment[
                                        "payment_date"
                                    ]
                                )
                            ) ?>

                        <?php else: ?>

                            Not Paid Yet

                        <?php endif; ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        Booking Date
                    </div>

                    <div class="info-value">

                        <?= date(
                            "d M Y",
                            strtotime(
                                $payment[
                                    "booking_date"
                                ]
                            )
                        ) ?>

                    </div>

                </div>

            </div>

            <?php if (
                !empty(
                    $payment["payment_note"]
                )
            ): ?>

                <div class="note">

                    <strong>
                        Payment Note:
                    </strong>

                    <?= htmlspecialchars(
                        $payment["payment_note"]
                    ) ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="empty">

        <div class="empty-icon">
            💳
        </div>

        <h2>
            No Payments Yet
        </h2>

        <p>
            You haven't made any payments yet.
        </p>

    </div>

<?php endif; ?>

</div>

</body>

</html>

<?php

$stmt->close();

?>