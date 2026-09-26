<?php

session_start();

require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "provider") {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["user_id"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: services.php");
    exit();
}

$service_id = (int) $_GET["id"];

$stmt = $conn->prepare("
    SELECT
        id,
        event_id,
        service_name,
        service_image,
        category,
        description,
        price,
        min_price,
        max_price,
        unit,
        status
    FROM services
    WHERE id = ?
    AND provider_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("ii", $service_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$service = $result->fetch_assoc();

$stmt->close();

if (!$service) {
    header("Location: services.php");
    exit();
}

$event_types = [];

$event_stmt = $conn->prepare("
    SELECT id, event_name
    FROM event_types
    ORDER BY event_name ASC
");

if (!$event_stmt) {
    die("Database error: " . $conn->error);
}

$event_stmt->execute();

$event_result = $event_stmt->get_result();

while ($row = $event_result->fetch_assoc()) {
    $event_types[] = $row;
}

$event_stmt->close();

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

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = isset($_POST["event_id"]) ? (int) $_POST["event_id"] : 0;
    $service_name = trim($_POST["service_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");

    $price_input = trim($_POST["price"] ?? "");
    $min_price_input = trim($_POST["min_price"] ?? "");
    $max_price_input = trim($_POST["max_price"] ?? "");

    $price = $price_input === "" ? 0 : (float) $price_input;
    $min_price = $min_price_input === "" ? 0 : (float) $min_price_input;
    $max_price = $max_price_input === "" ? 0 : (float) $max_price_input;

    $unit = trim($_POST["unit"] ?? "Per Event");
    $status = $_POST["status"] ?? "active";

    if ($event_id <= 0) {

        $error = "Please select an event type.";

    } else {

        $event_check = $conn->prepare("
            SELECT id
            FROM event_types
            WHERE id = ?
            LIMIT 1
        ");

        if (!$event_check) {

            $error = "Unable to validate event type.";

        } else {

            $event_check->bind_param("i", $event_id);
            $event_check->execute();

            $event_result = $event_check->get_result();

            if ($event_result->num_rows !== 1) {
                $error = "Selected event type does not exist.";
            }

            $event_check->close();
        }
    }

    if ($error === "" && $service_name === "") {

        $error = "Please enter service name.";

    } elseif ($error === "" && strlen($service_name) > 255) {

        $error = "Service name is too long.";

    } elseif ($error === "" && $price < 0) {

        $error = "Main price cannot be negative.";

    } elseif ($error === "" && $min_price < 0) {

        $error = "Minimum price cannot be negative.";

    } elseif ($error === "" && $max_price < 0) {

        $error = "Maximum price cannot be negative.";

    } elseif ($error === "" && $max_price > 0 && $min_price > $max_price) {

        $error = "Minimum price cannot be greater than maximum price.";

    } elseif ($error === "" && !in_array($category, $categories, true) && $category !== "") {

        $error = "Invalid service category.";

    } elseif ($error === "" && !in_array($unit, $units, true)) {

        $error = "Invalid price unit.";

    } elseif ($error === "" && !in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid service status.";
    }

    $new_image = $service["service_image"];
    $uploaded_new_file = false;
    $new_image_path = "";

    if (
        $error === "" &&
        isset($_FILES["service_image"]) &&
        $_FILES["service_image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["service_image"]["error"] !== UPLOAD_ERR_OK) {

            $error = "Image upload failed.";

        } elseif ($_FILES["service_image"]["size"] <= 0) {

            $error = "Invalid image file.";

        } elseif ($_FILES["service_image"]["size"] > 5 * 1024 * 1024) {

            $error = "Image size must be less than 5MB.";

        } else {

            $allowed_types = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];

            $original_extension = strtolower(
                pathinfo(
                    $_FILES["service_image"]["name"],
                    PATHINFO_EXTENSION
                )
            );

            $mime_type = mime_content_type(
                $_FILES["service_image"]["tmp_name"]
            );

            if (!in_array($original_extension, $allowed_extensions, true)) {

                $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";

            } elseif (!isset($allowed_types[$mime_type])) {

                $error = "Invalid image file type.";

            } else {

                $upload_dir = __DIR__ . "/uploads/services/";

                if (!is_dir($upload_dir)) {

                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "Unable to create upload directory.";
                    }
                }

                if ($error === "") {

                    $extension = $allowed_types[$mime_type];

                    $file_name =
                        "service_" .
                        $provider_id .
                        "_" .
                        time() .
                        "_" .
                        bin2hex(random_bytes(8)) .
                        "." .
                        $extension;

                    $new_image_path = $upload_dir . $file_name;

                    if (
                        move_uploaded_file(
                            $_FILES["service_image"]["tmp_name"],
                            $new_image_path
                        )
                    ) {

                        $new_image = $file_name;
                        $uploaded_new_file = true;

                    } else {

                        $error = "Unable to save uploaded image.";
                    }
                }
            }
        }
    }

    if ($error === "") {

        $update = $conn->prepare("
            UPDATE services
            SET
                event_id = ?,
                service_name = ?,
                service_image = ?,
                category = ?,
                description = ?,
                price = ?,
                min_price = ?,
                max_price = ?,
                unit = ?,
                status = ?
            WHERE id = ?
            AND provider_id = ?
        ");

        if (!$update) {

            if ($uploaded_new_file && file_exists($new_image_path)) {
                unlink($new_image_path);
            }

            $error = "Database error: " . $conn->error;

        } else {

            $update->bind_param(
                "issssdddssii",
                $event_id,
                $service_name,
                $new_image,
                $category,
                $description,
                $price,
                $min_price,
                $max_price,
                $unit,
                $status,
                $service_id,
                $provider_id
            );

            if ($update->execute()) {

                if (
                    $uploaded_new_file &&
                    !empty($service["service_image"])
                ) {

                    $old_image_path =
                        __DIR__ .
                        "/uploads/services/" .
                        basename($service["service_image"]);

                    if (
                        file_exists($old_image_path) &&
                        is_file($old_image_path)
                    ) {
                        unlink($old_image_path);
                    }
                }

                $update->close();
                $conn->close();

                header("Location: services.php?success=updated");
                exit();

            } else {

                if (
                    $uploaded_new_file &&
                    file_exists($new_image_path)
                ) {
                    unlink($new_image_path);
                }

                $error = "Unable to update service.";

                $update->close();
            }
        }
    }

    $service["event_id"] = $event_id;
    $service["service_name"] = $service_name;
    $service["category"] = $category;
    $service["description"] = $description;
    $service["price"] = $price;
    $service["min_price"] = $min_price;
    $service["max_price"] = $max_price;
    $service["unit"] = $unit;
    $service["status"] = $status;

    if (!$uploaded_new_file) {
        $service["service_image"] = $service["service_image"];
    } else {
        $service["service_image"] = $new_image;
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Edit Service - Provider</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f4;
    color: #4a3b35;
}

.header {
    background: linear-gradient(135deg, #f8c8dc, #f4d58d);
    padding: 22px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
}

.header h1 {
    margin: 0;
    font-size: 26px;
    color: #5b4038;
}

.back-btn {
    text-decoration: none;
    background: #fff;
    color: #8a5a00;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: bold;
}

.container {
    max-width: 900px;
    margin: 35px auto;
    padding: 0 20px;
}

.card {
    background: #fff;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.card h2 {
    margin-top: 0;
    color: #7b4f42;
}

.error {
    background: #ffe1e1;
    color: #a40000;
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.current-image {
    margin-bottom: 20px;
}

.current-image img {
    width: 150px;
    height: 110px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid #f0d28b;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.full {
    grid-column: 1 / -1;
}

label {
    font-weight: bold;
    margin-bottom: 8px;
    color: #65463c;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #e5c9b8;
    border-radius: 10px;
    font-size: 15px;
    outline: none;
    background: #fffdfb;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #d6a83d;
    box-shadow: 0 0 0 3px rgba(214, 168, 61, 0.12);
}

textarea {
    min-height: 130px;
    resize: vertical;
}

.help {
    font-size: 13px;
    color: #8b7770;
    margin-top: 6px;
}

.actions {
    margin-top: 25px;
    display: flex;
    gap: 12px;
}

.save-btn,
.cancel-btn {
    border: none;
    padding: 13px 25px;
    border-radius: 10px;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
    text-decoration: none;
}

.save-btn {
    background: #e4b84d;
    color: #fff;
}

.save-btn:hover {
    background: #c99a2f;
}

.cancel-btn {
    background: #f3e5df;
    color: #65463c;
}

@media (max-width: 700px) {

    .header {
        padding: 18px;
        gap: 15px;
    }

    .header h1 {
        font-size: 20px;
    }

    .container {
        margin: 20px auto;
    }

    .card {
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .actions {
        flex-direction: column;
    }

    .save-btn,
    .cancel-btn {
        text-align: center;
    }
}

</style>

</head>

<body>

<div class="header">

    <h1>✏️ Edit Service</h1>

    <a
        href="services.php"
        class="back-btn"
    >
        ← My Services
    </a>

</div>

<div class="container">

<div class="card">

<h2>Update Service Information</h2>

<?php if ($error !== ""): ?>

<div class="error">

❌
<?= htmlspecialchars(
    $error,
    ENT_QUOTES,
    "UTF-8"
) ?>

</div>

<?php endif; ?>

<?php if (!empty($service["service_image"])): ?>

<div class="current-image">

<label>Current Image</label>

<br>

<img
    src="uploads/services/<?= rawurlencode(basename($service["service_image"])) ?>"
    alt="Service Image"
>

</div>

<?php endif; ?>

<form
    method="POST"
    enctype="multipart/form-data"
>

<div class="form-grid">

<div class="form-group">

<label for="event_id">
    Event Type
</label>

<select
    name="event_id"
    id="event_id"
    required
>

<option value="">
    Select Event Type
</option>

<?php foreach ($event_types as $event): ?>

<option
    value="<?= (int) $event["id"] ?>"
    <?= (
        (int) $service["event_id"] ===
        (int) $event["id"]
    ) ? "selected" : "" ?>
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

<label for="service_name">
    Service Name
</label>

<input
    type="text"
    name="service_name"
    id="service_name"
    value="<?= htmlspecialchars(
        $service["service_name"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
    maxlength="255"
    required
>

</div>

<div class="form-group">

<label for="category">
    Category
</label>

<select
    name="category"
    id="category"
    required
>

<?php foreach ($categories as $category): ?>

<option
    value="<?= htmlspecialchars(
        $category,
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
    <?= $service["category"] === $category ? "selected" : "" ?>
>

<?= htmlspecialchars(
    $category,
    ENT_QUOTES,
    "UTF-8"
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group">

<label for="unit">
    Unit
</label>

<select
    name="unit"
    id="unit"
    required
>

<?php foreach ($units as $unit): ?>

<option
    value="<?= htmlspecialchars(
        $unit,
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
    <?= $service["unit"] === $unit ? "selected" : "" ?>
>

<?= htmlspecialchars(
    $unit,
    ENT_QUOTES,
    "UTF-8"
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group">

<label for="price">
    Main Price
</label>

<input
    type="number"
    name="price"
    id="price"
    min="0"
    step="0.01"
    value="<?= htmlspecialchars(
        $service["price"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
    required
>

</div>

<div class="form-group">

<label for="min_price">
    Minimum Price
</label>

<input
    type="number"
    name="min_price"
    id="min_price"
    min="0"
    step="0.01"
    value="<?= htmlspecialchars(
        $service["min_price"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
    required
>

</div>

<div class="form-group">

<label for="max_price">
    Maximum Price
</label>

<input
    type="number"
    name="max_price"
    id="max_price"
    min="0"
    step="0.01"
    value="<?= htmlspecialchars(
        $service["max_price"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
    required
>

</div>

<div class="form-group">

<label for="status">
    Status
</label>

<select
    name="status"
    id="status"
    required
>

<option
    value="active"
    <?= $service["status"] === "active" ? "selected" : "" ?>
>
    Active
</option>

<option
    value="inactive"
    <?= $service["status"] === "inactive" ? "selected" : "" ?>
>
    Inactive
</option>

</select>

</div>

<div class="form-group full">

<label for="description">
    Description
</label>

<textarea
    name="description"
    id="description"
    placeholder="Enter service description"
><?= htmlspecialchars(
    $service["description"],
    ENT_QUOTES,
    "UTF-8"
) ?></textarea>

</div>

<div class="form-group full">

<label for="service_image">
    Replace Service Image
</label>

<input
    type="file"
    name="service_image"
    id="service_image"
    accept=".jpg,.jpeg,.png,.webp"
>

<div class="help">
    Leave empty if you want to keep the current image.
    Maximum 5MB.
</div>

</div>

</div>

<div class="actions">

<button
    type="submit"
    class="save-btn"
>
    💾 Update Service
</button>

<a
    href="services.php"
    class="cancel-btn"
>
    Cancel
</a>

</div>

</form>

</div>

</div>

</body>

</html>