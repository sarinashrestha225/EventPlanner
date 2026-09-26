<?php

session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$error = "";
$venue = null;

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT *
    FROM venues
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

$venue = $result->fetch_assoc();

$stmt->close();

if (!$venue) {
    die("Venue not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $venue_name = trim(
        $_POST["venue_name"] ?? ""
    );

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

    $old_image = $venue["image"];

    $new_image = $old_image;

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

            $allowed_types_image = [
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
                $allowed_types_image,
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

                $new_image =
                    uniqid(
                        "venue_",
                        true
                    )
                    . "."
                    . $extension;

                if (!move_uploaded_file(
                    $_FILES["image"]["tmp_name"],
                    $upload_dir . $new_image
                )) {

                    $error =
                        "Could not save new image.";

                    $new_image = $old_image;
                }
            }
        }
    }

    if ($error === "") {

        $stmt = $conn->prepare("
            UPDATE venues
            SET
                venue_name = ?,
                venue_type = ?,
                location_id = ?,
                description = ?,
                capacity = ?,
                price = ?,
                image = ?,
                status = ?
            WHERE id = ?
        ");

        if (!$stmt) {

            $error =
                "Database error: "
                . $conn->error;

        } else {

            $stmt->bind_param(
                "ssisidssi",
                $venue_name,
                $venue_type,
                $location_id,
                $description,
                $capacity,
                $price,
                $new_image,
                $status,
                $id
            );

            if ($stmt->execute()) {

                if (
                    !empty($old_image)
                    &&
                    $new_image !== $old_image
                    &&
                    file_exists(
                        $upload_dir . $old_image
                    )
                ) {

                    unlink(
                        $upload_dir . $old_image
                    );
                }

                header(
                    "Location: index.php?updated=1"
                );

                exit();

            } else {

                $error =
                    "Update failed: "
                    . $stmt->error;
            }

            $stmt->close();
        }
    }

    $venue["venue_name"] =
        $venue_name;

    $venue["venue_type"] =
        $venue_type;

    $venue["location_id"] =
        $location_id;

    $venue["description"] =
        $description;

    $venue["capacity"] =
        $capacity;

    $venue["price"] =
        $price;

    $venue["status"] =
        $status;

    $venue["image"] =
        $new_image;
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

<title>Edit Venue | Event Planner</title>

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

.current-image{

    margin-top:10px;
}

.current-image img{

    width:200px;

    height:140px;

    object-fit:cover;

    border-radius:15px;

    border:3px solid #f2d184;
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

.back-link{

    display:inline-block;

    margin-bottom:20px;

    text-decoration:none;

    color:#71394a;

    font-weight:bold;
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
            ✏️ Edit Venue
        </h1>

        <p class="subtitle">
            Update venue information, location, price and image.
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
                        $venue["venue_name"]
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

                        <?php

                        $venue_types = [

                            "Party Palace",

                            "Resort",

                            "Hotel",

                            "Banquet",

                            "Outdoor Venue",

                            "Other"

                        ];

                        foreach (
                            $venue_types
                            as $type
                        ):

                        ?>

                            <option
                                value="<?= htmlspecialchars($type) ?>"
                                <?= $venue["venue_type"] === $type
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars($type) ?>

                            </option>

                        <?php endforeach; ?>

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
                                    $venue["location_id"]
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
                            $venue["capacity"]
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
                                $venue["price"]
                            ) ?>"
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
                    placeholder="Write venue details..."
                ><?= htmlspecialchars(
                    $venue["description"] ?? ""
                ) ?></textarea>

            </div>

            <?php if (
                !empty($venue["image"])
                &&
                file_exists(
                    "../../uploads/venues/"
                    . $venue["image"]
                )
            ): ?>

                <div class="form-group">

                    <label>
                        Current Image
                    </label>

                    <div class="current-image">

                        <img
                            src="../../uploads/venues/<?= htmlspecialchars(
                                $venue["image"]
                            ) ?>"
                            alt="Venue Image"
                        >

                    </div>

                </div>

            <?php endif; ?>

            <div class="form-group">

                <label>
                    Change Venue Image
                </label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small>
                    JPG, PNG or WEBP — maximum 5MB
                </small>

            </div>

            <div class="form-group">

                <label>
                    Status
                </label>

                <select name="status">

                    <option
                        value="active"
                        <?= $venue["status"] === "active"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $venue["status"] === "inactive"
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