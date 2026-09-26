<?php

require_once "../../database.php";
require_once "../includes/auth.php";

$error = "";
$success = "";

$events = [];

$event_result = $conn->query("
    SELECT
        id,
        event_name
    FROM events
    WHERE status = 'active'
    ORDER BY event_name ASC
");

if ($event_result) {
    while ($row = $event_result->fetch_assoc()) {
        $events[] = $row;
    }
}

$services = [];

$service_result = $conn->query("
    SELECT
        id,
        service_name,
        category,
        min_price,
        max_price,
        unit
    FROM services
    WHERE status = 'active'
    ORDER BY service_name ASC
");

if ($service_result) {
    while ($row = $service_result->fetch_assoc()) {
        $services[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $event_id = isset($_POST["event_id"])
        ? (int) $_POST["event_id"]
        : 0;

    $package_name = trim(
        $_POST["package_name"] ?? ""
    );

    $experience_level =
        $_POST["experience_level"] ?? "standard";

    $description = trim(
        $_POST["description"] ?? ""
    );

    $price = isset($_POST["price"])
        ? (float) $_POST["price"]
        : 0;

    $min_budget = isset($_POST["min_budget"])
        ? (float) $_POST["min_budget"]
        : 0;

    $max_budget = isset($_POST["max_budget"])
        ? (float) $_POST["max_budget"]
        : 0;

    $min_price = isset($_POST["min_price"])
        ? (float) $_POST["min_price"]
        : 0;

    $max_price = isset($_POST["max_price"])
        ? (float) $_POST["max_price"]
        : 0;

    $status = $_POST["status"] ?? "active";

    $selected_services =
        $_POST["services"] ?? [];

    $quantities =
        $_POST["quantity"] ?? [];

    $allowed_levels = [
        "standard",
        "good",
        "premium",
        "luxury",
        "vip"
    ];

    if ($event_id <= 0) {

        $error = "Please select an event.";

    } elseif ($package_name === "") {

        $error = "Package name is required.";

    } elseif (!in_array(
        $experience_level,
        $allowed_levels,
        true
    )) {

        $error = "Invalid experience level.";

    } elseif ($price < 0) {

        $error = "Price cannot be negative.";

    } elseif ($min_budget < 0 || $max_budget < 0) {

        $error = "Budget values cannot be negative.";

    } elseif ($max_budget > 0 && $min_budget > $max_budget) {

        $error =
            "Minimum budget cannot be greater than maximum budget.";

    } elseif ($min_price < 0 || $max_price < 0) {

        $error = "Price range cannot be negative.";

    } elseif ($max_price > 0 && $min_price > $max_price) {

        $error =
            "Minimum price cannot be greater than maximum price.";

    } elseif (!in_array(
        $status,
        ["active", "inactive"],
        true
    )) {

        $error = "Invalid status.";

    } elseif (empty($selected_services)) {

        $error = "Please select at least one service.";

    }

    if ($error === "") {

        $check_event = $conn->prepare("
            SELECT id
            FROM events
            WHERE id = ?
            LIMIT 1
        ");

        if (!$check_event) {

            $error =
                "Database Error: " .
                htmlspecialchars($conn->error);

        } else {

            $check_event->bind_param(
                "i",
                $event_id
            );

            $check_event->execute();

            $event_check_result =
                $check_event->get_result();

            if ($event_check_result->num_rows !== 1) {

                $error = "Selected event does not exist.";

            }

            $check_event->close();
        }
    }

    if ($error === "") {

        $image_name = null;

        if (
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES["image"]["error"] !== UPLOAD_ERR_OK
            ) {

                $error = "Image upload failed.";

            } else {

                $extension = strtolower(
                    pathinfo(
                        $_FILES["image"]["name"],
                        PATHINFO_EXTENSION
                    )
                );

                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                if (!in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )) {

                    $error =
                        "Only JPG, JPEG, PNG and WEBP images are allowed.";

                } else {

                    $upload_dir = "../../uploads/packages/";

                    if (!is_dir($upload_dir)) {

                        mkdir(
                            $upload_dir,
                            0777,
                            true
                        );
                    }

                    $image_name =
                        time() . "_" .
                        preg_replace(
                            "/[^a-zA-Z0-9]/",
                            "_",
                            pathinfo(
                                $_FILES["image"]["name"],
                                PATHINFO_FILENAME
                            )
                        ) .
                        "." .
                        $extension;

                    if (!move_uploaded_file(
                        $_FILES["image"]["tmp_name"],
                        $upload_dir . $image_name
                    )) {

                        $error =
                            "Unable to upload package image.";

                    }
                }
            }
        }
    }

    if ($error === "") {

        $conn->begin_transaction();

        try {

            $package_stmt = $conn->prepare("
                INSERT INTO packages (
                    provider_id,
                    event_id,
                    package_name,
                    experience_level,
                    description,
                    price,
                    image,
                    status,
                    min_budget,
                    max_budget,
                    min_price,
                    max_price
                )
                VALUES (
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$package_stmt) {
                throw new Exception(
                    $conn->error
                );
            }

            $package_stmt->bind_param(
                "isssds sdddd",
                $event_id,
                $package_name,
                $experience_level,
                $description,
                $price,
                $image_name,
                $status,
                $min_budget,
                $max_budget,
                $min_price,
                $max_price
            );

            $package_stmt->execute();

            $package_id =
                $conn->insert_id;

            $package_stmt->close();

            $service_stmt = $conn->prepare("
                INSERT INTO package_services (
                    package_id,
                    service_id,
                    quantity
                )
                VALUES (?, ?, ?)
            ");

            if (!$service_stmt) {
                throw new Exception(
                    $conn->error
                );
            }

            foreach (
                $selected_services
                as $service_id
            ) {

                $service_id =
                    (int) $service_id;

                $quantity =
                    isset($quantities[$service_id])
                    ? (int) $quantities[$service_id]
                    : 1;

                if ($service_id <= 0) {
                    continue;
                }

                if ($quantity <= 0) {
                    $quantity = 1;
                }

                $service_stmt->bind_param(
                    "iii",
                    $package_id,
                    $service_id,
                    $quantity
                );

                $service_stmt->execute();
            }

            $service_stmt->close();

            $conn->commit();

            header(
                "Location: index.php?added=1"
            );

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            if (
                $image_name !== null &&
                file_exists(
                    "../../uploads/packages/" .
                    $image_name
                )
            ) {

                unlink(
                    "../../uploads/packages/" .
                    $image_name
                );
            }

            $error =
                "Unable to create package: " .
                htmlspecialchars(
                    $e->getMessage()
                );
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

    <title>
        Add Package | Event Planner Admin
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #fffaf0;
            color: #5a4630;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background:
                linear-gradient(
                    135deg,
                    #fff2a8,
                    #ffd75e,
                    #f8c1d4
                );
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.15
                );
        }

        .page-header h1 {
            color: #704800;
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: #765d40;
            font-size: 14px;
        }

        .card {
            background: #fffdf7;
            border: 1px solid #f0d88a;
            border-radius: 20px;
            padding: 30px;
            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );
            max-width: 900px;
        }

        .error {
            background: #ffe0e8;
            border: 1px solid #e5a5b7;
            color: #9a3655;
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #674300;
            font-weight: bold;
            font-size: 13px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2c878;
            border-radius: 10px;
            background: #fffef9;
            color: #5a4630;
            font-size: 14px;
            outline: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #d8a928;
            box-shadow:
                0 0 0 3px
                rgba(
                    216,
                    169,
                    40,
                    0.12
                );
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 20px;
        }

        .section-title {
            color: #704800;
            font-size: 18px;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom:
                1px solid #efdca4;
        }

        .services-box {
            border: 1px solid #efd48a;
            border-radius: 12px;
            padding: 15px;
            background: #fffaf0;
        }

        .service-item {
            display: grid;
            grid-template-columns: 30px 1fr 110px;
            gap: 12px;
            align-items: center;
            padding: 12px 5px;
            border-bottom:
                1px solid #f1dfb0;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        .service-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .service-name {
            color: #704800;
            font-weight: bold;
            font-size: 13px;
        }

        .service-info {
            color: #8c7858;
            font-size: 11px;
            margin-top: 3px;
        }

        .quantity-input {
            padding: 8px 10px;
            width: 100%;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            border: none;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }

        .btn-primary {
            background: #d9a928;
            color: #fff;
        }

        .btn-primary:hover {
            background: #c39319;
        }

        .btn-secondary {
            background: #f8d9e3;
            color: #8a3f5d;
        }

        .btn-secondary:hover {
            background: #f2c4d3;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #8c7858;
        }

        .help {
            color: #8c7858;
            font-size: 11px;
            margin-top: 6px;
        }

        @media (max-width: 900px) {

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .row {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 600px) {

            .card {
                padding: 20px;
            }

            .service-item {
                grid-template-columns:
                    30px 1fr 80px;
            }

        }

    </style>

</head>

<body>

<?php

include "../includes/sidebar.php";

?>

<div class="main-content">

    <div class="page-header">

        <h1>
            📦 Add Package
        </h1>

        <p>
            Create a package and add services to it
        </p>

    </div>

    <div class="card">

        <?php if ($error !== ""): ?>

            <div class="error">
                ⚠️ <?= $error ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="row">

                <div class="form-group">

                    <label for="event_id">
                        Event
                    </label>

                    <select
                        name="event_id"
                        id="event_id"
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
                                <?= (
                                    isset(
                                        $_POST["event_id"]
                                    ) &&
                                    (int)$_POST["event_id"]
                                    === (int)$event["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $event["event_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="package_name">
                        Package Name
                    </label>

                    <input
                        type="text"
                        name="package_name"
                        id="package_name"
                        placeholder="Example: Premium Wedding Package"
                        value="<?= htmlspecialchars(
                            $_POST["package_name"] ?? ""
                        ) ?>"
                        required
                    >

                </div>

            </div>

            <div class="row">

                <div class="form-group">

                    <label for="experience_level">
                        Experience Level
                    </label>

                    <select
                        name="experience_level"
                        id="experience_level"
                        required
                    >

                        <?php

                        $levels = [
                            "standard" => "Standard",
                            "good" => "Good",
                            "premium" => "Premium",
                            "luxury" => "Luxury",
                            "vip" => "VIP"
                        ];

                        foreach (
                            $levels
                            as $value => $label
                        ):

                        ?>

                            <option
                                value="<?= $value ?>"
                                <?= (
                                    (
                                        $_POST[
                                            "experience_level"
                                        ] ?? "standard"
                                    ) === $value
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= $label ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        name="status"
                        id="status"
                    >

                        <option
                            value="active"
                            <?= (
                                (
                                    $_POST["status"]
                                    ?? "active"
                                ) === "active"
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
                                (
                                    $_POST["status"]
                                    ?? "active"
                                ) === "inactive"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>

            <div class="form-group">

                <label for="description">
                    Package Description
                </label>

                <textarea
                    name="description"
                    id="description"
                    placeholder="Describe what this package includes..."
                ><?= htmlspecialchars(
                    $_POST["description"] ?? ""
                ) ?></textarea>

            </div>

            <div class="section-title">
                Package Pricing
            </div>

            <div class="row">

                <div class="form-group">

                    <label for="price">
                        Package Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        id="price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $_POST["price"] ?? "0"
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="min_price">
                        Minimum Service Price
                    </label>

                    <input
                        type="number"
                        name="min_price"
                        id="min_price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $_POST["min_price"] ?? "0"
                        ) ?>"
                    >

                </div>

            </div>

            <div class="row">

                <div class="form-group">

                    <label for="max_price">
                        Maximum Service Price
                    </label>

                    <input
                        type="number"
                        name="max_price"
                        id="max_price"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $_POST["max_price"] ?? "0"
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="min_budget">
                        Minimum Budget
                    </label>

                    <input
                        type="number"
                        name="min_budget"
                        id="min_budget"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $_POST["min_budget"] ?? "0"
                        ) ?>"
                    >

                </div>

            </div>

            <div class="form-group">

                <label for="max_budget">
                    Maximum Budget
                </label>

                <input
                    type="number"
                    name="max_budget"
                    id="max_budget"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars(
                        $_POST["max_budget"] ?? "0"
                    ) ?>"
                >

            </div>

            <div class="section-title">
                Package Services
            </div>

            <div class="services-box">

                <?php if (!empty($services)): ?>

                    <?php foreach (
                        $services
                        as $service
                    ): ?>

                        <div class="service-item">

                            <div>

                                <input
                                    type="checkbox"
                                    name="services[]"
                                    value="<?= (int)$service["id"] ?>"
                                    id="service_<?= (int)$service["id"] ?>"
                                    <?= (
                                        isset(
                                            $_POST["services"]
                                        ) &&
                                        in_array(
                                            $service["id"],
                                            $_POST["services"]
                                        )
                                    )
                                        ? "checked"
                                        : ""
                                    ?>
                                >

                            </div>

                            <label
                                for="service_<?= (int)$service["id"] ?>"
                                style="margin: 0;"
                            >

                                <div class="service-name">

                                    <?= htmlspecialchars(
                                        $service["service_name"]
                                    ) ?>

                                </div>

                                <div class="service-info">

                                    <?= htmlspecialchars(
                                        $service["category"]
                                        ?? ""
                                    ) ?>

                                    <?php if (
                                        isset(
                                            $service["unit"]
                                        ) &&
                                        $service["unit"] !== ""
                                    ): ?>

                                        ·
                                        <?= htmlspecialchars(
                                            $service["unit"]
                                        ) ?>

                                    <?php endif; ?>

                                    <?php if (
                                        isset(
                                            $service["min_price"]
                                        ) &&
                                        isset(
                                            $service["max_price"]
                                        )
                                    ): ?>

                                        · Rs.
                                        <?= number_format(
                                            (float)$service[
                                                "min_price"
                                            ]
                                        ) ?>

                                        -
                                        Rs.
                                        <?= number_format(
                                            (float)$service[
                                                "max_price"
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </div>

                            </label>

                            <input
                                type="number"
                                class="quantity-input"
                                name="quantity[<?= (int)$service["id"] ?>]"
                                min="1"
                                value="<?= htmlspecialchars(
                                    $_POST["quantity"][
                                        $service["id"]
                                    ] ?? "1"
                                ) ?>"
                                title="Quantity"
                            >

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty">

                        No active services found.

                    </div>

                <?php endif; ?>

            </div>

            <div class="help">

                Select the services that should be included in this package and set their quantities.

            </div>

            <div class="section-title">
                Package Image
            </div>

            <div class="form-group">

                <label for="image">
                    Package Image
                </label>

                <input
                    type="file"
                    name="image"
                    id="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <div class="help">

                    JPG, JPEG, PNG and WEBP images are allowed.

                </div>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Package
                </button>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Back
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>