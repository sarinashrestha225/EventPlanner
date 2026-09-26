<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["provider_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["provider_id"];

$categories = [
    "Catering",
    "DJ",
    "Beauty",
    "Transportation",
    "Photography",
    "Decoration",
    "Venue",
    "Entertainment",
    "Other"
];

$units = [
    "Per Event",
    "Per Person",
    "Per Hour",
    "Per Day",
    "Per Vehicle",
    "Per Package"
];

$event_types = [];
$error = "";

$event_stmt = $conn->prepare("
    SELECT id, event_name
    FROM event_types
    ORDER BY event_name ASC
");

if ($event_stmt) {
    $event_stmt->execute();
    $event_result = $event_stmt->get_result();

    while ($row = $event_result->fetch_assoc()) {
        $event_types[] = $row;
    }

    $event_stmt->close();
}

$event_id = "";
$service_name = "";
$category = "";
$description = "";
$price = "";
$min_price = "";
$max_price = "";
$unit = "Per Event";
$status = "active";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = (int) ($_POST["event_id"] ?? 0);
    $service_name = trim($_POST["service_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $min_price = trim($_POST["min_price"] ?? "");
    $max_price = trim($_POST["max_price"] ?? "");
    $unit = trim($_POST["unit"] ?? "Per Event");
    $status = trim($_POST["status"] ?? "active");

    if ($event_id <= 0) {
        $error = "Please select an event type.";
    } elseif ($service_name === "") {
        $error = "Please enter service name.";
    } elseif (strlen($service_name) > 255) {
        $error = "Service name is too long.";
    } elseif ($price === "" || !is_numeric($price)) {
        $error = "Please enter a valid price.";
    } elseif ((float) $price < 0) {
        $error = "Price cannot be negative.";
    } elseif ($min_price !== "" && !is_numeric($min_price)) {
        $error = "Please enter a valid minimum price.";
    } elseif ($max_price !== "" && !is_numeric($max_price)) {
        $error = "Please enter a valid maximum price.";
    } elseif ($min_price !== "" && (float) $min_price < 0) {
        $error = "Minimum price cannot be negative.";
    } elseif ($max_price !== "" && (float) $max_price < 0) {
        $error = "Maximum price cannot be negative.";
    } elseif (
        $max_price !== "" &&
        $min_price !== "" &&
        (float) $max_price > 0 &&
        (float) $min_price > (float) $max_price
    ) {
        $error = "Minimum price cannot be greater than maximum price.";
    } elseif (
        $category !== "" &&
        !in_array($category, $categories, true)
    ) {
        $error = "Invalid service category.";
    } elseif (!in_array($unit, $units, true)) {
        $error = "Invalid service unit.";
    } elseif (!in_array($status, ["active", "inactive"], true)) {
        $error = "Invalid service status.";
    }

    if ($error === "") {

        $check_event = $conn->prepare("
            SELECT id
            FROM event_types
            WHERE id = ?
            LIMIT 1
        ");

        if (!$check_event) {
            $error = "Database error: " . $conn->error;
        } else {

            $check_event->bind_param("i", $event_id);
            $check_event->execute();

            $event_check_result = $check_event->get_result();

            if (
                !$event_check_result ||
                $event_check_result->num_rows !== 1
            ) {
                $error = "Selected event type does not exist.";
            }

            $check_event->close();
        }
    }

    $service_image = "";

    if ($error === "" && isset($_FILES["service_image"])) {

        if ($_FILES["service_image"]["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES["service_image"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Unable to upload service image.";
            } elseif ($_FILES["service_image"]["size"] > 5 * 1024 * 1024) {
                $error = "Image size must be 5MB or less.";
            } else {

                $original_name = $_FILES["service_image"]["name"];
                $tmp_name = $_FILES["service_image"]["tmp_name"];

                $extension = strtolower(
                    pathinfo($original_name, PATHINFO_EXTENSION)
                );

                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                if (!in_array($extension, $allowed_extensions, true)) {
                    $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                } else {

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);

                    if (!$finfo) {
                        $error = "Unable to verify image type.";
                    } else {

                        $mime_type = finfo_file($finfo, $tmp_name);
                        finfo_close($finfo);

                        $allowed_mimes = [
                            "image/jpeg",
                            "image/png",
                            "image/webp"
                        ];

                        if (!in_array($mime_type, $allowed_mimes, true)) {
                            $error = "Invalid image file.";
                        }
                    }
                }

                if ($error === "") {

                    $upload_dir = __DIR__ . "/uploads/services/";

                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $error = "Unable to create upload directory.";
                        }
                    }

                    if ($error === "") {

                        try {
                            $random_name = bin2hex(random_bytes(8));
                        } catch (Exception $e) {
                            $random_name = uniqid("", true);
                        }

                        $new_filename =
                            "provider_" .
                            $provider_id .
                            "_" .
                            $random_name .
                            "." .
                            $extension;

                        $destination =
                            $upload_dir .
                            $new_filename;

                        if (!move_uploaded_file($tmp_name, $destination)) {
                            $error = "Unable to save service image.";
                        } else {
                            $service_image = $new_filename;
                        }
                    }
                }
            }
        }
    }

    if ($error === "") {

        $price_value = (float) $price;
        $min_price_value = $min_price === ""
            ? 0
            : (float) $min_price;
        $max_price_value = $max_price === ""
            ? 0
            : (float) $max_price;

        $stmt = $conn->prepare("
            INSERT INTO services
            (
                event_id,
                provider_id,
                service_name,
                service_image,
                category,
                description,
                price,
                min_price,
                max_price,
                unit,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {

            if ($service_image !== "") {
                $image_path =
                    __DIR__ .
                    "/uploads/services/" .
                    basename($service_image);

                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "iissssdddss",
                $event_id,
                $provider_id,
                $service_name,
                $service_image,
                $category,
                $description,
                $price_value,
                $min_price_value,
                $max_price_value,
                $unit,
                $status
            );

            if ($stmt->execute()) {

                $stmt->close();
                $conn->close();

                header("Location: services.php?success=added");
                exit();

            } else {

                if ($service_image !== "") {
                    $image_path =
                        __DIR__ .
                        "/uploads/services/" .
                        basename($service_image);

                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }

                $error = "Unable to add service.";
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Service</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f5f7;
            color: #333;
        }

        .container {
            width: 92%;
            max-width: 900px;
            margin: 40px auto;
        }

        .card {
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
            color: #b8860b;
        }

        .error {
            background: #ffe5e5;
            color: #b00020;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        button,
        .back-btn {
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
        }

        button {
            background: #d4af37;
            color: #fff;
        }

        .back-btn {
            background: #eee;
            color: #333;
        }

        button:hover {
            background: #b8961f;
        }

        .back-btn:hover {
            background: #ddd;
        }

        @media (max-width: 650px) {
            .container {
                width: 95%;
                margin: 20px auto;
            }

            .card {
                padding: 20px;
            }

            .row {
                grid-template-columns: 1fr;
                gap: 0;
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

        <h1>Add New Service</h1>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label for="event_id">Event Type</label>

                <select name="event_id" id="event_id" required>
                    <option value="">Select Event Type</option>

                    <?php foreach ($event_types as $event): ?>
                        <option
                            value="<?= (int) $event["id"] ?>"
                            <?= (string) $event_id === (string) $event["id"] ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars(
                                $event["event_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_name">Service Name</label>

                <input
                    type="text"
                    name="service_name"
                    id="service_name"
                    maxlength="255"
                    value="<?= htmlspecialchars(
                        $service_name,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="category">Category</label>

                <select name="category" id="category">
                    <option value="">Select Category</option>

                    <?php foreach ($categories as $item): ?>
                        <option
                            value="<?= htmlspecialchars(
                                $item,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            <?= $category === $item ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars(
                                $item,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>

                <textarea
                    name="description"
                    id="description"
                ><?= htmlspecialchars(
                    $description,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>
            </div>

            <div class="row">

                <div class="form-group">
                    <label for="price">Price</label>

                    <input
                        type="number"
                        name="price"
                        id="price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="unit">Unit</label>

                    <select name="unit" id="unit" required>

                        <?php foreach ($units as $item): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $item,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                <?= $unit === $item ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars(
                                    $item,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <div class="row">

                <div class="form-group">
                    <label for="min_price">Minimum Price</label>

                    <input
                        type="number"
                        name="min_price"
                        id="min_price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $min_price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="max_price">Maximum Price</label>

                    <input
                        type="number"
                        name="max_price"
                        id="max_price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $max_price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >
                </div>

            </div>

            <div class="form-group">
                <label for="status">Status</label>

                <select name="status" id="status" required>

                    <option
                        value="active"
                        <?= $status === "active" ? "selected" : "" ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $status === "inactive" ? "selected" : "" ?>
                    >
                        Inactive
                    </option>

                </select>
            </div>

            <div class="form-group">
                <label for="service_image">Service Image</label>

                <input
                    type="file"
                    name="service_image"
                    id="service_image"
                    accept=".jpg,.jpeg,.png,.webp"
                >
            </div>

            <div class="buttons">

                <button type="submit">
                    Add Service
                </button>

                <a
                    href="services.php"
                    class="back-btn"
                >
                    Back to My Services
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>