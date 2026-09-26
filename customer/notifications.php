<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;

}

$customer_id = (int) $_SESSION["user_id"];

if (
    isset($_GET["mark_all"]) &&
    $_GET["mark_all"] === "1"
) {

    $sql = "
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
        AND user_role = 'customer'
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $customer_id
        );

        $stmt->execute();

        $stmt->close();

    }

    header("Location: notifications.php");
    exit;
}

if (
    isset($_GET["read"]) &&
    is_numeric($_GET["read"])
) {

    $notification_id = (int) $_GET["read"];

    $sql = "
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
        AND user_id = ?
        AND user_role = 'customer'
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $notification_id,
            $customer_id
        );

        $stmt->execute();

        $stmt->close();

    }

    header("Location: notifications.php");
    exit;
}

$sql = "
    SELECT

        n.id,
        n.title,
        n.message,
        n.type,
        n.is_read,
        n.created_at,

        b.id AS booking_id,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status AS booking_status,
        b.payment_status,
        b.customer_note,

        s.service_name,
        s.unit,

        e.event_name

    FROM notifications n

    LEFT JOIN bookings b

        ON n.type = 'booking'

        AND n.message LIKE CONCAT(
            '%Booking ID: #',
            b.id,
            '%'
        )

        AND b.customer_id = n.user_id

    LEFT JOIN services s

        ON b.service_id = s.id

    LEFT JOIN events e

        ON b.event_type_id = e.id

    WHERE n.user_id = ?

    AND n.user_role = 'customer'

    ORDER BY n.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database Error: "
        . htmlspecialchars(
            $conn->error
        )
    );

}

$stmt->bind_param(
    "i",
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

$unread_count = 0;

$count_sql = "
    SELECT COUNT(*) AS total

    FROM notifications

    WHERE user_id = ?

    AND user_role = 'customer'

    AND is_read = 0
";

$count_stmt = $conn->prepare(
    $count_sql
);

if ($count_stmt) {

    $count_stmt->bind_param(
        "i",
        $customer_id
    );

    $count_stmt->execute();

    $count_result =
        $count_stmt->get_result();

    $count_row =
        $count_result->fetch_assoc();

    $unread_count =
        (int) $count_row["total"];

    $count_stmt->close();

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
    Notifications - Event Planner
</title>

<style>

* {

    box-sizing: border-box;

}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

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

    gap: 15px;

}

.header h2 {

    margin: 0;
    font-size: 24px;

}

.header-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}

.header-btn {

    display: inline-block;

    text-decoration: none;

    background: #b8860b;

    color: white;

    padding: 10px 17px;

    border-radius: 8px;

    font-weight: bold;

    transition: 0.2s;

}

.header-btn:hover {

    background: #8f6908;

    transform: translateY(-1px);

}

.container {

    width: 92%;

    max-width: 950px;

    margin: 40px auto 70px;

}

.top-section {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 20px;

}

.top-left h1 {

    margin: 0 0 10px;

    color: #b8860b;

    font-size: 30px;

}

.unread {

    display: inline-block;

    background: #fff1c7;

    color: #a06b00;

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

}

.mark-all {

    display: inline-block;

    text-decoration: none;

    background: #d4af37;

    color: white;

    padding: 11px 17px;

    border-radius: 8px;

    font-weight: bold;

    margin-bottom: 20px;

    transition: 0.2s;

}

.mark-all:hover {

    background: #b8860b;

    transform: translateY(-1px);

}

.notification {

    background: white;

    border-radius: 15px;

    padding: 22px;

    margin-bottom: 18px;

    box-shadow:
        0 4px 20px
        rgba(0,0,0,0.07);

    border-left:
        5px solid #d4af37;

    position: relative;

    transition: 0.2s;

}

.notification:hover {

    transform: translateY(-2px);

    box-shadow:
        0 7px 25px
        rgba(0,0,0,0.09);

}

.notification.unread-card {

    background: #fffdf3;

    border-left-color: #b8860b;

}

.notification.read-card {

    opacity: 0.78;

    border-left-color: #ddd;

}

.notification-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;

}

.notification-title {

    margin: 0;

    color: #444;

    font-size: 19px;

    line-height: 1.4;

}

.dot {

    width: 9px;

    height: 9px;

    background: #d4af37;

    border-radius: 50%;

    display: inline-block;

    margin-right: 6px;

    vertical-align: middle;

}

.type {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 15px;

    font-size: 11px;

    font-weight: bold;

    text-transform: capitalize;

    background: #fff8e1;

    color: #a06b00;

    white-space: nowrap;

}

.notification-message {

    margin-top: 12px;

    line-height: 1.6;

    color: #666;

    font-size: 15px;

}

