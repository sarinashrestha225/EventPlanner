<?php

session_start();

require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['user_id'];

$event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$service_id = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0;

$event_name = '';
$service_name = '';

if ($event_id > 0) {

    $stmt = $conn->prepare("
        SELECT event_name
        FROM event_types
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $event_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $event_name = $row['event_name'];
        }

        $stmt->close();
    }
}

if ($service_id > 0) {

    $stmt = $conn->prepare("
        SELECT service_name
        FROM services
        WHERE id = ?
        AND status = 'active'
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $service_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $service_name = $row['service_name'];
        }

        $stmt->close();
    }
}

$providers = [];

if ($service_id > 0) {

    $sql = "
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
            p.status,
            s.id AS service_id,
            s.service_name,
            s.price,
            s.min_price,
            s.max_price,
            s.unit,
            s.availability,
            COALESCE(AVG(
                CASE
                    WHEN r.status = 'published'
                    THEN r.rating
                END
            ), 0) AS rating,
            COUNT(
                CASE
                    WHEN r.status = 'published'
                    THEN r.id
                END
            ) AS review_count
        FROM services s
        INNER JOIN providers p
            ON s.provider_id = p.id
        LEFT JOIN reviews r
            ON r.provider_id = p.id
            AND r.service_id = s.id
        WHERE s.id = ?
        AND s.status = 'active'
        AND p.status = 'active'
        GROUP BY
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
            p.status,
            s.id,
            s.service_name,
            s.price,
            s.min_price,
            s.max_price,
            s.unit,
            s.availability
        ORDER BY rating DESC, p.name ASC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $service_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $providers[] = $row;
        }

        $stmt->close();
    }

} else {

    $sql = "
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
            p.status,
            s.id AS service_id,
            s.service_name,
            s.price,
            s.min_price,
            s.max_price,
            s.unit,
            s.availability,
            COALESCE(AVG(
                CASE
                    WHEN r.status = 'published'
                    THEN r.rating
                END
            ), 0) AS rating,
            COUNT(
                CASE
                    WHEN r.status = 'published'
                    THEN r.id
                END
            ) AS review_count
        FROM services s
        INNER JOIN providers p
            ON s.provider_id = p.id
        LEFT JOIN reviews r
            ON r.provider_id = p.id
            AND r.service_id = s.id
        WHERE s.status = 'active'
        AND p.status = 'active'
        GROUP BY
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
            p.status,
            s.id,
            s.service_name,
            s.price,
            s.min_price,
            s.max_price,
            s.unit,
            s.availability
        ORDER BY rating DESC, p.name ASC
    ";

    $result = $conn->query($sql);

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $providers[] = $row;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Nearby Providers - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #fff8f0;
    color: #4b3621;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 25px;
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.back {
    text-decoration: none;
    color: #8b6914;
    font-weight: bold;
}

h1 {
    margin: 0;
    color: #4b3621;
}

.subtitle {
    margin-top: 8px;
    color: #777;
}

.filters {
    background: #fff;
    padding: 18px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

.filter-title {
    font-weight: bold;
    color: #b8860b;
    margin-bottom: 10px;
}

.filter-info {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.tag {
    background: #fff4cf;
    color: #7a5a00;
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
}

.providers {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.provider-card {
    background: #fff;
    border-radius: 17px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: 0.2s;
}

.provider-card:hover {
    transform: translateY(-4px);
}

.photo {
    height: 190px;
    background: #f6e8dc;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #d4af37;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    font-weight: bold;
}

.availability {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.available {
    background: #d4edda;
    color: #155724;
}

.busy {
    background: #f8d7da;
    color: #721c24;
}

.card-body {
    padding: 20px;
}

.provider-name {
    font-size: 21px;
    font-weight: bold;
    margin-bottom: 6px;
}

.service-name {
    color: #b8860b;
    font-weight: bold;
    margin-bottom: 12px;
}

.rating {
    margin-bottom: 12px;
}

.stars {
    color: #d4af37;
    font-size: 18px;
}

.review-count {
    color: #777;
    font-size: 13px;
}

.location {
    color: #777;
    font-size: 14px;
    margin-bottom: 12px;
}

.price {
    font-size: 17px;
    font-weight: bold;
    margin-bottom: 15px;
}

.distance {
    background: #fff8f0;
    border-radius: 8px;
    padding: 9px;
    margin-bottom: 15px;
    font-size: 14px;
    color: #6b4f2a;
}

.actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
}

.button {
    display: inline-block;
    text-align: center;
    padding: 11px 8px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
}

.view {
    background: #d4af37;
    color: #fff;
}

.chat {
    background: #c88a9b;
    color: #fff;
}

.call {
    background: #ead7a0;
    color: #4b3621;
}

.request {
    background: #4b3621;
    color: #fff;
    grid-column: 1 / -1;
}

.no-results {
    background: #fff;
    padding: 45px 20px;
    text-align: center;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
}

.no-results h2 {
    color: #b8860b;
}

.location-button {
    border: none;
    background: #d4af37;
    color: #fff;
    padding: 11px 16px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.location-button:hover {
    background: #b8860b;
}

@media(max-width: 950px) {

    .providers {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media(max-width: 650px) {

    .container {
        padding: 15px;
    }

    .providers {
        grid-template-columns: 1fr;
    }

    .top {
        align-items: flex-start;
        gap: 10px;
        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="container">

<div class="top">

<div>

<a
    href="services.php<?= $event_id > 0 ? '?event_id=' . $event_id : '' ?>"
    class="back"
>
    ← Back to Services
</a>

<h1>
    Nearby Providers
</h1>

<div class="subtitle">
    Find providers for your event and service.
</div>

</div>

<button
    type="button"
    class="location-button"
    id="locationButton"
>
    Use My Location
</button>

</div>

<div class="filters">

<div class="filter-title">
Your Selection
</div>

<div class="filter-info">

<?php if ($event_name !== ''): ?>

<div class="tag">
<?= htmlspecialchars($event_name, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<?php if ($service_name !== ''): ?>

<div class="tag">
<?= htmlspecialchars($service_name, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<?php if ($event_name === '' && $service_name === ''): ?>

<div class="tag">
All Services
</div>

<?php endif; ?>

</div>

</div>

<?php if (empty($providers)): ?>

<div class="no-results">

<h2>
No Providers Found
</h2>

<p>
No active providers are currently available for this service.
</p>

<a
    href="services.php<?= $event_id > 0 ? '?event_id=' . $event_id : '' ?>"
    class="button view"
>
    Back to Services
</a>

</div>

<?php else: ?>

<div class="providers">

<?php foreach ($providers as $provider): ?>

<?php

$rating = (float) $provider['rating'];

$review_count = (int) $provider['review_count'];

$availability =
    strtolower($provider['availability'] ?? '') === 'available';

$price = (float) ($provider['price'] ?? 0);

$min_price = (float) ($provider['min_price'] ?? 0);

$max_price = (float) ($provider['max_price'] ?? 0);

?>

<div
    class="provider-card"
    data-latitude="<?= htmlspecialchars((string) $provider['latitude'], ENT_QUOTES, 'UTF-8') ?>"
    data-longitude="<?= htmlspecialchars((string) $provider['longitude'], ENT_QUOTES, 'UTF-8') ?>"
>

<div class="photo">

<?php if (!empty($provider['profile_photo'])): ?>

<img
    src="../provider/uploads/profile/<?= htmlspecialchars($provider['profile_photo'], ENT_QUOTES, 'UTF-8') ?>"
    alt="Provider Photo"
>

<?php else: ?>

<div class="avatar">

<?= strtoupper(
    htmlspecialchars(
        substr($provider['name'] ?? 'P', 0, 1),
        ENT_QUOTES,
        'UTF-8'
    )
) ?>

</div>

<?php endif; ?>

<div class="availability <?= $availability ? 'available' : 'busy' ?>">

<?= $availability ? 'Available' : 'Busy' ?>

</div>

</div>

<div class="card-body">

<div class="provider-name">

<?= htmlspecialchars(
    $provider['name'],
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<div class="service-name">

<?= htmlspecialchars(
    $provider['service_name'],
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<div class="rating">

<span class="stars">

<?php

$rounded_rating = round($rating);

for ($i = 1; $i <= 5; $i++) {
    echo $i <= $rounded_rating ? '★' : '☆';
}

?>

</span>

<span class="review-count">

<?= number_format($rating, 1) ?>
(<?= $review_count ?> reviews)

</span>

</div>

<div class="location">

<?= htmlspecialchars(
    $provider['area'] ?: 'Area not provided',
    ENT_QUOTES,
    'UTF-8'
) ?>

<?php if (!empty($provider['city'])): ?>

, <?= htmlspecialchars(
    $provider['city'],
    ENT_QUOTES,
    'UTF-8'
) ?>

<?php endif; ?>

</div>

<div class="price">

<?php if ($price > 0): ?>

Rs. <?= number_format($price, 2) ?>

<?php elseif ($min_price > 0 && $max_price > 0): ?>

Rs. <?= number_format($min_price, 0) ?>
-
<?= number_format($max_price, 0) ?>

<?php elseif ($min_price > 0): ?>

From Rs. <?= number_format($min_price, 0) ?>

<?php else: ?>

Price on request

<?php endif; ?>

</div>

<div
    class="distance"
    data-distance
>
Distance: Calculating...
</div>

<div class="actions">

<a
    href="provider_profile.php?id=<?= (int) $provider['id'] ?>&service_id=<?= (int) $provider['service_id'] ?>&event_id=<?= $event_id ?>"
    class="button view"
>
    View Profile
</a>

<a
    href="messages.php?provider_id=<?= (int) $provider['id'] ?>"
    class="button chat"
>
    Chat
</a>

<?php if (!empty($provider['phone'])): ?>

<a
    href="tel:<?= htmlspecialchars($provider['phone'], ENT_QUOTES, 'UTF-8') ?>"
    class="button call"
>
    Call
</a>

<?php else: ?>

<a
    href="#"
    class="button call"
    onclick="return false;"
>
    Call
</a>

<?php endif; ?>

<a
    href="book_service.php?service_id=<?= (int) $provider['service_id'] ?>&event_id=<?= $event_id ?>&provider_id=<?= (int) $provider['id'] ?>"
    class="button request"
>
    Request Service
</a>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

<script>

const locationButton = document.getElementById("locationButton");

let customerLatitude = null;
let customerLongitude = null;

function calculateDistance(lat1, lon1, lat2, lon2) {

    const earthRadius = 6371;

    const latitudeDifference =
        (lat2 - lat1) * Math.PI / 180;

    const longitudeDifference =
        (lon2 - lon1) * Math.PI / 180;

    const a =
        Math.sin(latitudeDifference / 2) *
        Math.sin(latitudeDifference / 2) +
        Math.cos(lat1 * Math.PI / 180) *
        Math.cos(lat2 * Math.PI / 180) *
        Math.sin(longitudeDifference / 2) *
        Math.sin(longitudeDifference / 2);

    const c =
        2 * Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a)
        );

    return earthRadius * c;
}

function updateDistances() {

    const cards =
        document.querySelectorAll(".provider-card");

    cards.forEach(card => {

        const distanceElement =
            card.querySelector("[data-distance]");

        const latitude =
            parseFloat(card.dataset.latitude);

        const longitude =
            parseFloat(card.dataset.longitude);

        if (
            customerLatitude === null ||
            customerLongitude === null
        ) {

            distanceElement.textContent =
                "Distance: Location needed";

            return;
        }

        if (
            Number.isNaN(latitude) ||
            Number.isNaN(longitude)
        ) {

            distanceElement.textContent =
                "Distance: Not available";

            return;
        }

        const distance =
            calculateDistance(
                customerLatitude,
                customerLongitude,
                latitude,
                longitude
            );

        distanceElement.textContent =
            "Distance: " +
            distance.toFixed(1) +
            " km";
    });
}

locationButton.addEventListener("click", function() {

    if (!navigator.geolocation) {

        alert(
            "Your browser does not support location."
        );

        return;
    }

    locationButton.disabled = true;

    locationButton.textContent =
        "Getting Location...";

    navigator.geolocation.getCurrentPosition(

        function(position) {

            customerLatitude =
                position.coords.latitude;

            customerLongitude =
                position.coords.longitude;

            updateDistances();

            locationButton.disabled = false;

            locationButton.textContent =
                "Location Updated";

        },

        function(error) {

            locationButton.disabled = false;

            locationButton.textContent =
                "Use My Location";

            if (error.code === 1) {

                alert(
                    "Please allow location permission."
                );

            } else {

                alert(
                    "Could not get your current location."
                );
            }
        },

        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );

});

updateDistances();

</script>

</body>

</html>