
<?php

session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['provider_id'])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION['provider_id'];
$error = "";

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        service_type,
        address,
        city,
        area,
        latitude,
        longitude,
        bio,
        profile_photo
    FROM providers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("
        <h2>Provider Not Found</h2>
        <p>Your provider profile could not be found.</p>
        <a href='dashboard.php'>Back to Dashboard</a>
    ");
}

$provider = $result->fetch_assoc();
$stmt->close();

$locations = [];

$location_stmt = $conn->prepare("
    SELECT city, area
    FROM locations
    WHERE status = 'active'
    ORDER BY city ASC, area ASC
");

if ($location_stmt) {
    $location_stmt->execute();
    $location_result = $location_stmt->get_result();

    while ($row = $location_result->fetch_assoc()) {
        $locations[] = $row;
    }

    $location_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $service_type = trim($_POST['service_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    $profile_photo = $provider['profile_photo'];

    if ($name === '') {
        $error = "Please enter your name.";
    } elseif (strlen($name) < 2) {
        $error = "Name must contain at least 2 characters.";
    } elseif ($phone === '') {
        $error = "Please enter your phone number.";
    } elseif ($city === '') {
        $error = "Please select your city.";
    } elseif ($area === '') {
        $error = "Please select your area.";
    }

    if ($error === '') {

        $location_check = $conn->prepare("
            SELECT id
            FROM locations
            WHERE city = ?
            AND area = ?
            AND status = 'active'
            LIMIT 1
        ");

        if (!$location_check) {
            $error = "Location verification failed.";
        } else {

            $location_check->bind_param(
                "ss",
                $city,
                $area
            );

            $location_check->execute();

            $location_result = $location_check->get_result();

            if ($location_result->num_rows === 0) {
                $error = "Please select a valid city and area.";
            }

            $location_check->close();
        }
    }

    if ($error === '' && $latitude !== '') {

        if (!is_numeric($latitude) || $latitude < -90 || $latitude > 90) {
            $error = "Invalid latitude.";
        }
    }

    if ($error === '' && $longitude !== '') {

        if (!is_numeric($longitude) || $longitude < -180 || $longitude > 180) {
            $error = "Invalid longitude.";
        }
    }

    $latitude_value = null;
    $longitude_value = null;

    if ($error === '') {

        if ($latitude !== '') {
            $latitude_value = (float) $latitude;
        }

        if ($longitude !== '') {
            $longitude_value = (float) $longitude;
        }
    }

    if (
        $error === '' &&
        isset($_FILES['profile_photo']) &&
        $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {

            $error = "Profile photo upload failed.";

        } else {

            $allowed_extensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            $extension = strtolower(
                pathinfo(
                    $_FILES['profile_photo']['name'],
                    PATHINFO_EXTENSION
                )
            );

            if (!in_array($extension, $allowed_extensions, true)) {

                $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";

            } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {

                $error = "Profile photo must be smaller than 5MB.";

            } else {

                $upload_dir = __DIR__ . '/uploads/profile/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_photo =
                    'provider_' .
                    $provider_id .
                    '_' .
                    time() .
                    '_' .
                    uniqid() .
                    '.' .
                    $extension;

                $target = $upload_dir . $new_photo;

                if (
                    move_uploaded_file(
                        $_FILES['profile_photo']['tmp_name'],
                        $target
                    )
                ) {

                    if (
                        !empty($profile_photo) &&
                        file_exists(
                            __DIR__ .
                            '/uploads/profile/' .
                            $profile_photo
                        )
                    ) {
                        unlink(
                            __DIR__ .
                            '/uploads/profile/' .
                            $profile_photo
                        );
                    }

                    $profile_photo = $new_photo;

                } else {

                    $error = "Could not save profile photo.";
                }
            }
        }
    }

    if ($error === '') {

        $update = $conn->prepare("
            UPDATE providers
            SET
                name = ?,
                phone = ?,
                service_type = ?,
                address = ?,
                city = ?,
                area = ?,
                latitude = ?,
                longitude = ?,
                bio = ?,
                profile_photo = ?
            WHERE id = ?
        ");

        if (!$update) {
            die("SQL Error: " . htmlspecialchars($conn->error));
        }

        $update->bind_param(
            "ssssssddssi",
            $name,
            $phone,
            $service_type,
            $address,
            $city,
            $area,
            $latitude_value,
            $longitude_value,
            $bio,
            $profile_photo,
            $provider_id
        );

        if ($update->execute()) {

            $update->close();

            $notification_title = "Profile Updated";
            $notification_message = "Your provider profile and location have been updated successfully.";
            $notification_type = "system";
            $is_read = 0;

            $notification = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    user_role,
                    title,
                    message,
                    type,
                    is_read
                )
                VALUES
                (
                    ?,
                    'provider',
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            if ($notification) {

                $notification->bind_param(
                    "isssi",
                    $provider_id,
                    $notification_title,
                    $notification_message,
                    $notification_type,
                    $is_read
                );

                $notification->execute();
                $notification->close();
            }

            header("Location: profile.php?success=updated");
            exit;

        } else {

            $error = "Profile update failed: " . $update->error;
            $update->close();
        }
    }
}

$current_name = $_POST['name'] ?? $provider['name'];
$current_phone = $_POST['phone'] ?? $provider['phone'];
$current_service_type = $_POST['service_type'] ?? $provider['service_type'];
$current_address = $_POST['address'] ?? $provider['address'];
$current_bio = $_POST['bio'] ?? $provider['bio'];

$current_city = $_POST['city'] ?? $provider['city'];
$current_area = $_POST['area'] ?? $provider['area'];

$current_latitude = $_POST['latitude'] ?? $provider['latitude'];
$current_longitude = $_POST['longitude'] ?? $provider['longitude'];

$locations_json = json_encode(
    $locations,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$current_city_json = json_encode($current_city);
$current_area_json = json_encode($current_area);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Profile - Provider</title>

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
    max-width: 850px;
    margin: 40px auto;
    padding: 20px;
}

.card {
    background: #ffffff;
    padding: 30px;
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

h1 {
    margin: 0 0 8px;
    color: #4b3621;
}

.subtitle {
    color: #777;
    margin-bottom: 25px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 13px 15px;
    border-radius: 9px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input,
textarea,
select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #ddd;
    border-radius: 9px;
    font-size: 15px;
    background: #fff;
    color: #4b3621;
}

input:focus,
textarea:focus,
select:focus {
    outline: none;
    border-color: #d4af37;
    box-shadow: 0 0 0 2px rgba(212,175,55,0.12);
}

input[readonly] {
    background: #f5f5f5;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.location-box {
    background: #fffaf0;
    border: 1px solid #ead7a0;
    border-radius: 14px;
    padding: 22px;
    margin: 25px 0;
}

.location-title {
    font-size: 19px;
    font-weight: bold;
    color: #b8860b;
    margin-bottom: 18px;
}

.location-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.gps-button {
    width: 100%;
    margin-top: 18px;
    background: #d4af37;
    color: #fff;
    border: none;
    padding: 13px;
    border-radius: 9px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

.gps-button:hover {
    background: #b8860b;
}

.gps-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.gps-status {
    margin-top: 10px;
    font-size: 13px;
    color: #777;
}

.coordinates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 18px;
}

.current-photo {
    margin-bottom: 15px;
}

.current-photo img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #d4af37;
}

.no-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 45px;
    margin-bottom: 15px;
}

.help {
    font-size: 12px;
    color: #777;
    margin-top: 6px;
}

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 28px;
}

.save-button {
    border: none;
    background: #d4af37;
    color: white;
    padding: 13px 24px;
    border-radius: 9px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

.save-button:hover {
    background: #b8860b;
}

.back {
    background: #eee;
    color: #4b3621;
    text-decoration: none;
    padding: 13px 24px;
    border-radius: 9px;
    font-weight: bold;
}

.back:hover {
    background: #ddd;
}

.section-title {
    color: #b8860b;
    font-size: 18px;
    font-weight: bold;
    margin: 28px 0 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

@media(max-width: 600px) {

    .container {
        margin: 15px auto;
        padding: 12px;
    }

    .card {
        padding: 20px;
    }

    .location-row,
    .coordinates {
        grid-template-columns: 1fr;
    }

    .buttons {
        flex-direction: column;
    }

    .save-button,
    .back {
        width: 100%;
        text-align: center;
    }
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>Edit Provider Profile</h1>

<div class="subtitle">
Update your provider information and service location.
</div>

<?php if ($error !== ''): ?>

<div class="error">
<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<div class="section-title">
Basic Information
</div>

<div class="form-group">

<label for="name">
Full Name *
</label>

<input
    type="text"
    name="name"
    id="name"
    value="<?= htmlspecialchars($current_name, ENT_QUOTES, 'UTF-8') ?>"
    required
>

</div>

<div class="form-group">

<label for="email">
Email
</label>

<input
    type="email"
    id="email"
    value="<?= htmlspecialchars($provider['email'], ENT_QUOTES, 'UTF-8') ?>"
    readonly
>

<div class="help">
Email cannot be changed here.
</div>

</div>

<div class="form-group">

<label for="phone">
Phone *
</label>

<input
    type="text"
    name="phone"
    id="phone"
    value="<?= htmlspecialchars($current_phone, ENT_QUOTES, 'UTF-8') ?>"
    required
>

</div>

<div class="form-group">

<label for="service_type">
Service Type
</label>

<input
    type="text"
    name="service_type"
    id="service_type"
    placeholder="Example: Catering, Photography, Makeup"
    value="<?= htmlspecialchars($current_service_type, ENT_QUOTES, 'UTF-8') ?>"
>

</div>

<div class="location-box">

<div class="location-title">
Service Location
</div>

<div class="location-row">

<div>

<label for="city">
City *
</label>

<select
    name="city"
    id="city"
    required
>

<option value="">
Select City
</option>

</select>

</div>

<div>

<label for="area">
Area *
</label>

<select
    name="area"
    id="area"
    required
>

<option value="">
Select Area
</option>

</select>

</div>

</div>

<button
    type="button"
    class="gps-button"
    id="gpsButton"
>
Use My Current Location
</button>

<div
    class="gps-status"
    id="gpsStatus"
>
Use your phone or browser location to save your exact service location.
</div>

<div class="coordinates">

<div>

<label for="latitude">
Latitude
</label>

<input
    type="text"
    name="latitude"
    id="latitude"
    value="<?= htmlspecialchars((string) $current_latitude, ENT_QUOTES, 'UTF-8') ?>"
    readonly
>

</div>

<div>

<label for="longitude">
Longitude
</label>

<input
    type="text"
    name="longitude"
    id="longitude"
    value="<?= htmlspecialchars((string) $current_longitude, ENT_QUOTES, 'UTF-8') ?>"
    readonly
>

</div>

</div>

</div>

<div class="form-group">

<label for="address">
Full Address
</label>

<input
    type="text"
    name="address"
    id="address"
    placeholder="Your business/service location"
    value="<?= htmlspecialchars($current_address, ENT_QUOTES, 'UTF-8') ?>"
>

</div>

<div class="form-group">

<label for="bio">
About / Bio
</label>

<textarea
    name="bio"
    id="bio"
    placeholder="Tell customers about yourself and your service..."
><?= htmlspecialchars($current_bio, ENT_QUOTES, 'UTF-8') ?></textarea>

</div>

<div class="section-title">
Profile Photo
</div>

<div class="form-group">

<label>
Current Profile Photo
</label>

<?php if (!empty($provider['profile_photo'])): ?>

<div class="current-photo">

<img
    src="uploads/profile/<?= htmlspecialchars($provider['profile_photo'], ENT_QUOTES, 'UTF-8') ?>"
    alt="Profile Photo"
>

</div>

<?php else: ?>

<div class="no-photo">
👤
</div>

<?php endif; ?>

<label for="profile_photo">
Change Profile Photo
</label>

<input
    type="file"
    name="profile_photo"
    id="profile_photo"
    accept=".jpg,.jpeg,.png,.webp"
>

<div class="help">
JPG, JPEG, PNG or WEBP. Maximum 5MB.
</div>

</div>

<div class="buttons">

<button
    type="submit"
    class="save-button"
>
Save Changes
</button>

<a
    href="profile.php"
    class="back"
>
Cancel
</a>

</div>

</form>

</div>

</div>

<script>

const locations = <?= $locations_json ?>;

const citySelect = document.getElementById("city");
const areaSelect = document.getElementById("area");
const gpsButton = document.getElementById("gpsButton");
const gpsStatus = document.getElementById("gpsStatus");
const latitudeInput = document.getElementById("latitude");
const longitudeInput = document.getElementById("longitude");

const currentCity = <?= $current_city_json ?>;
const currentArea = <?= $current_area_json ?>;

const cities = [
    ...new Set(
        locations.map(location => location.city)
    )
];

cities.forEach(city => {

    const option = document.createElement("option");

    option.value = city;
    option.textContent = city;

    if (city === currentCity) {
        option.selected = true;
    }

    citySelect.appendChild(option);
});

function loadAreas(city, selectedArea = "") {

    areaSelect.innerHTML = "";

    const defaultOption = document.createElement("option");

    defaultOption.value = "";
    defaultOption.textContent = "Select Area";

    areaSelect.appendChild(defaultOption);

    locations
        .filter(location => location.city === city)
        .forEach(location => {

            const option = document.createElement("option");

            option.value = location.area;
            option.textContent = location.area;

            if (location.area === selectedArea) {
                option.selected = true;
            }

            areaSelect.appendChild(option);
        });
}

loadAreas(currentCity || "", currentArea || "");

citySelect.addEventListener("change", function() {

    loadAreas(this.value, "");

});

gpsButton.addEventListener("click", function() {

    if (!navigator.geolocation) {

        gpsStatus.textContent =
            "Your browser does not support GPS location.";

        return;
    }

    gpsStatus.textContent =
        "Getting your current location...";

    gpsButton.disabled = true;

    navigator.geolocation.getCurrentPosition(

        function(position) {

            const latitude =
                position.coords.latitude;

            const longitude =
                position.coords.longitude;

            latitudeInput.value =
                latitude.toFixed(8);

            longitudeInput.value =
                longitude.toFixed(8);

            gpsStatus.textContent =
                "Current location captured successfully.";

            gpsButton.disabled = false;

        },

        function(error) {

            gpsButton.disabled = false;

            if (error.code === 1) {

                gpsStatus.textContent =
                    "Location permission was denied. Please allow location access.";

            } else if (error.code === 2) {

                gpsStatus.textContent =
                    "Your location could not be determined.";

            } else if (error.code === 3) {

                gpsStatus.textContent =
                    "Location request timed out. Please try again.";

            } else {

                gpsStatus.textContent =
                    "Could not get your current location.";
            }
        },

        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
});

</script>

</body>

</html>

