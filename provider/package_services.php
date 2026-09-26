<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$package_id = (int) ($_GET["id"] ?? $_POST["package_id"] ?? 0);

if ($package_id <= 0) {
    header("Location: packages.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        package_name,
        event_id
    FROM packages
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Package query error: " . $conn->error);
}

$stmt->bind_param("ii", $package_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    header("Location: packages.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selected_services = $_POST["services"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if (!is_array($selected_services)) {
        $selected_services = [];
    }

    $selected_services = array_map("intval", $selected_services);
    $selected_services = array_values(
        array_unique(
            array_filter(
                $selected_services,
                function ($id) {
                    return $id > 0;
                }
            )
        )
    );

    $valid_services = [];

    if (!empty($selected_services)) {

        $placeholders = implode(
            ",",
            array_fill(0, count($selected_services), "?")
        );

        $types = str_repeat("i", count($selected_services));

        $sql = "
            SELECT id
            FROM services
            WHERE provider_id = ?
            AND id IN ($placeholders)
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Service validation error: " . $conn->error;
        } else {

            $params = [$provider_id];
            $bind_types = "i" . $types;

            foreach ($selected_services as $service_id) {
                $params[] = $service_id;
            }

            $stmt->bind_param($bind_types, ...$params);
            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $valid_services[] = (int) $row["id"];
            }

            $stmt->close();
        }
    }

    if ($error === "") {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                DELETE FROM package_services
                WHERE package_id = ?
            ");

            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param("i", $package_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($valid_services)) {

                $stmt = $conn->prepare("
                    INSERT INTO package_services
                    (
                        package_id,
                        service_id,
                        quantity
                    )
                    VALUES (?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception($conn->error);
                }

                foreach ($valid_services as $service_id) {

                    $quantity = (int) ($quantities[$service_id] ?? 1);

                    if ($quantity < 1) {
                        $quantity = 1;
                    }

                    $stmt->bind_param(
                        "iii",
                        $package_id,
                        $service_id,
                        $quantity
                    );

                    $stmt->execute();
                }

                $stmt->close();
            }

            $conn->commit();

            $message = "Package services updated successfully.";

        } catch (Exception $e) {

            $conn->rollback();

            $error = "Failed to update package services.";
        }
    }
}

$services = [];

$stmt = $conn->prepare("
    SELECT
        id,
        service_name,
        category,
        description,
        price,
        unit,
        availability,
        status
    FROM services
    WHERE provider_id = ?
    ORDER BY service_name ASC
");

if ($stmt) {

    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    $stmt->close();
}

$package_services = [];

$stmt = $conn->prepare("
    SELECT
        service_id,
        quantity
    FROM package_services
    WHERE package_id = ?
");

if ($stmt) {

    $stmt->bind_param("i", $package_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $package_services[(int) $row["service_id"]] = (int) $row["quantity"];
    }

    $stmt->close();
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

    <title>Package Services</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf0;
            color: #4a3b2a;
        }

        .container {
            width: 92%;
            max-width: 1000px;
            margin: 35px auto;
        }

        .card {
            background: #ffffff;
            border: 1px solid #ead7a7;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #b8860b;
        }

        .package-name {
            background: #fff4d6;
            border-left: 5px solid #d4af37;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .message {
            background: #e9f8e9;
            color: #28752b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .error {
            background: #ffe8e8;
            color: #b42323;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .service-list {
            display: grid;
            gap: 15px;
        }

        .service-item {
            border: 1px solid #ead7a7;
            border-radius: 12px;
            padding: 18px;
            background: #fffdf7;
        }

        .service-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .service-top input {
            width: 20px;
            height: 20px;
            margin-top: 3px;
        }

        .service-info {
            flex: 1;
        }

        .service-name {
            font-size: 18px;
            font-weight: bold;
            color: #7a5b16;
        }

        .service-meta {
            margin-top: 6px;
            color: #777;
            font-size: 14px;
        }

        .quantity-box {
            margin-top: 12px;
            margin-left: 32px;
        }

        .quantity-box label {
            font-weight: bold;
            font-size: 14px;
        }

        .quantity {
            width: 100px;
            padding: 9px;
            margin-left: 8px;
            border: 1px solid #dfc98a;
            border-radius: 7px;
        }

        .empty {
            text-align: center;
            padding: 35px;
            background: #fff8e8;
            border-radius: 10px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        button,
        .back-btn {
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
        }

        button {
            background: #d4af37;
            color: #ffffff;
        }

        button:hover {
            background: #b8860b;
        }

        .back-btn {
            background: #f3d6df;
            color: #6b3f4b;
        }

        .back-btn:hover {
            background: #edc0ce;
        }

        @media (max-width: 650px) {

            .container {
                width: 95%;
            }

            .card {
                padding: 18px;
            }

            .quantity-box {
                margin-left: 32px;
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

        <h1>Package Services</h1>

        <div class="package-name">
            <strong>Package:</strong>
            <?= htmlspecialchars($package["package_name"]) ?>
        </div>

        <?php if ($message): ?>

            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="package_id"
                value="<?= (int) $package_id ?>"
            >

            <?php if (empty($services)): ?>

                <div class="empty">

                    <h3>No Services Found</h3>

                    <p>
                        Please add a service first from My Services.
                    </p>

                </div>

            <?php else: ?>

                <div class="service-list">

                    <?php foreach ($services as $service): ?>

                        <?php
                        $service_id = (int) $service["id"];
                        $is_selected = isset($package_services[$service_id]);
                        $quantity = $package_services[$service_id] ?? 1;
                        ?>

                        <div class="service-item">

                            <div class="service-top">

                                <input
                                    type="checkbox"
                                    name="services[]"
                                    value="<?= $service_id ?>"
                                    <?= $is_selected ? "checked" : "" ?>
                                >

                                <div class="service-info">

                                    <div class="service-name">
                                        <?= htmlspecialchars($service["service_name"]) ?>
                                    </div>

                                    <div class="service-meta">

                                        Category:
                                        <?= htmlspecialchars($service["category"] ?? "N/A") ?>

                                        |
                                        
                                        Price:
                                        Rs.
                                        <?= number_format((float) $service["price"], 2) ?>

                                        |

                                        Unit:
                                        <?= htmlspecialchars($service["unit"] ?? "service") ?>

                                    </div>

                                </div>

                            </div>

                            <div class="quantity-box">

                                <label>
                                    Quantity
                                </label>

                                <input
                                    class="quantity"
                                    type="number"
                                    name="quantity[<?= $service_id ?>]"
                                    min="1"
                                    value="<?= $quantity ?>"
                                >

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <div class="buttons">

                <button type="submit">
                    Save Package Services
                </button>

                <a
                    href="packages.php"
                    class="back-btn"
                >
                    Back to Packages
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>