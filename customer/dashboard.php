<?php

session_start();

require_once "../database.php";

$isLoggedIn = isset($_SESSION["user_id"]);

$userName = "Guest";
$customer_id = 0;

if ($isLoggedIn) {

    $customer_id = (int) $_SESSION["user_id"];

    $userName = $_SESSION["user_name"]
        ?? $_SESSION["name"]
        ?? "Customer";
}

$booking_count = 0;
$unread_count = 0;
$message_count = 0;
$paid_amount = 0;

if ($isLoggedIn) {

    $sql = "
        SELECT COUNT(*) AS total
        FROM bookings
        WHERE customer_id = ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $customer_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $booking_count = (int) ($row["total"] ?? 0);

        $stmt->close();
    }
}

if ($isLoggedIn) {

    $sql = "
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE user_id = ?
        AND user_role = 'customer'
        AND is_read = 0
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $customer_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $unread_count = (int) ($row["total"] ?? 0);

        $stmt->close();
    }
}

if ($isLoggedIn) {

    $sql = "
        SELECT COUNT(*) AS total
        FROM messages
        WHERE receiver_id = ?
        AND receiver_role = 'customer'
        AND is_read = 0
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $customer_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $message_count = (int) ($row["total"] ?? 0);

        $stmt->close();
    }
}

if ($isLoggedIn) {

    $sql = "
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE customer_id = ?
        AND status = 'paid'
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $customer_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $paid_amount = (float) ($row["total"] ?? 0);

        $stmt->close();
    }
}

$event_types = [];

$sql = "
    SELECT
        id,
        event_name,
        description,
        icon
    FROM event_types
    WHERE status = 'active'
    ORDER BY id ASC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $event_types[] = $row;
    }
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
    Customer Dashboard | Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: Arial, sans-serif;

    background: #fffaf2;

    color: #444;
}

.navbar {

    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #f7d774
        );

    padding: 15px 40px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 3px 10px
        rgba(0, 0, 0, 0.12);
}

.logo {

    font-size: 25px;

    font-weight: bold;

    color: #8b6508;

    white-space: nowrap;
}

.nav-right {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-wrap: wrap;

    justify-content: flex-end;
}

.hello {

    font-weight: bold;

    color: #6b4e00;

    margin-right: 5px;
}

.nav-btn {

    text-decoration: none;

    padding: 9px 15px;

    border-radius: 20px;

    background: white;

    color: #8b6508;

    font-weight: bold;

    font-size: 14px;

    transition: 0.3s;
}

.nav-btn:hover {

    background: #fff1b8;

    transform: translateY(-2px);
}

.hero {

    padding: 55px 25px;

    text-align: center;

    background:
        linear-gradient(
            135deg,
            #fff3df,
            #fff8ed
        );
}

.hero h1 {

    color: #8b6508;

    font-size: 36px;

    margin-bottom: 12px;
}

.hero p {

    font-size: 17px;

    color: #666;

    line-height: 1.6;
}

.container {

    width: 90%;

    max-width: 1200px;

    margin: 35px auto;
}

.welcome {

    background: white;

    padding: 25px;

    border-radius: 15px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px
        rgba(0, 0, 0, 0.08);

    border-left: 5px solid #d4af37;
}

.welcome h2 {

    color: #8b6508;

    margin-bottom: 8px;
}

.welcome p {

    color: #777;

    line-height: 1.6;
}

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 45px;
}

.stat-card {

    background: white;

    padding: 22px;

    border-radius: 15px;

    box-shadow:
        0 5px 15px
        rgba(0, 0, 0, 0.08);

    text-align: center;

    border-top: 4px solid #f2c94c;
}

.stat-icon {

    font-size: 32px;

    margin-bottom: 8px;
}

.stat-number {

    font-size: 25px;

    font-weight: bold;

    color: #b8860b;

    margin-bottom: 5px;
}

.stat-label {

    color: #777;

    font-size: 14px;
}

.section-title {

    text-align: center;

    color: #8b6508;

    margin-bottom: 10px;

    font-size: 30px;
}

.section-subtitle {

    text-align: center;

    color: #777;

    margin-bottom: 30px;

    line-height: 1.6;
}

.event-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 25px;

    margin-bottom: 50px;
}

.event-card {

    background: white;

    border-radius: 18px;

    padding: 30px 25px;

    text-align: center;

    box-shadow:
        0 6px 18px
        rgba(0, 0, 0, 0.09);

    border-top: 5px solid #f2c94c;

    transition: 0.3s;

    min-height: 280px;

    display: flex;

    flex-direction: column;

    justify-content: space-between;
}

