
<?php

session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$error = "";
$service = null;

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT *
     FROM services
     WHERE id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

$service = $result->fetch_assoc();

$stmt->close();

if (!$service) {
    header("Location: index.php?error=notfound");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = (int)($_POST["event_id"] ?? 0);

    $service_name = trim(
        $_POST["service_name"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $price = (float)(
        $_POST["price"] ?? 0
    );

    $min_price = (float)(
        $_POST["min_price"] ?? 0
    );

    $max_price = (float)(
        $_POST["max_price"] ?? 0
    );

    $unit = trim(
        $_POST["unit"] ?? ""
    );

    $status = $_POST["status"] ?? "active";

    if ($event_id <= 0) {

        $error = "Please select an event.";

    } elseif ($service_name === "") {

        $error = "Please enter service name.";

    } elseif ($price < 0) {

        $error = "Price cannot be negative.";

    } elseif ($min_price < 0) {

        $error = "Minimum price cannot be negative.";

    } elseif ($max_price < 0) {

        $error = "Maximum price cannot be negative.";

    } elseif (
        $min_price > 0 &&
        $max_price > 0 &&
        $min_price > $max_price
    ) {

        $error =
            "Minimum price cannot be greater than maximum price.";

    } elseif (
        !in_array(
            $status,
            ["active", "inactive"],
            true
        )
    ) {

        $error = "Invalid status.";
    }

    $old_image = $service["image"] ?? "";

    $new_image = $old_image;

    $upload_dir = "../../uploads/services/";

    $uploaded_new_image = false;

    if (
        $error === ""
        &&
        isset($_FILES["image"])
        &&
        $_FILES["image"]["error"]
        !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES["image"]["error"]
            !== UPLOAD_ERR_OK
        ) {

            $error = "Image upload failed.";

        } else {

            $allowed_types = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            $file_type = mime_content_type(
                $_FILES["image"]["tmp_name"]
            );

            if (
                !in_array(
                    $file_type,
                    $allowed_types,
                    true
                )
            ) {

                $error =
                    "Only JPG, PNG and WEBP images are allowed.";

            } elseif (
                $_FILES["image"]["size"]
                > 5 * 1024 * 1024
            ) {

                $error =
                    "Image must be less than 5MB.";

            } else {

                if (!is_dir($upload_dir)) {

                    mkdir(
                        $upload_dir,
                        0777,
                        true
                    );
                }

                $extension = strtolower(
                    pathinfo(
                        $_FILES["image"]["name"],
                        PATHINFO_EXTENSION
                    )
                );

                $new_image =
                    uniqid(
                        "service_",
                        true
                    )
                    . "."
                    . $extension;

                if (
                    move_uploaded_file(
                        $_FILES["image"]["tmp_name"],
                        $upload_dir . $new_image
                    )
                ) {

                    $uploaded_new_image = true;

                } else {

                    $error =
                        "Could not save new image.";

                    $new_image = $old_image;
                }
            }
        }
    }

    if ($error === "") {

        $stmt = $conn->prepare(
            "UPDATE services
             SET
                event_id = ?,
                service_name = ?,
                description = ?,
                price = ?,
                min_price = ?,
                max_price = ?,
                unit = ?,
                image = ?,
                status = ?
             WHERE id = ?"
        );

        if (!$stmt) {

            $error =
                "Database error: "
                . $conn->error;

        } else {

            $stmt->bind_param(
                "issdddsssi",
                $event_id,
                $service_name,
                $description,
                $price,
                $min_price,
                $max_price,
                $unit,
                $new_image,
                $status,
                $id
            );

            if ($stmt->execute()) {

                if (
                    $uploaded_new_image
                    &&
                    !empty($old_image)
                    &&
                    $old_image !== $new_image
                ) {

                    $old_path =
                        $upload_dir
                        . $old_image;

                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }

                header(
                    "Location: index.php?updated=1"
                );

                exit();

            } else {

                if (
                    $uploaded_new_image
                    &&
                    file_exists(
                        $upload_dir . $new_image
                    )
                ) {

                    unlink(
                        $upload_dir . $new_image
                    );
                }

                $error =
                    "Update failed: "
                    . $stmt->error;
            }

            $stmt->close();
        }
    }

    $service["event_id"] =
        $event_id;

    $service["service_name"] =
        $service_name;

    $service["description"] =
        $description;

    $service["price"] =
        $price;

    $service["min_price"] =
        $min_price;

    $service["max_price"] =
        $max_price;

    $service["unit"] =
        $unit;

    $service["status"] =
        $status;

    if (!$uploaded_new_image) {
        $service["image"] = $old_image;
    }
}

$events = $conn->query(
    "SELECT id, event_name
     FROM events
     WHERE status = 'active'
     ORDER BY event_name ASC"
);

if (!$events) {

    die(
        "Could not load events: "
        . $conn->error
    );
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

<title>Edit Service | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family: Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #fff8f0,
            #fff0f4,
            #fff8d8
        );

    color: #4b3037;
}

