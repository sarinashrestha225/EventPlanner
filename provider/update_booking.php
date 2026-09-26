<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];

$booking_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

$action = isset($_GET["action"])
    ? strtolower(trim($_GET["action"]))
    : "";

if ($booking_id <= 0) {
    die("
        <h2>❌ Invalid Booking ID</h2>
        <p>Booking ID is missing or invalid.</p>
        <a href='bookings.php'>← Back to Bookings</a>
    ");
}

if ($action !== "accept" && $action !== "reject") {
    die("
        <h2>❌ Invalid Action</h2>
        <p>Please use Accept or Reject button from Booking Details.</p>
        <p>
            Booking ID:
            <strong>" . $booking_id . "</strong>
        </p>
        <p>
            Action received:
            <strong>" . htmlspecialchars(
                $action,
                ENT_QUOTES,
                "UTF-8"
            ) . "</strong>
        </p>
        <a href='view_booking.php?id=" . $booking_id . "'>
            ← Back to Booking Details
        </a>
    ");
}

$stmt = $conn->prepare("
    SELECT
        id,
        customer_id,
        provider_id,
        status
    FROM bookings
    WHERE id = ?
    AND provider_id = ?
    LIMIT 1
");

if (!$stmt) {
    die(
        "SQL Error: " .
        htmlspecialchars(
            $conn->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$stmt->bind_param(
    "ii",
    $booking_id,
    $provider_id
);

if (!$stmt->execute()) {
    die(
        "Execute Error: " .
        htmlspecialchars(
            $stmt->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    die("
        <h2>❌ Booking Not Found</h2>

        <p>
            This booking does not belong to your provider account.
        </p>

        <p>
            Booking ID:
            <strong>" . $booking_id . "</strong>
        </p>

        <a href='bookings.php'>
            ← Back to Bookings
        </a>
    ");
}

$booking = $result->fetch_assoc();

$stmt->close();

$customer_id = (int) $booking["customer_id"];

$current_status = strtolower(
    trim(
        $booking["status"] ?? ""
    )
);

if ($current_status !== "pending") {
    die("
        <h2>⚠️ Booking Already Updated</h2>

        <p>
            This booking is already
            <strong>" .
            htmlspecialchars(
                ucfirst($current_status),
                ENT_QUOTES,
                "UTF-8"
            ) .
            "</strong>.
        </p>

        <p>
            Booking ID:
            <strong>" . $booking_id . "</strong>
        </p>

        <a href='view_booking.php?id=" .
            $booking_id .
            "'>
            ← Back to Booking Details
        </a>
    ");
}

if ($action === "accept") {
    $new_status = "confirmed";
    $title = "Booking Accepted";
    $message =
        "Your booking #" .
        $booking_id .
        " has been accepted by the provider.";
} else {
    $new_status = "rejected";
    $title = "Booking Rejected";
    $message =
        "Your booking #" .
        $booking_id .
        " has been rejected by the provider.";
}

$update = $conn->prepare("
    UPDATE bookings
    SET status = ?
    WHERE id = ?
    AND provider_id = ?
    AND status = 'pending'
");

if (!$update) {
    die(
        "SQL Error: " .
        htmlspecialchars(
            $conn->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$update->bind_param(
    "sii",
    $new_status,
    $booking_id,
    $provider_id
);

if (!$update->execute()) {
    die(
        "Update Error: " .
        htmlspecialchars(
            $update->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

if ($update->affected_rows > 0) {

    $update->close();

    $type = "booking";
    $is_read = 0;

    $notification = $conn->prepare("
        INSERT INTO notifications
        (
            user_id,
            user_role,
            title,
            message,
            type,
            is_read
        )
        VALUES
        (
            ?,
            'customer',
            ?,
            ?,
            ?,
            ?
        )
    ");

    if ($notification) {

        $notification->bind_param(
            "isssi",
            $customer_id,
            $title,
            $message,
            $type,
            $is_read
        );

        $notification->execute();

        $notification->close();
    }

    header(
        "Location: view_booking.php?id=" .
        $booking_id
    );

    exit;
}

$update->close();

die("
    <h2>❌ Update Failed</h2>

    <p>
        Booking status could not be updated.
    </p>

    <a href='view_booking.php?id=" .
        $booking_id .
        "'>
        ← Back to Booking Details
    </a>
");

?>