<?php

session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $venue_name = trim($_POST["venue_name"] ?? "");

    $venue_type = $_POST["venue_type"] ?? "";

    $location_id = (int)(
        $_POST["location_id"] ?? 0
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $capacity = (int)(
        $_POST["capacity"] ?? 0
    );

    $price = (float)(
        $_POST["price"] ?? 0
    );

    $status = $_POST["status"] ?? "active";

    $allowed_types = [
        "Party Palace",
        "Resort",
        "Hotel",
        "Banquet",
        "Outdoor Venue",
        "Other"
    ];

    if ($venue_name === "") {

        $error = "Please enter venue name.";

    } elseif (!in_array(
        $venue_type,
        $allowed_types,
        true
    )) {

        $error = "Please select a valid venue type.";

    } elseif ($location_id <= 0) {

        $error = "Please select a location.";

    } elseif ($capacity < 0) {

        $error = "Capacity cannot be negative.";

    } elseif ($price < 0) {

        $error = "Price cannot be negative.";

    } elseif (!in_array(
        $status,
        ["active", "inactive"],
        true
    )) {

        $error = "Invalid status.";

    }

    $image_name = null;

    $upload_dir = "../../uploads/venues/";

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

            $allowed_image_types = [
                "image/jpeg",
                "image/png",
                "image/webp",
                "image/jpg"
            ];

            $file_type = mime_content_type(
                $_FILES["image"]["tmp_name"]
            );

            if (!in_array(
                $file_type,
                $allowed_image_types,
                true
            )) {

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

                $image_name =
                    uniqid(
                        "venue_",
                        true
                    )
                    . "."
                    . $extension;

                if (!move_uploaded_file(
                    $_FILES["image"]["tmp_name"],
                    $upload_dir . $image_name
                )) {

                    $error =
                        "Could not save image.";

                    $image_name = null;
                }
            }
        }
    }

    if ($error === "") {

        $stmt = $conn->prepare("
            INSERT INTO venues
            (
                venue_name,
                venue_type,
                location_id,
                description,
                capacity,
                price,
                image,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {

            $error =
                "Database error: "
                . $conn->error;

        } else {

            $stmt->bind_param(
                "ssisidss",
                $venue_name,
                $venue_type,
                $location_id,
                $description,
                $capacity,
                $price,
                $image_name,
                $status
            );

            if ($stmt->execute()) {

                header(
                    "Location: index.php?added=1"
                );

                exit();

            } else {

                if (
                    $image_name !== null
                    &&
                    file_exists(
                        $upload_dir . $image_name
                    )
                ) {

                    unlink(
                        $upload_dir . $image_name
                    );
                }

                $error =
                    "Could not add venue: "
                    . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$locations = $conn->query("
    SELECT
        id,
        city,
        area
    FROM locations
    WHERE status = 'active'
    ORDER BY city ASC, area ASC
");

if (!$locations) {

    die(
        "Could not load locations: "
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

<title>Add Venue | Event Planner</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial,sans-serif;
}

body{

    min-height:100vh;

    background:
        linear-gradient(
            135deg,
            #fff8f0,
            #fff0f4,
            #fff8d8
        );

    color:#4b3037;
}

.header{

    background:
        linear-gradient(
            135deg,
            #ffd5df,
            #fff0a8,
            #f2d184
        );

    padding:18px 30px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,.08);
}

.logo{

    font-size:26px;

    font-weight:bold;

    color:#71394a;
}

.admin{

    background:#fffaf0;

    padding:10px 18px;

    border-radius:25px;

    font-weight:bold;
}

.container{

    max-width:900px;

    margin:40px auto;

    padding:0 20px;
}

.back-link{

    display:inline-block;

    margin-bottom:20px;

    text-decoration:none;

    color:#71394a;

    font-weight:bold;
}

.card{

    background:white;

    padding:35px;

    border-radius:25px;

    box-shadow:
        0 8px 30px
        rgba(0,0,0,.08);
}

.card h1{

    color:#71394a;

    margin-bottom:8px;
}

.subtitle{

    color:#8c7076;

    margin-bottom:30px;
}

.error{

    background:#ffe0e5;

    color:#a33;

    padding:14px;

    border-radius:12px;

    margin-bottom:20px;

    font-weight:bold;
}

.form-group{

    margin-bottom:22px;
}

label{

    display:block;

    margin-bottom:8px;

    font-weight:bold;

    color:#63414a;
}

input,
select,
textarea{

    width:100%;

    padding:14px;

    border:
        1px solid #e7d3b7;

    border-radius:12px;

    outline:none;

    background:#fffdf9;

    font-size:15px;
}

input:focus,
select:focus,
textarea:focus{

    border-color:#e1b94e;

    box-shadow:
        0 0 0 3px
        rgba(225,185,78,.15);
}

textarea{

    min-height:130px;

    resize:vertical;
}

.form-row{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:20px;
}

.price-box{

    position:relative;
}

.price-symbol{

    position:absolute;

    left:14px;

    top:14px;

    font-weight:bold;

    color:#a77900;
}

.price-box input{

    padding-left:42px;
}

.file-help{

    display:block;

    margin-top:7px;

    color:#999;

    font-size:13px;
}

.buttons{

    display:flex;

    gap:12px;

    margin-top:30px;
}

.save-btn{

    border:none;

    cursor:pointer;

    background:
        linear-gradient(
            135deg,
            #e5b942,
            #d9a62c
        );

    color:white;

    padding:14px 30px;

    border-radius:25px;

    font-weight:bold;

    font-size:15px;
}

.cancel-btn{

    text-decoration:none;

    background:#f8d5dc;

    color:#703b49;

    padding:14px 30px;

    border-radius:25px;

    font-weight:bold;
}

.save-btn:hover,
.cancel-btn:hover{

    opacity:.85;
}

@media(max-width:650px){

    .header{

        flex-direction:column;

        gap:10px;
    }

    .card{

        padding:25px 20px;
    }

    .form-row{

        grid-template-columns:1fr;
    }

    .buttons{

        flex-direction:column;
    }

    .save-btn,
    .cancel-btn{

        text-align:center;

        width:100%;
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

    <a
        href="index.php"
        class="back-link"
    >
        ← Back to Manage Venues
    </a>

    <div class="card">

        <h1>
            ➕ Add New Venue
        </h1>

        <p class="subtitle">
            Add Party Palace, Resort, Hotel, Banquet or other event venues.
        </p>

        <?php if ($error !== ""): ?>

            <div class="error">

                ⚠️
                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-group">

                <label>
                    Venue Name *
                </label>

                <input
                    type="text"
                    name="venue_name"
                    value="<?= htmlspecialchars(
                        $_POST["venue_name"] ?? ""
                    ) ?>"
                    placeholder="Example: Rose Party Palace"
                    required
                >

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>
                        Venue Type *
                    </label>

                    <select
                        name="venue_type"
                        required
                    >

                        <option value="">
                            Select Venue Type
                        </option>

                        <option value="Party Palace">
                            Party Palace
                        </option>

                        <option value="Resort">
                            Resort
                        </option>

                        <option value="Hotel">
                            Hotel
                        </option>

                        <option value="Banquet">
                            Banquet
                        </option>

                        <option value="Outdoor Venue">
                            Outdoor Venue
                        </option>

                        <option value="Other">
                            Other
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>
                        Location *
                    </label>

                    <select
                        name="location_id"
                        required
                    >

                        <option value="">
                            Select Location
                        </option>

                        <?php while (
                            $location =
                            $locations->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= $location["id"] ?>"
                                <?= (
                                    ($_POST["location_id"] ?? "")
                                    == $location["id"]
                                )
                                ? "selected"
                                : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $location["city"]
                                ) ?>

                                →

                                <?= htmlspecialchars(
                                    $location["area"]
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>
                        Capacity *
                    </label>

                    <input
                        type="number"
                        name="capacity"
                        min="0"
                        value="<?= htmlspecialchars(
                            $_POST["capacity"] ?? ""
                        ) ?>"
                        placeholder="Example: 500"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Price *
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
                                $_POST["price"] ?? ""
                            ) ?>"
                            placeholder="Example: 150000"
                            required
                        >

                    </div>

                </div>

            </div>

            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Write venue details, facilities, parking, decoration space, catering facilities etc."
                ><?= htmlspecialchars(
                    $_POST["description"] ?? ""
                ) ?></textarea>

            </div>

            <div class="form-group">

                <label>
                    Venue Image
                </label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <span class="file-help">
                    JPG, PNG or WEBP only. Maximum 5MB.
                </span>

            </div>

            <div class="form-group">

                <label>
                    Status
                </label>

                <select name="status">

                    <option
                        value="active"
                        <?= (
                            ($_POST["status"] ?? "active")
                            === "active"
                        )
                        ? "selected"
                        : ""
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= (
                            ($_POST["status"] ?? "")
                            === "inactive"
                        )
                        ? "selected"
                        : ""
                        ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="save-btn"
                >
                    💾 Add Venue
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