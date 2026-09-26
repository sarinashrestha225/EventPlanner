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

$sql = "
    SELECT
        p.id,
        p.package_name,
        p.event_id,
        p.description,
        p.status,
        et.event_name,
        pp.min_guests,
        pp.max_guests,
        pp.original_price,
        pp.combo_price
    FROM packages p
    LEFT JOIN event_types et
        ON p.event_id = et.id
    LEFT JOIN package_pricing pp
        ON p.id = pp.package_id
    ORDER BY
        et.event_name ASC,
        CASE
            WHEN p.package_name = 'Basic' THEN 1
            WHEN p.package_name = 'Silver' THEN 2
            WHEN p.package_name = 'Premium' THEN 3
            WHEN p.package_name = 'Luxury' THEN 4
            WHEN p.package_name = 'VIP' THEN 5
            ELSE 6
        END ASC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

$events = [];

while ($row = $result->fetch_assoc()) {

    $event_name = !empty($row["event_name"])
        ? $row["event_name"]
        : "Other Event";

    $events[$event_name][] = $row;
}

$package_order = [
    "Basic",
    "Silver",
    "Premium",
    "Luxury",
    "VIP"
];

$package_icons = [
    "basic" => "🌸",
    "silver" => "💎",
    "premium" => "👑",
    "luxury" => "✨",
    "vip" => "🏆"
];

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Manage Packages - Event Planner</title>

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
    background:
        linear-gradient(
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
        rgba(0,0,0,0.12);
}

.logo {
    font-size: 26px;
    font-weight: bold;
    color: #7a4b00;
}

.admin-name {
    font-weight: bold;
    color: #7a4b00;
}

.container {
    width: 95%;
    max-width: 1500px;
    margin: 30px auto;
}

.title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.title {
    margin: 0;
    color: #8a5a00;
}

.add-btn {
    text-decoration: none;
    background: #d4af37;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: bold;
}

.add-btn:hover {
    background: #b8941f;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 13px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info {
    background: #fff3cd;
    border-left: 5px solid #d4af37;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.event-section {
    margin-bottom: 45px;
}

.event-title {
    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #ffe7a3
        );
    color: #7a4b00;
    padding: 15px 20px;
    border-radius:
        12px 12px 0 0;
    font-size: 23px;
    font-weight: bold;
}

.package-grid {
    display: grid;
    grid-template-columns:
        repeat(5, 1fr);
    gap: 15px;
    background: white;
    padding: 20px;
    border-radius:
        0 0 12px 12px;
    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.10);
}

.package-card {
    background: #fffaf0;
    border:
        1px solid #ead9a6;
    border-radius: 12px;
    overflow: hidden;
    min-height: 380px;
    display: flex;
    flex-direction: column;
}

.package-name {
    background: #d4af37;
    color: white;
    padding: 12px;
    text-align: center;
    font-size: 19px;
    font-weight: bold;
}

.package-icon {
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        linear-gradient(
            135deg,
            #fff8e7,
            #f8e8ef
        );
    font-size: 60px;
}

.package-content {
    padding: 15px;
    flex: 1;
}

.description {
    color: #666;
    font-size: 14px;
    line-height: 1.5;
    min-height: 45px;
    margin-bottom: 12px;
}

.guest-range {
    background: #f8e8ef;
    color: #7a2348;
    padding: 9px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 10px;
}

.price {
    font-size: 16px;
    font-weight: bold;
    color: #b8860b;
    margin-top: 10px;
    line-height: 1.6;
}

.no-price {
    color: #999;
    font-size: 14px;
    margin-top: 10px;
}

