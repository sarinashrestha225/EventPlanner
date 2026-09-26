<?php

session_start();

require_once __DIR__ . "/../../database.php";

if (
    !isset($_SESSION["admin_logged_in"]) ||
    empty($_SESSION["admin_logged_in"]) ||
    !isset($_SESSION["admin_id"])
) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

if (
    !isset($_GET["package_id"]) ||
    !is_numeric($_GET["package_id"])
) {
    header("Location: index.php");
    exit();
}

$package_id = (int) $_GET["package_id"];

$errors = [];
$success = "";

$package_stmt = $conn->prepare("
    SELECT
        p.id,
        p.event_id,
        p.package_name,
        p.description,
        p.price,
        p.image,
        p.status,
        et.event_name
    FROM packages p
    LEFT JOIN event_types et
        ON p.event_id = et.id
    WHERE p.id = ?
    LIMIT 1
");

$package_stmt->bind_param("i", $package_id);
$package_stmt->execute();

$package_result = $package_stmt->get_result();

if ($package_result->num_rows !== 1) {
    header("Location: index.php");
    exit();
}

$package = $package_result->fetch_assoc();

$event_id = (int) $package["event_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selected_services = $_POST["services"] ?? [];

    if (!is_array($selected_services)) {
        $selected_services = [];
    }

    $clean_services = [];

    foreach ($selected_services as $service_id => $service_data) {

        $service_id = (int) $service_id;

        if ($service_id <= 0) {
            continue;
        }

        if (!is_array($service_data)) {
            continue;
        }

        if (!isset($service_data["selected"])) {
            continue;
        }

        $quantity = isset($service_data["quantity"])
            ? (int) $service_data["quantity"]
            : 1;

        if ($quantity < 1) {
            $quantity = 1;
        }

        $min_price = isset($service_data["min_price"])
            ? (float) $service_data["min_price"]
            : 0;

        $max_price = isset($service_data["max_price"])
            ? (float) $service_data["max_price"]
            : 0;

        if ($min_price < 0 || $max_price < 0) {
            $errors[] = "Service prices cannot be negative.";
            continue;
        }

        if ($max_price < $min_price) {
            $errors[] = "Maximum price cannot be lower than minimum price.";
            continue;
        }

        $clean_services[$service_id] = [
            "quantity" => $quantity,
            "min_price" => $min_price,
            "max_price" => $max_price
        ];
    }

    if (empty($errors)) {

        $conn->begin_transaction();

        try {

            $delete_old = $conn->prepare("
                DELETE FROM package_services
                WHERE package_id = ?
            ");

            $delete_old->bind_param("i", $package_id);

            if (!$delete_old->execute()) {
                throw new Exception(
                    "Failed to remove old package services."
                );
            }

            $service_check = $conn->prepare("
                SELECT
                    id,
                    service_name,
                    price,
                    min_price,
                    max_price,
                    unit
                FROM services
                WHERE id = ?
                AND event_id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $update_service = $conn->prepare("
                UPDATE services
                SET
                    price = ?,
                    min_price = ?,
                    max_price = ?
                WHERE id = ?
                AND event_id = ?
                AND status = 'active'
            ");

            $insert_service = $conn->prepare("
                INSERT INTO package_services
                (
                    package_id,
                    service_id,
                    quantity
                )
                VALUES (?, ?, ?)
            ");

            foreach ($clean_services as $service_id => $service_data) {

                $quantity = (int) $service_data["quantity"];
                $min_price = (float) $service_data["min_price"];
                $max_price = (float) $service_data["max_price"];

                $service_check->bind_param(
                    "ii",
                    $service_id,
                    $event_id
                );

                $service_check->execute();

                $service_result = $service_check->get_result();

                if ($service_result->num_rows !== 1) {
                    throw new Exception(
                        "One of the selected services is invalid for this event."
                    );
                }

                $update_service->bind_param(
                    "dddii",
                    $min_price,
                    $min_price,
                    $max_price,
                    $service_id,
                    $event_id
                );

                if (!$update_service->execute()) {
                    throw new Exception(
                        "Failed to update service price."
                    );
                }

                $insert_service->bind_param(
                    "iii",
                    $package_id,
                    $service_id,
                    $quantity
                );

                if (!$insert_service->execute()) {
                    throw new Exception(
                        "Failed to save selected service."
                    );
                }
            }

            $conn->commit();

            header(
                "Location: services.php?package_id=" .
                $package_id .
                "&saved=1"
            );

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            $errors[] = $e->getMessage();
        }
    }
}

if (
    isset($_GET["saved"]) &&
    $_GET["saved"] === "1"
) {
    $success = "Package services and prices saved successfully.";
}

$selected = [];

$selected_stmt = $conn->prepare("
    SELECT
        service_id,
        quantity
    FROM package_services
    WHERE package_id = ?
");

$selected_stmt->bind_param("i", $package_id);
$selected_stmt->execute();

$selected_result = $selected_stmt->get_result();

while ($row = $selected_result->fetch_assoc()) {

    $selected[
        (int) $row["service_id"]
    ] = (int) $row["quantity"];
}

$services = [];

$services_stmt = $conn->prepare("
    SELECT
        id,
        service_name,
        category,
        description,
        price,
        min_price,
        max_price,
        unit,
        image,
        service_image
    FROM services
    WHERE event_id = ?
    AND status = 'active'
    ORDER BY service_name ASC
");

$services_stmt->bind_param("i", $event_id);
$services_stmt->execute();

$services_result = $services_stmt->get_result();

while ($service = $services_result->fetch_assoc()) {
    $services[] = $service;
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
    Package Services - Event Planner
</title>

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
    background: linear-gradient(
        90deg,
        #f8c8dc,
        #ffd966
    );

    padding: 20px 35px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow:
        0 3px 10px
        rgba(0, 0, 0, 0.12);
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
    max-width: 1250px;
    margin: 30px auto;
}

.top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

h1 {
    margin: 0;
    color: #8a5a00;
}

.back-btn {
    text-decoration: none;
    background: #f8c8dc;
    color: #7a2348;
    padding: 11px 18px;
    border-radius: 8px;
    font-weight: bold;
}

.package-info {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;

    box-shadow:
        0 4px 15px
        rgba(0, 0, 0, 0.08);
}

.package-info h2 {
    margin-top: 0;
    color: #7a4b00;
}

.package-info p {
    margin: 7px 0;
}

.info-note {
    margin-top: 14px;
    padding: 12px;
    background: #fff8e7;
    border-left: 4px solid #d4af37;
    border-radius: 6px;
    color: #765800;
    font-size: 13px;
    line-height: 1.5;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
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

.search-box {
    width: 100%;
    padding: 13px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 15px;
}

.service-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.service-card {
    background: white;
    border: 1px solid #ead9a6;
    border-radius: 12px;
    overflow: hidden;

    box-shadow:
        0 4px 14px
        rgba(0, 0, 0, 0.07);

    transition: 0.2s;
}

.service-card:hover {
    transform: translateY(-2px);
}

.service-card.selected {
    border: 2px solid #d4af37;
}

.service-image {
    width: 100%;
    height: 150px;
    object-fit: cover;

    background: linear-gradient(
        135deg,
        #fff8e7,
        #f8e8ef
    );
}

.service-content {
    padding: 15px;
}

.service-name {
    font-size: 18px;
    font-weight: bold;
    color: #7a4b00;
    margin-bottom: 7px;
}

.category {
    display: inline-block;
    background: #f8c8dc;
    color: #7a2348;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    margin-bottom: 8px;
}

.description {
    color: #666;
    font-size: 13px;
    line-height: 1.4;
    min-height: 38px;
    margin-bottom: 12px;
}

.price-edit-box {
    background: #fffaf2;
    border: 1px solid #ead9a6;
    border-radius: 8px;
    padding: 11px;
    margin-bottom: 12px;
}

.price-edit-title {
    font-size: 13px;
    font-weight: bold;
    color: #7a4b00;
    margin-bottom: 8px;
}

.price-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.price-field label {
    display: block;
    font-size: 11px;
    color: #666;
    margin-bottom: 4px;
}

.price-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #d8cda9;
    border-radius: 6px;
    font-size: 14px;
}

.unit-text {
    margin-top: 8px;
    color: #777;
    font-size: 12px;
}

.select-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.select-row input[type="checkbox"] {
    width: 19px;
    height: 19px;
    accent-color: #d4af37;
}

.quantity {
    width: 80px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.summary {
    background: #fff8e7;
    border: 2px solid #d4af37;
    border-radius: 12px;
    padding: 20px;
    margin-top: 25px;

    position: sticky;
    bottom: 15px;

    box-shadow:
        0 5px 18px
        rgba(0, 0, 0, 0.10);
}

.summary h2 {
    margin-top: 0;
    color: #7a4b00;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 7px 0;
    font-size: 16px;
}

.save-btn {
    width: 100%;
    border: none;
    background: #d4af37;
    color: white;
    padding: 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    margin-top: 12px;
}

.save-btn:hover {
    background: #b8941f;
}

.no-services {
    background: white;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    color: #888;
}

@media (max-width: 1000px) {

    .service-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 650px) {

    .service-grid {
        grid-template-columns: 1fr;
    }

    .header {
        padding: 18px;
    }

    .top-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .price-fields {
        grid-template-columns: 1fr;
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

    <div class="top-row">

        <h1>
            🧩 Package Services
        </h1>

        <a
            href="index.php"
            class="back-btn"
        >
            ← Back to Packages
        </a>

    </div>

    <?php if ($success !== ""): ?>

        <div class="success">

            ✅
            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>

    <?php if (!empty($errors)): ?>

        <div class="error-box">

            <?php foreach ($errors as $error): ?>

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

    <div class="package-info">

        <h2>
            <?= htmlspecialchars(
                $package["package_name"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </h2>

        <p>

            <strong>
                Event:
            </strong>

            <?= htmlspecialchars(
                $package["event_name"] ?? "Unknown Event",
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </p>

        <p>

            <strong>
                Current Package Price:
            </strong>

            Rs.
            <?= number_format(
                (float) $package["price"],
                2
            ) ?>

        </p>

        <div class="info-note">

            💡 <strong>Service Price:</strong>
            Edit the minimum and maximum price of each service below.
            The selected services are used to calculate the package total.

            <br><br>

            ⚠️ Changing a service price updates that service in the
            services table.

        </div>

    </div>

    <?php if (empty($services)): ?>

        <div class="no-services">

            No active services are available
            for this event.

        </div>

    <?php else: ?>

        <form method="POST">

            <input
                type="text"
                id="searchService"
                class="search-box"
                placeholder="🔍 Search services..."
            >

            <div class="service-grid">

                <?php foreach ($services as $service): ?>

                    <?php

                    $service_id = (int) $service["id"];

                    $is_selected = isset(
                        $selected[$service_id]
                    );

                    $quantity = $is_selected
                        ? $selected[$service_id]
                        : 1;

                    $min_price = $service["min_price"];

                    $max_price = $service["max_price"];

                    if (
                        $min_price === null ||
                        $min_price === ""
                    ) {
                        $min_price = $service["price"];
                    }

                    if (
                        $max_price === null ||
                        $max_price === ""
                    ) {
                        $max_price = $service["price"];
                    }

                    $image = !empty($service["image"])
                        ? $service["image"]
                        : $service["service_image"];

                    $service_name_lower = strtolower(
                        $service["service_name"]
                    );

                    ?>

                    <div
                        class="service-card <?= $is_selected ? "selected" : "" ?>"
                        data-name="<?= htmlspecialchars(
                            $service_name_lower,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                        <?php if (!empty($image)): ?>

                            <img
                                src="../../<?= htmlspecialchars(
                                    $image,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                class="service-image"
                                alt="Service Image"
                            >

                        <?php else: ?>

                            <div class="service-image"></div>

                        <?php endif; ?>

                        <div class="service-content">

                            <div class="service-name">

                                <?= htmlspecialchars(
                                    $service["service_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                            <?php if (!empty($service["category"])): ?>

                                <span class="category">

                                    <?= htmlspecialchars(
                                        $service["category"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            <?php endif; ?>

                            <div class="description">

                                <?= htmlspecialchars(
                                    $service["description"] ??
                                    "No description available.",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                            <div class="price-edit-box">

                                <div class="price-edit-title">
                                    💰 Edit Service Price
                                </div>

                                <div class="price-fields">

                                    <div class="price-field">

                                        <label>
                                            Minimum Price
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="price-input min-price"
                                            name="services[<?= $service_id ?>][min_price]"
                                            value="<?= htmlspecialchars(
                                                (string) $min_price,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            data-service-id="<?= $service_id ?>"
                                        >

                                    </div>

                                    <div class="price-field">

                                        <label>
                                            Maximum Price
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="price-input max-price"
                                            name="services[<?= $service_id ?>][max_price]"
                                            value="<?= htmlspecialchars(
                                                (string) $max_price,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            data-service-id="<?= $service_id ?>"
                                        >

                                    </div>

                                </div>

                                <?php if (!empty($service["unit"])): ?>

                                    <div class="unit-text">

                                        Unit:
                                        <strong>
                                            <?= htmlspecialchars(
                                                $service["unit"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </strong>

                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="select-row">

                                <input
                                    type="checkbox"
                                    class="service-check"
                                    name="services[<?= $service_id ?>][selected]"
                                    value="1"
                                    data-service-id="<?= $service_id ?>"
                                    <?= $is_selected ? "checked" : "" ?>
                                >

                                <label>
                                    Select
                                </label>

                                <input
                                    type="number"
                                    class="quantity"
                                    id="quantity_<?= $service_id ?>"
                                    name="services[<?= $service_id ?>][quantity]"
                                    min="1"
                                    value="<?= (int) $quantity ?>"
                                    <?= !$is_selected ? "disabled" : "" ?>
                                >

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="summary">

                <h2>
                    💰 Package Total
                </h2>

                <div class="total-row">

                    <span>
                        Minimum Total
                    </span>

                    <strong>

                        Rs.

                        <span id="minTotal">
                            0.00
                        </span>

                    </strong>

                </div>

                <div class="total-row">

                    <span>
                        Maximum Total
                    </span>

                    <strong>

                        Rs.

                        <span id="maxTotal">
                            0.00
                        </span>

                    </strong>

                </div>

                <button
                    type="submit"
                    class="save-btn"
                >
                    💾 Save Package Services & Prices
                </button>

            </div>

        </form>

    <?php endif; ?>

</div>

<script>

const searchInput =
    document.getElementById("searchService");

if (searchInput) {

    searchInput.addEventListener(
        "input",
        function () {

            const search =
                this.value
                    .toLowerCase()
                    .trim();

            const cards =
                document.querySelectorAll(
                    ".service-card"
                );

            cards.forEach(
                function (card) {

                    const name =
                        card.dataset.name || "";

                    if (name.includes(search)) {
                        card.style.display = "";
                    } else {
                        card.style.display = "none";
                    }

                }
            );

        }
    );

}

function calculateTotals() {

    let minTotal = 0;
    let maxTotal = 0;

    const checkboxes =
        document.querySelectorAll(
            ".service-check"
        );

    checkboxes.forEach(
        function (checkbox) {

            if (!checkbox.checked) {
                return;
            }

            const serviceId =
                checkbox.dataset.serviceId;

            const minInput =
                document.querySelector(
                    '.min-price[data-service-id="' +
                    serviceId +
                    '"]'
                );

            const maxInput =
                document.querySelector(
                    '.max-price[data-service-id="' +
                    serviceId +
                    '"]'
                );

            const quantityInput =
                document.getElementById(
                    "quantity_" +
                    serviceId
                );

            const min =
                parseFloat(
                    minInput
                        ? minInput.value
                        : 0
                ) || 0;

            const max =
                parseFloat(
                    maxInput
                        ? maxInput.value
                        : 0
                ) || 0;

            let quantity = 1;

            if (quantityInput) {

                quantity =
                    parseInt(
                        quantityInput.value
                    ) || 1;

            }

            minTotal +=
                min * quantity;

            maxTotal +=
                max * quantity;

        }
    );

    const minElement =
        document.getElementById("minTotal");

    const maxElement =
        document.getElementById("maxTotal");

    if (minElement) {

        minElement.textContent =
            minTotal.toLocaleString(
                "en-US",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }

    if (maxElement) {

        maxElement.textContent =
            maxTotal.toLocaleString(
                "en-US",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }

}

document
    .querySelectorAll(".service-check")
    .forEach(
        function (checkbox) {

            checkbox.addEventListener(
                "change",
                function () {

                    const serviceId =
                        this.dataset.serviceId;

                    const quantityInput =
                        document.getElementById(
                            "quantity_" +
                            serviceId
                        );

                    const card =
                        this.closest(
                            ".service-card"
                        );

                    if (this.checked) {

                        if (quantityInput) {
                            quantityInput.disabled = false;
                        }

                        if (card) {
                            card.classList.add("selected");
                        }

                    } else {

                        if (quantityInput) {
                            quantityInput.disabled = true;
                        }

                        if (card) {
                            card.classList.remove("selected");
                        }

                    }

                    calculateTotals();

                }
            );

        }
    );

document
    .querySelectorAll(".quantity")
    .forEach(
        function (input) {

            input.addEventListener(
                "input",
                calculateTotals
            );

        }
    );

document
    .querySelectorAll(".min-price, .max-price")
    .forEach(
        function (input) {

            input.addEventListener(
                "input",
                calculateTotals
            );

        }
    );

calculateTotals();

</script>

</body>

</html>