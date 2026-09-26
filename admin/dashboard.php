<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../database.php";

if (
    !isset($_SESSION["admin_id"]) &&
    isset($_COOKIE["admin_id"])
) {

    $admin_id = (int) $_COOKIE["admin_id"];

    if ($admin_id > 0) {

        $stmt = $conn->prepare("
            SELECT id, name
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param("i", $admin_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {

                $admin = $result->fetch_assoc();

                $_SESSION["admin_id"] = (int) $admin["id"];
                $_SESSION["admin_name"] = $admin["name"];
                $_SESSION["admin_logged_in"] = true;
                $_SESSION["user_role"] = "admin";
                $_SESSION["admin_username"] = $admin["name"];
            }

            $stmt->close();
        }
    }
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$customer_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE LOWER(TRIM(role)) = 'customer'
");

if ($result) {
    $row = $result->fetch_assoc();
    $customer_count = (int) ($row["total"] ?? 0);
}

$provider_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM providers
");

if ($result) {
    $row = $result->fetch_assoc();
    $provider_count = (int) ($row["total"] ?? 0);
}

$booking_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bookings
");

if ($result) {
    $row = $result->fetch_assoc();
    $booking_count = (int) ($row["total"] ?? 0);
}

$service_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM services
");

if ($result) {
    $row = $result->fetch_assoc();
    $service_count = (int) ($row["total"] ?? 0);
}

$event_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
");

if ($result) {
    $row = $result->fetch_assoc();
    $event_count = (int) ($row["total"] ?? 0);
}

$transportation_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM transportation
");

if ($result) {
    $row = $result->fetch_assoc();
    $transportation_count = (int) ($row["total"] ?? 0);
}

$total_revenue = 0;

$result = $conn->query("
    SELECT
        COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE payment_status = 'paid'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_revenue = (float) ($row["total"] ?? 0);
}

$total_commission = 0;

$result = $conn->query("
    SELECT
        COALESCE(SUM(commission_amount), 0) AS total
    FROM commissions
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_commission = (float) ($row["total"] ?? 0);
}

$offer_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM offers
");

if ($result) {
    $row = $result->fetch_assoc();
    $offer_count = (int) ($row["total"] ?? 0);
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
    Admin Dashboard | Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #fffaf3,
            #fff1f5,
            #fff9df
        );

    color: #5b4148;

    min-height: 100vh;
}

.sidebar {
    position: fixed;

    left: 0;
    top: 0;

    width: 250px;
    height: 100vh;

    padding: 25px 18px;

    overflow-y: auto;

    background:
        linear-gradient(
            180deg,
            #ffd7e4,
            #fff0ad,
            #fff5dc
        );

    border-right:
        2px solid #e7c65e;

    box-shadow:
        4px 0 20px
        rgba(0,0,0,.07);
}

.logo {
    text-align: center;

    font-family: Georgia, serif;

    font-size: 25px;

    font-weight: bold;

    color: #9a7010;

    margin-bottom: 25px;
}

.admin-box {
    background:
        rgba(255,255,255,.75);

    border:
        1px solid #efd783;

    border-radius: 15px;

    padding: 14px;

    text-align: center;

    margin-bottom: 20px;

    color: #704854;

    font-weight: bold;
}

.admin-role {
    display: block;

    font-size: 12px;

    color: #9a8086;

    margin-top: 3px;
}

.menu a {
    display: block;

    text-decoration: none;

    color: #674650;

    padding: 12px 14px;

    margin-bottom: 5px;

    border-radius: 10px;

    font-size: 14px;

    transition: .25s;
}

.menu a:hover {
    background:
        rgba(255,255,255,.8);

    color: #9b7108;

    transform:
        translateX(3px);
}

.menu .active {
    background:
        linear-gradient(
            90deg,
            #f4d26a,
            #ffe9a5
        );

    color: #674700;

    font-weight: bold;
}

.logout {
    margin-top: 18px;

    background:
        rgba(255,255,255,.75);

    color: #b13e58 !important;
}

.main {
    margin-left: 250px;

    padding: 30px;
}

.topbar {
    background:
        rgba(255,255,255,.9);

    border:
        1px solid #efd78c;

    border-radius: 20px;

    padding: 22px 25px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow:
        0 6px 22px
        rgba(160,110,20,.08);

    margin-bottom: 25px;
}

.topbar h1 {
    font-family: Georgia, serif;

    color: #754858;

    font-size: 28px;
}

.welcome {
    margin-top: 5px;

    color: #967b82;

    font-size: 14px;
}

.brand {
    color: #b1840d;

    font-weight: bold;
}

.cards {
    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(190px, 1fr)
        );

    gap: 18px;
}

.card {
    display: block;

    text-decoration: none;

    color: inherit;

    background:
        rgba(255,255,255,.92);

    border:
        1px solid #eed58a;

    border-radius: 18px;

    padding: 22px;

    box-shadow:
        0 5px 20px
        rgba(160,110,20,.07);

    transition: .25s;
}

