<?php

session_start();

require_once __DIR__ . "/../database.php";

$is_logged_in = false;
$provider_id = 0;
$provider_name = "Provider";

if (
    isset($_SESSION["provider_id"]) &&
    (int)$_SESSION["provider_id"] > 0
) {
    $is_logged_in = true;
    $provider_id = (int)$_SESSION["provider_id"];
} elseif (
    isset($_SESSION["user_id"]) &&
    isset($_SESSION["user_role"]) &&
    $_SESSION["user_role"] === "provider" &&
    (int)$_SESSION["user_id"] > 0
) {
    $is_logged_in = true;
    $provider_id = (int)$_SESSION["user_id"];
}

if (
    $is_logged_in &&
    isset($_SESSION["provider_name"]) &&
    !empty($_SESSION["provider_name"])
) {
    $provider_name = $_SESSION["provider_name"];
}

$total_bookings = 0;
$total_services = 0;
$total_earnings = 0;
$average_rating = 0;

if ($is_logged_in) {

    if ($provider_name === "Provider") {

        $stmt = $conn->prepare("
            SELECT name
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param("i", $provider_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {

                if (!empty($row["name"])) {
                    $provider_name = $row["name"];
                }
            }

            $stmt->close();
        }
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM bookings
        WHERE provider_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param("i", $provider_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $total_bookings = (int)$row["total"];
        }

        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM services
        WHERE provider_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param("i", $provider_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $total_services = (int)$row["total"];
        }

        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(pay.amount), 0) AS total_sales
        FROM payments pay
        INNER JOIN bookings b
            ON pay.booking_id = b.id
        WHERE b.provider_id = ?
        AND pay.payment_status IN ('pending', 'paid')
    ");

    if ($stmt) {

        $stmt->bind_param("i", $provider_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $total_sales = (float)$row["total_sales"];

            $commission_rate = 20.00;

            $commission_amount =
                $total_sales * ($commission_rate / 100);

            $total_earnings =
                $total_sales - $commission_amount;
        }

        $stmt->close();
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(AVG(rating), 0) AS average_rating
        FROM reviews
        WHERE provider_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param("i", $provider_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $average_rating = (float)$row["average_rating"];
        }

        $stmt->close();
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

<title>Provider Dashboard - Event Planner</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #fffaf3;
    color: #4b3621;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: linear-gradient(
        180deg,
        #f8c8dc 0%,
        #fff1dc 55%,
        #f6d365 100%
    );
    padding: 25px 15px;
    overflow-y: auto;
}

.brand {
    text-align: center;
    margin-bottom: 30px;
}

.brand h2 {
    color: #6b4f00;
    font-size: 24px;
    margin-bottom: 8px;
}

.brand p {
    color: #795548;
    font-size: 14px;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.menu a {
    text-decoration: none;
    color: #5a3d00;
    padding: 12px 15px;
    border-radius: 9px;
    font-weight: bold;
    transition: 0.2s;
}

.menu a:hover {
    background: rgba(255, 255, 255, 0.75);
    transform: translateX(3px);
}

.menu .active {
    background: white;
    color: #9a7200;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
}

.login-link {
    margin-top: 15px;
    background: #b8860b;
    color: white !important;
    text-align: center;
}

.login-link:hover {
    background: #946f08 !important;
    transform: none !important;
}

.logout {
    margin-top: 15px;
    background: #dc3545;
    color: white !important;
}

.logout:hover {
    background: #c82333 !important;
    transform: none !important;
}

.main {
    margin-left: 250px;
    min-height: 100vh;
}

.topbar {
    background: white;
    padding: 20px 30px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar h1 {
    color: #4b3621;
    font-size: 26px;
}

.topbar p {
    color: #777;
    margin-top: 5px;
}

.provider-name {
    background: #fff4e5;
    color: #8a6500;
    padding: 10px 16px;
    border-radius: 20px;
    font-weight: bold;
}

.content {
    padding: 30px;
}

.welcome {
    background: linear-gradient(
        135deg,
        #f8c8dc,
        #fff1dc,
        #f6d365
    );
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
}

.welcome h2 {
    color: #5a3d00;
    margin-bottom: 8px;
}

.welcome p {
    color: #6d5a45;
    line-height: 1.6;
}

.guest-notice {
    background: white;
    border: 1px solid #f0d9b5;
    padding: 20px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.06);
}

.guest-notice h3 {
    color: #7b5a00;
    margin-bottom: 8px;
}

.guest-notice p {
    color: #777;
    line-height: 1.6;
}

.login-button {
    display: inline-block;
    margin-top: 14px;
    padding: 11px 18px;
    background: #b8860b;
    color: white;
    text-decoration: none;
    border-radius: 9px;
    font-weight: bold;
}

.login-button:hover {
    background: #946f08;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.stat {
    background: white;
    padding: 22px;
    border-radius: 15px;
    text-decoration: none;
    color: #4b3621;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: 0.2s;
}

.stat:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    font-size: 30px;
    margin-bottom: 10px;
}

.stat-title {
    color: #777;
    font-size: 14px;
    margin-bottom: 7px;
}

.stat-number {
    font-size: 25px;
    font-weight: bold;
    color: #b8860b;
}

.section-title {
    margin: 10px 0 18px;
    color: #5a3d00;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.quick {
    background: white;
    padding: 24px;
    border-radius: 15px;
    text-decoration: none;
    color: #4b3621;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: 0.2s;
}

.quick:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.quick-icon {
    font-size: 38px;
    margin-bottom: 12px;
}

.quick-name {
    font-size: 17px;
    font-weight: bold;
    color: #5a3d00;
    margin-bottom: 7px;
}

.quick-description {
    color: #777;
    line-height: 1.5;
    font-size: 14px;
}

.quick-login {
    border: 1px solid #ead9a8;
}

@media(max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .quick-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 800px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .menu {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }

    .login-link,
    .logout {
        margin-top: 0;
    }
}

@media(max-width: 600px) {

    .content {
        padding: 15px;
    }

    .topbar {
        padding: 18px;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .stats,
    .quick-grid,
    .menu {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<aside class="sidebar">

    <div class="brand">

        <h2>✦ Event Planner</h2>

        <p>👨‍💼 Provider Panel</p>

    </div>

    <nav class="menu">

        <a href="dashboard.php" class="active">
            🏠 Dashboard
        </a>

        <a href="../portfolio.php">
            📸 Public Portfolio
        </a>

        <?php if ($is_logged_in): ?>

            <a href="profile.php">
                👤 My Profile
            </a>

            <a href="services.php">
                🛠️ My Services
            </a>

            <a href="bookings.php">
                📅 My Bookings
            </a>

            <a href="earnings.php">
                💰 Earnings
            </a>

            <a href="payments.php">
                💳 Payments
            </a>

            <a href="portfolio.php">
                📸 Manage Portfolio
            </a>

            <a href="messages.php">
                💬 Messages
            </a>

            <a href="notifications.php">
                🔔 Notifications
            </a>

            <a href="verification.php">
                ✅ Verification
            </a>

            <a href="logout.php" class="logout">
                🚪 Logout
            </a>

        <?php else: ?>

            <a href="login.php" class="login-link">
                🔐 Provider Login
            </a>

        <?php endif; ?>

    </nav>

</aside>

<main class="main">

    <header class="topbar">

        <div>

            <h1>
                Provider Dashboard
            </h1>

            <p>
                Browse provider information and portfolio.
            </p>

        </div>

        <div class="provider-name">

            <?php if ($is_logged_in): ?>

                👨‍💼 <?= htmlspecialchars(
                    $provider_name,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            <?php else: ?>

                👤 Guest Provider

            <?php endif; ?>

        </div>

    </header>

    <div class="content">

        <div class="welcome">

            <?php if ($is_logged_in): ?>

                <h2>
                    Welcome back, <?= htmlspecialchars(
                        $provider_name,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?> 🌸
                </h2>

                <p>
                    Manage your Event Planner services, bookings, portfolio and earnings from here.
                </p>

            <?php else: ?>

                <h2>
                    Welcome to the Provider Dashboard 🌸
                </h2>

                <p>
                    Explore the provider dashboard before logging in. You can view the public portfolio, while management features require provider login.
                </p>

            <?php endif; ?>

        </div>

        <?php if (!$is_logged_in): ?>

            <div class="guest-notice">

                <h3>
                    🔐 Provider Login Required for Management
                </h3>

                <p>
                    You can browse the public portfolio without logging in.
                    To manage your profile, services, bookings, earnings,
                    messages or portfolio, please login as a provider.
                </p>

                <a
                    href="login.php"
                    class="login-button"
                >
                    🔐 Provider Login
                </a>

            </div>

        <?php else: ?>

            <div class="stats">

                <a href="bookings.php" class="stat">

                    <div class="stat-icon">
                        📅
                    </div>

                    <div class="stat-title">
                        Total Bookings
                    </div>

                    <div class="stat-number">
                        <?= $total_bookings ?>
                    </div>

                </a>

                <a href="services.php" class="stat">

                    <div class="stat-icon">
                        🛠️
                    </div>

                    <div class="stat-title">
                        My Services
                    </div>

                    <div class="stat-number">
                        <?= $total_services ?>
                    </div>

                </a>

                <a href="earnings.php" class="stat">

                    <div class="stat-icon">
                        💰
                    </div>

                    <div class="stat-title">
                        Total Earnings
                    </div>

                    <div class="stat-number">
                        Rs. <?= number_format(
                            $total_earnings,
                            2
                        ) ?>
                    </div>

                </a>

                <a href="reviews.php" class="stat">

                    <div class="stat-icon">
                        ⭐
                    </div>

                    <div class="stat-title">
                        Average Rating
                    </div>

                    <div class="stat-number">
                        <?= number_format(
                            $average_rating,
                            1
                        ) ?>
                    </div>

                </a>

            </div>

        <?php endif; ?>

        <h2 class="section-title">
            Quick Management
        </h2>

        <div class="quick-grid">

            <a href="../portfolio.php" class="quick">

                <div class="quick-icon">
                    📸
                </div>

                <div class="quick-name">
                    Public Portfolio
                </div>

                <div class="quick-description">
                    View portfolio photos and videos without logging in.
                </div>

            </a>

            <?php if ($is_logged_in): ?>

                <a href="profile.php" class="quick">

                    <div class="quick-icon">
                        👤
                    </div>

                    <div class="quick-name">
                        My Profile
                    </div>

                    <div class="quick-description">
                        View and manage your provider profile.
                    </div>

                </a>

                <a href="services.php" class="quick">

                    <div class="quick-icon">
                        🛠️
                    </div>

                    <div class="quick-name">
                        Manage Services
                    </div>

                    <div class="quick-description">
                        Add, edit and manage the services you provide.
                    </div>

                </a>

                <a href="bookings.php" class="quick">

                    <div class="quick-icon">
                        📅
                    </div>

                    <div class="quick-name">
                        Booking Requests
                    </div>

                    <div class="quick-description">
                        View customer requests and manage bookings.
                    </div>

                </a>

                <a href="earnings.php" class="quick">

                    <div class="quick-icon">
                        💰
                    </div>

                    <div class="quick-name">
                        Earnings
                    </div>

                    <div class="quick-description">
                        View your income and commission details.
                    </div>

                </a>

                <a href="payments.php" class="quick">

                    <div class="quick-icon">
                        💳
                    </div>

                    <div class="quick-name">
                        Payments
                    </div>

                    <div class="quick-description">
                        View customer payment information.
                    </div>

                </a>

                <a href="portfolio.php" class="quick">

                    <div class="quick-icon">
                        📸
                    </div>

                    <div class="quick-name">
                        Manage Portfolio
                    </div>

                    <div class="quick-description">
                        Upload and manage your portfolio photos and videos.
                    </div>

                </a>

                <a href="messages.php" class="quick">

                    <div class="quick-icon">
                        💬
                    </div>

                    <div class="quick-name">
                        Messages
                    </div>

                    <div class="quick-description">
                        Chat with customers about their bookings.
                    </div>

                </a>

                <a href="notifications.php" class="quick">

                    <div class="quick-icon">
                        🔔
                    </div>

                    <div class="quick-name">
                        Notifications
                    </div>

                    <div class="quick-description">
                        View booking and system notifications.
                    </div>

                </a>

                <a href="verification.php" class="quick">

                    <div class="quick-icon">
                        ✅
                    </div>

                    <div class="quick-name">
                        Verification
                    </div>

                    <div class="quick-description">
                        Submit and manage your provider verification.
                    </div>

                </a>

            <?php else: ?>

                <a href="login.php" class="quick quick-login">

                    <div class="quick-icon">
                        🔐
                    </div>

                    <div class="quick-name">
                        Provider Login
                    </div>

                    <div class="quick-description">
                        Login to manage your services, bookings, earnings and portfolio.
                    </div>

                </a>

            <?php endif; ?>

        </div>

    </div>

</main>

</body>

</html>