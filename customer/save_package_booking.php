<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: services.php");
    exit;
}

$package_id = isset($_POST['package_id'])
    ? (int) $_POST['package_id']
    : 0;

$pricing_id = isset($_POST['pricing_id'])
    ? (int) $_POST['pricing_id']
    : 0;

$event_type_id = isset($_POST['event_type_id'])
    ? (int) $_POST['event_type_id']
    : 0;

$guests_min = isset($_POST['guests_min'])
    ? (int) $_POST['guests_min']
    : 0;

$guests_max = isset($_POST['guests_max'])
    ? (int) $_POST['guests_max']
    : 0;

$guests = isset($_POST['guests'])
    ? (int) $_POST['guests']
    : 0;

$booking_date = isset($_POST['booking_date'])
    ? trim($_POST['booking_date'])
    : '';

$booking_time = isset($_POST['booking_time'])
    ? trim($_POST['booking_time'])
    : '';

$customer_note = isset($_POST['customer_note'])
    ? trim($_POST['customer_note'])
    : '';

if (
    $package_id <= 0 ||
    $pricing_id <= 0 ||
    $event_type_id <= 0 ||
    $guests_min <= 0 ||
    $guests_max <= 0 ||
    empty($booking_date) ||
    empty($booking_time)
) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Invalid Booking Information</h2>
            <p>Please go back and select the package, guest range, date and time again.</p>
            <a href='services.php'>← Back to Services</a>
        </div>
    ");
}

if ($guests_max < $guests_min) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Invalid Guest Range</h2>
            <p>The selected guest range is invalid.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Back to Package
            </a>
        </div>
    ");
}

$guests = $guests_min;

$package_sql = "
    SELECT
        p.id,
        p.event_id,
        p.provider_id,
        p.package_name,
        p.status,
        et.event_name
    FROM packages p
    LEFT JOIN event_types et
        ON p.event_id = et.id
    WHERE p.id = ?
      AND LOWER(p.status) = 'active'
    LIMIT 1
";

$package_stmt = $conn->prepare($package_sql);

if (!$package_stmt) {
    die("Package Query Error: " . htmlspecialchars($conn->error));
}

$package_stmt->bind_param("i", $package_id);
$package_stmt->execute();

$package_result = $package_stmt->get_result();
$package = $package_result->fetch_assoc();

$package_stmt->close();

if (!$package) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Package Not Found</h2>
            <p>The selected package does not exist or is inactive.</p>
            <a href='services.php'>← Back to Services</a>
        </div>
    ");
}

if ((int)$package['event_id'] !== $event_type_id) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Event Mismatch</h2>
            <p>This package does not belong to the selected event.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Back to Package
            </a>
        </div>
    ");
}

$provider_id = (int)$package['provider_id'];

if ($provider_id <= 0) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Provider Not Assigned</h2>
            <p>This package does not have a service provider assigned yet.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Back to Package
            </a>
        </div>
    ");
}

$pricing_sql = "
    SELECT
        id,
        package_id,
        min_guests,
        max_guests,
        original_price,
        combo_price
    FROM package_pricing
    WHERE id = ?
      AND package_id = ?
    LIMIT 1
";

$pricing_stmt = $conn->prepare($pricing_sql);

if (!$pricing_stmt) {
    die("Pricing Query Error: " . htmlspecialchars($conn->error));
}

$pricing_stmt->bind_param(
    "ii",
    $pricing_id,
    $package_id
);

$pricing_stmt->execute();

$pricing_result = $pricing_stmt->get_result();
$pricing = $pricing_result->fetch_assoc();

$pricing_stmt->close();

if (!$pricing) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Pricing Not Found</h2>
            <p>The selected guest pricing does not belong to this package.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Select Guest Range Again
            </a>
        </div>
    ");
}

$db_min_guests = (int)$pricing['min_guests'];
$db_max_guests = (int)$pricing['max_guests'];

if (
    $guests_min !== $db_min_guests ||
    $guests_max !== $db_max_guests
) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Guest Range Mismatch</h2>
            <p>The selected guest range is no longer valid.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Select Guest Range Again
            </a>
        </div>
    ");
}

$amount = (float)$pricing['combo_price'];

if ($amount <= 0) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>⚠️ Price Not Available</h2>
            <p>The selected guest range does not have a valid package price.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Select Guest Range Again
            </a>
        </div>
    ");
}

$provider_sql = "
    SELECT
        id,
        name,
        status
    FROM providers
    WHERE id = ?
      AND LOWER(status) = 'active'
    LIMIT 1
";

$provider_stmt = $conn->prepare($provider_sql);

if (!$provider_stmt) {
    die("Provider Query Error: " . htmlspecialchars($conn->error));
}

$provider_stmt->bind_param("i", $provider_id);
$provider_stmt->execute();

$provider_result = $provider_stmt->get_result();
$provider = $provider_result->fetch_assoc();

$provider_stmt->close();

