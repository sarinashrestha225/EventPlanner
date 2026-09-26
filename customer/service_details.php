<?php

session_start();

require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$customer_name = "Customer";

$user_stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($user_stmt) {
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_result && $user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();

        if (!empty($user["name"])) {
            $customer_name = $user["name"];
        }
    }

    $user_stmt->close();
}

$service_id = 0;

if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $service_id = (int) $_GET["id"];
} elseif (isset($_GET["service_id"]) && is_numeric($_GET["service_id"])) {
    $service_id = (int) $_GET["service_id"];
}

$event_id = 0;

if (isset($_GET["event_id"]) && is_numeric($_GET["event_id"])) {
    $event_id = (int) $_GET["event_id"];
}

if ($service_id <= 0) {
    die("
        <div style='font-family:Arial,sans-serif;text-align:center;margin-top:100px;padding:30px;'>
            <h2 style='color:#b8860b;'>Invalid Service ID</h2>
            <p>Please select a valid service.</p>
            <a href='services.php' style='display:inline-block;margin-top:15px;padding:11px 20px;background:#d4af37;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>
                Back to Services
            </a>
        </div>
    ");
}

$sql = "
    SELECT
        s.id,
        s.event_id,
        s.provider_id,
        s.service_name,
        s.service_image,
        s.category,
        s.description,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.image,
        s.availability,
        s.status,
        e.event_name
    FROM services s
    LEFT JOIN events e
        ON s.event_id = e.id
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Service Query Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $service_id);
$stmt->execute();

$result = $stmt->get_result();
$service = $result->fetch_assoc();

$stmt->close();

