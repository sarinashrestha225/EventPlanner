<?php

session_start();

require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$provider_id = isset($_GET["id"]) && is_numeric($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

$service_id = isset($_GET["service_id"]) && is_numeric($_GET["service_id"])
    ? (int) $_GET["service_id"]
    : 0;

$event_id = isset($_GET["event_id"]) && is_numeric($_GET["event_id"])
    ? (int) $_GET["event_id"]
    : 0;

if ($provider_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

$provider = null;

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.email,
        p.phone,
        p.service_type,
        p.address,
        p.city,
        p.area,
        p.latitude,
        p.longitude,
        p.bio,
        p.profile_photo,
        p.status
    FROM providers p
    WHERE p.id = ?
    AND p.status = 'active'
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $provider = $result->fetch_assoc();
    }

    $stmt->close();
}

if (!$provider) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;padding:30px;'>
            <h2 style='color:#b8860b;'>Provider Not Found</h2>
            <p>This provider is currently unavailable.</p>
            <a href='nearby_providers.php' style='display:inline-block;margin-top:15px;padding:11px 20px;background:#d4af37;color:white;text-decoration:none;border-radius:8px;font-weight:bold;'>
                Back to Providers
            </a>
        </div>
    ");
}

$services = [];

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.service_name,
        s.category,
        s.description,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.availability,
        s.service_image,
        s.image
    FROM services s
    WHERE s.provider_id = ?
    AND s.status = 'active'
    ORDER BY s.service_name ASC
");

if ($stmt) {
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    $stmt->close();
}

$rating = 0;
$review_count = 0;

$stmt = $conn->prepare("
    SELECT
        COALESCE(AVG(rating), 0) AS average_rating,
        COUNT(id) AS review_count
    FROM reviews
    WHERE provider_id = ?
    AND status = 'published'
");

if ($stmt) {
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $rating = (float) $row["average_rating"];
        $review_count = (int) $row["review_count"];
    }

    $stmt->close();
}

$reviews = [];

$stmt = $conn->prepare("
    SELECT
        r.rating,
        r.comment,
        r.created_at,
        u.name AS customer_name
    FROM reviews r
    LEFT JOIN users u
        ON r.customer_id = u.id
    WHERE r.provider_id = ?
    AND r.status = 'published'
    ORDER BY r.created_at DESC
    LIMIT 10
");

if ($stmt) {
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }

    $stmt->close();
}

$selected_service = null;

if ($service_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            service_name,
            price,
            min_price,
            max_price,
            unit,
            availability
        FROM services
        WHERE id = ?
        AND provider_id = ?
        AND status = 'active'
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("ii", $service_id, $provider_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $selected_service = $result->fetch_assoc();
        }

        $stmt->close();
    }
}

if (!$selected_service && !empty($services)) {
    $selected_service = $services[0];
    $service_id = (int) $selected_service["id"];
}

$back_url = "nearby_providers.php";

if ($service_id > 0) {
    $back_url .= "?service_id=" . $service_id;

    if ($event_id > 0) {
        $back_url .= "&event_id=" . $event_id;
    }
}

$request_url = "book_service.php?service_id=" . $service_id;

if ($event_id > 0) {
    $request_url .= "&event_id=" . $event_id;
}

$request_url .= "&provider_id=" . $provider_id;

$chat_url = "messages.php?provider_id=" . $provider_id;

$profile_image = "";

if (!empty($provider["profile_photo"])) {
    $profile_file = basename($provider["profile_photo"]);
    $profile_path = __DIR__ . "/../provider/uploads/profile/" . $profile_file;

    if (file_exists($profile_path)) {
        $profile_image = "../provider/uploads/profile/" . $profile_file;
    }
}

$location = "Location not provided";

if (!empty($provider["area"]) && !empty($provider["city"])) {
    $location = $provider["area"] . ", " . $provider["city"];
} elseif (!empty($provider["city"])) {
    $location = $provider["city"];
} elseif (!empty($provider["area"])) {
    $location = $provider["area"];
} elseif (!empty($provider["address"])) {
    $location = $provider["address"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
<?= htmlspecialchars($provider["name"], ENT_QUOTES, "UTF-8") ?> - Event Planner
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
    color: #4b3621;
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
    max-width: 1100px;
    margin: 30px auto;
}

.profile-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #f0dca8;
    box-shadow: 0 6px 25px rgba(0,0,0,0.09);
}

.profile-top {
    background: linear-gradient(
        135deg,
        #f8c8dc,
        #fff1dc,
        #f6d365
    );
    padding: 35px;
    display: flex;
    align-items: center;
    gap: 30px;
}