.header {

    background:
        linear-gradient(
            135deg,
            #ffd5df,
            #fff0a8,
            #f2d184
        );

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,.08);
}

.logo {

    font-size: 26px;

    font-weight: bold;

    color: #71394a;
}

.admin {

    background: #fffaf0;

    padding: 10px 18px;

    border-radius: 25px;

    font-weight: bold;

    color: #71394a;
}

.container {

    max-width: 850px;

    margin: 40px auto;

    padding: 0 20px;
}

.card {

    background: white;

    padding: 35px;

    border-radius: 25px;

    box-shadow:
        0 8px 30px
        rgba(0,0,0,.08);
}

.card h1 {

    color: #71394a;

    margin-bottom: 8px;
}

.subtitle {

    color: #8c7076;

    margin-bottom: 30px;
}

.error {

    background: #ffe0e5;

    color: #a33;

    padding: 14px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-weight: bold;
}

.form-group {

    margin-bottom: 22px;
}

label {

    display: block;

    margin-bottom: 8px;

    font-weight: bold;

    color: #63414a;
}

input,
select,
textarea {

    width: 100%;

    padding: 14px;

    border:
        1px solid #e7d3b7;

    border-radius: 12px;

    outline: none;

    background: #fffdf9;

    font-size: 15px;
}

input:focus,
select:focus,
textarea:focus {

    border-color: #e1b94e;

    box-shadow:
        0 0 0 3px
        rgba(225,185,78,.15);
}

textarea {

    min-height: 130px;

    resize: vertical;
}

.price-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px;
}

.price-box {

    position: relative;
}

.price-symbol {

    position: absolute;

    left: 14px;

    top: 14px;

    font-weight: bold;

    color: #a77900;
}

.price-box input {

    padding-left: 42px;
}

.price-help {

    margin-top: 7px;

    font-size: 12px;

    color: #8c7076;

    line-height: 1.5;
}

.current-image {

    margin-top: 10px;
}

.current-image img {

    width: 180px;

    height: 120px;

    object-fit: cover;

    border-radius: 15px;

    border:
        3px solid #f2d184;
}

.buttons {

    display: flex;

    gap: 12px;

    margin-top: 30px;
}

.save-btn {

    border: none;

    cursor: pointer;

    background:
        linear-gradient(
            135deg,
            #e5b942,
            #d9a62c
        );

    color: white;

    padding: 14px 30px;

    border-radius: 25px;

    font-weight: bold;

    font-size: 15px;
}

.cancel-btn {

    text-decoration: none;

    background: #f8d5dc;

    color: #703b49;

    padding: 14px 30px;

    border-radius: 25px;

    font-weight: bold;
}

.save-btn:hover,
.cancel-btn:hover {

    opacity: .85;
}

