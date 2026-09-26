<?php
session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$stmt = $conn->prepare("
    SELECT *
    FROM transportation
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$transportation = $result->fetch_assoc();

if (!$transportation) {
    header("Location: index.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $vehicle_name = trim($_POST["vehicle_name"]);
    $vehicle_type = trim($_POST["vehicle_type"]);
    $capacity = (int) $_POST["capacity"];
    $price = (float) $_POST["price"];
    $description = trim($_POST["description"]);
    $status = $_POST["status"];

    $image_name = $transportation["image"];

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] === 0) {

        $upload_dir = "../../uploads/transportation/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $extension = strtolower(
            pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION)
        );

        $allowed = ["jpg", "jpeg", "png", "webp"];

        if (in_array($extension, $allowed)) {

            if (!empty($transportation["image"])) {

                $old_image = $upload_dir . $transportation["image"];

                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }

            $image_name = time() . "_" . uniqid() . "." . $extension;

            move_uploaded_file(
                $_FILES["image"]["tmp_name"],
                $upload_dir . $image_name
            );
        }
    }

    $update = $conn->prepare("
        UPDATE transportation
        SET
            vehicle_name = ?,
            vehicle_type = ?,
            capacity = ?,
            price = ?,
            description = ?,
            image = ?,
            status = ?
        WHERE id = ?
    ");

    $update->bind_param(
        "ssidsssi",
        $vehicle_name,
        $vehicle_type,
        $capacity,
        $price,
        $description,
        $image_name,
        $status,
        $id
    );

    if ($update->execute()) {
        header("Location: index.php");
        exit();
    }

    $message = "Transportation could not be updated.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transportation</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf3;
        }

        .header {
            background: linear-gradient(135deg, #f8c8dc, #f6d365);
            padding: 20px 30px;
        }

        .header h1 {
            margin: 0;
            color: #5a3d00;
        }

        .container {
            max-width: 800px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .form-box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #5a3d00;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .current-image {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
            display: block;
        }

        .buttons {
            display: flex;
            gap: 12px;
        }

        .save {
            border: none;
            background: #d4af37;
            color: white;
            padding: 12px 25px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .cancel {
            background: #eee;
            color: #555;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 7px;
        }

        .message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 7px;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>🚗 Edit Transportation</h1>
</div>

<div class="container">

    <div class="form-box">

        <?php if ($message): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Vehicle Name</label>

                <input
                    type="text"
                    name="vehicle_name"
                    value="<?= htmlspecialchars($transportation["vehicle_name"]) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Vehicle Type</label>

                <select name="vehicle_type" required>
                    <option value="">Select Vehicle Type</option>

                    <?php
                    $types = [
                        "Car",
                        "Van",
                        "Hiace",
                        "Bus",
                        "Luxury Car",
                        "Jeep",
                        "Other"
                    ];
                    ?>

                    <?php foreach ($types as $type): ?>

                        <option
                            value="<?= $type ?>"
                            <?= $transportation["vehicle_type"] === $type ? "selected" : "" ?>
                        >
                            <?= $type ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label>Capacity</label>

                <input
                    type="number"
                    name="capacity"
                    min="1"
                    value="<?= $transportation["capacity"] ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Price</label>

                <input
                    type="number"
                    name="price"
                    min="0"
                    step="0.01"
                    value="<?= $transportation["price"] ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description</label>

                <textarea name="description"><?= htmlspecialchars($transportation["description"]) ?></textarea>
            </div>

            <div class="form-group">
                <label>Current Image</label>

                <?php if (!empty($transportation["image"])): ?>

                    <img
                        src="../../uploads/transportation/<?= htmlspecialchars($transportation["image"]) ?>"
                        class="current-image"
                    >

                <?php else: ?>

                    <p>No image uploaded.</p>

                <?php endif; ?>
            </div>

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
                        <?= $transportation["status"] === "active" ? "selected" : "" ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $transportation["status"] === "inactive" ? "selected" : "" ?>
                    >
                        Inactive
                    </option>

                </select>
            </div>

            <div class="buttons">

                <button type="submit" class="save">
                    Update Transportation
                </button>

                <a href="index.php" class="cancel">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>