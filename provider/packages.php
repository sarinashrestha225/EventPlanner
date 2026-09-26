<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];

$message = "";
$error = "";

$upload_dir = __DIR__ . "/uploads/packages/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = (int) ($_POST["event_id"] ?? 0);
    $package_name = trim($_POST["package_name"] ?? "");
    $experience_level = $_POST["experience_level"] ?? "standard";
    $description = trim($_POST["description"] ?? "");
    $price = (float) ($_POST["price"] ?? 0);
    $min_budget = (float) ($_POST["min_budget"] ?? 0);
    $max_budget = (float) ($_POST["max_budget"] ?? 0);
    $min_price = (float) ($_POST["min_price"] ?? 0);
    $max_price = (float) ($_POST["max_price"] ?? 0);
    $status = $_POST["status"] ?? "active";

    $image_name = null;

    if ($event_id <= 0) {

        $error = "Please select an event type.";

    } elseif ($package_name === "") {

        $error = "Please enter package name.";

    } elseif (!in_array($experience_level, ["standard", "good", "premium", "luxury", "vip"], true)) {

        $error = "Invalid experience level.";

    } elseif ($price < 0) {

        $error = "Price cannot be negative.";

    } elseif (
        $min_budget < 0 ||
        $max_budget < 0 ||
        $min_price < 0 ||
        $max_price < 0
    ) {

        $error = "Budget and price values cannot be negative.";

    } elseif ($max_budget > 0 && $min_budget > $max_budget) {

        $error = "Minimum budget cannot be greater than maximum budget.";

    } elseif ($max_price > 0 && $min_price > $max_price) {

        $error = "Minimum price cannot be greater than maximum price.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid status.";

    } else {

        if (
            isset($_FILES["package_image"]) &&
            $_FILES["package_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["package_image"]["error"] !== UPLOAD_ERR_OK) {

                $error = "Unable to upload package image.";

            } elseif ($_FILES["package_image"]["size"] > 5 * 1024 * 1024) {

                $error = "Package image must be 5MB or smaller.";

            } else {

                $allowed_types = [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];

                $file_type = mime_content_type(
                    $_FILES["package_image"]["tmp_name"]
                );

                if (!in_array($file_type, $allowed_types, true)) {

                    $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";

                } else {

                    $extension = strtolower(
                        pathinfo(
                            $_FILES["package_image"]["name"],
                            PATHINFO_EXTENSION
                        )
                    );

                    $image_name = "package_" .
                        $provider_id . "_" .
                        time() . "_" .
                        bin2hex(random_bytes(5)) .
                        "." .
                        $extension;

                    $image_path = $upload_dir . $image_name;

                    if (!move_uploaded_file(
                        $_FILES["package_image"]["tmp_name"],
                        $image_path
                    )) {

                        $error = "Unable to save package image.";
                        $image_name = null;
                    }
                }
            }
        }

        if ($error === "") {

            $stmt = $conn->prepare("
                SELECT id
                FROM event_types
                WHERE id = ? AND status = 'active'
            ");

            if (!$stmt) {

                $error = "Event type query error: " . $conn->error;

            } else {

                $stmt->bind_param("i", $event_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $event_exists = $result->fetch_assoc();

                $stmt->close();

                if (!$event_exists) {

                    $error = "Selected event type does not exist.";

                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO packages
                        (
                            provider_id,
                            event_id,
                            package_name,
                            experience_level,
                            description,
                            price,
                            image,
                            status,
                            min_budget,
                            max_budget,
                            min_price,
                            max_price
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {

                        $error = "Package insert query error: " . $conn->error;

                    } else {

                        $stmt->bind_param(
                            "iisssdssdddd",
                            $provider_id,
                            $event_id,
                            $package_name,
                            $experience_level,
                            $description,
                            $price,
                            $image_name,
                            $status,
                            $min_budget,
                            $max_budget,
                            $min_price,
                            $max_price
                        );

                        if ($stmt->execute()) {

                            $message = "Package created successfully.";

                        } else {

                            $error = "Unable to create package: " . $stmt->error;

                            if ($image_name !== null) {

                                $saved_image = $upload_dir . $image_name;

                                if (file_exists($saved_image)) {
                                    unlink($saved_image);
                                }
                            }
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

$events = [];

$result = $conn->query("
    SELECT
        id,
        event_name
    FROM event_types
    WHERE status = 'active'
    ORDER BY event_name ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

$packages = [];

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.provider_id,
        p.event_id,
        p.package_name,
        p.experience_level,
        p.description,
        p.price,
        p.image,
        p.status,
        p.created_at,
        p.min_budget,
        p.max_budget,
        p.min_price,
        p.max_price,
        e.event_name AS event_name
    FROM packages p
    LEFT JOIN event_types e
        ON p.event_id = e.id
    WHERE p.provider_id = ?
    ORDER BY p.id DESC
");

if (!$stmt) {
    die("Package list query error: " . $conn->error);
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $packages[] = $row;
}

$stmt->close();

$current_page = basename($_SERVER["PHP_SELF"]);

include __DIR__ . "/includes/header.php";
include __DIR__ . "/includes/sidebar.php";

?>

<div class="provider-content">

    <div class="page-header">

        <div>

            <h1>My Packages</h1>

            <p>
                Create and manage your event packages.
            </p>

        </div>

    </div>

    <?php if ($message !== ""): ?>

        <div class="alert success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="package-form-card">

        <h2>Create New Package</h2>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-grid">

                <div class="form-group">

                    <label>Event Type</label>

                    <select name="event_id" required>

                        <option value="">
                            Select Event Type
                        </option>

                        <?php foreach ($events as $event): ?>

                            <option value="<?= (int) $event["id"] ?>">

                                <?= htmlspecialchars(
                                    $event["event_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Package Name</label>

                    <input
                        type="text"
                        name="package_name"
                        placeholder="Example: Premium Wedding Package"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Experience Level</label>

                    <select name="experience_level">

                        <option value="standard">
                            Standard
                        </option>

                        <option value="good">
                            Good
                        </option>

                        <option value="premium">
                            Premium
                        </option>

                        <option value="luxury">
                            Luxury
                        </option>

                        <option value="vip">
                            VIP
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Package Price</label>

                    <input
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                    >

                </div>

                <div class="form-group">

                    <label>Package Image</label>

                    <input
                        type="file"
                        name="package_image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        JPG, PNG or WEBP. Maximum 5MB.
                    </small>

                </div>

                <div class="form-group">

                    <label>Minimum Budget</label>

                    <input
                        type="number"
                        name="min_budget"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </div>

                <div class="form-group">

                    <label>Maximum Budget</label>

                    <input
                        type="number"
                        name="max_budget"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </div>

                <div class="form-group">

                    <label>Minimum Price</label>

                    <input
                        type="number"
                        name="min_price"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </div>

                <div class="form-group">

                    <label>Maximum Price</label>

                    <input
                        type="number"
                        name="max_price"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </div>

                <div class="form-group full-width">

                    <label>Description</label>

                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Describe what is included in this package..."
                    ></textarea>

                </div>

                <div class="form-group">

                    <label>Status</label>

                    <select name="status">

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                    </select>

                </div>

            </div>

            <button
                type="submit"
                class="btn-primary"
            >
                Create Package
            </button>

        </form>

    </div>

    <div class="packages-section">

        <div class="section-title">

            <h2>My Packages</h2>

            <span>
                <?= count($packages) ?> Packages
            </span>

        </div>

        <?php if (empty($packages)): ?>

            <div class="empty-state">

                <h3>
                    No packages yet
                </h3>

                <p>
                    Create your first package using the form above.
                </p>

            </div>

        <?php else: ?>

            <div class="package-grid">

                <?php foreach ($packages as $package): ?>

                    <div class="package-card">

                        <?php if (!empty($package["image"])): ?>

                            <div class="package-image">

                                <img
                                    src="uploads/packages/<?= htmlspecialchars($package["image"]) ?>"
                                    alt="<?= htmlspecialchars($package["package_name"]) ?>"
                                >

                            </div>

                        <?php else: ?>

                            <div class="package-image no-image">

                                <span>
                                    No Image
                                </span>

                            </div>

                        <?php endif; ?>

                        <div class="package-top">

                            <div>

                                <span class="event-badge">

                                    <?= htmlspecialchars(
                                        $package["event_name"] ?? "Event"
                                    ) ?>

                                </span>

                                <h3>

                                    <?= htmlspecialchars(
                                        $package["package_name"]
                                    ) ?>

                                </h3>

                            </div>

                            <span
                                class="status-badge <?= $package["status"] === "active" ? "active" : "inactive" ?>"
                            >

                                <?= ucfirst(
                                    $package["status"]
                                ) ?>

                            </span>

                        </div>

                        <div class="level">

                            <?= ucfirst(
                                $package["experience_level"]
                            ) ?>

                        </div>

                        <p class="description">

                            <?= htmlspecialchars(
                                $package["description"] ?? ""
                            ) ?>

                        </p>

                        <div class="package-price">

                            Rs.
                            <?= number_format(
                                (float) $package["price"],
                                2
                            ) ?>

                        </div>

                        <div class="price-details">

                            <div>

                                <span>
                                    Budget
                                </span>

                                <strong>

                                    Rs.
                                    <?= number_format(
                                        (float) $package["min_budget"],
                                        2
                                    ) ?>

                                    -

                                    Rs.
                                    <?= number_format(
                                        (float) $package["max_budget"],
                                        2
                                    ) ?>

                                </strong>

                            </div>

                            <div>

                                <span>
                                    Price Range
                                </span>

                                <strong>

                                    Rs.
                                    <?= number_format(
                                        (float) $package["min_price"],
                                        2
                                    ) ?>

                                    -

                                    Rs.
                                    <?= number_format(
                                        (float) $package["max_price"],
                                        2
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                        <div class="package-actions">

                            <a
                                href="edit_package.php?id=<?= (int) $package["id"] ?>"
                                class="btn-edit"
                            >
                                Edit
                            </a>

                            <a
                                href="package_services.php?id=<?= (int) $package["id"] ?>"
                                class="btn-services"
                            >
                                Manage Services
                            </a>

                            <a
                                href="package_pricing.php?id=<?= (int) $package["id"] ?>"
                                class="btn-pricing"
                            >
                                Manage Pricing
                            </a>

                            <a
                                href="toggle_package.php?id=<?= (int) $package["id"] ?>"
                                class="btn-toggle"
                            >

                                <?= $package["status"] === "active"
                                    ? "Deactivate"
                                    : "Activate" ?>

                            </a>

                            <a
                                href="delete_package.php?id=<?= (int) $package["id"] ?>"
                                class="btn-delete"
                                onclick="return confirm('Delete this package?')"
                            >
                                Delete
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<style>

.provider-content {
    padding: 30px;
    background: #fffaf0;
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
    color: #6b4b16;
}

.page-header p {
    margin-top: 6px;
    color: #777;
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
}

.alert.success {
    background: #fff1b8;
    color: #725200;
    border: 1px solid #e7c75f;
}

.alert.error {
    background: #ffe3e3;
    color: #9d1c1c;
    border: 1px solid #e5aaaa;
}

.package-form-card {
    background: #fff;
    border: 1px solid #ead9a5;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(176, 137, 50, 0.08);
}

.package-form-card h2,
.section-title h2 {
    color: #6b4b16;
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 600;
    color: #5f4a28;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #dbc98e;
    border-radius: 9px;
    background: #fffdf7;
    font-size: 14px;
}

.form-group input[type="file"] {
    padding: 10px;
    background: #fffaf0;
}

.form-group small {
    color: #888;
    font-size: 12px;
}

.form-group textarea {
    resize: vertical;
}

.btn-primary {
    margin-top: 20px;
    border: none;
    background: #d4af37;
    color: #fff;
    padding: 12px 22px;
    border-radius: 9px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary:hover {
    background: #b99420;
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.section-title span {
    background: #fff1b8;
    color: #725200;
    padding: 7px 12px;
    border-radius: 20px;
    font-weight: 600;
}

.package-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.package-card {
    background: #fff;
    border: 1px solid #ead9a5;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(176, 137, 50, 0.08);
    overflow: hidden;
}

.package-image {
    width: 100%;
    height: 190px;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 18px;
    background: #fffaf0;
    border: 1px solid #ead9a5;
}

.package-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.package-image.no-image {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a98942;
    font-weight: 700;
    font-size: 14px;
}

.package-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.package-top h3 {
    color: #5d4318;
    margin: 10px 0 0;
}

.event-badge {
    display: inline-block;
    background: #f9e8a6;
    color: #725200;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 700;
}

.status-badge {
    height: fit-content;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 700;
}

.status-badge.active {
    background: #e1f5e4;
    color: #237b35;
}

.status-badge.inactive {
    background: #f3e2e2;
    color: #9d3030;
}

.level {
    margin-top: 12px;
    color: #b38a19;
    font-weight: 700;
}

.description {
    color: #777;
    min-height: 55px;
    line-height: 1.5;
}

.package-price {
    font-size: 22px;
    font-weight: 800;
    color: #a97900;
    margin: 15px 0;
}

.price-details {
    display: grid;
    gap: 10px;
    background: #fffaf0;
    padding: 12px;
    border-radius: 10px;
}

.price-details div {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.price-details span {
    color: #777;
}

.price-details strong {
    color: #5d4318;
    text-align: right;
}

.package-actions {
    display: flex;
    gap: 8px;
    margin-top: 18px;
    flex-wrap: wrap;
}

.package-actions a {
    flex: 1;
    min-width: 100px;
    text-align: center;
    text-decoration: none;
    padding: 9px 8px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
}

.btn-edit {
    background: #fff1b8;
    color: #725200;
}

.btn-services {
    background: #f3d6df;
    color: #6b3f4b;
}

.btn-pricing {
    background: #f7d98a;
    color: #6b4b16;
}

.btn-toggle {
    background: #f5e6c4;
    color: #76591d;
}

.btn-delete {
    background: #ffe0e0;
    color: #a32626;
}

.btn-edit:hover {
    background: #f5df82;
}

.btn-services:hover {
    background: #edc0ce;
}

.btn-pricing:hover {
    background: #e9c45f;
}

.btn-toggle:hover {
    background: #ead5a7;
}

.btn-delete:hover {
    background: #ffcaca;
}

.empty-state {
    background: #fff;
    border: 1px solid #ead9a5;
    border-radius: 16px;
    padding: 45px;
    text-align: center;
}

.empty-state h3 {
    color: #6b4b16;
}

.empty-state p {
    color: #777;
}

@media (max-width: 1000px) {

    .package-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: auto;
    }

    .package-grid {
        grid-template-columns: 1fr;
    }

    .provider-content {
        padding: 18px;
    }

    .package-actions {
        flex-direction: column;
    }

    .package-actions a {
        width: 100%;
    }

}

</style>