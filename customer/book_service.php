<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {

    $query_string = $_SERVER["QUERY_STRING"] ?? "";

    $redirect_url = "customer/book_service.php";

    if ($query_string !== "") {
        $redirect_url .= "?" . $query_string;
    }

    header(
        "Location: ../login.php?redirect=" .
        urlencode($redirect_url)
    );

    exit;
}

$customer_id = (int) $_SESSION["user_id"];

if (
    !isset($_GET["service_id"]) ||
    !is_numeric($_GET["service_id"])
) {
    header("Location: ../index.php#services");
    exit;
}

$service_id = (int) $_GET["service_id"];

if ($service_id <= 0) {
    header("Location: ../index.php#services");
    exit;
}

$provider_id = 0;

if (
    isset($_GET["provider_id"]) &&
    is_numeric($_GET["provider_id"])
) {
    $provider_id = (int) $_GET["provider_id"];
}

$requested_event_id = 0;

if (
    isset($_GET["event_id"]) &&
    is_numeric($_GET["event_id"])
) {
    $requested_event_id = (int) $_GET["event_id"];
}

$sql = "
    SELECT
        s.id,
        s.service_name,
        s.description,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.image,
        s.status,
        es.event_type_id,
        et.event_name
    FROM services s
    LEFT JOIN event_services es
        ON es.service_id = s.id
    LEFT JOIN event_types et
        ON et.id = es.event_type_id
    WHERE s.id = ?
      AND s.status = 'active'
";

if ($requested_event_id > 0) {
    $sql .= "
        AND es.event_type_id = ?
    ";
}

$sql .= "
    ORDER BY
        CASE
            WHEN es.event_type_id = ? THEN 0
            ELSE 1
        END
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "Service Query Error: " .
        htmlspecialchars($conn->error)
    );
}

if ($requested_event_id > 0) {

    $stmt->bind_param(
        "iii",
        $service_id,
        $requested_event_id,
        $requested_event_id
    );

} else {

    $stmt->bind_param(
        "ii",
        $service_id,
        $requested_event_id
    );
}

$stmt->execute();

$result = $stmt->get_result();

if (
    !$result ||
    $result->num_rows === 0
) {

    $stmt->close();

    die(
        "Service not found or service is not available."
    );
}

$service = $result->fetch_assoc();

$stmt->close();

$event_type_id = (int) ($service["event_type_id"] ?? 0);

if ($event_type_id <= 0) {

    $event_type_id = $requested_event_id;
}

if ($event_type_id <= 0) {

    $event_type_id = 1;
}

$provider = null;

if ($provider_id > 0) {

    $provider_sql = "
        SELECT
            id,
            name,
            phone,
            service_type,
            city,
            area,
            profile_photo,
            status
        FROM providers
        WHERE id = ?
          AND status = 'active'
        LIMIT 1
    ";

    $provider_stmt =
        $conn->prepare($provider_sql);

    if ($provider_stmt) {

        $provider_stmt->bind_param(
            "i",
            $provider_id
        );

        $provider_stmt->execute();

        $provider_result =
            $provider_stmt->get_result();

        if (
            $provider_result &&
            $provider_result->num_rows === 1
        ) {

            $provider =
                $provider_result->fetch_assoc();

        } else {

            $provider_id = 0;
        }

        $provider_stmt->close();

    } else {

        $provider_id = 0;
    }
}

$service_price =
    (float) ($service["price"] ?? 0);

$min_price =
    (float) ($service["min_price"] ?? 0);

$max_price =
    (float) ($service["max_price"] ?? 0);

if ($min_price <= 0 && $service_price > 0) {
    $min_price = $service_price;
}

if ($max_price <= 0 && $service_price > 0) {
    $max_price = $service_price;
}

if ($max_price > 0 && $min_price > $max_price) {

    $temporary_price = $min_price;

    $min_price = $max_price;

    $max_price = $temporary_price;
}

if ($min_price <= 0) {
    $min_price = 0;
}

if ($max_price <= 0) {
    $max_price = 0;
}

$booking_date = "";
$booking_time = "";