.card:hover {
    transform:
        translateY(-5px);

    box-shadow:
        0 12px 28px
        rgba(160,110,20,.14);

    border-color:
        #d8b442;
}

.icon {
    font-size: 29px;

    margin-bottom: 10px;
}

.card-title {
    color: #8d7379;

    font-size: 14px;
}

.number {
    margin-top: 5px;

    color: #aa7b00;

    font-size: 27px;

    font-weight: bold;
}

.card-link {
    margin-top: 12px;

    font-size: 12px;

    color: #a77a08;

    font-weight: bold;
}

.portfolio-card {
    border:
        2px solid #f0c84b;

    background:
        linear-gradient(
            135deg,
            #fffdf5,
            #fff0f6
        );
}

.portfolio-card:hover {
    border-color: #d4a91e;
}

.section-title {
    margin-top: 32px;

    margin-bottom: 15px;

    color: #754858;

    font-family: Georgia, serif;

    font-size: 22px;
}

.quick-grid {
    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(160px, 1fr)
        );

    gap: 15px;
}

.quick {
    text-decoration: none;

    color: #694852;

    background:
        rgba(255,255,255,.9);

    border:
        1px solid #efd995;

    border-radius: 15px;

    padding: 20px;

    text-align: center;

    transition: .25s;
}

.quick:hover {
    background: #fff4f7;

    transform:
        translateY(-3px);

    border-color:
        #d7b33f;
}

.quick-icon {
    font-size: 30px;

    margin-bottom: 8px;
}

.quick-name {
    font-size: 14px;

    font-weight: bold;
}

.portfolio-quick {
    border:
        2px solid #f0c84b;

    background:
        linear-gradient(
            135deg,
            #fffdf5,
            #fff0f6
        );
}

.login-link {
    display: inline-block;

    margin-top: 10px;

    padding: 8px 15px;

    border-radius: 10px;

    background: #f4d26a;

    color: #674700;

    text-decoration: none;

    font-size: 13px;

    font-weight: bold;
}

@media(max-width:800px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;

        padding: 20px;
    }
}

@media(max-width:600px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .main {
        margin-left: 0;

        padding: 15px;
    }

    .topbar {
        flex-direction: column;

        align-items: flex-start;

        gap: 10px;
    }
}

</style>

</head>

<body>

<div class="sidebar">

    <div class="logo">
        ✦ Event Planner
    </div>

    <div class="admin-box">

        👑

        <?= htmlspecialchars($admin_name) ?>

        <span class="admin-role">
            Administrator
        </span>

    </div>

    <div class="menu">

        <a
            href="dashboard.php"
            class="active"
        >
            🏠 Dashboard
        </a>

        <a href="../portfolio.php">
            🖼️ Portfolio
        </a>

        <a href="customers/index.php">
            👥 Customers
        </a>

        <a href="providers/index.php">
            👨‍💼 Providers
        </a>

        <a href="events/index.php">
            🎉 Events
        </a>

        <a href="services/index.php">
            🛎️ Services
        </a>

        <a href="transportation/index.php">
            🚗 Transportation
        </a>

        <a href="locations/index.php">
            📍 Locations
        </a>

        <a href="venues/index.php">
            🏨 Venues
        </a>

        <a href="bookings/index.php">
            📅 Bookings
        </a>

        <a href="payments/index.php">
            💳 Payments
        </a>

        <a href="packages/index.php">
            📦 Packages
        </a>

        <a href="offers.php">
            🎁 Offers
        </a>

        <a href="coupons/index.php">
            🎟️ Coupons
        </a>

        <a href="reviews/index.php">
            ⭐ Reviews
        </a>

        <a href="comments/index.php">
            💬 Comments
        </a>

        <a href="messages.php">
            💬 Messages
        </a>

        <a href="commissions/index.php">
            💰 Commissions
        </a>

        <a href="notifications/index.php">
            🔔 Notifications
        </a>

        <a href="reports/index.php">
            📊 Reports
        </a>

        <a
            href="login.php"
            class="logout"
        >
            🔐 Admin Login
        </a>

    </div>

</div>

