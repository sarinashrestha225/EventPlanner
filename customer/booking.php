<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}

$customer_id = (int) $_SESSION['user_id'];

$package_id = 0;

if (
    isset($_GET['package_id']) &&
    is_numeric($_GET['package_id'])
) {

    $package_id = (int) $_GET['package_id'];

}

$guests = 0;

if (
    isset($_GET['guests']) &&
    is_numeric($_GET['guests'])
) {

    $guests = (int) $_GET['guests'];

}

if ($package_id <= 0) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>

            <h2>❌ Invalid Package</h2>

            <p>
                Package ID was not received.
            </p>

            <a href='packages.php'>
                ← Back to Packages
            </a>

        </div>
    ");

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
        "Package Query Error: "
        . $conn->error
    );

}

$stmt->bind_param(
    "i",
    $package_id
);

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
                Package ID:
                <strong>
                    " . $package_id . "
                </strong>
                does not exist.
            </p>

            <a href='packages.php'>
                ← Back to Packages
            </a>

        </div>
    ");

}

$pricing = [];

$pricing_sql = "

    SELECT

        min_guests,
        max_guests,
        original_price,
        combo_price

    FROM package_pricing

    WHERE package_id = ?

    ORDER BY min_guests ASC

";

$pricing_stmt =
    $conn->prepare($pricing_sql);

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

$selected_price = 0;

$selected_original_price = 0;

$selected_min_guests = 0;

$selected_max_guests = 0;

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

            $selected_original_price =
                (float)$row['original_price'];

            $selected_min_guests =
                $min;

            $selected_max_guests =
                $max;

            break;

        }

    }

}

$icons = [

    'basic' =>
        '⭐',

    'silver' =>
        '🥈',

    'premium' =>
        '💎',

    'luxury' =>
        '👑',

    'vip' =>
        '💠'

];

$package_key =
    strtolower(
        trim(
            $package['package_name']
        )
    );

$icon =
    $icons[$package_key]
    ?? '📦';

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

Book
<?= htmlspecialchars(
    $package['package_name']
) ?>

-
Event Planner

</title>

<style>

* {

    box-sizing:
        border-box;

}

body {

    margin:0;

    font-family:
        Arial,
        sans-serif;

    background:
        #fffaf3;

    color:
        #333;

}

.header {

    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #ffd966
        );

    padding:
        20px 40px;

    display:flex;

    justify-content:
        space-between;

    align-items:
        center;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.10);

}

.logo {

    font-size:
        25px;

    font-weight:
        bold;

    color:
        #7a4b00;

}

.back {

    text-decoration:
        none;

    color:
        #7a4b00;

    font-weight:
        bold;

}

.container {

    width:
        94%;

    max-width:
        900px;

    margin:
        35px auto;

}

.card {

    background:
        white;

    border-radius:
        16px;

    overflow:
        hidden;

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

    text-align:
        center;

    padding:
        30px;

    color:
        #7a4b00;

}

.icon {

    font-size:
        60px;

}

.top h1 {

    margin:
        10px 0;

    font-size:
        32px;

}

.event {

    font-size:
        18px;

    font-weight:
        bold;

}

.content {

    padding:
        30px;

}

.summary {

    background:
        #fffaf0;

    border:
        1px solid #ead9a6;

    border-radius:
        12px;

    padding:
        20px;

}

.summary-row {

    display:
        flex;

    justify-content:
        space-between;

    padding:
        12px 0;

    border-bottom:
        1px solid #eee;

}

.summary-row:last-child {

    border-bottom:
        none;

}

.label {

    font-weight:
        bold;

    color:
        #7a4b00;

}

.value {

    font-weight:
        bold;

}

.section {

    margin-top:
        30px;

    color:
        #8a5a00;

    border-bottom:
        2px solid #ead9a6;

    padding-bottom:
        10px;

}

.form-group {

    margin-top:
        18px;

}

.form-group label {

    display:
        block;

    font-weight:
        bold;

    color:
        #7a4b00;

    margin-bottom:
        8px;

}

.form-group input,
.form-group textarea {

    width:
        100%;

    padding:
        13px;

    border:
        1px solid #d4af37;

    border-radius:
        8px;

    font-size:
        16px;

    background:
        #fffdf7;

}

.form-group textarea {

    min-height:
        120px;

    resize:
        vertical;

}

.price-box {

    margin-top:
        25px;

    background:
        #fff8df;

    border:
        1px solid #ead9a6;

    border-radius:
        12px;

    padding:
        20px;

    text-align:
        center;

}

.price {

    font-size:
        32px;

    color:
        #b8860b;

    font-weight:
        bold;

    margin-top:
        8px;

}