.profile-photo {
    width: 145px;
    height: 145px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.avatar {
    width: 145px;
    height: 145px;
    border-radius: 50%;
    background: #d4af37;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 55px;
    font-weight: bold;
    border: 5px solid white;
}

.profile-info h1 {
    font-size: 32px;
    color: #6b4f00;
    margin-bottom: 10px;
}

.service-type {
    display: inline-block;
    background: white;
    color: #8b6508;
    padding: 7px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 13px;
}

.rating {
    margin-top: 13px;
}

.stars {
    color: #d4af37;
    font-size: 21px;
}

.rating-text {
    color: #666;
    font-size: 14px;
}

.content {
    padding: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.info-box {
    background: #fffaf0;
    border: 1px solid #ead9a6;
    border-radius: 12px;
    padding: 18px;
}

.info-title {
    color: #9b7411;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 7px;
}

.info-value {
    color: #4b3621;
    font-weight: bold;
}

.section {
    margin-top: 30px;
}

.section-title {
    color: #8a5a00;
    font-size: 23px;
    border-bottom: 2px solid #ead9a6;
    padding-bottom: 9px;
    margin-bottom: 18px;
}

.bio {
    color: #666;
    line-height: 1.8;
    background: #fffaf0;
    border-radius: 12px;
    padding: 18px;
}

.services {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.service-card {
    border: 1px solid #ead9a6;
    background: #fff;
    border-radius: 13px;
    padding: 18px;
}

.service-card.selected {
    border: 2px solid #d4af37;
    background: #fffaf0;
}

.service-name {
    color: #6b4f00;
    font-size: 19px;
    font-weight: bold;
    margin-bottom: 7px;
}

.category {
    color: #9b7411;
    font-size: 13px;
    margin-bottom: 12px;
}

.service-price {
    color: #6b4f00;
    font-weight: bold;
    font-size: 17px;
    margin-bottom: 10px;
}

.service-unit {
    color: #777;
    font-size: 13px;
}

.service-status {
    display: inline-block;
    margin-top: 10px;
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.status-available {
    background: #e5f6e9;
    color: #26733a;
}

.status-unavailable {
    background: #fbe4e4;
    color: #9b2c2c;
}

.action-box {
    margin-top: 30px;
    padding: 25px;
    background: linear-gradient(
        135deg,
        #fff0f6,
        #fff8df
    );
    border: 1px solid #f0dca8;
    border-radius: 14px;
    text-align: center;
}

.action-box h2 {
    color: #7a4b00;
    margin-bottom: 8px;
}

.action-box p {
    color: #777;
    margin-bottom: 20px;
}

.actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 12px 23px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.request {
    background: #d4af37;
    color: white;
}

.request:hover {
    background: #b8860b;
}

.chat {
    background: #c88a9b;
    color: white;
}

.chat:hover {
    background: #a96d7e;
}

.call {
    background: #ead7a0;
    color: #4b3621;
}

.call:hover {
    background: #d7bd72;
}

.reviews {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.review {
    background: #fffaf0;
    border: 1px solid #ead9a6;
    border-radius: 12px;
    padding: 17px;
}

.review-top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.customer-name {
    font-weight: bold;
    color: #6b4f00;
}

.review-stars {
    color: #d4af37;
}

.review-date {
    color: #999;
    font-size: 12px;
    margin-top: 5px;
}

.review-comment {
    color: #666;
    line-height: 1.6;
    margin-top: 10px;
}

.no-data {
    background: #fffaf0;
    border: 1px solid #ead9a6;
    border-radius: 12px;
    padding: 20px;
    color: #777;
    text-align: center;
}

footer {
    margin-top: 45px;
    padding: 22px;
    text-align: center;
    background: #f8c8dc;
    color: #6b3d52;
    line-height: 1.7;
}

@media(max-width: 800px) {

    .profile-top {
        flex-direction: column;
        text-align: center;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .services {
        grid-template-columns: 1fr;
    }

}

@media(max-width: 600px) {

    .header {
        flex-direction: column;
        padding: 18px;
    }

    .content {
        padding: 18px;
    }

    .profile-top {
        padding: 25px 18px;
    }

    .profile-info h1 {
        font-size: 26px;
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

<header class="header">

    <div class="logo">
        🎉 Event Planner
    </div>

    <a
        href="<?= htmlspecialchars($back_url, ENT_QUOTES, "UTF-8") ?>"
        class="back"
    >
        ← Back to Providers
    </a>

</header>

<div class="container">

<div class="profile-card">

<div class="profile-top">

<?php if (!empty($profile_image)): ?>

<img
    src="<?= htmlspecialchars($profile_image, ENT_QUOTES, "UTF-8") ?>"
    class="profile-photo"
    alt="<?= htmlspecialchars($provider["name"], ENT_QUOTES, "UTF-8") ?>"
>

<?php else: ?>

<div class="avatar">
    <?= strtoupper(
        htmlspecialchars(
            substr($provider["name"], 0, 1),
            ENT_QUOTES,
            "UTF-8"
        )
    ) ?>
</div>

<?php endif; ?>

<div class="profile-info">

<h1>
<?= htmlspecialchars($provider["name"], ENT_QUOTES, "UTF-8") ?>
</h1>

<div class="service-type">
<?= htmlspecialchars(
    $provider["service_type"] ?: "Event Service Provider",
    ENT_QUOTES,
    "UTF-8"
) ?>
</div>

<div class="rating">

<span class="stars">

<?php

$rounded_rating = round($rating);

for ($i = 1; $i <= 5; $i++) {
    echo $i <= $rounded_rating ? "★" : "☆";
}

?>

</span>

<span class="rating-text">
<?= number_format($rating, 1) ?>
(<?= $review_count ?> reviews)
</span>

</div>

</div>

</div>

<div class="content">

<div class="info-grid">

<div class="info-box">

<div class="info-title">
Location
</div>

<div class="info-value">
<?= htmlspecialchars($location, ENT_QUOTES, "UTF-8") ?>
</div>

</div>

<div class="info-box">

<div class="info-title">
Phone
</div>

<div class="info-value">

<?= !empty($provider["phone"])
    ? htmlspecialchars($provider["phone"], ENT_QUOTES, "UTF-8")
    : "Not provided"
?>

</div>

</div>

<div class="info-box">

<div class="info-title">
Provider Status
</div>

<div class="info-value">
Active
</div>

</div>

</div>

<div class="section">

<h2 class="section-title">
About This Provider
</h2>

<div class="bio">

<?php if (!empty($provider["bio"])): ?>

<?= nl2br(
    htmlspecialchars(
        $provider["bio"],
        ENT_QUOTES,
        "UTF-8"
    )
) ?>

<?php else: ?>

Professional event service provider ready to help make your event special.

<?php endif; ?>

</div>

</div>

<div class="section">

<h2 class="section-title">
Services
</h2>

<?php if (empty($services)): ?>

<div class="no-data">
No active services available.
</div>

<?php else: ?>

<div class="services">

<?php foreach ($services as $item): ?>

<?php

$item_price = (float) ($item["price"] ?? 0);
$item_min = (float) ($item["min_price"] ?? 0);
$item_max = (float) ($item["max_price"] ?? 0);

$is_selected =
    $selected_service &&
    (int) $selected_service["id"] === (int) $item["id"];

$is_available =
    strtolower($item["availability"] ?? "") === "available";

?>

<div class="service-card <?= $is_selected ? "selected" : "" ?>">

<div class="service-name">

<?= htmlspecialchars(
    $item["service_name"],
    ENT_QUOTES,
    "UTF-8"
) ?>

</div>

<?php if (!empty($item["category"])): ?>

<div class="category">

<?= htmlspecialchars(
    $item["category"],
    ENT_QUOTES,
    "UTF-8"
) ?>

</div>

<?php endif; ?>

<div class="service-price">

<?php if ($item_price > 0): ?>

Rs. <?= number_format($item_price, 2) ?>

<?php elseif ($item_min > 0 && $item_max > 0): ?>

Rs. <?= number_format($item_min, 0) ?>
-
Rs. <?= number_format($item_max, 0) ?>

<?php elseif ($item_min > 0): ?>

From Rs. <?= number_format($item_min, 0) ?>

<?php else: ?>

Price on request

<?php endif; ?>

</div>

<?php if (!empty($item["unit"])): ?>

<div class="service-unit">

Unit:
<?= htmlspecialchars(
    $item["unit"],
    ENT_QUOTES,
    "UTF-8"
) ?>

</div>

<?php endif; ?>

<div class="service-status <?= $is_available ? "status-available" : "status-unavailable" ?>">

<?= $is_available ? "Available" : "Unavailable" ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

<div class="action-box">

<h2>
Choose This Provider
</h2>

<p>
Contact the provider or request the selected service for your event.
</p>

<div class="actions">

<?php if ($service_id > 0): ?>

<a
    href="<?= htmlspecialchars($request_url, ENT_QUOTES, "UTF-8") ?>"
    class="btn request"
>
    Request Service
</a>

<?php endif; ?>

<a
    href="<?= htmlspecialchars($chat_url, ENT_QUOTES, "UTF-8") ?>"
    class="btn chat"
>
    Chat
</a>

<?php if (!empty($provider["phone"])): ?>

<a
    href="tel:<?= htmlspecialchars($provider["phone"], ENT_QUOTES, "UTF-8") ?>"
    class="btn call"
>
    Call
</a>

<?php endif; ?>

</div>

</div>

<div class="section">

<h2 class="section-title">
Reviews & Ratings
</h2>

<?php if (empty($reviews)): ?>

<div class="no-data">
No reviews yet.
</div>

<?php else: ?>

<div class="reviews">

<?php foreach ($reviews as $review): ?>

<div class="review">

<div class="review-top">

<div class="customer-name">

<?= htmlspecialchars(
    $review["customer_name"] ?: "Customer",
    ENT_QUOTES,
    "UTF-8"
) ?>

</div>

<div class="review-stars">

<?php

$review_rating = (int) $review["rating"];

for ($i = 1; $i <= 5; $i++) {
    echo $i <= $review_rating ? "★" : "☆";
}

?>

</div>

</div>

<?php if (!empty($review["created_at"])): ?>

<div class="review-date">

<?= date(
    "M d, Y",
    strtotime($review["created_at"])
) ?>

</div>

<?php endif; ?>

<?php if (!empty($review["comment"])): ?>

<div class="review-comment">

<?= nl2br(
    htmlspecialchars(
        $review["comment"],
        ENT_QUOTES,
        "UTF-8"
    )
) ?>

</div>

<?php endif; ?>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

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
