<?php
session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$package_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($package_id <= 0) {
    header("Location: packages.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        p.*,
        e.event_name
    FROM packages p
    LEFT JOIN event_types e ON p.event_id = e.id
    WHERE p.id = ? AND p.provider_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $package_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    header("Location: packages.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        ps.id,
        ps.service_id,
        ps.quantity,
        s.service_name,
        s.category,
        s.description,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.availability,
        s.status
    FROM package_services ps
    INNER JOIN services s ON ps.service_id = s.id
    WHERE ps.package_id = ? AND s.provider_id = ?
    ORDER BY ps.id ASC
");

$stmt->bind_param("ii", $package_id, $provider_id);
$stmt->execute();

$services_result = $stmt->get_result();
$services = [];

while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
}

$stmt->close();

$stmt = $conn->prepare("
    SELECT
        id,
        min_guests,
        max_guests,
        original_price,
        combo_price
    FROM package_pricing
    WHERE package_id = ?
    ORDER BY min_guests ASC
");

$stmt->bind_param("i", $package_id);
$stmt->execute();

$pricing_result = $stmt->get_result();
$pricing = [];

while ($row = $pricing_result->fetch_assoc()) {
    $pricing[] = $row;
}

$stmt->close();

$stmt = $conn->prepare("
    SELECT
        name,
        email,
        phone,
        city,
        area,
        profile_photo
    FROM providers
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$provider_result = $stmt->get_result();
$provider = $provider_result->fetch_assoc();

$stmt->close();

function money_value($value)
{
    if ($value === null || $value === "") {
        return "0.00";
    }

    return number_format((float) $value, 2);
}

function range_value($min, $max)
{
    $min = (float) $min;
    $max = (float) $max;

    if ($min == 0 && $max == 0) {
        return "Not specified";
    }

    if ($min == $max) {
        return "Rs. " . money_value($min);
    }

    return "Rs. " . money_value($min) . " - Rs. " . money_value($max);
}

$package_image = "";

if (!empty($package["image"])) {
    $package_image = "uploads/packages/" . $package["image"];
}

$status_class = strtolower($package["status"] ?? "inactive");
$experience_level = ucfirst($package["experience_level"] ?? "Standard");

$description = trim($package["description"] ?? "");

$main_price = (float) ($package["price"] ?? 0);
$min_budget = (float) ($package["min_budget"] ?? 0);
$max_budget = (float) ($package["max_budget"] ?? 0);
$min_price = (float) ($package["min_price"] ?? 0);
$max_price = (float) ($package["max_price"] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Details</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #fffaf0;
            color: #4d4030;
        }

        .page {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px 50px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .back-btn {
            display: inline-block;
            text-decoration: none;
            background: #f8e1e7;
            color: #76545e;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 600;
        }

        .back-btn:hover {
            background: #f2ccd6;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            color: #8a691f;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 10px;
            font-weight: 600;
            border: 1px solid #d8b75b;
            background: #fff;
            color: #7a5a16;
        }

        .action-btn:hover {
            background: #fff3c9;
        }

        .main-card {
            background: #ffffff;
            border: 1px solid #ead9a8;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(125, 95, 30, 0.08);
        }

        .package-header {
            display: grid;
            grid-template-columns: 42% 58%;
            min-height: 360px;
        }

        .image-section {
            background: #f9eadf;
            min-height: 360px;
        }

        .image-section img {
            width: 100%;
            height: 100%;
            min-height: 360px;
            object-fit: cover;
            display: block;
        }

        .no-image {
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a58a55;
            font-size: 20px;
            font-weight: 600;
        }

        .details-section {
            padding: 35px;
        }

        .event-badge {
            display: inline-block;
            background: #fff1b8;
            color: #765710;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .package-name {
            margin: 0 0 12px;
            color: #705414;
            font-size: 32px;
            line-height: 1.2;
        }

        .experience {
            display: inline-block;
            background: #f8dce4;
            color: #8a5967;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .description {
            line-height: 1.7;
            color: #6d6253;
            margin: 0 0 25px;
        }

        .price-box {
            background: #fff8df;
            border: 1px solid #efd58b;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .price-label {
            font-size: 13px;
            color: #8b7a55;
            margin-bottom: 5px;
        }

        .main-price {
            font-size: 28px;
            font-weight: 800;
            color: #a2760c;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }

        .status.active {
            background: #dff3e4;
            color: #28703b;
        }

        .status.inactive {
            background: #f7dcdc;
            color: #9a3838;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }

        .info-box {
            background: #fffdf8;
            border: 1px solid #eee0bd;
            border-radius: 12px;
            padding: 16px;
        }

        .info-label {
            font-size: 12px;
            color: #9a8968;
            margin-bottom: 7px;
        }

        .info-value {
            font-weight: 700;
            color: #5e503d;
        }

        .section {
            padding: 30px;
            border-top: 1px solid #eee2c3;
        }

        .section-title {
            margin: 0 0 20px;
            color: #7b5d17;
            font-size: 22px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .service-card {
            border: 1px solid #eadfca;
            border-radius: 14px;
            padding: 18px;
            background: #fffdfa;
        }

        .service-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }

        .service-name {
            margin: 0;
            color: #705414;
            font-size: 18px;
        }

        .quantity {
            background: #f8dce4;
            color: #855565;
            padding: 5px 9px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .category {
            color: #9a7b30;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .service-description {
            color: #746958;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .service-price {
            font-weight: 700;
            color: #876711;
        }

        .service-status {
            margin-top: 10px;
            font-size: 12px;
            color: #777;
        }

        .empty-box {
            text-align: center;
            padding: 30px 20px;
            border: 1px dashed #ddc98d;
            border-radius: 12px;
            background: #fffdf5;
            color: #927e54;
        }

        .pricing-table-wrap {
            overflow-x: auto;
        }

        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        .pricing-table th {
            background: #fff2bd;
            color: #705414;
            text-align: left;
            padding: 14px;
            font-size: 14px;
        }

        .pricing-table td {
            padding: 14px;
            border-bottom: 1px solid #eee4ce;
            color: #5e5548;
        }

        .pricing-table tr:last-child td {
            border-bottom: none;
        }

        .combo-price {
            font-weight: 800;
            color: #a2760c;
        }

        .saving {
            font-weight: 700;
            color: #31723e;
        }

        .not-available {
            color: #9b6c6c;
        }

        .provider-card {
            display: flex;
            align-items: center;
            gap: 18px;
            background: #fffaf1;
            border: 1px solid #eadbb5;
            border-radius: 14px;
            padding: 20px;
        }

        .provider-photo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e5c866;
        }

        .provider-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #f8dce4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #875565;
            font-weight: 700;
        }

        .provider-name {
            margin: 0 0 6px;
            color: #705414;
            font-size: 19px;
        }

        .provider-info {
            margin: 3px 0;
            color: #746958;
            font-size: 14px;
        }

        @media (max-width: 850px) {
            .package-header {
                grid-template-columns: 1fr;
            }

            .image-section,
            .image-section img {
                min-height: 280px;
                height: 280px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .page {
                padding: 20px 12px 40px;
            }

            .page-title {
                font-size: 23px;
            }

            .details-section,
            .section {
                padding: 22px;
            }

            .package-name {
                font-size: 26px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                width: 100%;
            }

            .action-btn {
                flex: 1;
                text-align: center;
            }

            .provider-card {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<div class="page">

    <div class="top-bar">

        <div>
            <a href="packages.php" class="back-btn">← Back to Packages</a>
        </div>

        <h1 class="page-title">Package Details</h1>

        <div class="action-buttons">
            <a
                href="edit_package.php?id=<?= $package_id ?>"
                class="action-btn"
            >
                Edit Package
            </a>

            <a
                href="package_services.php?id=<?= $package_id ?>"
                class="action-btn"
            >
                Manage Services
            </a>

            <a
                href="package_pricing.php?id=<?= $package_id ?>"
                class="action-btn"
            >
                Manage Pricing
            </a>
        </div>

    </div>

    <div class="main-card">

        <div class="package-header">

            <div class="image-section">

                <?php if (!empty($package_image)): ?>

                    <img
                        src="<?= htmlspecialchars($package_image) ?>"
                        alt="<?= htmlspecialchars($package["package_name"]) ?>"
                    >

                <?php else: ?>

                    <div class="no-image">
                        No Image
                    </div>

                <?php endif; ?>

            </div>

            <div class="details-section">

                <?php if (!empty($package["event_name"])): ?>

                    <div class="event-badge">
                        <?= htmlspecialchars($package["event_name"]) ?>
                    </div>

                <?php endif; ?>

                <h2 class="package-name">
                    <?= htmlspecialchars($package["package_name"]) ?>
                </h2>

                <div class="experience">
                    <?= htmlspecialchars($experience_level) ?>
                </div>

                <?php if ($description !== ""): ?>

                    <p class="description">
                        <?= nl2br(htmlspecialchars($description)) ?>
                    </p>

                <?php else: ?>

                    <p class="description">
                        No description added for this package.
                    </p>

                <?php endif; ?>

                <div class="price-box">

                    <div class="price-label">
                        Main Package Price
                    </div>

                    <div class="main-price">
                        Rs. <?= money_value($main_price) ?>
                    </div>

                </div>

                <span class="status <?= htmlspecialchars($status_class) ?>">
                    <?= ucfirst(htmlspecialchars($status_class)) ?>
                </span>

                <div class="info-grid">

                    <div class="info-box">

                        <div class="info-label">
                            Budget Range
                        </div>

                        <div class="info-value">
                            <?= range_value($min_budget, $max_budget) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <div class="info-label">
                            Package Price Range
                        </div>

                        <div class="info-value">
                            <?= range_value($min_price, $max_price) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="section">

            <h3 class="section-title">
                Included Services
            </h3>

            <?php if (!empty($services)): ?>

                <div class="services-grid">

                    <?php foreach ($services as $service): ?>

                        <div class="service-card">

                            <div class="service-top">

                                <h4 class="service-name">
                                    <?= htmlspecialchars($service["service_name"]) ?>
                                </h4>

                                <div class="quantity">
                                    × <?= (int) $service["quantity"] ?>
                                </div>

                            </div>

                            <?php if (!empty($service["category"])): ?>

                                <div class="category">
                                    <?= htmlspecialchars($service["category"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($service["description"])): ?>

                                <div class="service-description">
                                    <?= nl2br(htmlspecialchars($service["description"])) ?>
                                </div>

                            <?php endif; ?>

                            <div class="service-price">

                                <?php if (
                                    isset($service["min_price"]) &&
                                    isset($service["max_price"]) &&
                                    (float) $service["min_price"] > 0 &&
                                    (float) $service["max_price"] > 0
                                ): ?>

                                    Rs. <?= money_value($service["min_price"]) ?>
                                    -
                                    Rs. <?= money_value($service["max_price"]) ?>

                                <?php elseif ((float) $service["price"] > 0): ?>

                                    Rs. <?= money_value($service["price"]) ?>

                                <?php else: ?>

                                    Price not specified

                                <?php endif; ?>

                                <?php if (!empty($service["unit"])): ?>

                                    / <?= htmlspecialchars($service["unit"]) ?>

                                <?php endif; ?>

                            </div>

                            <div class="service-status">

                                Availability:
                                <strong>
                                    <?= ucfirst(htmlspecialchars($service["availability"] ?? "available")) ?>
                                </strong>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-box">
                    No services have been added to this package yet.
                </div>

            <?php endif; ?>

        </div>

        <div class="section">

            <h3 class="section-title">
                Guest-wise Pricing
            </h3>

            <?php if (!empty($pricing)): ?>

                <div class="pricing-table-wrap">

                    <table class="pricing-table">

                        <thead>

                            <tr>
                                <th>Guests</th>
                                <th>Original Price</th>
                                <th>Combo Price</th>
                                <th>Savings</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($pricing as $price_row): ?>

                                <?php
                                $original = (float) $price_row["original_price"];
                                $combo = (float) $price_row["combo_price"];
                                $saving = $original - $combo;
                                ?>

                                <tr>

                                    <td>
                                        <?= (int) $price_row["min_guests"] ?>
                                        -
                                        <?= (int) $price_row["max_guests"] ?>
                                    </td>

                                    <td>
                                        Rs. <?= money_value($original) ?>
                                    </td>

                                    <td class="combo-price">
                                        Rs. <?= money_value($combo) ?>
                                    </td>

                                    <td>

                                        <?php if ($saving > 0): ?>

                                            <span class="saving">
                                                Rs. <?= money_value($saving) ?>
                                            </span>

                                        <?php elseif ($saving == 0): ?>

                                            <span>
                                                No savings
                                            </span>

                                        <?php else: ?>

                                            <span class="not-available">
                                                N/A
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-box">
                    No guest-wise pricing has been added yet.
                </div>

            <?php endif; ?>

        </div>

        <?php if ($provider): ?>

            <div class="section">

                <h3 class="section-title">
                    Provider Information
                </h3>

                <div class="provider-card">

                    <?php if (!empty($provider["profile_photo"])): ?>

                        <img
                            src="uploads/profile/<?= htmlspecialchars($provider["profile_photo"]) ?>"
                            alt="<?= htmlspecialchars($provider["name"]) ?>"
                            class="provider-photo"
                        >

                    <?php else: ?>

                        <div class="provider-placeholder">
                            <?= strtoupper(substr($provider["name"], 0, 1)) ?>
                        </div>

                    <?php endif; ?>

                    <div>

                        <h4 class="provider-name">
                            <?= htmlspecialchars($provider["name"]) ?>
                        </h4>

                        <?php if (!empty($provider["email"])): ?>

                            <div class="provider-info">
                                <?= htmlspecialchars($provider["email"]) ?>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($provider["phone"])): ?>

                            <div class="provider-info">
                                <?= htmlspecialchars($provider["phone"]) ?>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($provider["city"]) || !empty($provider["area"])): ?>

                            <div class="provider-info">
                                <?= htmlspecialchars($provider["area"] ?? "") ?>

                                <?php if (!empty($provider["area"]) && !empty($provider["city"])): ?>
                                    ,
                                <?php endif; ?>

                                <?= htmlspecialchars($provider["city"] ?? "") ?>
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>