.event-card:hover {

    transform: translateY(-7px);

    box-shadow:
        0 12px 25px
        rgba(0, 0, 0, 0.14);
}

.event-icon {

    font-size: 58px;

    margin-bottom: 15px;
}

.event-card h3 {

    color: #8b6508;

    font-size: 22px;

    margin-bottom: 10px;
}

.event-description {

    color: #777;

    font-size: 14px;

    line-height: 1.6;

    min-height: 45px;

    margin-bottom: 20px;
}

.view-services-btn {

    display: block;

    width: 100%;

    padding: 12px;

    border-radius: 25px;

    background:
        linear-gradient(
            90deg,
            #f4c542,
            #f6b6d2
        );

    color: #5b4300;

    text-decoration: none;

    font-weight: bold;

    transition: 0.3s;
}

.view-services-btn:hover {

    opacity: 0.85;

    transform: scale(1.02);
}

.portfolio-btn {

    display: block;

    width: 100%;

    padding: 13px 20px;

    border-radius: 25px;

    background:
        linear-gradient(
            90deg,
            #f6b6d2,
            #f4c542
        );

    color: #5b4300;

    text-decoration: none;

    font-weight: bold;

    text-align: center;

    margin-top: 15px;

    transition: 0.3s;
}

.portfolio-btn:hover {

    opacity: 0.88;

    transform: scale(1.02);
}

.portfolio-section {

    background: white;

    border-radius: 18px;

    padding: 30px;

    margin-bottom: 45px;

    box-shadow:
        0 6px 18px
        rgba(0, 0, 0, 0.08);

    text-align: center;

    border-top: 5px solid #f2c94c;
}

.portfolio-section h2 {

    color: #8b6508;

    margin-bottom: 10px;

    font-size: 28px;
}

.portfolio-section p {

    color: #777;

    line-height: 1.6;

    margin-bottom: 20px;
}

.portfolio-main-btn {

    display: inline-block;

    padding: 13px 28px;

    border-radius: 25px;

    background:
        linear-gradient(
            90deg,
            #f4c542,
            #f6b6d2
        );

    color: #5b4300;

    text-decoration: none;

    font-weight: bold;

    transition: 0.3s;
}

.portfolio-main-btn:hover {

    transform: translateY(-2px);

    opacity: 0.9;
}

.notice {

    background: #fff0f6;

    border: 1px solid #f3b5ce;

    padding: 18px;

    border-radius: 12px;

    margin-bottom: 30px;

    text-align: center;

    line-height: 1.6;
}

.notice a {

    color: #b07800;

    font-weight: bold;

    text-decoration: none;
}

.empty {

    background: white;

    padding: 60px 30px;

    border-radius: 18px;

    text-align: center;

    box-shadow:
        0 5px 15px
        rgba(0, 0, 0, 0.08);

    margin-bottom: 50px;
}

.empty-icon {

    font-size: 55px;

    margin-bottom: 15px;
}

.empty h3 {

    color: #8b6508;

    margin-bottom: 10px;
}

.empty p {

    color: #777;
}

footer {

    margin-top: 60px;

    background: #f8c8dc;

    padding: 25px;

    text-align: center;

    color: #6b4e00;

    line-height: 1.8;
}

