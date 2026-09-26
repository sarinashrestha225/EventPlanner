<?php

session_start();

require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["user_id"];

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
        service_image
    FROM services
    WHERE id = ?
    AND provider_id = ?
    LIMIT 1
");

if (!$stmt) {
    $conn->close();
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $service_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();
$service = $result->fetch_assoc();

$stmt->close();

if (!$service) {

    $conn->close();

    header("Location: services.php?error=not_found");
    exit();
}

$delete = $conn->prepare("
    DELETE FROM services
    WHERE id = ?
    AND provider_id = ?
");

if (!$delete) {

    $conn->close();

    die("Database error: " . $conn->error);
}

$delete->bind_param(
    "ii",
    $service_id,
    $provider_id
);

if ($delete->execute()) {

    if (
        $delete->affected_rows === 1 &&
        !empty($service["service_image"])
    ) {

        $image_name = basename($service["service_image"]);

        $image_path =
            __DIR__ .
            "/uploads/services/" .
            $image_name;

        if (
            file_exists($image_path) &&
            is_file($image_path)
        ) {
            unlink($image_path);
        }
    }

    $delete->close();
    $conn->close();

    header("Location: services.php?success=deleted");
    exit();
}

$delete_error = $delete->error;

$delete->close();
$conn->close();

header("Location: services.php?error=delete_failed");
exit();
?>