<div class="main">

    <div class="topbar">

        <div>

            <h1>
                Admin Dashboard
            </h1>

            <div class="welcome">

                Welcome to

                <strong>
                    Event Planner
                </strong>

                🌸

            </div>

        </div>

        <div class="brand">
            ✦ Event Planner
        </div>

    </div>

    <div class="cards">

        <a
            href="customers/index.php"
            class="card"
        >

            <div class="icon">
                👥
            </div>

            <div class="card-title">
                Total Customers
            </div>

            <div class="number">
                <?= $customer_count ?>
            </div>

            <div class="card-link">
                View Customers →
            </div>

        </a>

        <a
            href="providers/index.php"
            class="card"
        >

            <div class="icon">
                👨‍💼
            </div>

            <div class="card-title">
                Total Providers
            </div>

            <div class="number">
                <?= $provider_count ?>
            </div>

            <div class="card-link">
                View Providers →
            </div>

        </a>

        <a
            href="bookings/index.php"
            class="card"
        >

            <div class="icon">
                📅
            </div>

            <div class="card-title">
                Total Bookings
            </div>

            <div class="number">
                <?= $booking_count ?>
            </div>

            <div class="card-link">
                View Bookings →
            </div>

        </a>

        <a
            href="services/index.php"
            class="card"
        >

            <div class="icon">
                🛎️
            </div>

            <div class="card-title">
                Total Services
            </div>

            <div class="number">
                <?= $service_count ?>
            </div>

            <div class="card-link">
                Manage Services →
            </div>

        </a>

        <a
            href="transportation/index.php"
            class="card"
        >

            <div class="icon">
                🚗
            </div>

            <div class="card-title">
                Total Transportation
            </div>

            <div class="number">
                <?= $transportation_count ?>
            </div>

            <div class="card-link">
                Manage Transportation →
            </div>

        </a>

        <a
            href="events/index.php"
            class="card"
        >

            <div class="icon">
                🎉
            </div>

            <div class="card-title">
                Total Events
            </div>

            <div class="number">
                <?= $event_count ?>
            </div>

            <div class="card-link">
                Manage Events →
            </div>

        </a>

        <a
            href="offers.php"
            class="card"
        >

            <div class="icon">
                🎁
            </div>

            <div class="card-title">
                Total Offers
            </div>

            <div class="number">
                <?= $offer_count ?>
            </div>

            <div class="card-link">
                Manage Offers →
            </div>

        </a>

        <a
            href="../portfolio.php"
            class="card portfolio-card"
        >

            <div class="icon">
                🖼️
            </div>

            <div class="card-title">
                Provider Portfolio
            </div>

            <div class="number">
                🖼️
            </div>

            <div class="card-link">
                View All Portfolios →
            </div>

        </a>

        <a
            href="payments/index.php"
            class="card"
        >

            <div class="icon">
                💰
            </div>

            <div class="card-title">
                Total Revenue
            </div>

            <div class="number">

                Rs.
                <?= number_format(
                    $total_revenue,
                    2
                ) ?>

            </div>

            <div class="card-link">
                View Payments →
            </div>

        </a>

        <a
            href="commissions/index.php"
            class="card"
        >

            <div class="icon">
                💵
            </div>

            <div class="card-title">
                Total Commission
            </div>

            <div class="number">

                Rs.
                <?= number_format(
                    $total_commission,
                    2
                ) ?>

            </div>

            <div class="card-link">
                View Commission →
            </div>

        </a>

    </div>

    <div class="section-title">
        Quick Management
    </div>

    <div class="quick-grid">

        <a
            href="../portfolio.php"
            class="quick portfolio-quick"
        >

            <div class="quick-icon">
                🖼️
            </div>

            <div class="quick-name">
                View Portfolios
            </div>

        </a>

        <a
            href="services/index.php"
            class="quick"
        >

            <div class="quick-icon">
                🛎️
            </div>

            <div class="quick-name">
                Manage Services
            </div>

        </a>

        <a
            href="providers/index.php"
            class="quick"
        >

            <div class="quick-icon">
                👨‍💼
            </div>

            <div class="quick-name">
                Manage Providers
            </div>

        </a>

        <a
            href="customers/index.php"
            class="quick"
        >

            <div class="quick-icon">
                👥
            </div>

            <div class="quick-name">
                Manage Customers
            </div>

        </a>

        <a
            href="bookings/index.php"
            class="quick"
        >

            <div class="quick-icon">
                📅
            </div>

            <div class="quick-name">
                Manage Bookings
            </div>

        </a>

        <a
            href="packages/index.php"
            class="quick"
        >

            <div class="quick-icon">
                📦
            </div>

            <div class="quick-name">
                Manage Packages
            </div>

        </a>

        <a
            href="offers.php"
            class="quick"
        >

            <div class="quick-icon">
                🎁
            </div>

            <div class="quick-name">
                Manage Offers
            </div>

        </a>

        <a
            href="transportation/index.php"
            class="quick"
        >

            <div class="quick-icon">
                🚗
            </div>

            <div class="quick-name">
                Manage Transportation
            </div>

        </a>

        <a
            href="coupons/index.php"
            class="quick"
        >

            <div class="quick-icon">
                🎟️
            </div>

            <div class="quick-name">
                Manage Coupons
            </div>

        </a>

        <a
            href="payments/index.php"
            class="quick"
        >

            <div class="quick-icon">
                💳
            </div>

            <div class="quick-name">
                Manage Payments
            </div>

        </a>

        <a
            href="reports/index.php"
            class="quick"
        >

            <div class="quick-icon">
                📊
            </div>

            <div class="quick-name">
                View Reports
            </div>

        </a>

    </div>

</div>

</body>

</html>