if ($min_price > 0) {
    $amount = $min_price;
} elseif ($service_price > 0) {
    $amount = $service_price;
} else {
    $amount = 0;
}

$customer_note = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $booking_date =
        trim($_POST["booking_date"] ?? "");

    $booking_time =
        trim($_POST["booking_time"] ?? "");

    $amount_input =
        trim($_POST["amount"] ?? "");

    $customer_note =
        trim($_POST["customer_note"] ?? "");

    if ($booking_date === "") {

        $error =
            "Please select booking date.";

    } elseif ($booking_time === "") {

        $error =
            "Please select booking time.";

    } elseif (
        $amount_input === "" ||
        !is_numeric($amount_input)
    ) {

        $error =
            "Please enter a valid amount.";

    } else {

        $amount = (float) $amount_input;

        if (
            $min_price > 0 &&
            $max_price > 0 &&
            (
                $amount < $min_price ||
                $amount > $max_price
            )
        ) {

            $error =
                "Amount must be between Rs. "
                . number_format($min_price, 0)
                . " and Rs. "
                . number_format($max_price, 0)
                . ".";

        } elseif (
            $min_price > 0 &&
            $max_price <= 0 &&
            $amount < $min_price
        ) {

            $error =
                "Amount must be at least Rs. "
                . number_format($min_price, 0)
                . ".";

        } elseif (
            $max_price > 0 &&
            $min_price <= 0 &&
            $amount > $max_price
        ) {

            $error =
                "Amount cannot be more than Rs. "
                . number_format($max_price, 0)
                . ".";

        } else {

            $today = date("Y-m-d");

            if ($booking_date < $today) {

                $error =
                    "Booking date cannot be in the past.";

            } else {

                $check_sql = "
                    SELECT id
                    FROM bookings
                    WHERE customer_id = ?
                      AND service_id = ?
                      AND booking_date = ?
                      AND booking_time = ?
                      AND status IN
                          ('pending', 'accepted')
                    LIMIT 1
                ";

                $check_stmt =
                    $conn->prepare($check_sql);

                $duplicate = false;

                if ($check_stmt) {

                    $check_stmt->bind_param(
                        "iiss",
                        $customer_id,
                        $service_id,
                        $booking_date,
                        $booking_time
                    );

                    $check_stmt->execute();

                    $check_result =
                        $check_stmt->get_result();

                    if (
                        $check_result &&
                        $check_result->num_rows > 0
                    ) {

                        $duplicate = true;
                    }

                    $check_stmt->close();
                }

                if ($duplicate) {

                    $error =
                        "You already have a booking for this service at the selected date and time.";

                } else {

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
                            ?,
                            NULL,
                            ?,
                            ?,
                            ?,
                            ?,
                            'pending',
                            'unpaid',
                            ?
                        )
                    ";

                    $insert_stmt =
                        $conn->prepare($insert_sql);

                    if (!$insert_stmt) {

                        $error =
                            "Booking Query Error: "
                            . htmlspecialchars(
                                $conn->error
                            );

                    } else {

                        $insert_stmt->bind_param(
                            "iiisssds",
                            $customer_id,
                            $event_type_id,
                            $service_id,
                            $provider_id,
                            $booking_date,
                            $booking_time,
                            $amount,
                            $customer_note
                        );

                        if (
                            $insert_stmt->execute()
                        ) {

                            $booking_id =
                                $conn->insert_id;

                            $insert_stmt->close();

                            $notification_title =
                                "Booking Submitted";

                            $notification_message =
                                "Your "
                                . $service["service_name"]
                                . " booking (#"
                                . $booking_id
                                . ") has been submitted successfully. Booking status: Pending.";

                            $notification_type =
                                "booking";

                            $customer_notification_sql = "
                                INSERT INTO notifications
                                (
                                    user_id,
                                    user_role,
                                    title,
                                    message,
                                    type,
                                    is_read,
                                    created_at
                                )
                                VALUES
                                (
                                    ?,
                                    'customer',
                                    ?,
                                    ?,
                                    ?,
                                    0,
                                    NOW()
                                )
                            ";

                            $customer_notification_stmt =
                                $conn->prepare(
                                    $customer_notification_sql
                                );

                            if (
                                $customer_notification_stmt
                            ) {

                                $customer_notification_stmt
                                    ->bind_param(
                                        "isss",
                                        $customer_id,
                                        $notification_title,
                                        $notification_message,
                                        $notification_type
                                    );

                                $customer_notification_stmt
                                    ->execute();

                                $customer_notification_stmt
                                    ->close();
                            }

                            if ($provider_id > 0) {

                                $provider_notification_title =
                                    "New Booking Request";

                                $provider_notification_message =
                                    "You received a new "
                                    . $service["service_name"]
                                    . " booking request (#"
                                    . $booking_id
                                    . ") for "
                                    . $booking_date
                                    . " at "
                                    . $booking_time
                                    . ".";

                                $provider_notification_sql = "
                                    INSERT INTO notifications
                                    (
                                        user_id,
                                        user_role,
                                        title,
                                        message,
                                        type,
                                        is_read,
                                        created_at
                                    )
                                    VALUES
                                    (
                                        ?,
                                        'provider',
                                        ?,
                                        ?,
                                        ?,
                                        0,
                                        NOW()
                                    )
                                ";

                                $provider_notification_stmt =
                                    $conn->prepare(
                                        $provider_notification_sql
                                    );

                                if (
                                    $provider_notification_stmt
                                ) {

                                    $provider_notification_stmt
                                        ->bind_param(
                                            "isss",
                                            $provider_id,
                                            $provider_notification_title,
                                            $provider_notification_message,
                                            $notification_type
                                        );

                                    $provider_notification_stmt
                                        ->execute();

                                    $provider_notification_stmt
                                        ->close();
                                }
                            }

                            header(
                                "Location: booking_success.php?id="
                                . $booking_id
                            );

                            exit;

                        } else {

                            $error =
                                "Booking failed: "
                                . htmlspecialchars(
                                    $insert_stmt->error
                                );

                            $insert_stmt->close();
                        }
                    }
                }
            }
        }
    }
}

