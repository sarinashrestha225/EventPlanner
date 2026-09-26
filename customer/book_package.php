<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int)$_SESSION["user_id"];

$package_id = 0;
$pricing_id = 0;

if (isset($_GET["package_id"]) && is_numeric($_GET["package_id"])) {
    $package_id = (int)$_GET["package_id"];
} elseif (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $package_id = (int)$_GET["id"];
}

if (isset($_GET["pricing_id"]) && is_numeric($_GET["pricing_id"])) {
    $pricing_id = (int)$_GET["pricing_id"];
}

if ($package_id <= 0) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Invalid Package</h2>
            <p>Package ID was not received.</p>
            <a href='../index.php'>← Back to Home</a>
        </div>
    ");
}

$sql = "
    SELECT
        p.id AS package_id,
        p.event_id,
        p.package_name,
        p.experience_level,
        p.description,
        p.image,
        p.status,
        et.event_name
    FROM packages p
    LEFT JOIN event_types et
        ON p.event_id = et.id
    WHERE p.id = ?
      AND LOWER(p.status) = 'active'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Package Query Error: " . $conn->error);
}

$stmt->bind_param("i", $package_id);
$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Package Not Found</h2>
            <p>Package ID: <strong>" . $package_id . "</strong> does not exist or is inactive.</p>
            <a href='../index.php'>← Back to Home</a>
        </div>
    ");
}

$pricing = [];

$pricing_sql = "
    SELECT
        id,
        min_guests,
        max_guests,
        original_price,
        combo_price
    FROM package_pricing
    WHERE package_id = ?
    ORDER BY min_guests ASC
";

$pricing_stmt = $conn->prepare($pricing_sql);

if ($pricing_stmt) {

    $pricing_stmt->bind_param("i", $package_id);
    $pricing_stmt->execute();

    $pricing_result = $pricing_stmt->get_result();

    while ($row = $pricing_result->fetch_assoc()) {
        $pricing[] = $row;
    }

    $pricing_stmt->close();
}

$selected_price = 0;
$selected_original_price = 0;
$selected_min = 0;
$selected_max = 0;
$selected_pricing_id = 0;

if ($pricing_id > 0) {

    foreach ($pricing as $row) {

        if ((int)$row["id"] === $pricing_id) {

            $selected_pricing_id = (int)$row["id"];

            $selected_min = (int)$row["min_guests"];
            $selected_max = (int)$row["max_guests"];

            $selected_original_price = (float)$row["original_price"];
            $selected_price = (float)$row["combo_price"];

            break;
        }
    }
}

$icons = [
    "basic" => "⭐",
    "silver" => "🥈",
    "premium" => "💎",
    "luxury" => "👑",
    "vip" => "💠"
];

$package_key = strtolower(trim($package["package_name"]));

$icon = $icons[$package_key] ?? "📦";

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
Book <?= htmlspecialchars($package["package_name"], ENT_QUOTES, "UTF-8") ?>
- Event Planner
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fffaf3;
    color: #333;
}

.header {
    background: linear-gradient(90deg, #f8c8dc, #ffd966);
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: #7a4b00;
}

.back {
    text-decoration: none;
    color: #7a4b00;
    font-weight: bold;
}

.container {
    width: 94%;
    max-width: 900px;
    margin: 35px auto;
}

.card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,.10);
}