if (!$service) {
    die("
        <div style='font-family:Arial,sans-serif;text-align:center;margin-top:100px;padding:30px;'>
            <h2 style='color:#b8860b;'>Service Not Found</h2>
            <p>The selected service does not exist or is no longer available.</p>
            <a href='services.php' style='display:inline-block;margin-top:15px;padding:11px 20px;background:#d4af37;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>
                Back to Services
            </a>
        </div>
    ");
}

if ($service["status"] !== "active") {
    die("
        <div style='font-family:Arial,sans-serif;text-align:center;margin-top:100px;padding:30px;'>
            <h2 style='color:#b8860b;'>Service Unavailable</h2>
            <p>This service is currently unavailable.</p>
            <a href='services.php' style='display:inline-block;margin-top:15px;padding:11px 20px;background:#d4af37;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>
                Back to Services
            </a>
        </div>
    ");
}

if ($event_id <= 0 && !empty($service["event_id"])) {
    $event_id = (int) $service["event_id"];
}

$icons = [
    "catering" => "🍽️",
    "chef" => "👨‍🍳",
    "decoration" => "🎀",
    "dishwasher/cleaner" => "🧹",
    "dj" => "🎧",
    "dj & music" => "🎧",
    "makeup artist" => "💄",
    "makeup & beauty" => "💄",
    "pandit" => "🙏",
    "photography/videography" => "📸",
    "photography & videography" => "📸",
    "transportation" => "🚗",
    "venue" => "🏛️",
    "waiter" => "🤵"
];

$service_key = strtolower(trim($service["service_name"]));
$icon = $icons[$service_key] ?? "🛍️";

$image_name = "";

if (!empty($service["service_image"])) {
    $image_name = $service["service_image"];
} elseif (!empty($service["image"])) {
    $image_name = $service["image"];
}

$image_path = "";

if (!empty($image_name)) {
    $safe_image = basename($image_name);
    $possible_path = "../uploads/services/" . $safe_image;

    if (file_exists(__DIR__ . "/../uploads/services/" . $safe_image)) {
        $image_path = $possible_path;
    }
}

$back_url = "services.php";

if ($event_id > 0) {
    $back_url .= "?event_id=" . $event_id;
}

$booking_url = "book_service.php?service_id=" . $service_id;

if ($event_id > 0) {
    $booking_url .= "&event_id=" . $event_id;
}

$nearby_url = "nearby_providers.php?service_id=" . $service_id;

if ($event_id > 0) {
    $nearby_url .= "&event_id=" . $event_id;
}

$price = (float) ($service["price"] ?? 0);
$min_price = (float) ($service["min_price"] ?? 0);
$max_price = (float) ($service["max_price"] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
<?= htmlspecialchars($service["service_name"], ENT_QUOTES, "UTF-8") ?> - Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #fffaf3;
    color: #333;
}

.header {
    background: linear-gradient(
        90deg,
        #f8c8dc,
        #fff1dc,
        #f6d365
    );
    padding: 16px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: #6b4f00;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.hello {
    color: #6b3d52;
    font-weight: bold;
}

.back {
    color: #7a4b00;
    text-decoration: none;
    font-weight: bold;
}

.back:hover {
    color: #b8860b;
}

.container {
    width: 94%;
    max-width: 1050px;
    margin: 35px auto;
}

.card {
    background: white;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid #f0dca8;
    box-shadow: 0 6px 25px rgba(0,0,0,0.10);
}

.top {
    background: linear-gradient(
        135deg,
        #f8c8dc,
        #fff1dc,
        #f6d365
    );
    text-align: center;
    padding: 35px 20px;
}

.icon {
    font-size: 65px;
}

.top h1 {
    margin-top: 10px;
    color: #7a4b00;
    font-size: 32px;
}

.category {
    display: inline-block;
    margin-top: 12px;
    padding: 7px 16px;
    background: white;
    color: #8b6508;
    border-radius: 20px;
    font-weight: bold;
    font-size: 13px;
}

.content {
    padding: 30px;
}

.service-image {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 14px;
    margin-bottom: 25px;
    display: block;
}

.no-image {
    width: 100%;
    height: 300px;
    background: linear-gradient(
        135deg,
        #fff0f6,
        #fff6d9
    );
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 90px;
    margin-bottom: 25px;
}

.event-box {
    background: #fffaf0;
    border: 1px solid #ead9a6;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 22px;
    color: #666;
}

.event-box strong {
    color: #8a5a00;
}

.section-title {
    color: #8a5a00;
    font-size: 22px;
    margin-bottom: 12px;
    border-bottom: 2px solid #ead9a6;
    padding-bottom: 8px;
}

.description {
    color: #666;
    line-height: 1.8;
    font-size: 15px;
}

.unit-box {
    margin-top: 22px;
    background: #fff8df;
    border: 1px solid #ead9a6;
    padding: 15px;
    border-radius: 10px;
    color: #666;
}

.unit-box strong {
    color: #8a5a00;
}

.price-box {
    margin-top: 22px;
    background: #fff4cf;
    border: 1px solid #ead9a6;
    padding: 18px;
    border-radius: 10px;
}

.price-title {
    color: #8a5a00;
    font-weight: bold;
    margin-bottom: 8px;
}

.price-value {
    color: #6b4f00;
    font-size: 24px;
    font-weight: bold;
}

.availability-box {
    margin-top: 18px;
    padding: 13px 16px;
    border-radius: 10px;
    font-weight: bold;
}

.available {
    background: #e5f6e9;
    color: #26733a;
    border: 1px solid #b9e2c2;
}

.unavailable {
    background: #fbe4e4;
    color: #9b2c2c;
    border: 1px solid #edbaba;
}

.action-box {
    margin-top: 30px;
    padding: 28px;
    text-align: center;
    background: linear-gradient(
        135deg,
        #fff0f6,
        #fff8df
    );
    border: 1px solid #f0dca8;
    border-radius: 14px;
}

.action-box h3 {
    color: #7a4b00;
    font-size: 23px;
}

.action-box p {
    color: #777;
    margin-top: 8px;
    margin-bottom: 22px;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-block;
    padding: 13px 25px;
    text-decoration: none;
    border-radius: 9px;
    font-weight: bold;
    font-size: 14px;
    transition: 0.3s;
}

.nearby-btn {
    background: linear-gradient(
        90deg,
        #d4af37,
        #f6d365
    );
    color: white;
}

.nearby-btn:hover {
    background: #b8860b;
    transform: translateY(-2px);
}

.book-btn {
    background: #6b4f2a;
    color: white;
}

.book-btn:hover {
    background: #4b3621;
    transform: translateY(-2px);
}

footer {
    margin-top: 50px;
    padding: 22px;
    text-align: center;
    background: #f8c8dc;
    color: #6b3d52;
    line-height: 1.7;
}

@media(max-width: 700px) {

    .header {
        flex-direction: column;
        padding: 18px;
    }

    .header-right {
        flex-direction: column;
        gap: 10px;
    }

    .content {
        padding: 20px;
    }

    .top h1 {
        font-size: 27px;
    }

    .service-image {
        height: 280px;
    }

    .no-image {
        height: 230px;
        font-size: 70px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="logo">
        🎉 Event Planner
    </div>

    <div class="header-right">

        <span class="hello">
            👋 Hello, <?= htmlspecialchars($customer_name, ENT_QUOTES, "UTF-8") ?>
        </span>

        <a href="<?= htmlspecialchars($back_url, ENT_QUOTES, "UTF-8") ?>" class="back">
            ← Back to Services
        </a>

    </div>

</header>

<div class="container">

    <div class="card">

        <div class="top">

            <div class="icon">
                <?= $icon ?>
            </div>

            <h1>
                <?= htmlspecialchars($service["service_name"], ENT_QUOTES, "UTF-8") ?>
            </h1>

            <?php if (!empty($service["category"])): ?>

                <span class="category">
                    <?= htmlspecialchars($service["category"], ENT_QUOTES, "UTF-8") ?>
                </span>

            <?php endif; ?>

        </div>

        <div class="content">

            <?php if (!empty($image_path)): ?>

                <img
                    src="<?= htmlspecialchars($image_path, ENT_QUOTES, "UTF-8") ?>"
                    class="service-image"
                    alt="<?= htmlspecialchars($service["service_name"], ENT_QUOTES, "UTF-8") ?>"
                >

            <?php else: ?>

                <div class="no-image">
                    <?= $icon ?>
                </div>

            <?php endif; ?>

            <?php if (!empty($service["event_name"])): ?>

                <div class="event-box">

                    🎉

                    <strong>
                        Event:
                    </strong>

                    <?= htmlspecialchars($service["event_name"], ENT_QUOTES, "UTF-8") ?>

                </div>

            <?php endif; ?>

            <h2 class="section-title">
                📋 Service Description
            </h2>

            <div class="description">

                <?php if (!empty($service["description"])): ?>

                    <?= nl2br(htmlspecialchars($service["description"], ENT_QUOTES, "UTF-8")) ?>

                <?php else: ?>

                    Professional service for your special event.

                <?php endif; ?>

            </div>

            <?php if (
                $price > 0 ||
                $min_price > 0 ||
                $max_price > 0
            ): ?>

                <div class="price-box">

                    <div class="price-title">
                        Service Price
                    </div>

                    <div class="price-value">

                        <?php if ($price > 0): ?>

                            Rs. <?= number_format($price, 2) ?>

                        <?php elseif ($min_price > 0 && $max_price > 0): ?>

                            Rs. <?= number_format($min_price, 0) ?>
                            -
                            Rs. <?= number_format($max_price, 0) ?>

                        <?php elseif ($min_price > 0): ?>

                            From Rs. <?= number_format($min_price, 0) ?>

                        <?php elseif ($max_price > 0): ?>

                            Up to Rs. <?= number_format($max_price, 0) ?>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

            <?php if (!empty($service["unit"])): ?>

                <div class="unit-box">

                    📌

                    <strong>
                        Service Unit:
                    </strong>

                    <?= htmlspecialchars($service["unit"], ENT_QUOTES, "UTF-8") ?>

                </div>

            <?php endif; ?>

            <?php if (($service["availability"] ?? "") === "available"): ?>

                <div class="availability-box available">
                    ✓ This service is currently available
                </div>

            <?php else: ?>

                <div class="availability-box unavailable">
                    This service is currently unavailable
                </div>

            <?php endif; ?>

            <div class="action-box">

                <h3>
                    Find the Best Provider
                </h3>

                <p>
                    View nearby providers, compare ratings and prices, then choose the provider you prefer.
                </p>

                <div class="action-buttons">

                    <a
                        href="<?= htmlspecialchars($nearby_url, ENT_QUOTES, "UTF-8") ?>"
                        class="action-btn nearby-btn"
                    >
                        Find Nearby Providers
                    </a>

                    <a
                        href="<?= htmlspecialchars($booking_url, ENT_QUOTES, "UTF-8") ?>"
                        class="action-btn book-btn"
                    >
                        Book This Service
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

<footer>

    ©  Event Planner

    <br>

    Plan • Book • Celebrate 🎉

</footer>

</body>

</html>
