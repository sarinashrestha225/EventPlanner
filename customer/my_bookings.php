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
        b.id,
        b.customer_id,
        b.event_type_id,
        b.service_id,
        b.package_id,
        b.provider_id,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status,
        b.payment_status,
        b.customer_note,
        b.created_at,
        b.updated_at,

        e.event_name,

        s.service_name,
        s.unit,

        p.package_name,
        p.experience_level,

        pr.name AS provider_name,
        pr.phone AS provider_phone,

        (
            SELECT COUNT(*)
            FROM reviews r
            WHERE r.booking_id = b.id
              AND r.customer_id = b.customer_id
        ) AS review_count

    FROM bookings b

    LEFT JOIN events e
        ON b.event_type_id = e.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN packages p
        ON b.package_id = p.id

    LEFT JOIN providers pr
        ON b.provider_id = pr.id

    WHERE b.customer_id = ?

    ORDER BY b.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $customer_id);
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

<title>My Bookings - Event Planner</title>

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
    background: linear-gradient(90deg, #d4af37, #f3d36a);
    padding: 20px 40px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.header h2 {
    margin: 0;
    font-size: 25px;
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
    margin: 0;
}

.booking-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.booking-type {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 12px;
}

.type-service {
    background: #f8c8dc;
    color: #8a3157;
}

.type-package {
    background: #ffe7a3;
    color: #7a4b00;
}

.booking-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.booking-top h2 {
    margin: 0;
    color: #444;
}

.booking-id {
    color: #888;
    font-size: 14px;
    margin-top: 5px;
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

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 20px;
}

.info {
    background: #fff8e1;
    padding: 15px;
    border-radius: 9px;
}

.info-label {
    font-size: 13px;
    color: #888;
    margin-bottom: 5px;
}

.info-value {
    font-weight: bold;
    color: #555;
}

.price {
    color: #b8860b;
    font-size: 20px;
    font-weight: bold;
}

.payment {
    display: inline-block;
    padding: 7px 14px;
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

.payment-pending {
    background: #fff1c7;
    color: #a06b00;
}

.payment-refunded {
    background: #e5e5e5;
    color: #555;
}

.package-box {
    background: linear-gradient(135deg, #fff8df, #fffaf0);
    border: 1px solid #ead9a6;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 20px;
}

.package-title {
    font-size: 20px;
    font-weight: bold;
    color: #8a5a00;
    margin-bottom: 8px;
}

.package-level {
    display: inline-block;
    background: #f8c8dc;
    color: #8a3157;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    text-transform: capitalize;
}

.guests {
    color: #555;
    font-weight: bold;
    margin-top: 10px;
}

.provider-box {
    background: #fff4f8;
    border-left: 4px solid #e8a1bb;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.provider-name {
    color: #a84f70;
    font-size: 17px;
    font-weight: bold;
}

.provider-phone {
    color: #777;
    margin-top: 5px;
}

.note {
    background: #fafafa;
    border-left: 4px solid #d4af37;
    padding: 12px 15px;
    margin-top: 20px;
    color: #666;
    border-radius: 5px;
}

.actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn {
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 8px;
    font-weight: bold;
    text-align: center;
    display: inline-block;
    border: none;
    cursor: pointer;
}

.service-btn {
    background: #d4af37;
    color: white;
}

.service-btn:hover {
    background: #b8860b;
}

.package-btn {
    background: #d4af37;
    color: white;
}

.package-btn:hover {
    background: #b8860b;
}

.pay-btn {
    background: #28a745;
    color: white;
}

.pay-btn:hover {
    background: #218838;
}

.chat-btn {
    background: #e9a8bf;
    color: #6f3048;
}

.chat-btn:hover {
    background: #d889a6;
    color: white;
}

.review-btn {
    background: #f4b400;
    color: white;
}

.review-btn:hover {
    background: #d99d00;
}

.review-disabled {
    background: #eeeeee;
    color: #888;
    cursor: not-allowed;
}

.reviewed {
    background: #dff5df;
    color: #287a28;
    cursor: default;
}

.empty {
    background: white;
    padding: 60px 30px;
    text-align: center;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
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

.browse-btn {
    display: inline-block;
    margin-top: 15px;
    background: #d4af37;
    color: white;
    text-decoration: none;
    padding: 12px 22px;
    border-radius: 8px;
    font-weight: bold;
}

@media (max-width: 800px) {

    .info-grid {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 600px) {

    .header {
        padding: 18px 20px;
    }

    .container {
        width: 94%;
    }

    .booking-top {
        flex-direction: column;
        align-items: flex-start;
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
        ✦ Event Planner
    </h2>

    <a
        href="dashboard.php"
        class="back-btn"
    >
        ← Dashboard
    </a>

</div>

<div class="container">

    <div class="title">

        <h1>
            📋 My Bookings
        </h1>

        <p>
            View and manage your service and package bookings
        </p>

    </div>

<?php if ($result->num_rows > 0): ?>

    <?php while ($booking = $result->fetch_assoc()): ?>

        <?php

        $booking_id = (int)($booking["id"] ?? 0);

        $service_id = (int)($booking["service_id"] ?? 0);

        $package_id = (int)($booking["package_id"] ?? 0);

        $provider_id = (int)($booking["provider_id"] ?? 0);

        $booking_status = strtolower(
            trim(
                $booking["status"] ?? "pending"
            )
        );

        $payment_status = strtolower(
            trim(
                $booking["payment_status"] ?? "unpaid"
            )
        );

        $review_count = (int)(
            $booking["review_count"] ?? 0
        );

        if ($package_id > 0) {
            $booking_type = "package";
        } else {
            $booking_type = "service";
        }

        if ($booking_type === "package") {
            $display_name =
                $booking["package_name"]
                ?: "Package";
        } else {
            $display_name =
                $booking["service_name"]
                ?: "Service";
        }

        ?>

        <div class="booking-card">

            <?php if ($booking_type === "package"): ?>

                <div class="booking-type type-package">
                    📦 Package Booking
                </div>

            <?php else: ?>

                <div class="booking-type type-service">
                    🧩 Service Booking
                </div>

            <?php endif; ?>

            <div class="booking-top">

                <div>

                    <h2>
                        <?= htmlspecialchars($display_name) ?>
                    </h2>

                    <div class="booking-id">
                        Booking #<?= $booking_id ?>
                    </div>

                </div>

                <span
                    class="status status-<?= htmlspecialchars($booking_status) ?>"
                >
                    <?= htmlspecialchars(
                        ucfirst(
                            $booking["status"] ?? "Pending"
                        )
                    ) ?>
                </span>

            </div>

            <?php if ($booking_type === "package"): ?>

                <div class="package-box">

                    <div class="package-title">

                        📦
                        <?= htmlspecialchars(
                            $booking["package_name"]
                            ?? "Package"
                        ) ?>

                    </div>

                    <?php if (!empty($booking["experience_level"])): ?>

                        <span class="package-level">

                            <?= htmlspecialchars(
                                $booking["experience_level"]
                            ) ?>

                        </span>

                    <?php endif; ?>

                    <div class="guests">

                        👥 Guests:
                        Selected guests are recorded with this booking.

                    </div>

                </div>

            <?php endif; ?>

            <div class="info-grid">

                <div class="info">

                    <div class="info-label">
                        🎉 Event
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $booking["event_name"]
                            ?? "N/A"
                        ) ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        📅 Booking Date
                    </div>

                    <div class="info-value">

                        <?= !empty($booking["booking_date"])
                            ? date(
                                "d M Y",
                                strtotime(
                                    $booking["booking_date"]
                                )
                            )
                            : "N/A"
                        ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        ⏰ Booking Time
                    </div>

                    <div class="info-value">

                        <?= !empty($booking["booking_time"])
                            ? date(
                                "h:i A",
                                strtotime(
                                    $booking["booking_time"]
                                )
                            )
                            : "N/A"
                        ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        💰 Estimated Amount
                    </div>

                    <div class="price">

                        Rs.
                        <?= number_format(
                            (float)(
                                $booking["amount"] ?? 0
                            ),
                            2
                        ) ?>

                    </div>

                </div>

                <div class="info">

                    <div class="info-label">
                        💳 Payment
                    </div>

                    <span
                        class="payment payment-<?= htmlspecialchars($payment_status) ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst(
                                $booking["payment_status"]
                                ?? "Unpaid"
                            )
                        ) ?>

                    </span>

                </div>

                <div class="info">

                    <div class="info-label">
                        🕐 Booked On
                    </div>

                    <div class="info-value">

                        <?= !empty($booking["created_at"])
                            ? date(
                                "d M Y",
                                strtotime(
                                    $booking["created_at"]
                                )
                            )
                            : "N/A"
                        ?>

                    </div>

                </div>

            </div>

            <?php if ($provider_id > 0): ?>

                <div class="provider-box">

                    <div class="provider-name">

                        👨‍💼 Provider:
                        <?= htmlspecialchars(
                            $booking["provider_name"]
                            ?: "Service Provider"
                        ) ?>

                    </div>

                    <?php if (!empty($booking["provider_phone"])): ?>

                        <div class="provider-phone">

                            📞
                            <?= htmlspecialchars(
                                $booking["provider_phone"]
                            ) ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <?php if (!empty($booking["customer_note"])): ?>

                <div class="note">

                    <strong>
                        📝 Your Note:
                    </strong>

                    <?= htmlspecialchars(
                        $booking["customer_note"]
                    ) ?>

                </div>

            <?php endif; ?>

            <div class="actions">

                <?php if (
                    $booking_type === "service"
                    &&
                    $service_id > 0
                ): ?>

                    <a
                        href="services_details.php?id=<?= $service_id ?>"
                        class="btn service-btn"
                    >
                        📋 Service Details
                    </a>

                <?php endif; ?>

                <?php if (
                    $booking_type === "package"
                    &&
                    $package_id > 0
                ): ?>

                    <a
                        href="package_details.php?package_id=<?= $package_id ?>"
                        class="btn package-btn"
                    >
                        📦 Package Details
                    </a>

                <?php endif; ?>

                <?php if ($provider_id > 0): ?>

                    <a
                        href="messages.php?booking_id=<?= $booking_id ?>"
                        class="btn chat-btn"
                    >
                        💬 Messages
                    </a>

                <?php endif; ?>

                <?php if (
                    $booking_status === "confirmed"
                    &&
                    $payment_status !== "paid"
                ): ?>

                    <a
                        href="payment.php?booking_id=<?= $booking_id ?>"
                        class="btn pay-btn"
                    >
                        💳 Make Payment
                    </a>

                <?php endif; ?>

                <?php if ($review_count > 0): ?>

                    <span class="btn reviewed">
                        ✓ Reviewed
                    </span>

                <?php elseif ($booking_status === "completed"): ?>

                    <a
                        href="review.php?booking_id=<?= $booking_id ?>"
                        class="btn review-btn"
                    >
                        ⭐ Give Review
                    </a>

                <?php elseif ($booking_status !== "cancelled"): ?>

                    <span
                        class="btn review-disabled"
                        title="Review will be available after the booking is completed."
                    >
                        ⭐ Review after Completion
                    </span>

                <?php endif; ?>

            </div>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="empty">

        <div class="empty-icon">
            📅
        </div>

        <h2>
            No Bookings Yet
        </h2>

        <p>
            You haven't booked any services or packages yet.
        </p>

        <a
            href="dashboard.php"
            class="browse-btn"
        >
            ← Back to Dashboard
        </a>

    </div>

<?php endif; ?>

</div>

</body>

</html>

<?php

$stmt->close();

$conn->close();

?>