.status {
    display: inline-block;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.active {
    background: #d4edda;
    color: #155724;
}

.inactive {
    background: #f8d7da;
    color: #721c24;
}

.actions {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.action-btn {
    text-decoration: none;
    text-align: center;
    padding: 9px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: bold;
}

.services {
    background: #f8c8dc;
    color: #7a2348;
}

.edit {
    background: #fff0b3;
    color: #795900;
}

.delete {
    background: #f8d7da;
    color: #721c24;
}

.services:hover,
.edit:hover,
.delete:hover {
    opacity: 0.85;
}

.empty {
    background: white;
    padding: 30px;
    text-align: center;
    color: #888;
    border-radius: 10px;
}

@media(max-width: 1200px) {

    .package-grid {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media(max-width: 750px) {

    .package-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .title-row {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

}

@media(max-width: 500px) {

    .package-grid {
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

    <div class="title-row">

        <h1 class="title">
            📦 Manage Packages
        </h1>

        <a
            href="add.php"
            class="add-btn"
        >
            ➕ Add Package
        </a>

    </div>

    <?php if (
        isset($_GET["success"]) &&
        $_GET["success"] === "updated"
    ): ?>

        <div class="success">
            ✅ Package updated successfully!
        </div>

    <?php endif; ?>

    <?php if (
        isset($_GET["success"]) &&
        $_GET["success"] === "deleted"
    ): ?>

        <div class="success">
            ✅ Package deleted successfully!
        </div>

    <?php endif; ?>

    <?php if (
        isset($_GET["success"]) &&
        $_GET["success"] === "added"
    ): ?>

        <div class="success">
            ✅ Package added successfully!
        </div>

    <?php endif; ?>

    <div class="info">

        ℹ️ Each event has its own

        <strong>
            Basic, Silver, Premium, Luxury and VIP
        </strong>

        packages with

        <strong>
            guest range and price range.
        </strong>

    </div>

    <?php if (empty($events)): ?>

        <div class="empty">
            No packages found.
        </div>

    <?php endif; ?>

    <?php foreach (
        $events
        as $event_name => $event_packages
    ): ?>

        <?php

        $package_map = [];

        foreach (
            $event_packages
            as $package
        ) {

            $key = strtolower(
                trim(
                    $package["package_name"]
                )
            );

            $package_map[$key] =
                $package;
        }

        ?>

        <div class="event-section">

            <div class="event-title">

                🎉

                <?= htmlspecialchars(
                    $event_name,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

            <div class="package-grid">

                <?php foreach (
                    $package_order
                    as $package_name
                ): ?>

                    <?php

                    $key = strtolower(
                        $package_name
                    );

                    ?>

                    <?php if (
                        isset(
                            $package_map[$key]
                        )
                    ): ?>

                        <?php

                        $package =
                            $package_map[$key];

                        $icon =
                            $package_icons[$key]
                            ?? "📦";

                        $status =
                            strtolower(
                                $package["status"]
                                ?? ""
                            );

                        ?>

                        <div class="package-card">

                            <div class="package-name">

                                <?= htmlspecialchars(
                                    $package["package_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                            <div class="package-icon">

                                <?= $icon ?>

                            </div>

                            <div class="package-content">

                                <div class="description">

                                    <?php if (
                                        !empty(
                                            $package["description"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $package["description"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    <?php else: ?>

                                        No description available.

                                    <?php endif; ?>

                                </div>

                                <?php if (
                                    $package["min_guests"] !== null &&
                                    $package["max_guests"] !== null
                                ): ?>

                                    <div class="guest-range">

                                        👥

                                        <?= number_format(
                                            (int)$package["min_guests"]
                                        ) ?>

                                        -

                                        <?= number_format(
                                            (int)$package["max_guests"]
                                        ) ?>

                                        Guests

                                    </div>

                                <?php else: ?>

                                    <div class="guest-range">

                                        👥 Guest range not set

                                    </div>

                                <?php endif; ?>

                                <?php if (
                                    $package["original_price"] !== null &&
                                    $package["combo_price"] !== null
                                ): ?>

                                    <div class="price">

                                        💰 Rs.

                                        <?= number_format(
                                            (float)$package["original_price"],
                                            2
                                        ) ?>

                                        -

                                        Rs.

                                        <?= number_format(
                                            (float)$package["combo_price"],
                                            2
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <div class="no-price">

                                        💰 Price not set

                                    </div>

                                <?php endif; ?>

                                <span
                                    class="status
                                    <?= $status === "active"
                                        ? "active"
                                        : "inactive"
                                    ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $package["status"]
                                            ?? "inactive"
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </div>

                            <div class="actions">

                                <a
                                    href="services.php?package_id=<?= (int)$package["id"] ?>"
                                    class="action-btn services"
                                >
                                    🧩 Services
                                </a>

                                <a
                                    href="edit.php?id=<?= (int)$package["id"] ?>"
                                    class="action-btn edit"
                                >
                                    ✏️ Edit
                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$package["id"] ?>"
                                    class="action-btn delete"
                                    onclick="return confirm('Are you sure you want to delete this package?');"
                                >
                                    🗑️ Delete
                                </a>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="package-card">

                            <div class="package-name">

                                <?= htmlspecialchars(
                                    $package_name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                            <div class="package-icon">

                                <?= $package_icons[
                                    strtolower(
                                        $package_name
                                    )
                                ] ?? "📦" ?>

                            </div>

                            <div class="package-content">

                                <div class="description">

                                    This package has not been
                                    created for this event yet.

                                </div>

                                <div class="no-price">

                                    💰 Price not set

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endforeach; ?>

</div>

</body>

</html>