@media(max-width:600px) {

    .header {

        flex-direction: column;

        gap: 10px;

        text-align: center;
    }

    .card {

        padding: 25px 20px;
    }

    .price-row {

        grid-template-columns: 1fr;
    }

    .buttons {

        flex-direction: column;
    }

    .save-btn,
    .cancel-btn {

        width: 100%;

        text-align: center;
    }

}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        ✦ Event Planner
    </div>

    <div class="admin">
        👑 <?= htmlspecialchars($admin_name) ?>
    </div>

</div>

<div class="container">

    <div class="card">

        <h1>
            ✏️ Edit Service
        </h1>

        <p class="subtitle">
            Update service information and pricing.
        </p>

        <?php if ($error !== ""): ?>

            <div class="error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-group">

                <label>
                    Service Name *
                </label>

                <input
                    type="text"
                    name="service_name"
                    value="<?= htmlspecialchars(
                        $service["service_name"] ?? ""
                    ) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Event *
                </label>

                <select
                    name="event_id"
                    required
                >

                    <?php while (
                        $event =
                        $events->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= (int)$event["id"] ?>"
                            <?= (
                                (int)($service["event_id"] ?? 0)
                                ===
                                (int)$event["id"]
                            )
                            ? "selected"
                            : ""
                            ?>
                        >

                            <?= htmlspecialchars(
                                $event["event_name"]
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Standard Price
                </label>

                <div class="price-box">

                    <span class="price-symbol">
                        Rs.
                    </span>

                    <input
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $service["price"] ?? 0
                        ) ?>"
                    >

                </div>

                <div class="price-help">
                    Use this when the service has one fixed standard price.
                </div>

            </div>

            <div class="form-group">

                <label>
                    Price Range
                </label>

                <div class="price-row">

                    <div>

                        <label>
                            Minimum Price
                        </label>

                        <div class="price-box">

                            <span class="price-symbol">
                                Rs.
                            </span>

                            <input
                                type="number"
                                name="min_price"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $service["min_price"] ?? 0
                                ) ?>"
                            >

                        </div>

                    </div>

                    <div>

                        <label>
                            Maximum Price
                        </label>

                        <div class="price-box">

                            <span class="price-symbol">
                                Rs.
                            </span>

                            <input
                                type="number"
                                name="max_price"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $service["max_price"] ?? 0
                                ) ?>"
                            >

                        </div>

                    </div>

                </div>

                <div class="price-help">
                    Example: Minimum Rs. 50,000 and Maximum Rs. 300,000 will appear on the customer page as Rs. 50,000 - Rs. 300,000.
                </div>

            </div>

            <div class="form-group">

                <label>
                    Service Unit
                </label>

                <input
                    type="text"
                    name="unit"
                    value="<?= htmlspecialchars(
                        $service["unit"] ?? ""
                    ) ?>"
                    placeholder="Example: Per Event, Per Hour, Per Person"
                >

            </div>

            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                ><?= htmlspecialchars(
                    $service["description"] ?? ""
                ) ?></textarea>

            </div>

            <?php

            $current_image =
                $service["image"] ?? "";

            $current_image_path =
                "../../uploads/services/"
                . $current_image;

            ?>

            <?php if (
                !empty($current_image)
                &&
                file_exists($current_image_path)
            ): ?>

                <div class="form-group">

                    <label>
                        Current Image
                    </label>

                    <div class="current-image">

                        <img
                            src="<?= htmlspecialchars(
                                $current_image_path
                            ) ?>"
                            alt="Service Image"
                        >

                    </div>

                </div>

            <?php endif; ?>

            <div class="form-group">

                <label>
                    Change Image
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
                        <?= (
                            ($service["status"] ?? "")
                            === "active"
                        )
                        ? "selected"
                        : ""
                        ?>
                    >
                        🟢 Active
                    </option>

                    <option
                        value="inactive"
                        <?= (
                            ($service["status"] ?? "")
                            === "inactive"
                        )
                        ? "selected"
                        : ""
                        ?>
                    >
                        🔴 Inactive
                    </option>

                </select>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="save-btn"
                >
                    💾 Save Changes
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

