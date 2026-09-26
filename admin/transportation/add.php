<?php
session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
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

    $image_name = "";

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

            $image_name = time() . "_" . uniqid() . "." . $extension;

            move_uploaded_file(
                $_FILES["image"]["tmp_name"],
                $upload_dir . $image_name
            );
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO transportation
        (
            vehicle_name,
            vehicle_type,
            capacity,
            price,
            description,
            image,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssidsss",
        $vehicle_name,
        $vehicle_type,
        $capacity,
        $price,
        $description,
        $image_name,
        $status
    );

    if ($stmt->execute()) {
        header("Location: index.php");
        exit();
    }

    $message = "Transportation could not be added.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transportation</title>

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
    <h1>🚗 Add Transportation</h1>
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
                    placeholder="Example: Toyota Hiace"
                    required
                >
            </div>

            <div class="form-group">
                <label>Vehicle Type</label>

                <select name="vehicle_type" required>
                    <option value="">Select Vehicle Type</option>
                    <option value="Car">Car</option>
                    <option value="Van">Van</option>
                    <option value="Hiace">Hiace</option>
                    <option value="Bus">Bus</option>
                    <option value="Luxury Car">Luxury Car</option>
                    <option value="Jeep">Jeep</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Capacity</label>

                <input
                    type="number"
                    name="capacity"
                    min="1"
                    placeholder="Number of passengers"
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
                    placeholder="Transportation price"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description</label>

                <textarea
                    name="description"
                    placeholder="Enter transportation details"
                ></textarea>
            </div>

            <div class="form-group">
                <label>Image</label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >
            </div>

            <div class="form-group">
                <label>Status</label>

                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="buttons">

                <button type="submit" class="save">
                    Save Transportation
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