$back_url =
    "../index.php#services";

if (
    isset($_GET["event_id"]) &&
    is_numeric($_GET["event_id"])
) {

    $back_url =
        "service_details.php?id="
        . (int) $service["id"]
        . "&event_id="
        . (int) $_GET["event_id"];
}

$provider_photo = "";

if (
    $provider &&
    !empty($provider["profile_photo"])
) {

    $provider_photo =
        "../provider/uploads/profile/"
        . basename(
            $provider["profile_photo"]
        );
}

$portfolio_url =
    "../portfolio.php";

if ($provider_id > 0) {

    $portfolio_url =
        "../portfolio.php?provider_id="
        . $provider_id;
}

$display_min_price =
    $min_price > 0
        ? "Rs. " . number_format($min_price, 0)
        : "Contact Provider";

$display_max_price =
    $max_price > 0
        ? "Rs. " . number_format($max_price, 0)
        : "";

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
    Book <?= htmlspecialchars(
        $service["service_name"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>
    - Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #fffaf5;
    color: #444;
}

.header {
    background: linear-gradient(
        135deg,
        #f8c8dc,
        #f6d365
    );
    padding: 18px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: #805d00;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.back-btn,
.portfolio-btn {
    text-decoration: none;
    color: white;
    padding: 10px 17px;
    border-radius: 8px;
    font-weight: bold;
}

.back-btn {
    background: #b8860b;
}

.portfolio-btn {
    background: #8b5e83;
}

.container {
    width: 92%;
    max-width: 850px;
    margin: 40px auto 70px;
}

.booking-card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

h1 {
    color: #8b6508;
    margin-bottom: 8px;
}

.subtitle {
    color: #777;
    margin-bottom: 25px;
}

.event-badge {
    display: inline-block;
    background: #fff0c8;
    color: #8b6508;
    border: 1px solid #e4c75c;
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 20px;
}

.service-info {
    background: #fffaf0;
    border: 1px solid #ead58b;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.service-info h2 {
    color: #8b6508;
    font-size: 21px;
    margin-bottom: 8px;
}

.service-info p {
    color: #666;
    line-height: 1.5;
}

.price {
    color: #b8860b;
    font-size: 20px;
    font-weight: bold;
    margin-top: 12px;
}

.provider-box {
    display: flex;
    align-items: center;
    gap: 15px;
    background: #fff8ef;
    border: 1px solid #ead58b;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 25px;
}

.provider-photo {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #d4af37;
}

.provider-avatar {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: #f8c8dc;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    color: #8b6508;
    font-weight: bold;
    flex-shrink: 0;
}

.provider-info h3 {
    color: #805d00;
    margin-bottom: 5px;
}

.provider-info p {
    color: #777;
    font-size: 14px;
    margin-top: 3px;
}

.request-label {
    display: inline-block;
    margin-top: 7px;
    background: #fff0c8;
    color: #8b6508;
    padding: 4px 9px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #805d00;
    font-weight: bold;
    margin-bottom: 7px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #ddd;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
    background: white;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #d4af37;
    box-shadow: 0 0 0 3px rgba(212,175,55,0.12);
}

textarea {
    resize: vertical;
    min-height: 100px;
}

.amount-note {
    color: #888;
    font-size: 12px;
    margin-top: 5px;
    line-height: 1.5;
}

.error {
    background: #ffe5e5;
    color: #b00020;
    border: 1px solid #f3aaaa;
    padding: 13px;
    border-radius: 9px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.book-submit {
    width: 100%;
    border: none;
    cursor: pointer;
    background: linear-gradient(
        135deg,
        #d4af37,
        #b8860b
    );
    color: white;
    padding: 15px;
    border-radius: 10px;
    font-size: 17px;
    font-weight: bold;
}

.book-submit:hover {
    opacity: 0.92;
}

.note {
    text-align: center;
    margin-top: 15px;
    color: #888;
    font-size: 13px;
    line-height: 1.5;
}

.login-note {
    background: #f8eff8;
    border: 1px solid #e5cde2;
    color: #76506e;
    padding: 12px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.5;
}

@media(max-width:600px) {

    .header {
        padding: 15px 20px;
        align-items: flex-start;
        flex-direction: column;
    }

    .logo {
        font-size: 20px;
    }

    .header-actions {
        width: 100%;
    }

    .back-btn,
    .portfolio-btn {
        padding: 8px 12px;
        font-size: 13px;
    }

    .container {
        width: 94%;
        margin-top: 25px;
    }

    .booking-card {
        padding: 20px;
    }

    h1 {
        font-size: 26px;
    }

    .provider-box {
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="logo">
        🎉 Event Planner
    </div>

    <div class="header-actions">

        <a
            href="<?= htmlspecialchars(
                $portfolio_url,
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
            class="portfolio-btn"
        >
            📸 Portfolio
        </a>

        <a
            href="<?= htmlspecialchars(
                $back_url,
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
            class="back-btn"
        >
            ← Back
        </a>

    </div>

</header>

<div class="container">

    <div class="booking-card">

        <h1>
            📅 Book Service
        </h1>

        <p class="subtitle">
            Enter your event booking details.
        </p>

        <?php if (!empty($service["event_name"])): ?>

            <div class="event-badge">

                🎉 Event:
                <?= htmlspecialchars(
                    $service["event_name"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <div class="login-note">

            You are logged in as
            <strong>
                <?= htmlspecialchars(
                    $_SESSION["user_name"] ??
                    $_SESSION["user_email"] ??
                    "Customer",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>.

            You can now submit your booking request.

        </div>

        <?php if ($error !== ""): ?>

            <div class="error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <div class="service-info">

            <h2>

                <?= htmlspecialchars(
                    $service["service_name"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </h2>

            <p>

                <?= htmlspecialchars(
                    $service["description"]
                    ?: "Professional event service.",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </p>

            <div class="price">

                <?php if (
                    $min_price > 0 &&
                    $max_price > 0 &&
                    $min_price != $max_price
                ): ?>

                    <?= $display_min_price ?>
                    -
                    <?= $display_max_price ?>

                <?php elseif ($min_price > 0): ?>

                    <?= $display_min_price ?>

                <?php elseif ($max_price > 0): ?>

                    Up to
                    <?= $display_max_price ?>

                <?php else: ?>

                    Contact Provider

                <?php endif; ?>

                <?php if (!empty($service["unit"])): ?>

                    /
                    <?= htmlspecialchars(
                        $service["unit"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                <?php endif; ?>

            </div>

        </div>

        <?php if ($provider): ?>

            <div class="provider-box">

                <?php if ($provider_photo): ?>

                    <img
                        src="<?= htmlspecialchars(
                            $provider_photo,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        class="provider-photo"
                        alt="Provider"
                    >

                <?php else: ?>

                    <div class="provider-avatar">

                        <?= htmlspecialchars(
                            strtoupper(
                                substr(
                                    $provider["name"],
                                    0,
                                    1
                                )
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>

                <?php endif; ?>

                <div class="provider-info">

                    <h3>

                        <?= htmlspecialchars(
                            $provider["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </h3>

                    <p>

                        <?= htmlspecialchars(
                            $provider["service_type"]
                            ?: "Service Provider",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </p>

                    <?php if (
                        !empty($provider["area"]) ||
                        !empty($provider["city"])
                    ): ?>

                        <p>

                            <?= htmlspecialchars(
                                trim(
                                    ($provider["area"] ?? "")
                                    .
                                    (
                                        !empty(
                                            $provider["area"]
                                        ) &&
                                        !empty(
                                            $provider["city"]
                                        )
                                        ? ", "
                                        : ""
                                    )
                                    .
                                    ($provider["city"] ?? "")
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </p>

                    <?php endif; ?>

                    <span class="request-label">
                        Requesting this provider
                    </span>

                </div>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
        >

            <div class="form-group">

                <label>
                    📅 Booking Date
                </label>

                <input
                    type="date"
                    name="booking_date"
                    value="<?= htmlspecialchars(
                        $booking_date,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    min="<?= date("Y-m-d") ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    ⏰ Booking Time
                </label>

                <input
                    type="time"
                    name="booking_time"
                    value="<?= htmlspecialchars(
                        $booking_time,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    💰 Service Amount
                </label>

                <input
                    type="number"
                    name="amount"
                    value="<?= htmlspecialchars(
                        $amount,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    <?php if ($min_price > 0): ?>

                        min="<?= htmlspecialchars(
                            $min_price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"

                    <?php endif; ?>

                    <?php if ($max_price > 0): ?>

                        max="<?= htmlspecialchars(
                            $max_price,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"

                    <?php endif; ?>

                    step="0.01"
                    required
                >

                <?php if (
                    $min_price > 0 &&
                    $max_price > 0
                ): ?>

                    <div class="amount-note">

                        Enter amount between
                        Rs.
                        <?= number_format(
                            $min_price,
                            0
                        ) ?>
                        and
                        Rs.
                        <?= number_format(
                            $max_price,
                            0
                        ) ?>.

                    </div>

                <?php elseif ($min_price > 0): ?>

                    <div class="amount-note">

                        Minimum amount:
                        Rs.
                        <?= number_format(
                            $min_price,
                            0
                        ) ?>

                    </div>

                <?php elseif ($max_price > 0): ?>

                    <div class="amount-note">

                        Maximum amount:
                        Rs.
                        <?= number_format(
                            $max_price,
                            0
                        ) ?>

                    </div>

                <?php else: ?>

                    <div class="amount-note">

                        Enter the amount agreed with the provider.

                    </div>

                <?php endif; ?>

            </div>

            <div class="form-group">

                <label>
                    📝 Additional Note
                </label>

                <textarea
                    name="customer_note"
                    placeholder="Write any special requirement..."
                ><?= htmlspecialchars(
                    $customer_note,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

            </div>

            <button
                type="submit"
                class="book-submit"
            >
                📅 Send Booking Request
            </button>

            <div class="note">

                Your booking will be sent to the selected provider as
                <strong>Pending</strong>.

                <br>

                Payment can be completed after the provider confirms the booking.

            </div>

        </form>

    </div>

</div>

</body>

</html>