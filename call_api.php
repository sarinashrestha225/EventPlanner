<?php

session_start();

require_once __DIR__ . "/database.php";

header("Content-Type: application/json; charset=UTF-8");

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["active_role"])
) {
    echo json_encode([
        "success" => false,
        "message" => "Please login first."
    ]);
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$user_role = $_SESSION["active_role"];

if (!in_array($user_role, ["customer", "provider"], true)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid user role."
    ]);
    exit;
}

$action = $_POST["action"] ?? $_GET["action"] ?? "";

function jsonResponse($data)
{
    echo json_encode($data);
    exit;
}

function getBooking($conn, $booking_id, $user_id, $user_role)
{
    if ($user_role === "customer") {

        $stmt = $conn->prepare("
            SELECT
                b.id,
                b.customer_id,
                b.provider_id
            FROM bookings b
            WHERE b.id = ?
              AND b.customer_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $booking_id, $user_id);

    } else {

        $stmt = $conn->prepare("
            SELECT
                b.id,
                b.customer_id,
                b.provider_id
            FROM bookings b
            WHERE b.id = ?
              AND b.provider_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $booking_id, $user_id);
    }

    $stmt->execute();

    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    $stmt->close();

    return $booking ?: null;
}

function getReceiver($booking, $user_role)
{
    if ($user_role === "customer") {
        return [
            "id" => (int) $booking["provider_id"],
            "role" => "provider"
        ];
    }

    return [
        "id" => (int) $booking["customer_id"],
        "role" => "customer"
    ];
}

if ($action === "send_signal") {

    $booking_id = isset($_POST["booking_id"])
        ? (int) $_POST["booking_id"]
        : 0;

    $signal_type = trim($_POST["signal_type"] ?? "");
    $signal_data = $_POST["signal_data"] ?? "";

    if ($booking_id <= 0) {
        jsonResponse([
            "success" => false,
            "message" => "Invalid booking."
        ]);
    }

    $allowed_types = [
        "offer",
        "answer",
        "ice",
        "call",
        "accept",
        "reject",
        "end"
    ];

    if (!in_array($signal_type, $allowed_types, true)) {
        jsonResponse([
            "success" => false,
            "message" => "Invalid signal type."
        ]);
    }

    $booking = getBooking(
        $conn,
        $booking_id,
        $user_id,
        $user_role
    );

    if (!$booking) {
        jsonResponse([
            "success" => false,
            "message" => "Booking not found or access denied."
        ]);
    }

    $receiver = getReceiver(
        $booking,
        $user_role
    );

    $stmt = $conn->prepare("
        INSERT INTO call_signaling
        (
            booking_id,
            sender_id,
            sender_role,
            receiver_id,
            receiver_role,
            signal_type,
            signal_data,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "iisisss",
        $booking_id,
        $user_id,
        $user_role,
        $receiver["id"],
        $receiver["role"],
        $signal_type,
        $signal_data
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        jsonResponse([
            "success" => false,
            "message" => "Could not save call signal.",
            "error" => $error
        ]);
    }

    $signal_id = $stmt->insert_id;

    $stmt->close();

    jsonResponse([
        "success" => true,
        "signal_id" => (int) $signal_id
    ]);
}


if ($action === "get_signals") {

    $booking_id = isset($_POST["booking_id"])
        ? (int) $_POST["booking_id"]
        : (int) ($_GET["booking_id"] ?? 0);

    $after_id = isset($_POST["after_id"])
        ? (int) $_POST["after_id"]
        : (int) ($_GET["after_id"] ?? 0);

    if ($booking_id <= 0) {
        jsonResponse([
            "success" => false,
            "message" => "Invalid booking."
        ]);
    }

    $booking = getBooking(
        $conn,
        $booking_id,
        $user_id,
        $user_role
    );

    if (!$booking) {
        jsonResponse([
            "success" => false,
            "message" => "Booking not found or access denied."
        ]);
    }

    $stmt = $conn->prepare("
        SELECT
            id,
            sender_id,
            sender_role,
            receiver_id,
            receiver_role,
            signal_type,
            signal_data,
            created_at
        FROM call_signaling
        WHERE booking_id = ?
          AND receiver_id = ?
          AND receiver_role = ?
          AND id > ?
        ORDER BY id ASC
        LIMIT 100
    ");

    $stmt->bind_param(
        "iisi",
        $booking_id,
        $user_id,
        $user_role,
        $after_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $signals = [];

    while ($row = $result->fetch_assoc()) {

        $signals[] = [
            "id" => (int) $row["id"],
            "sender_id" => (int) $row["sender_id"],
            "sender_role" => $row["sender_role"],
            "receiver_id" => (int) $row["receiver_id"],
            "receiver_role" => $row["receiver_role"],
            "signal_type" => $row["signal_type"],
            "signal_data" => $row["signal_data"],
            "created_at" => $row["created_at"]
        ];
    }

    $stmt->close();

    jsonResponse([
        "success" => true,
        "signals" => $signals
    ]);
}


if ($action === "clear_old_signals") {

    $booking_id = isset($_POST["booking_id"])
        ? (int) $_POST["booking_id"]
        : 0;

    if ($booking_id <= 0) {
        jsonResponse([
            "success" => false,
            "message" => "Invalid booking."
        ]);
    }

    $booking = getBooking(
        $conn,
        $booking_id,
        $user_id,
        $user_role
    );

    if (!$booking) {
        jsonResponse([
            "success" => false,
            "message" => "Booking not found or access denied."
        ]);
    }

    $stmt = $conn->prepare("
        DELETE FROM call_signaling
        WHERE booking_id = ?
          AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");

    $stmt->bind_param(
        "i",
        $booking_id
    );

    $stmt->execute();

    $deleted = $stmt->affected_rows;

    $stmt->close();

    jsonResponse([
        "success" => true,
        "deleted" => $deleted
    ]);
}


jsonResponse([
    "success" => false,
    "message" => "Invalid action."
]);