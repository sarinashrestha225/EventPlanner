<?php

require_once "../includes/auth.php";
require_once "../../database.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$id = (int) $_GET["id"];

$stmt = $conn->prepare(
    "SELECT * FROM events WHERE id = ? LIMIT 1"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: index.php");
    exit();
}

$event = $result->fetch_assoc();

$stmt->close();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_name = trim($_POST["event_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $status = $_POST["status"] ?? "active";

    if ($event_name === "") {

        $error = "Event name is required.";

    } else {

        $image_name = $event["image"];

        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] === 0
        ) {

            $extension = strtolower(
                pathinfo(
                    $_FILES["image"]["name"],
                    PATHINFO_EXTENSION
                )
            );

            $allowed = [
                "jpg",
                "jpeg",
                "png",
                "webp"
            ];

            if (!in_array($extension, $allowed)) {

                $error =
                    "Only JPG, JPEG, PNG and WEBP images are allowed.";

            } else {

                $upload_dir = "../../uploads/events/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $image_name =
                    time() . "_" .
                    preg_replace(
                        "/[^a-zA-Z0-9]/",
                        "_",
                        $_FILES["image"]["name"]
                    );

                move_uploaded_file(
                    $_FILES["image"]["tmp_name"],
                    $upload_dir . $image_name
                );
            }
        }

        if ($error === "") {

            $stmt = $conn->prepare(
                "UPDATE events
                 SET event_name = ?,
                     description = ?,
                     image = ?,
                     status = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "ssssi",
                $event_name,
                $description,
                $image_name,
                $status,
                $id
            );

            if ($stmt->execute()) {

                header("Location: index.php");
                exit();

            } else {

                $error = "Failed to update event.";
            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Event | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8ef;
    color: #5a4148;
}

.container {
    max-width: 750px;
    margin: 50px auto;
    padding: 20px;
}

.card {
    background: #fffdf8;
    border: 1px solid #efd48a;
    border-radius: 22px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(180,130,30,0.10);
}

h1 {
    font-family: Georgia, serif;
    color: #7d4b5c;
    margin-bottom: 8px;
}

.subtitle {
    color: #999;
    margin-bottom: 25px;
}

.error {
    background: #ffe1e8;
    color: #a33d55;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #684754;
}

input,
textarea,
select {
    width: 100%;
    padding: 13px;
    border: 1px solid #e7c98c;
    border-radius: 10px;
    background: #fffaf2;
    font-size: 15px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

input:focus,
textarea:focus,
select:focus {
    outline: none;
    border-color: #e2b735;
    box-shadow: 0 0 0 3px #ffe5ef;
}

.current-image {
    margin-top: 10px;
    margin-bottom: 15px;
}

.current-image img {
    width: 150px;
    height: 100px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid #efd48a;
}

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

button,
.back {
    padding: 13px 20px;
    border-radius: 10px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
}

button {
    background: linear-gradient(135deg, #f5c84c, #d9a62e);
    color: white;
}

.back {
    background: #ffdce9;
    color: #704c56;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>✏️ Edit Event</h1>

        <p class="subtitle">
            Update Event Planner event information.
        </p>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">

                <label>Event Name</label>

                <input
                    type="text"
                    name="event_name"
                    value="<?= htmlspecialchars($event["event_name"]) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Description</label>

                <textarea
                    name="description"
                ><?= htmlspecialchars($event["description"] ?? "") ?></textarea>

            </div>

            <?php if (!empty($event["image"])): ?>

                <div class="form-group current-image">

                    <label>Current Image</label>

                    <br>

                    <img
                        src="../../uploads/events/<?= htmlspecialchars($event["image"]) ?>"
                        alt="Event Image"
                    >

                </div>

            <?php endif; ?>

            <div class="form-group">

                <label>Change Image</label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

            </div>

            <div class="form-group">

                <label>Status</label>

                <select name="status">

                    <option
                        value="active"
                        <?= $event["status"] === "active" ? "selected" : "" ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $event["status"] === "inactive" ? "selected" : "" ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div class="buttons">

                <button type="submit">
                    💾 Update Event
                </button>

                <a href="index.php" class="back">
                    ← Back
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>