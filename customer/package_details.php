<?php

session_start();

require_once "../database.php";

$package_id = 0;

if (
    isset($_GET['package_id']) &&
    is_numeric($_GET['package_id'])
) {

    $package_id = (int)$_GET['package_id'];

} elseif (
    isset($_GET['id']) &&
    is_numeric($_GET['id'])
) {

    $package_id = (int)$_GET['id'];

}

if ($package_id <= 0) {

    header("Location: packages.php");

    exit;
}

$sql = "

    SELECT
        p.id AS package_id,
        p.event_id,
        p.package_name,
        p.experience_level,
        p.description,
        p.image,
        p.status,

        e.event_name

    FROM packages p

    LEFT JOIN events e
        ON p.event_id = e.id

    WHERE p.id = ?

    LIMIT 1

";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Package Query Error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param("i", $package_id);

$stmt->execute();

$result = $stmt->get_result();

$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {

    die("

        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>

            <h2>❌ Package Not Found</h2>

            <p>
                Package ID
                <strong>
                    " . $package_id . "
                </strong>
                does not exist.
            </p>

            <a
                href='packages.php'
                style='
                    display:inline-block;
                    margin-top:15px;
                    padding:10px 20px;
                    background:#d4af37;
                    color:white;
                    text-decoration:none;
                    border-radius:8px;
                '
            >
                ← Back to Packages
            </a>

        </div>

    ");

}

$package_icons = [

    'basic'   => '⭐',
    'silver'  => '🥈',
    'premium' => '💎',
    'luxury'  => '👑',
    'vip'     => '💠'

];

$package_key = strtolower(
    trim($package['package_name'])
);

$icon =
    $package_icons[$package_key]
    ?? '📦';

$pricing = [];

$pricing_sql = "

    SELECT
        id,
        min_guests,
        max_guests,
        original_price,
        combo_price

    FROM package_pricing

    WHERE package_id = ?

    ORDER BY min_guests ASC

";

$pricing_stmt = $conn->prepare($pricing_sql);

if ($pricing_stmt) {

    $pricing_stmt->bind_param(
        "i",
        $package_id
    );

    $pricing_stmt->execute();

    $pricing_result =
        $pricing_stmt->get_result();

    while (
        $row =
        $pricing_result->fetch_assoc()
    ) {

        $pricing[] = $row;

    }

    $pricing_stmt->close();

}

$guests = 0;

if (
    isset($_GET['guests']) &&
    is_numeric($_GET['guests'])
) {

    $guests = (int)$_GET['guests'];

}

$selected_price = 0;

$selected_original = 0;

$selected_min = 0;

$selected_max = 0;

if ($guests > 0) {

    foreach ($pricing as $row) {

        $min =
            (int)$row['min_guests'];

        $max =
            (int)$row['max_guests'];

        if (
            $guests >= $min &&
            $guests <= $max
        ) {

            $selected_price =
                (float)$row['combo_price'];

            $selected_original =
                (float)$row['original_price'];

            $selected_min =
                $min;

            $selected_max =
                $max;

            break;

        }

    }

}

$services = [];

$services_sql = "

    SELECT

        s.id,
        s.service_name,
        s.description

    FROM package_services ps

    INNER JOIN services s
        ON ps.service_id = s.id

    WHERE ps.package_id = ?

    AND s.status = 'active'

    ORDER BY s.service_name ASC

";

$services_stmt =
    $conn->prepare($services_sql);

if ($services_stmt) {

    $services_stmt->bind_param(
        "i",
        $package_id
    );

    $services_stmt->execute();

    $services_result =
        $services_stmt->get_result();

    while (
        $row =
        $services_result->fetch_assoc()
    ) {

        $services[] = $row;

    }

    $services_stmt->close();

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

<?= htmlspecialchars(
    $package['package_name']
) ?>

- Event Planner

</title>

<style>

* {
    box-sizing:border-box;
}

body {

    margin:0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:#fffaf3;

    color:#333;

}

.header {

    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #ffd966
        );

    padding:18px 40px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.12);

}

.logo {

    font-size:25px;

    font-weight:bold;

    color:#7a4b00;

}

.back {

    text-decoration:none;

    color:#7a4b00;

    font-weight:bold;

}

.back:hover {

    color:#b8860b;

}

.container {

    width:94%;

    max-width:1050px;

    margin:35px auto;

}

.card {

    background:white;

    border-radius:16px;

    overflow:hidden;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.10);

}

.top {

    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #ffe7a3
        );

    text-align:center;

    padding:30px;

    color:#7a4b00;

}

.icon {

    font-size:65px;

}

.top h1 {

    margin:10px 0;

    font-size:32px;

}

.event {

    font-weight:bold;

    font-size:17px;

}

