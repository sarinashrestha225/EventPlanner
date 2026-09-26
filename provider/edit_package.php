<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$package_id = (int) ($_GET["id"] ?? $_POST["package_id"] ?? 0);

if ($package_id <= 0) {
    header("Location: packages.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM packages
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Package query error: " . $conn->error);
}

$stmt->bind_param("ii", $package_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    header("Location: packages.php");
    exit;
}

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
    $status = $_POST["status"] ?? "active";
    $min_budget = (float) ($_POST["min_budget"] ?? 0);
    $max_budget = (float) ($_POST["max_budget"] ?? 0);
    $min_price = (float) ($_POST["min_price"] ?? 0);
    $max_price = (float) ($_POST["max_price"] ?? 0);

    $allowed_levels = [
        "standard",
        "good",
        "premium",
        "luxury",
        "vip"
    ];

    if ($event_id <= 0) {

        $error = "Please select an event type.";

    } elseif ($package_name === "") {

        $error = "Package name is required.";

    } elseif (!in_array($experience_level, $allowed_levels, true)) {

        $error = "Invalid experience level.";

    } elseif (
        $price < 0 ||
        $min_budget < 0 ||
        $max_budget < 0 ||
        $min_price < 0 ||
        $max_price < 0
    ) {

        $error = "Price and budget cannot be negative.";

    } elseif ($min_budget > $max_budget) {

        $error = "Minimum budget cannot be greater than maximum budget.";

    } elseif ($min_price > $max_price) {

        $error = "Minimum price cannot be greater than maximum price.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid status.";

    } else {

        $new_image_name = $package["image"] ?? null;
        $old_image_name = $package["image"] ?? null;
        $image_uploaded = false;

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

                    $new_image_name =
                        "package_" .
                        $provider_id .
                        "_" .
                        time() .
                        "_" .
                        bin2hex(random_bytes(5)) .
                        "." .
                        $extension;

                    $new_image_path = $upload_dir . $new_image_name;

                    if (!move_uploaded_file(
                        $_FILES["package_image"]["tmp_name"],
                        $new_image_path
                    )) {

                        $error = "Unable to save package image.";

                        $new_image_name = $old_image_name;

                    } else {

                        $image_uploaded = true;
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

                $event_result = $stmt->get_result();
                $event_exists = $event_result->fetch_assoc();

                $stmt->close();

                if (!$event_exists) {

                    $error = "Selected event type does not exist.";

                } else {

                    $stmt = $conn->prepare("
                        UPDATE packages
                        SET
                            event_id = ?,
                            package_name = ?,
                            experience_level = ?,
                            description = ?,
                            price = ?,
                            image = ?,
                            status = ?,
                            min_budget = ?,
                            max_budget = ?,
                            min_price = ?,
                            max_price = ?
                        WHERE id = ? AND provider_id = ?
                    ");

                    if (!$stmt) {

                        $error = "Package update query error: " . $conn->error;

                    } else {

                        $stmt->bind_param(
                            "isssds sddddii",
                            $event_id,
                            $package_name,
                            $experience_level,
                            $description,
                            $price,
                            $new_image_name,
                            $status,
                            $min_budget,
                            $max_budget,
                            $min_price,
                            $max_price,
                            $package_id,
                            $provider_id
                        );

                        $bind_error = false;

                        if (!$stmt->execute()) {

                            $bind_error = true;
                            $error = "Failed to update package: " . $stmt->error;
                        }

                        $stmt->close();

                        if (!$bind_error && $error === "") {

                            if (
                                $image_uploaded &&
                                $old_image_name &&
                                $old_image_name !== $new_image_name
                            ) {

                                $old_image_path = $upload_dir . basename($old_image_name);

                                if (file_exists($old_image_path)) {
                                    unlink($old_image_path);
                                }
                            }

                            $package["event_id"] = $event_id;
                            $package["package_name"] = $package_name;
                            $package["experience_level"] = $experience_level;
                            $package["description"] = $description;
                            $package["price"] = $price;
                            $package["image"] = $new_image_name;
                            $package["status"] = $status;
                            $package["min_budget"] = $min_budget;
                            $package["max_budget"] = $max_budget;
                            $package["min_price"] = $min_price;
                            $package["max_price"] = $max_price;

                            $message = "Package updated successfully.";
                        }

                        if ($bind_error && $image_uploaded) {

                            $new_image_path = $upload_dir . $new_image_name;

                            if (file_exists($new_image_path)) {
                                unlink($new_image_path);
                            }
                        }
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Package</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf0;
            color: #4a3b2a;
        }

        .container {
            width: 90%;
            max-width: 900px;
            margin: 40px auto;
        }

        .card {
            background: #ffffff;
            border: 1px solid #ead7a7;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #b8860b;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfc98a;
            border-radius: 8px;
            font-size: 15px;
        }

        input[type="file"] {
            background: #fffaf0;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .message {
            background: #e9f8e9;
            color: #28752b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .error {
            background: #ffe8e8;
            color: #b42323;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .image-section {
            margin-top: 18px;
        }

        .current-image {
            width: 260px;
            height: 170px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #ead7a7;
            background: #fffaf0;
            margin-bottom: 12px;
        }

        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .no-image {
            width: 260px;
            height: 170px;
            border-radius: 12px;
            border: 1px solid #ead7a7;
            background: #fffaf0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a98942;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .image-note {
            color: #888;
            font-size: 13px;
            margin-top: 7px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        button,
        .back-btn {
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        button {
            background: #d4af37;
            color: white;
        }

        .back-btn {
            background: #f3d6df;
            color: #6b3f4b;
        }

        button:hover {
            background: #b8860b;
        }

        .back-btn:hover {
            background: #edc0ce;
        }

        @media (max-width: 700px) {

            .row {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 20px;
            }

            .current-image,
            .no-image {
                width: 100%;
                height: 200px;
            }

            .buttons {
                flex-direction: column;
            }

            button,
            .back-btn {
                text-align: center;
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>Edit Package</h1>

        <?php if ($message): ?>

            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="package_id"
                value="<?= (int) $package["id"] ?>"
            >

            <label>Event Type</label>

            <select name="event_id" required>

                <option value="">
                    Select Event
                </option>

                <?php foreach ($events as $event): ?>

                    <option
                        value="<?= (int) $event["id"] ?>"
                        <?= (int) $package["event_id"] === (int) $event["id"] ? "selected" : "" ?>
                    >

                        <?= htmlspecialchars(
                            $event["event_name"]
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <label>Package Name</label>

            <input
                type="text"
                name="package_name"
                value="<?= htmlspecialchars(
                    $package["package_name"]
                ) ?>"
                required
            >

            <label>Experience Level</label>

            <select
                name="experience_level"
                required
            >

                <option
                    value="standard"
                    <?= $package["experience_level"] === "standard" ? "selected" : "" ?>
                >
                    Standard
                </option>

                <option
                    value="good"
                    <?= $package["experience_level"] === "good" ? "selected" : "" ?>
                >
                    Good
                </option>

                <option
                    value="premium"
                    <?= $package["experience_level"] === "premium" ? "selected" : "" ?>
                >
                    Premium
                </option>

                <option
                    value="luxury"
                    <?= $package["experience_level"] === "luxury" ? "selected" : "" ?>
                >
                    Luxury
                </option>

                <option
                    value="vip"
                    <?= $package["experience_level"] === "vip" ? "selected" : "" ?>
                >
                    VIP
                </option>

            </select>

            <div class="image-section">

                <label>Current Package Image</label>

                <?php if (!empty($package["image"])): ?>

                    <div class="current-image">

                        <img
                            src="uploads/packages/<?= htmlspecialchars(
                                $package["image"]
                            ) ?>"
                            alt="Package Image"
                        >

                    </div>

                <?php else: ?>

                    <div class="no-image">
                        No Image
                    </div>

                <?php endif; ?>

                <label>Change Package Image</label>

                <input
                    type="file"
                    name="package_image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <div class="image-note">
                    Leave this empty if you want to keep the current image.
                    JPG, PNG or WEBP. Maximum 5MB.
                </div>

            </div>

            <label>Description</label>

            <textarea
                name="description"
            ><?= htmlspecialchars(
                $package["description"] ?? ""
            ) ?></textarea>

            <div class="row">

                <div>

                    <label>Price</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="price"
                        value="<?= htmlspecialchars(
                            $package["price"]
                        ) ?>"
                        required
                    >

                </div>

                <div>

                    <label>Status</label>

                    <select name="status">

                        <option
                            value="active"
                            <?= $package["status"] === "active" ? "selected" : "" ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $package["status"] === "inactive" ? "selected" : "" ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>

            <div class="row">

                <div>

                    <label>Minimum Budget</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="min_budget"
                        value="<?= htmlspecialchars(
                            $package["min_budget"]
                        ) ?>"
                    >

                </div>

                <div>

                    <label>Maximum Budget</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="max_budget"
                        value="<?= htmlspecialchars(
                            $package["max_budget"]
                        ) ?>"
                    >

                </div>

            </div>

            <div class="row">

                <div>

                    <label>Minimum Price</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="min_price"
                        value="<?= htmlspecialchars(
                            $package["min_price"]
                        ) ?>"
                    >

                </div>

                <div>

                    <label>Maximum Price</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="max_price"
                        value="<?= htmlspecialchars(
                            $package["max_price"]
                        ) ?>"
                    >

                </div>

            </div>

            <div class="buttons">

                <button type="submit">
                    Update Package
                </button>

                <a
                    href="packages.php"
                    class="back-btn"
                >
                    Back
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>