.original {

    color:
        #888;

    text-decoration:
        line-through;

    margin-top:
        5px;

}

.warning {

    background:
        #fff0f0;

    border:
        1px solid #e5aaaa;

    color:
        #a33;

    padding:
        18px;

    border-radius:
        10px;

    margin-top:
        20px;

}

.confirm-btn {

    width:
        100%;

    margin-top:
        25px;

    padding:
        15px;

    border:
        none;

    border-radius:
        9px;

    background:
        #d4af37;

    color:
        white;

    font-size:
        17px;

    font-weight:
        bold;

    cursor:
        pointer;

}

.confirm-btn:hover {

    background:
        #b8941f;

}

@media(max-width:600px) {

    .header {

        padding:
            18px;

    }

    .content {

        padding:
            20px;

    }

    .summary-row {

        flex-direction:
            column;

        gap:
            5px;

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
        href="package_details.php?package_id=<?= $package_id ?>&guests=<?= $guests ?>"
        class="back"
    >

        ← Back to Package

    </a>

</div>

<div class="container">

<div class="card">

<div class="top">

    <div class="icon">

        <?= $icon ?>

    </div>

    <h1>

        Book Package

    </h1>

    <div class="event">

        🎉

        <?= htmlspecialchars(
            $package['event_name']
        ) ?>

    </div>

</div>

<div class="content">

<div class="summary">

    <div class="summary-row">

        <span class="label">

            📦 Package

        </span>

        <span class="value">

            <?= htmlspecialchars(
                $package['package_name']
            ) ?>

        </span>

    </div>

    <div class="summary-row">

        <span class="label">

            🎉 Event

        </span>

        <span class="value">

            <?= htmlspecialchars(
                $package['event_name']
            ) ?>

        </span>

    </div>

    <div class="summary-row">

        <span class="label">

            👤 Customer ID

        </span>

        <span class="value">

            <?= $customer_id ?>

        </span>

    </div>

    <div class="summary-row">

        <span class="label">

            👥 Guests

        </span>

        <span class="value">

            <?= $guests > 0
                ? number_format($guests)
                : 'Not selected'
            ?>

        </span>

    </div>

</div>

<h2 class="section">

    📅 Event Booking Information

</h2>

<?php if ($selected_price > 0): ?>

<form
    method="POST"
    action="save_package_booking.php"
>

    <input
        type="hidden"
        name="package_id"
        value="<?= $package_id ?>"
    >

    <input
        type="hidden"
        name="event_type_id"
        value="<?= (int)$package['event_id'] ?>"
    >

    <input
        type="hidden"
        name="guests"
        value="<?= $guests ?>"
    >

    <input
        type="hidden"
        name="amount"
        value="<?= $selected_price ?>"
    >

    <div class="form-group">

        <label>

            📅 Event Date

        </label>

        <input
            type="date"
            name="booking_date"
            min="<?= date('Y-m-d') ?>"
            required
        >

    </div>

    <div class="form-group">

        <label>

            ⏰ Event Time

        </label>

        <input
            type="time"
            name="booking_time"
            required
        >

    </div>

    <div class="form-group">

        <label>

            📝 Additional Note

        </label>

        <textarea
            name="customer_note"
            placeholder="Write any special requirements..."
        ></textarea>

    </div>

    <div class="price-box">

        <strong>

            💰 Package Price

        </strong>

        <div class="price">

            Rs.

            <?= number_format(
                $selected_price,
                2
            ) ?>

        </div>

        <?php if (
            $selected_original_price > 0 &&
            $selected_original_price !=
            $selected_price
        ): ?>

            <div class="original">

                Original:

                Rs.

                <?= number_format(
                    $selected_original_price,
                    2
                ) ?>

            </div>

        <?php endif; ?>

        <div style="margin-top:10px;">

            👥

            <?= number_format(
                $selected_min_guests
            ) ?>

            -

            <?= number_format(
                $selected_max_guests
            ) ?>

            Guests

        </div>

    </div>

    <button
        type="submit"
        class="confirm-btn"
    >

        📅 Confirm Booking

    </button>

</form>

<?php else: ?>

<div class="warning">

    ⚠️

    No valid price was found for

    <strong>

        <?= number_format($guests) ?>

    </strong>

    guests.

    <br><br>

    Please go back and select a valid guest number.

</div>

<a
    href="package_details.php?package_id=<?= $package_id ?>"
    class="confirm-btn"
    style="
        display:block;
        text-align:center;
        text-decoration:none;
    "
>

    👥 Select Guests

</a>

<?php endif; ?>

</div>

</div>

</div>

</body>

</html>