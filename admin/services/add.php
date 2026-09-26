<?php

session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$message = "";
$error = "";

$upload_dir = "../../uploads/services/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
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

    $status = $_POST["status"] ?? "active";

    $image_name = null;

    if ($event_id <= 0) {

        $error = "Please select an event.";

    } elseif ($service_name === "") {

        $error = "Please enter service name.";

    } elseif ($price < 0) {

        $error = "Price cannot be negative.";

    } elseif (
        !in_array(
            $status,
            ["active", "inactive"],
            true
        )
    ) {

        $error = "Invalid status.";

    }

    if ($error === "" && isset($_FILES["image"])) {

        if (
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
                    "image/webp",
                    "image/jpg"
                ];

                $file_type =
                    mime_content_type(
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
                        "Image size must be less than 5MB.";

                } else {

                    $extension =
                        strtolower(
                            pathinfo(
                                $_FILES["image"]["name"],
                                PATHINFO_EXTENSION
                            )
                        );

                    $image_name =
                        uniqid(
                            "service_",
                            true
                        )
                        . "."
                        . $extension;

                    $target =
                        $upload_dir
                        . $image_name;

                    if (
                        !move_uploaded_file(
                            $_FILES["image"]["tmp_name"],
                            $target
                        )
                    ) {

                        $error =
                            "Could not save image.";

                        $image_name = null;
                    }
                }
            }
        }
    }

    if ($error === "") {

        $stmt = $conn->prepare(
            "INSERT INTO services
            (
                event_id,
                service_name,
                description,
                price,
                image,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {

            $error =
                "Database error: "
                . $conn->error;

        } else {

            $stmt->bind_param(
                "issdss",
                $event_id,
                $service_name,
                $description,
                $price,
                $image_name,
                $status
            );

            if ($stmt->execute()) {

                header(
                    "Location: index.php?success=1"
                );

                exit();

            } else {

                $error =
                    "Failed to add service: "
                    . $stmt->error;

                if (
                    $image_name !== null
                    &&
                    file_exists(
                        $upload_dir
                        . $image_name
                    )
                ) {

                    unlink(
                        $upload_dir
                        . $image_name
                    );
                }
            }

            $stmt->close();
        }
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

<title>Add Service | Event Planner</title>

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

    max-width:850px;

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

.image-info{

    font-size:13px;

    color:#8a7478;

    margin-top:7px;
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

@media(max-width:600px){

    .header{

        flex-direction:column;

        gap:10px;

    }

    .card{

        padding:25px 20px;
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

    <div class="card">

        <h1>
            🛎️ Add New Service
        </h1>

        <p class="subtitle">
            Add a service for a specific event.
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
                    Service Name *
                </label>

                <input
                    type="text"
                    name="service_name"
                    placeholder="Example: Bridal Makeup"
                    value="<?= htmlspecialchars(
                        $_POST["service_name"] ?? ""
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

                    <option value="">
                        -- Select Event --
                    </option>

                    <?php while(
                        $event =
                        $events->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $event["id"] ?>"
                            <?= (
                                ($_POST["event_id"] ?? "")
                                == $event["id"]
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
                        placeholder="0"
                        value="<?= htmlspecialchars(
                            $_POST["price"] ?? ""
                        ) ?>"
                        required
                    >

                </div>

            </div>

            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Describe this service..."
                ><?= htmlspecialchars(
                    $_POST["description"] ?? ""
                ) ?></textarea>

            </div>

            <div class="form-group">

                <label>
                    Service Image
                </label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <div class="image-info">
                    JPG, PNG or WEBP only. Maximum 5MB.
                </div>

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
                    ✨ Add Service
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