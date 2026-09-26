<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["provider_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["provider_id"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: services.php");
    exit();
}

$service_id = (int) $_GET["id"];

if ($service_id <= 0) {
    header("Location: services.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT
        id,
        service_name,
        availability
    FROM services
    WHERE id = ?
    AND provider_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $service_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {

    $stmt->close();
    $conn->close();

    header("Location: services.php");
    exit();
}

$service = $result->fetch_assoc();

$stmt->close();

$current_availability = $service["availability"];

if ($current_availability === "available") {

    $new_availability = "unavailable";

} elseif ($current_availability === "unavailable") {

    $new_availability = "available";

} else {

    $new_availability = "available";
}

$update = $conn->prepare("
    UPDATE services
    SET availability = ?
    WHERE id = ?
    AND provider_id = ?
");

if (!$update) {
    $conn->close();
    die("Database error: " . $conn->error);
}

$update->bind_param(
    "sii",
    $new_availability,
    $service_id,
    $provider_id
);

if ($update->execute()) {

    if ($update->affected_rows === 1) {

        $update->close();
        $conn->close();

        header("Location: services.php?success=availability_updated");
        exit();
    }

    $update->close();
    $conn->close();

    header("Location: services.php?error=update_failed");
    exit();
}

$error = $update->error;

$update->close();
$conn->close();

die("Unable to update service availability: " . htmlspecialchars(
    $error,
    ENT_QUOTES,
    "UTF-8"
));
?>