.content {

    padding:30px;

}

.package-image {

    width:100%;

    max-height:320px;

    object-fit:cover;

    border-radius:12px;

    margin-bottom:20px;

}

.no-image {

    height:200px;

    background:
        linear-gradient(
            135deg,
            #fff0f6,
            #fff6d9
        );

    border-radius:12px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:80px;

    margin-bottom:20px;

}

.experience {

    display:inline-block;

    padding:7px 14px;

    background:#f8c8dc;

    color:#6b3d52;

    border-radius:20px;

    text-transform:capitalize;

    font-weight:bold;

}

.description {

    margin-top:15px;

    color:#666;

    line-height:1.7;

}

.section {

    margin-top:30px;

    color:#8a5a00;

    border-bottom:
        2px solid #ead9a6;

    padding-bottom:10px;

}

.guest-box {

    background:#fffaf0;

    border:
        1px solid #ead9a6;

    padding:20px;

    border-radius:12px;

    margin-top:15px;

}

.guest-box input {

    width:100%;

    max-width:400px;

    padding:13px;

    border:
        1px solid #d4af37;

    border-radius:8px;

    font-size:16px;

}

.guest-box button {

    margin-top:12px;

    padding:12px 22px;

    background:#d4af37;

    color:white;

    border:0;

    border-radius:8px;

    font-weight:bold;

    cursor:pointer;

}

.guest-box button:hover {

    background:#b8941f;

}

.range {

    margin-top:12px;

    color:#777;

    font-size:13px;

}

.price-box {

    margin-top:20px;

    background:#fff8df;

    border:
        1px solid #ead9a6;

    padding:20px;

    border-radius:12px;

}

.exact-price {

    font-size:30px;

    color:#b8860b;

    font-weight:bold;

    margin-top:8px;

}

.original {

    color:#888;

    text-decoration:line-through;

    margin-top:5px;

}

.table {

    width:100%;

    border-collapse:collapse;

    margin-top:15px;

}

.table th {

    background:#d4af37;

    color:white;

    padding:12px;

    text-align:left;

}

.table td {

    padding:12px;

    border-bottom:
        1px solid #ead9a6;

}

.services {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:15px;

    margin-top:15px;

}

.service {

    background:#fffaf0;

    border:
        1px solid #ead9a6;

    padding:18px;

    border-radius:12px;

    transition:.2s;

}

.service:hover {

    transform:translateY(-2px);

    box-shadow:
        0 5px 15px
        rgba(0,0,0,.08);

}

.service-name {

    color:#8a5a00;

    font-weight:bold;

    font-size:17px;

}

.service-description {

    color:#777;

    font-size:13px;

    margin-top:7px;

    line-height:1.5;

}

.no-services {

    text-align:center;

    background:#fffaf0;

    padding:25px;

    border-radius:12px;

    margin-top:15px;

    color:#777;

}

.book-box {

    margin-top:30px;

    text-align:center;

    padding:25px;

    background:
        linear-gradient(
            135deg,
            #fff0f6,
            #fff8df
        );

    border-radius:12px;

}

.book-box h3 {

    color:#7a4b00;

    margin-bottom:8px;

}

.book-box p {

    color:#777;

    margin-bottom:20px;

}

.book-btn {

    display:inline-block;

    text-decoration:none;

    background:#d4af37;

    color:white;

    padding:13px 30px;

    border-radius:8px;

    font-weight:bold;

}

.book-btn:hover {

    background:#b8941f;

}

@media(max-width:700px) {

    .services {

        grid-template-columns:1fr;

    }

    .header {

        padding:18px;

        flex-direction:column;

        gap:12px;

    }

    .content {

        padding:20px;

    }

    .table {

        font-size:13px;

    }

}

</style>

</head>

<body>

<div class="header">

    <div class="logo">

        ✦ Event Planner

    </div>

    <a
        href="packages.php?event_id=<?= (int)$package['event_id'] ?>"
        class="back"
    >

        ← Back to Packages

    </a>

</div>

<div class="container">

<div class="card">

<div class="top">

    <div class="icon">

        <?= $icon ?>

    </div>

    <h1>

        <?= htmlspecialchars(
            $package['package_name']
        ) ?>

    </h1>

    <div class="event">

        🎉

        <?= htmlspecialchars(
            $package['event_name'] ?? 'Event'
        ) ?>

    </div>

</div>

<div class="content">

<?php if (
    !empty($package['image'])
): ?>

    <img
        src="../uploads/packages/<?= htmlspecialchars(
            basename(
                $package['image']
            )
        ) ?>"
        class="package-image"
        alt="Package Image"
    >

<?php else: ?>

    <div class="no-image">

        <?= $icon ?>

    </div>

<?php endif; ?>