.top {
    background: linear-gradient(90deg, #f8c8dc, #ffe7a3);
    text-align: center;
    padding: 30px;
    color: #7a4b00;
}

.icon {
    font-size: 60px;
}

.top h1 {
    margin: 10px 0;
    font-size: 32px;
}

.event {
    font-weight: bold;
    font-size: 18px;
}

.content {
    padding: 30px;
}

.package-summary {
    background: #fffaf0;
    border: 1px solid #ead9a6;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 11px 0;
    border-bottom: 1px solid #eee;
}

.summary-row:last-child {
    border-bottom: none;
}

.label {
    font-weight: bold;
    color: #7a4b00;
}

.value {
    font-weight: bold;
    text-align: right;
}

.section-title {
    color: #8a5a00;
    border-bottom: 2px solid #ead9a6;
    padding-bottom: 10px;
    margin-top: 30px;
}

.form-group {
    margin-top: 18px;
}

.form-group label {
    display: block;
    font-weight: bold;
    color: #7a4b00;
    margin-bottom: 8px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #d4af37;
    border-radius: 8px;
    font-size: 16px;
    background: #fffdf7;
}

.form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.price-box {
    background: #fff8df;
    border: 1px solid #ead9a6;
    border-radius: 12px;
    padding: 22px;
    margin-top: 25px;
    text-align: center;
}

.price-title {
    font-weight: bold;
    color: #7a4b00;
}

.price {
    font-size: 30px;
    font-weight: bold;
    color: #b8860b;
    margin-top: 8px;
}

.old-price {
    color: #999;
    text-decoration: line-through;
    margin-top: 5px;
}

.guest-range {
    margin-top: 10px;
    color: #6b5830;
    font-weight: bold;
}

.warning {
    background: #fff0f0;
    border: 1px solid #e5aaaa;
    color: #a33;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.submit-btn {
    width: 100%;
    margin-top: 25px;
    padding: 15px;
    border: none;
    border-radius: 9px;
    background: #d4af37;
    color: white;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
}

.submit-btn:hover {
    background: #b8941f;
}

.back-package-btn {
    display: block;
    width: 100%;
    text-align: center;
    margin-top: 15px;
    padding: 13px;
    border-radius: 9px;
    background: #fff1d0;
    border: 1px solid #e2c764;
    color: #7a5b00;
    text-decoration: none;
    font-weight: bold;
}

@media(max-width:600px) {

    .header {
        padding: 18px;
        flex-direction: column;
        align-items: flex-start;
    }

    .content {
        padding: 20px;
    }

    .summary-row {
        flex-direction: column;
        gap: 5px;
    }

    .value {
        text-align: left;
    }

}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        ✦ Event Planner
    </div>

    <a
        href="view_package.php?package_id=<?= $package_id ?>"
        class="back"
    >
        ← Back to Package
    </a>

</div>


<div class="container">

<div class="card">

<div class="top">

    <div class="icon">
        <?= $icon ?>
    </div>

    <h1>
        Book Package
    </h1>

    <div class="event">

        🎉
        <?= htmlspecialchars(
            $package["event_name"] ?: "Event",
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </div>

</div>


<div class="content">


<div class="package-summary">

    <div class="summary-row">

        <span class="label">
            📦 Package
        </span>

        <span class="value">

            <?= htmlspecialchars(
                $package["package_name"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </span>

    </div>


    <div class="summary-row">

        <span class="label">
            🎉 Event
        </span>

        <span class="value">

            <?= htmlspecialchars(
                $package["event_name"] ?: "Event",
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </span>

    </div>


    <?php if ($selected_pricing_id > 0): ?>

    <div class="summary-row">

        <span class="label">
            👥 Guest Range
        </span>

        <span class="value">

            <?= number_format($selected_min) ?>
            -
            <?= number_format($selected_max) ?>
            Guests

        </span>

    </div>


    <div class="summary-row">

        <span class="label">
            💰 Selected Price
        </span>

        <span class="value">

            Rs.
            <?= number_format($selected_price, 2) ?>

        </span>

    </div>

    <?php endif; ?>

</div>


<h2 class="section-title">
    📅 Event Booking Information
</h2>


<form
    method="POST"
    action="save_package_booking.php"
>


    <input
        type="hidden"
        name="package_id"
        value="<?= $package_id ?>"
    >


    <input
        type="hidden"
        name="pricing_id"
        value="<?= $selected_pricing_id ?>"
    >


    <input
        type="hidden"
        name="event_type_id"
        value="<?= (int)$package["event_id"] ?>"
    >


    <input
        type="hidden"
        name="guests_min"
        value="<?= $selected_min ?>"
    >


    <input
        type="hidden"
        name="guests_max"
        value="<?= $selected_max ?>"
    >


    <input
        type="hidden"
        name="amount"
        value="<?= $selected_price ?>"
    >


    <?php if ($selected_pricing_id <= 0): ?>

        <div class="warning">

            ⚠️ Guest range is not selected.

            Please go back and select the guest range
            before continuing with the booking.

        </div>


        <a
            href="view_package.php?package_id=<?= $package_id ?>"
            class="back-package-btn"
        >
            👥 Select Guest Range
        </a>

    <?php else: ?>


        <div class="form-group">

            <label>
                👥 Selected Guest Range
            </label>

            <input
                type="text"
                value="<?= number_format($selected_min) ?> - <?= number_format($selected_max) ?> Guests"
                readonly
            >

        </div>


        <div class="form-group">

            <label>
                📅 Event Date
            </label>

            <input
                type="date"
                name="booking_date"
                min="<?= date("Y-m-d") ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                ⏰ Event Time
            </label>

            <input
                type="time"
                name="booking_time"
                required
            >

        </div>


        <div class="form-group">

            <label>
                📝 Additional Note
            </label>

            <textarea
                name="customer_note"
                placeholder="Write any special requirements..."
            ></textarea>

        </div>


        <div class="price-box">

            <div class="price-title">
                💰 Selected Package Price
            </div>


            <div class="price">

                Rs.
                <?= number_format(
                    $selected_price,
                    2
                ) ?>

            </div>


            <?php if ($selected_original_price > 0 && $selected_original_price > $selected_price): ?>

                <div class="old-price">

                    Original:
                    Rs.
                    <?= number_format(
                        $selected_original_price,
                        2
                    ) ?>

                </div>

            <?php endif; ?>


            <div class="guest-range">

                👥
                <?= number_format($selected_min) ?>
                -
                <?= number_format($selected_max) ?>
                Guests

            </div>

        </div>


        <button
            type="submit"
            class="submit-btn"
        >
            📅 Confirm Package Booking
        </button>


    <?php endif; ?>


</form>


</div>

</div>

</div>

</body>

</html>