.booking-info {

    margin-top: 20px;

    background: #fffaf0;

    border:
        1px solid
        #ead58b;

    border-radius: 13px;

    padding: 18px;

}

.booking-info-title {

    color: #b8860b;

    font-size: 17px;

    font-weight: bold;

    margin-bottom: 15px;

    padding-bottom: 10px;

    border-bottom:
        1px solid
        #ead58b;

}

.booking-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

}

.booking-box {

    background: white;

    border-left:
        4px solid #d4af37;

    border-radius: 9px;

    padding: 13px;

    min-height: 65px;

}

.booking-box span {

    display: block;

    color: #888;

    font-size: 12px;

    margin-bottom: 6px;

}

.booking-box strong {

    display: block;

    color: #555;

    font-size: 14px;

    word-break: break-word;

}

.booking-box .amount {

    color: #b8860b;

    font-size: 17px;

}

.booking-status {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 15px;

    font-size: 12px;

    font-weight: bold;

    text-transform: capitalize;

}

.booking-status.pending {

    background: #fff1c7;

    color: #a06b00;

}

.booking-status.confirmed {

    background: #dff5df;

    color: #287a28;

}

.booking-status.completed {

    background: #d9ecff;

    color: #1769aa;

}

.booking-status.cancelled {

    background: #ffe1e1;

    color: #b00020;

}

.payment-status {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 15px;

    font-size: 12px;

    font-weight: bold;

    text-transform: capitalize;

}

.payment-status.unpaid {

    background: #ffe1e1;

    color: #b00020;

}

.payment-status.paid {

    background: #dff5df;

    color: #287a28;

}

.payment-status.partial {

    background: #fff1c7;

    color: #a06b00;

}

.payment-status.refunded {

    background: #e5e5e5;

    color: #555;

}

.customer-note {

    margin-top: 15px;

    background: white;

    border-left:
        4px solid #f3c84b;

    padding: 12px 14px;

    border-radius: 7px;

    color: #666;

    font-size: 14px;

}

.customer-note strong {

    color: #8f6908;

}

.date {

    margin-top: 15px;

    font-size: 12px;

    color: #999;

}

.read-btn {

    display: inline-block;

    margin-top: 13px;

    text-decoration: none;

    color: #b8860b;

    font-size: 13px;

    font-weight: bold;

}

.read-btn:hover {

    text-decoration: underline;

}

.read-text {

    display: inline-block;

    margin-top: 13px;

    color: #999;

    font-size: 13px;

}

.empty {

    background: white;

    border-radius: 16px;

    padding: 65px 30px;

    text-align: center;

    box-shadow:
        0 4px 20px
        rgba(0,0,0,0.06);

}

.empty-icon {

    font-size: 60px;

    margin-bottom: 15px;

}

.empty h2 {

    color: #555;

    margin-bottom: 8px;

}

.empty p {

    color: #999;

    margin-bottom: 20px;

}

.empty-btn {

    display: inline-block;

    text-decoration: none;

    background:
        linear-gradient(
            135deg,
            #d4af37,
            #b8860b
        );

    color: white;

    padding: 12px 22px;

    border-radius: 8px;

    font-weight: bold;

}

@media (max-width: 700px) {

    .header {

        padding: 18px 20px;

        flex-direction: column;

        align-items: flex-start;

    }

    .header-actions {

        width: 100%;

    }

    .header-btn {

        flex: 1;

        text-align: center;

    }

    .container {

        width: 94%;

        margin-top: 25px;

    }

    .top-section {

        flex-direction: column;

        align-items: flex-start;

    }

    .booking-grid {

        grid-template-columns: 1fr;

    }

    .notification-header {

        flex-direction: column;

    }

    .type {

        align-self: flex-start;

    }

}

</style>

</head>

<body>

<header class="header">

    <h2>
        Event Planner
    </h2>

    <div class="header-actions">

        <a
            href="dashboard.php"
            class="header-btn"
        >

            🏠 Dashboard

        </a>

        <a
            href="logout.php"
            class="header-btn"
        >

            Logout

        </a>

    </div>

</header>