<?php if (
    !empty(
        $package['experience_level']
    )
): ?>

    <span class="experience">

        <?= htmlspecialchars(
            $package['experience_level']
        ) ?>

    </span>

<?php endif; ?>

<div class="description">

    <?= !empty(
        $package['description']
    )
    ?
    nl2br(
        htmlspecialchars(
            $package['description']
        )
    )
    :
    "Package services included."
    ?>

</div>

<h2 class="section">

    👥 Select Number of Guests

</h2>

<div class="guest-box">

<form
    method="GET"
    action="package_details.php"
>

    <input
        type="hidden"
        name="package_id"
        value="<?= (int)$package_id ?>"
    >

    <input
        type="number"
        name="guests"
        min="1"
        value="<?= $guests > 0 ? $guests : '' ?>"
        placeholder="Enter number of guests"
        required
    >

    <br>

    <button type="submit">

        💰 Check Package Price

    </button>

</form>

<?php if (!empty($pricing)): ?>

    <div class="range">

        Available Guest Ranges:

        <?php

        $ranges = [];

        foreach ($pricing as $row) {

            $ranges[] =
                number_format(
                    $row['min_guests']
                )
                .
                " - "
                .
                number_format(
                    $row['max_guests']
                );

        }

        echo htmlspecialchars(
            implode(", ", $ranges)
        );

        ?>

    </div>

<?php else: ?>

    <div class="range">

        ⚠️ No guest pricing has been
        added for this package yet.

    </div>

<?php endif; ?>

<?php if ($guests > 0): ?>

    <?php if ($selected_price > 0): ?>

        <div class="price-box">

            <strong>

                💰 Package Price

            </strong>

            <div class="exact-price">

                Rs.

                <?= number_format(
                    $selected_price,
                    2
                ) ?>

            </div>

            <?php if (
                $selected_original > 0 &&
                $selected_original != $selected_price
            ): ?>

                <div class="original">

                    Original:

                    Rs.

                    <?= number_format(
                        $selected_original,
                        2
                    ) ?>

                </div>

            <?php endif; ?>

            <div class="range">

                👥

                <?= number_format(
                    $selected_min
                ) ?>

                -

                <?= number_format(
                    $selected_max
                ) ?>

                Guests

            </div>

        </div>

    <?php else: ?>

        <div class="price-box">

            ⚠️ No package price found for

            <strong>

                <?= number_format($guests) ?>

            </strong>

            guests.

            <br><br>

            Please enter a guest number
            within the available range.

        </div>

    <?php endif; ?>

<?php endif; ?>

</div>

<?php if (!empty($pricing)): ?>

<h2 class="section">

    💰 Package Pricing

</h2>

<table class="table">

<thead>

<tr>

    <th>
        👥 Guest Range
    </th>

    <th>
        💵 Original Price
    </th>

    <th>
        💰 Package Price
    </th>

</tr>

</thead>

<tbody>

<?php foreach (
    $pricing as $row
): ?>

<tr>

    <td>

        <?= number_format(
            $row['min_guests']
        ) ?>

        -

        <?= number_format(
            $row['max_guests']
        ) ?>

        Guests

    </td>

    <td>

        Rs.

        <?= number_format(
            $row['original_price'],
            2
        ) ?>

    </td>

    <td>

        <strong>

            Rs.

            <?= number_format(
                $row['combo_price'],
                2
            ) ?>

        </strong>

    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

<h2 class="section">

    🧩 Services Included in This Package

</h2>

<?php if (!empty($services)): ?>

<div class="services">

<?php foreach (
    $services as $service
): ?>

<div class="service">

    <div class="service-name">

        🛍️

        <?= htmlspecialchars(
            $service['service_name']
        ) ?>

    </div>

    <?php if (
        !empty(
            $service['description']
        )
    ): ?>

        <div class="service-description">

            <?= nl2br(
                htmlspecialchars(
                    $service['description']
                )
            ) ?>

        </div>

    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="no-services">

    <h3>

        🧩 No Services Added Yet

    </h3>

    <p>

        Admin has not added services
        to this package yet.

    </p>

</div>

<?php endif; ?>

<div class="book-box">

    <h3>

        🎉 Ready to Book This Package?

    </h3>

    <p>

        Select your guests and continue
        with your booking.

    </p>

    <?php if (
        $guests > 0 &&
        $selected_price > 0
    ): ?>

        <a
            href="booking.php?package_id=<?= (int)$package_id ?>&guests=<?= (int)$guests ?>"
            class="book-btn"
        >

            📅 Book This Package

        </a>

    <?php else: ?>

        <span
            style="
                color:#888;
                font-weight:bold;
            "
        >

            👥 Select Guests First

        </span>

    <?php endif; ?>

</div>

</div>

</div>

</div>

</body>

</html>