@media (max-width: 1000px) {

    .event-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 700px) {

    .navbar {

        padding: 15px;

        flex-direction: column;

        align-items: center;
    }

    .nav-right {

        justify-content: center;
    }

    .hero h1 {

        font-size: 28px;
    }

    .container {

        width: 94%;
    }

    .event-grid {

        grid-template-columns: 1fr;
    }

    .stats {

        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="navbar">

    <div class="logo">

        🎉 Event Planner

    </div>

    <div class="nav-right">

        <?php if ($isLoggedIn): ?>

            <span class="hello">

                👋 Hello,
                <?= htmlspecialchars($userName) ?>

            </span>

            <a
                href="dashboard.php"
                class="nav-btn"
            >
                🏠 Dashboard
            </a>

            <a
                href="../portfolio.php"
                class="nav-btn"
            >
                🖼️ Portfolio
            </a>

            <a
                href="profile.php"
                class="nav-btn"
            >
                👤 Profile
            </a>

            <a
                href="my_bookings.php"
                class="nav-btn"
            >

                📋 My Bookings

                <?php if ($booking_count > 0): ?>

                    (<?= $booking_count ?>)

                <?php endif; ?>

            </a>

            <a
                href="notifications.php"
                class="nav-btn"
            >

                🔔 Notifications

                <?php if ($unread_count > 0): ?>

                    (<?= $unread_count ?>)

                <?php endif; ?>

            </a>

            <a
                href="messages.php"
                class="nav-btn"
            >

                💬 Messages

                <?php if ($message_count > 0): ?>

                    (<?= $message_count ?>)

                <?php endif; ?>

            </a>

            <a
                href="change_password.php"
                class="nav-btn"
            >
                🔐
            </a>

            <a
                href="logout.php"
                class="nav-btn"
            >
                🚪 Logout
            </a>

        <?php else: ?>

            <a
                href="../portfolio.php"
                class="nav-btn"
            >
                🖼️ Portfolio
            </a>

            <a
                href="login.php"
                class="nav-btn"
            >
                🔑 Login
            </a>

            <a
                href="register.php"
                class="nav-btn"
            >
                📝 Register
            </a>

        <?php endif; ?>

    </div>

</div>

<section class="hero">

    <h1>

        Plan Your Perfect Event 🎉

    </h1>

    <p>

        Choose your event type and find the perfect
        services for your special occasion.

    </p>

</section>

<div class="container">

<?php if ($isLoggedIn): ?>

    <div class="welcome">

        <h2>

            Welcome back,
            <?= htmlspecialchars($userName) ?>! 👋

        </h2>

        <p>

            Manage your bookings, payments, messages
            and notifications from your dashboard.

        </p>

    </div>

    <div class="stats">

        <div class="stat-card">

            <div class="stat-icon">
                📋
            </div>

            <div class="stat-number">

                <?= $booking_count ?>

            </div>

            <div class="stat-label">

                Total Bookings

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                🔔
            </div>

            <div class="stat-number">

                <?= $unread_count ?>

            </div>

            <div class="stat-label">

                Unread Notifications

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                💬
            </div>

            <div class="stat-number">

                <?= $message_count ?>

            </div>

            <div class="stat-label">

                Unread Messages

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-number">

                Rs.
                <?= number_format($paid_amount, 0) ?>

            </div>

            <div class="stat-label">

                Total Paid

            </div>

        </div>

    </div>

<?php else: ?>

    <div class="notice">

        🔐 You can browse event types, services,
        prices and portfolios without logging in.

        <br>

        Login is required when you want to book,
        chat or make a payment.

        <br><br>

        <a href="login.php">

            Login Now

        </a>

    </div>

<?php endif; ?>

<div class="portfolio-section">

    <h2>

        🖼️ Explore Provider Portfolios

    </h2>

    <p>

        See the previous work of our event service
        providers before choosing the right service
        for your event.

    </p>

    <a
        href="../portfolio.php"
        class="portfolio-main-btn"
    >

        🖼️ View All Portfolios

    </a>

</div>

<h2 class="section-title">

    🎉 Choose Your Event

</h2>

<p class="section-subtitle">

    Select your event type to see services specially
    available for that event.

</p>

<?php if (count($event_types) > 0): ?>

    <div class="event-grid">

        <?php foreach ($event_types as $event): ?>

            <?php

            $eventName = strtolower(
                trim(
                    $event["event_name"] ?? ""
                )
            );

            $servicePage = "services.php";

            if ($eventName === "wedding") {

                $servicePage = "wedding_services.php";

            }

            ?>

            <div class="event-card">

                <div>

                    <div class="event-icon">

                        <?= htmlspecialchars(
                            $event["icon"] ?? "🎉"
                        ) ?>

                    </div>

                    <h3>

                        <?= htmlspecialchars(
                            $event["event_name"]
                        ) ?>

                    </h3>

                    <div class="event-description">

                        <?= htmlspecialchars(
                            $event["description"]
                            ??
                            "Plan your special event."
                        ) ?>

                    </div>

                </div>

                <div>

                    <a
                        href="<?= htmlspecialchars($servicePage) ?>?event_id=<?= (int)$event["id"] ?>"
                        class="view-services-btn"
                    >

                        🛎️ View Services

                    </a>

                    <a
                        href="../portfolio.php"
                        class="portfolio-btn"
                    >

                        🖼️ View Portfolio

                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <div class="empty">

        <div class="empty-icon">

            📭

        </div>

        <h3>

            No Event Types Available

        </h3>

        <p>

            Event types will appear here when they are
            added by the administrator.

        </p>

    </div>

<?php endif; ?>

</div>

<footer>

    🎉 <strong>Event Planner</strong>

    <br>

    Plan • Book • Celebrate

    <br>

    © <?= date("Y") ?> Event Planner. All Rights Reserved.

</footer>

</body>

</html>