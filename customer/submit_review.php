<?php

session_start();
require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int)$_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_bookings.php");
    exit;
}

$booking_id = (int)($_POST["booking_id"] ?? 0);
$rating = (int)($_POST["rating"] ?? 0);
$comment = trim($_POST["comment"] ?? "");

if ($booking_id <= 0) {
    die("Invalid booking ID.");
}

if ($rating < 1 || $rating > 5) {
    die("Please select a rating between 1 and 5.");
}

if ($comment === "") {
    die("Please write your review.");
}

if (strlen($comment) > 1000) {
    die("Review cannot exceed 1000 characters.");
}

$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.provider_id,
        b.service_id,
        b.status
    FROM bookings b
    WHERE b.id = ?
      AND b.customer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();

if (!$booking) {
    die("Booking not found or this booking does not belong to you.");
}

if (strtolower(trim($booking["status"])) !== "completed") {
    die("You can review only completed bookings.");
}

$provider_id = (int)($booking["provider_id"] ?? 0);
$service_id = (int)($booking["service_id"] ?? 0);

if ($provider_id <= 0) {
    die("This booking has no assigned provider.");
}

$provider_check = $conn->prepare("
    SELECT id
    FROM providers
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
");

if (!$provider_check) {
    die("Database error: " . $conn->error);
}

$provider_check->bind_param("i", $provider_id);
$provider_check->execute();

$provider_result = $provider_check->get_result();

if ($provider_result->num_rows === 0) {
    $provider_check->close();
    die("Provider not found.");
}

$provider_check->close();

$duplicate_check = $conn->prepare("
    SELECT id
    FROM reviews
    WHERE booking_id = ?
      AND customer_id = ?
    LIMIT 1
");

if (!$duplicate_check) {
    die("Database error: " . $conn->error);
}

$duplicate_check->bind_param("ii", $booking_id, $customer_id);
$duplicate_check->execute();

$duplicate_result = $duplicate_check->get_result();

if ($duplicate_result->num_rows > 0) {
    $duplicate_check->close();
    header("Location: my_bookings.php?review=already");
    exit;
}

$duplicate_check->close();

$insert = $conn->prepare("
    INSERT INTO reviews
    (
        customer_id,
        provider_id,
        service_id,
        booking_id,
        rating,
        comment,
        status,
        created_at,
        updated_at
    )
    VALUES
    (?, ?, ?, ?, ?, ?, 'published', NOW(), NOW())
");

if (!$insert) {
    die("Database error: " . $conn->error);
}

$insert->bind_param(
    "iiiiss",
    $customer_id,
    $provider_id,
    $service_id,
    $booking_id,
    $rating,
    $comment
);

if (!$insert->execute()) {
    die("Unable to submit review: " . $insert->error);
}

$insert->close();

$provider_title = "New Review Received";
$provider_message =
    "You received a " .
    $rating .
    "-star review for booking #" .
    $booking_id .
    ".";

$notification = $conn->prepare("
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
    (?, 'provider', ?, ?, 'booking', 0, NOW())
");

if ($notification) {
    $notification->bind_param(
        "iss",
        $provider_id,
        $provider_title,
        $provider_message
    );

    $notification->execute();
    $notification->close();
}

header("Location: my_bookings.php?review=success");
exit;