if (!$provider) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Provider Unavailable</h2>
            <p>The provider assigned to this package is not currently active.</p>
            <a href='view_package.php?package_id=" . $package_id . "'>
                ← Back to Package
            </a>
        </div>
    ");
}

$availability_sql = "
    SELECT
        id,
        start_time,
        end_time,
        status
    FROM provider_availability
    WHERE provider_id = ?
      AND available_date = ?
    ORDER BY id DESC
    LIMIT 1
";

$availability_stmt = $conn->prepare($availability_sql);

if (!$availability_stmt) {
    die("Availability Query Error: " . htmlspecialchars($conn->error));
}

$availability_stmt->bind_param(
    "is",
    $provider_id,
    $booking_date
);

$availability_stmt->execute();

$availability_result = $availability_stmt->get_result();
$availability = $availability_result->fetch_assoc();

$availability_stmt->close();

if (!$availability) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Provider Not Available</h2>
            <p>
                " . htmlspecialchars($provider['name']) . "
                is not available on
                <strong>" . htmlspecialchars($booking_date) . "</strong>.
            </p>
            <a href='book_package.php?package_id=" . $package_id . "&pricing_id=" . $pricing_id . "'>
                ← Choose Another Date
            </a>
        </div>
    ");
}

if (strtolower($availability['status']) !== 'available') {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Provider Unavailable</h2>
            <p>
                " . htmlspecialchars($provider['name']) . "
                is marked unavailable on
                <strong>" . htmlspecialchars($booking_date) . "</strong>.
            </p>
            <a href='book_package.php?package_id=" . $package_id . "&pricing_id=" . $pricing_id . "'>
                ← Choose Another Date
            </a>
        </div>
    ");
}

$selected_time = strtotime($booking_time);
$start_time = strtotime($availability['start_time']);
$end_time = strtotime($availability['end_time']);

if (
    $selected_time === false ||
    $start_time === false ||
    $end_time === false
) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Invalid Time</h2>
            <p>Please select a valid booking time.</p>
            <a href='book_package.php?package_id=" . $package_id . "&pricing_id=" . $pricing_id . "'>
                ← Back
            </a>
        </div>
    ");
}

if ($selected_time < $start_time || $selected_time > $end_time) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Time Not Available</h2>
            <p>
                " . htmlspecialchars($provider['name']) . "
                is available from
                <strong>" . date('h:i A', $start_time) . "</strong>
                to
                <strong>" . date('h:i A', $end_time) . "</strong>.
            </p>
            <a href='book_package.php?package_id=" . $package_id . "&pricing_id=" . $pricing_id . "'>
                ← Choose Another Time
            </a>
        </div>
    ");
}

$duplicate_sql = "
    SELECT id
    FROM bookings
    WHERE customer_id = ?
      AND package_id = ?
      AND booking_date = ?
      AND status IN ('pending', 'confirmed')
    LIMIT 1
";

$duplicate_stmt = $conn->prepare($duplicate_sql);

