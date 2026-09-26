<?php

session_start();

require_once __DIR__ . "/../../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$errors = [];

$event_id = "";
$package_name = "";
$experience_level = "";
$description = "";
$status = "active";

$min_guests = "";
$max_guests = "";
$min_price = "";
$max_price = "";

$events = [];

$event_query = "
    SELECT id, event_name
    FROM event_types
    WHERE status = 'active'
    ORDER BY event_name ASC
";

$event_result = $conn->query($event_query);

if ($event_result) {
    while ($event = $event_result->fetch_assoc()) {
        $events[] = $event;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = isset($_POST["event_id"])
        ? (int) $_POST["event_id"]
        : 0;

    $package_name = trim(
        $_POST["package_name"] ?? ""
    );

    $experience_level = trim(
        $_POST["experience_level"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $status = $_POST["status"] ?? "active";

    $min_guests = isset($_POST["min_guests"])
        ? (int) $_POST["min_guests"]
        : 0;

    $max_guests = isset($_POST["max_guests"])
        ? (int) $_POST["max_guests"]
        : 0;

    $min_price = isset($_POST["min_price"])
        ? (float) $_POST["min_price"]
        : 0;

    $max_price = isset($_POST["max_price"])
        ? (float) $_POST["max_price"]
        : 0;

    if ($event_id <= 0) {
        $errors[] = "Please select an event.";
    }

    if ($package_name === "") {
        $errors[] = "Package name is required.";
    }

    if ($min_guests <= 0) {
        $errors[] = "Minimum guests must be greater than 0.";
    }

    if ($max_guests <= 0) {
        $errors[] = "Maximum guests must be greater than 0.";
    }

    if (
        $min_guests > 0 &&
        $max_guests > 0 &&
        $min_guests > $max_guests
    ) {
        $errors[] =
            "Minimum guests cannot be greater than maximum guests.";
    }

    if ($min_price < 0) {
        $errors[] =
            "Minimum price cannot be negative.";
    }

    if ($max_price < 0) {
        $errors[] =
            "Maximum price cannot be negative.";
    }

    if (
        $min_price > 0 &&
        $max_price > 0 &&
        $min_price > $max_price
    ) {
        $errors[] =
            "Minimum price cannot be greater than maximum price.";
    }

    if (
        !in_array(
            $status,
            ["active", "inactive"],
            true
        )
    ) {
        $errors[] = "Invalid package status.";
    }

    $image_path = "";

    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES["image"]["error"] !== UPLOAD_ERR_OK
        ) {
            $errors[] =
                "There was an error uploading the image.";
        } else {

            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];

            $file_name =
                $_FILES["image"]["name"];

            $file_size =
                $_FILES["image"]["size"];

            $tmp_name =
                $_FILES["image"]["tmp_name"];

            $extension = strtolower(
                pathinfo(
                    $file_name,
                    PATHINFO_EXTENSION
                )
            );

            if (
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {
                $errors[] =
                    "Only JPG, JPEG, PNG and WEBP images are allowed.";
            }

            if ($file_size > 5 * 1024 * 1024) {
                $errors[] =
                    "Image size must be less than 5 MB.";
            }

            if (
                empty($errors) &&
                !is_uploaded_file($tmp_name)
            ) {
                $errors[] =
                    "Invalid image upload.";
            }

            if (empty($errors)) {

                $upload_dir =
                    __DIR__ .
                    "/../../uploads/packages/";

                if (
                    !is_dir($upload_dir)
                ) {
                    mkdir(
                        $upload_dir,
                        0777,
                        true
                    );
                }

                $new_file_name =
                    uniqid(
                        "package_",
                        true
                    ) .
                    "." .
                    $extension;

                $destination =
                    $upload_dir .
                    $new_file_name;

                if (
                    move_uploaded_file(
                        $tmp_name,
                        $destination
                    )
                ) {
                    $image_path =
                        "uploads/packages/" .
                        $new_file_name;
                } else {
                    $errors[] =
                        "Failed to save the uploaded image.";
                }
            }
        }
    }

    if (empty($errors)) {

        $conn->begin_transaction();

        try {

            $event_check = $conn->prepare("
                SELECT id
                FROM event_types
                WHERE id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $event_check->bind_param(
                "i",
                $event_id
            );

            $event_check->execute();

            $event_result =
                $event_check->get_result();

            if (
                $event_result->num_rows !== 1
            ) {
                throw new Exception(
                    "Selected event was not found."
                );
            }

            $duplicate_check = $conn->prepare("
                SELECT id
                FROM packages
                WHERE event_id = ?
                AND package_name = ?
                LIMIT 1
            ");

            $duplicate_check->bind_param(
                "is",
                $event_id,
                $package_name
            );

            $duplicate_check->execute();

            $duplicate_result =
                $duplicate_check->get_result();

            if (
                $duplicate_result->num_rows > 0
            ) {
                throw new Exception(
                    "This package already exists for the selected event."
                );
            }

            $insert = $conn->prepare("
                INSERT INTO packages
                (
                    event_id,
                    package_name,
                    experience_level,
                    description,
                    price,
                    image,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $package_price =
                $min_price > 0
                    ? $min_price
                    : 0;

            $insert->bind_param(
                "isssdss",
                $event_id,
                $package_name,
                $experience_level,
                $description,
                $package_price,
                $image_path,
                $status
            );

            if (!$insert->execute()) {
                throw new Exception(
                    "Failed to create package: " .
                    $insert->error
                );
            }

            $package_id =
                $conn->insert_id;

            $pricing = $conn->prepare("
                INSERT INTO package_pricing
                (
                    package_id,
                    min_guests,
                    max_guests,
                    original_price,
                    combo_price
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $pricing->bind_param(
                "iiidd",
                $package_id,
                $min_guests,
                $max_guests,
                $min_price,
                $max_price
            );

            if (!$pricing->execute()) {
                throw new Exception(
                    "Failed to save package pricing: " .
                    $pricing->error
                );
            }

            $conn->commit();

            header(
                "Location: index.php?success=added"
            );

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            if (
                !empty($image_path) &&
                file_exists(
                    __DIR__ .
                    "/../../" .
                    $image_path
                )
            ) {
                unlink(
                    __DIR__ .
                    "/../../" .
                    $image_path
                );
            }

            $errors[] =
                $e->getMessage();
        }
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

<title>Add Package - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fffaf2;
    color: #333;
}

.header {
    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #ffd966
        );
    padding: 20px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: #7a4b00;
}

.admin-name {
    font-weight: bold;
    color: #7a4b00;
}

.container {
    width: 95%;
    max-width: 900px;
    margin: 35px auto;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.10);
}

h1 {
    color: #8a5a00;
    margin-top: 0;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #6f4b00;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border:
        1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
    background: #fffdf8;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.row {
    display: grid;
    grid-template-columns:
        1fr 1fr;
    gap: 18px;
}

.error-box {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error-box div {
    margin-bottom: 5px;
}

.button-row {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.save-btn {
    border: none;
    background: #d4af37;
    color: white;
    padding: 13px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 15px;
}

.save-btn:hover {
    background: #b8941f;
}

.cancel-btn {
    text-decoration: none;
    background: #f8c8dc;
    color: #7a2348;
    padding: 13px 25px;
    border-radius: 8px;
    font-weight: bold;
}

@media(max-width: 650px) {

    .row {
        grid-template-columns: 1fr;
    }

    .header {
        padding: 18px;
    }

    .card {
        padding: 20px;
    }

}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        ✦ Event Planner
    </div>

    <div class="admin-name">

        👑
        <?= htmlspecialchars(
            $admin_name,
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </div>

</div>

<div class="container">

    <div class="card">

        <h1>
            ➕ Add New Package
        </h1>

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <?php foreach (
                    $errors
                    as $error
                ): ?>

                    <div>
                        ❌
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-group">

                <label>
                    Event Type
                </label>

                <select
                    name="event_id"
                    required
                >

                    <option value="">
                        Select Event
                    </option>

                    <?php foreach (
                        $events
                        as $event
                    ): ?>

                        <option
                            value="<?= (int)$event["id"] ?>"
                            <?= $event_id == $event["id"]
                                ? "selected"
                                : ""
                            ?>
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

                <label>
                    Package Name
                </label>

                <select
                    name="package_name"
                    required
                >

                    <option value="">
                        Select Package
                    </option>

                    <?php
                    $package_names = [
                        "Basic",
                        "Silver",
                        "Premium",
                        "Luxury",
                        "VIP"
                    ];
                    ?>

                    <?php foreach (
                        $package_names
                        as $name
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $name,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            <?= $package_name === $name
                                ? "selected"
                                : ""
                            ?>
                        >

                            <?= htmlspecialchars(
                                $name,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Experience Level
                </label>

                <input
                    type="text"
                    name="experience_level"
                    value="<?= htmlspecialchars(
                        $experience_level,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    placeholder="Example: Standard, Premium, Luxury"
                >

            </div>

            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Enter package description"
                ><?= htmlspecialchars(
                    $description,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

            </div>

            <div class="row">

                <div class="form-group">

                    <label>
                        Minimum Guests
                    </label>

                    <input
                        type="number"
                        name="min_guests"
                        min="1"
                        value="<?= htmlspecialchars(
                            (string)$min_guests,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Example: 50"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Maximum Guests
                    </label>

                    <input
                        type="number"
                        name="max_guests"
                        min="1"
                        value="<?= htmlspecialchars(
                            (string)$max_guests,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Example: 100"
                        required
                    >

                </div>

            </div>

            <div class="row">

                <div class="form-group">

                    <label>
                        Minimum Price
                    </label>

                    <input
                        type="number"
                        name="min_price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            (string)$min_price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Example: 300000"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Maximum Price
                    </label>

                    <input
                        type="number"
                        name="max_price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            (string)$max_price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Example: 500000"
                        required
                    >

                </div>

            </div>

            <div class="form-group">

                <label>
                    Package Image
                </label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

            </div>

            <div class="form-group">

                <label>
                    Status
                </label>

                <select name="status">

                    <option
                        value="active"
                        <?= $status === "active"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $status === "inactive"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div class="button-row">

                <button
                    type="submit"
                    class="save-btn"
                >
                    💾 Save Package
                </button>

                <a
                    href="index.php"
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