<div class="container">

    <div class="top-section">

        <div class="top-left">

            <h1>
                🔔 Notifications
            </h1>

            <?php if ($unread_count > 0): ?>

                <span class="unread">

                    <?= $unread_count ?>

                    Unread Notifications

                </span>

            <?php else: ?>

                <span class="unread">

                    ✓ All Notifications Read

                </span>

            <?php endif; ?>

        </div>

    </div>

    <?php if ($unread_count > 0): ?>

        <a
            href="notifications.php?mark_all=1"
            class="mark-all"
            onclick="
                return confirm(
                    'Mark all notifications as read?'
                );
            "
        >

            ✓ Mark All as Read

        </a>

    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <?php while (
            $notification =
            $result->fetch_assoc()
        ): ?>

            <div
                class="
                    notification
                    <?= 
                        $notification["is_read"]
                        ? "read-card"
                        : "unread-card"
                    ?>
                "
            >

                <div class="notification-header">

                    <h3 class="notification-title">

                        <?php if (
                            !$notification["is_read"]
                        ): ?>

                            <span class="dot"></span>

                        <?php endif; ?>

                        <?= htmlspecialchars(
                            $notification["title"]
                        ) ?>

                    </h3>

                    <span class="type">

                        <?= htmlspecialchars(
                            $notification["type"]
                            ?? "system"
                        ) ?>

                    </span>

                </div>

                <div class="notification-message">

                    <?= nl2br(
                        htmlspecialchars(
                            $notification["message"]
                        )
                    ) ?>

                </div>

                <?php if (
                    !empty(
                        $notification["booking_id"]
                    )
                ): ?>

                    <div class="booking-info">

                        <div class="booking-info-title">

                            📋 Booking Information

                        </div>

                        <div class="booking-grid">

                            <div class="booking-box">

                                <span>
                                    Booking ID
                                </span>

                                <strong>

                                    #<?= (int)
                                        $notification[
                                            "booking_id"
                                        ]
                                    ?>

                                </strong>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Event
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $notification[
                                            "event_name"
                                        ]
                                        ??
                                        "N/A"
                                    ) ?>

                                </strong>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Service
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $notification[
                                            "service_name"
                                        ]
                                        ??
                                        "N/A"
                                    ) ?>

                                </strong>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Booking Date
                                </span>

                                <strong>

                                    <?php if (
                                        !empty(
                                            $notification[
                                                "booking_date"
                                            ]
                                        )
                                    ): ?>

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $notification[
                                                    "booking_date"
                                                ]
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        N/A

                                    <?php endif; ?>

                                </strong>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Booking Time
                                </span>

                                <strong>

                                    <?php if (
                                        !empty(
                                            $notification[
                                                "booking_time"
                                            ]
                                        )
                                    ): ?>

                                        <?= date(
                                            "h:i A",
                                            strtotime(
                                                $notification[
                                                    "booking_time"
                                                ]
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        N/A

                                    <?php endif; ?>

                                </strong>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Amount
                                </span>

                                <strong class="amount">

                                    Rs.

                                    <?= number_format(
                                        (float)
                                        $notification[
                                            "amount"
                                        ],
                                        2
                                    ) ?>

                                </strong>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Booking Status
                                </span>

                                <?php

                                $booking_status =
                                    strtolower(
                                        $notification[
                                            "booking_status"
                                        ]
                                        ??
                                        "pending"
                                    );

                                ?>

                                <span
                                    class="
                                        booking-status
                                        <?= htmlspecialchars(
                                            $booking_status
                                        ) ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $booking_status
                                        )
                                    ) ?>

                                </span>

                            </div>

                            <div class="booking-box">

                                <span>
                                    Payment Status
                                </span>

                                <?php

                                $payment_status =
                                    strtolower(
                                        $notification[
                                            "payment_status"
                                        ]
                                        ??
                                        "unpaid"
                                    );

                                ?>

                                <span
                                    class="
                                        payment-status
                                        <?= htmlspecialchars(
                                            $payment_status
                                        ) ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $payment_status
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </div>

                        <?php if (
                            !empty(
                                $notification[
                                    "customer_note"
                                ]
                            )
                        ): ?>

                            <div class="customer-note">

                                <strong>
                                    📝 Your Note:
                                </strong>

                                <?= htmlspecialchars(
                                    $notification[
                                        "customer_note"
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <div class="date">

                    🕒

                    <?php if (
                        !empty(
                            $notification[
                                "created_at"
                            ]
                        )
                    ): ?>

                        <?= date(
                            "d M Y, h:i A",
                            strtotime(
                                $notification[
                                    "created_at"
                                ]
                            )
                        ) ?>

                    <?php endif; ?>

                </div>

                <?php if (
                    !$notification["is_read"]
                ): ?>

                    <a
                        href="
                            notifications.php?read=
                            <?= (int)
                                $notification["id"]
                            ?>
                        "
                        class="read-btn"
                    >

                        ✓ Mark as Read

                    </a>

                <?php else: ?>

                    <span class="read-text">

                        ✓ Read

                    </span>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                🔔
            </div>

            <h2>
                No Notifications
            </h2>

            <p>
                You don't have any notifications yet.
            </p>

            <a
                href="dashboard.php"
                class="empty-btn"
            >

                🏠 Back to Dashboard

            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>

<?php

$stmt->close();

?>