if ($duplicate_stmt) {

    $duplicate_stmt->bind_param(
        "iis",
        $customer_id,
        $package_id,
        $booking_date
    );

    $duplicate_stmt->execute();

    $duplicate_result = $duplicate_stmt->get_result();
    $duplicate = $duplicate_result->fetch_assoc();

    $duplicate_stmt->close();

    if ($duplicate) {
        die("
            <div style='font-family:Arial;text-align:center;margin-top:100px;'>
                <h2>⚠️ Booking Already Exists</h2>
                <p>
                    You already have a booking for this package
                    on this date.
                </p>
                <a href='my_bookings.php'>📅 View My Bookings</a>
            </div>
        ");
    }
}

$provider_booking_sql = "
    SELECT id
    FROM bookings
    WHERE provider_id = ?
      AND booking_date = ?
      AND booking_time = ?
      AND status IN ('pending', 'confirmed')
    LIMIT 1
";

$provider_booking_stmt = $conn->prepare($provider_booking_sql);

if (!$provider_booking_stmt) {
    die("Provider Booking Query Error: " . htmlspecialchars($conn->error));
}

$provider_booking_stmt->bind_param(
    "iss",
    $provider_id,
    $booking_date,
    $booking_time
);

$provider_booking_stmt->execute();

$provider_booking_result = $provider_booking_stmt->get_result();
$provider_booking = $provider_booking_result->fetch_assoc();

$provider_booking_stmt->close();

if ($provider_booking) {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>⚠️ Time Already Booked</h2>
            <p>
                " . htmlspecialchars($provider['name']) . "
                already has another booking at this date and time.
            </p>
            <a href='book_package.php?package_id=" . $package_id . "&pricing_id=" . $pricing_id . "'>
                ← Choose Another Time
            </a>
        </div>
    ");
}

$insert_sql = "
    INSERT INTO bookings
    (
        customer_id,
        event_type_id,
        service_id,
        package_id,
        provider_id,
        booking_date,
        booking_time,
        amount,
        status,
        payment_status,
        customer_note
    )
    VALUES
    (
        ?,
        ?,
        NULL,
        ?,
        ?,
        ?,
        ?,
        ?,
        'pending',
        'unpaid',
        ?
    )
";

$insert_stmt = $conn->prepare($insert_sql);

if (!$insert_stmt) {
    die("Booking Insert Query Error: " . htmlspecialchars($conn->error));
}

$insert_stmt->bind_param(
    "iiiissds",
    $customer_id,
    $event_type_id,
    $package_id,
    $provider_id,
    $booking_date,
    $booking_time,
    $amount,
    $customer_note
);

if ($insert_stmt->execute()) {

    $booking_id = $insert_stmt->insert_id;

    $insert_stmt->close();

    ?>

    <!DOCTYPE html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Booking Successful - Event Planner</title>

        <style>

            * {
                box-sizing:border-box;
            }

            body {
                margin:0;
                font-family:Arial,sans-serif;
                background:#fffaf3;
                color:#333;
            }

            .container {
                width:90%;
                max-width:650px;
                margin:80px auto;
            }

            .card {
                background:white;
                padding:40px;
                border-radius:18px;
                text-align:center;
                box-shadow:0 5px 25px rgba(0,0,0,.10);
            }

            .success {
                font-size:70px;
            }

            h1 {
                color:#7a4b00;
                margin-bottom:10px;
            }

            .message {
                color:#666;
                line-height:1.7;
            }

            .details {
                margin-top:25px;
                background:#fffaf0;
                border:1px solid #ead9a6;
                border-radius:12px;
                padding:20px;
                text-align:left;
            }

            .row {
                display:flex;
                justify-content:space-between;
                padding:10px 0;
                border-bottom:1px solid #eee;
                gap:20px;
            }

            .row:last-child {
                border-bottom:none;
            }

            .label {
                font-weight:bold;
                color:#7a4b00;
            }

            .price {
                color:#b8860b;
                font-weight:bold;
                font-size:20px;
            }

            .status {
                display:inline-block;
                margin-top:20px;
                padding:8px 18px;
                border-radius:20px;
                background:#fff0c2;
                color:#8a5a00;
                font-weight:bold;
            }

            .buttons {
                margin-top:25px;
            }

            .btn {
                display:inline-block;
                padding:12px 20px;
                margin:5px;
                border-radius:8px;
                text-decoration:none;
                font-weight:bold;
            }

            .primary {
                background:#d4af37;
                color:white;
            }

            .secondary {
                background:#f8c8dc;
                color:#7a4b00;
            }

        </style>

    </head>

    <body>

        <div class="container">

            <div class="card">

                <div class="success">✅</div>

                <h1>Booking Successful!</h1>

                <p class="message">

                    Your package booking has been submitted successfully.

                    <br>

                    The booking is currently
                    <strong>Pending</strong>
                    confirmation.

                </p>

                <div class="details">

                    <div class="row">
                        <span class="label">Booking ID</span>
                        <span>#<?= $booking_id ?></span>
                    </div>

                    <div class="row">
                        <span class="label">Package</span>
                        <span>
                            <?= htmlspecialchars($package['package_name']) ?>
                        </span>
                    </div>

                    <div class="row">
                        <span class="label">Event</span>
                        <span>
                            <?= htmlspecialchars($package['event_name']) ?>
                        </span>
                    </div>

                    <div class="row">
                        <span class="label">Provider</span>
                        <span>
                            <?= htmlspecialchars($provider['name']) ?>
                        </span>
                    </div>

                    <div class="row">
                        <span class="label">Guests</span>
                        <span>
                            <?= number_format($guests_min) ?>
                            -
                            <?= number_format($guests_max) ?>
                        </span>
                    </div>

                    <div class="row">
                        <span class="label">Date</span>
                        <span>
                            <?= htmlspecialchars($booking_date) ?>
                        </span>
                    </div>

                    <div class="row">
                        <span class="label">Time</span>
                        <span>
                            <?= htmlspecialchars($booking_time) ?>
                        </span>
                    </div>

                    <div class="row">
                        <span class="label">Amount</span>
                        <span class="price">
                            Rs. <?= number_format($amount, 2) ?>
                        </span>
                    </div>

                </div>

                <div class="status">
                    ⏳ Pending Confirmation
                </div>

                <div class="buttons">

                    <a href="my_bookings.php" class="btn primary">
                        📅 My Bookings
                    </a>

                    <a href="services.php" class="btn secondary">
                        📦 Browse Packages
                    </a>

                </div>

            </div>

        </div>

    </body>

    </html>

    <?php

} else {

    $error = $insert_stmt->error;

    $insert_stmt->close();

    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Booking Failed</h2>
            <p>" . htmlspecialchars($error) . "</p>
            <a href='book_package.php?package_id="
                . $package_id
                . "&pricing_id="
                . $pricing_id
                . "'>
                ← Back to Package Booking
            </a>
        </div>
    ");
}

?>