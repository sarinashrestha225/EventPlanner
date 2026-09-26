<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$package_id = (int) ($_GET["id"] ?? 0);

if ($package_id <= 0) {
    header("Location: packages.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, status
    FROM packages
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Package query error: " . $conn->error);
}

$stmt->bind_param("ii", $package_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    header("Location: packages.php");
    exit;
}

$new_status = $package["status"] === "active"
    ? "inactive"
    : "active";

$stmt = $conn->prepare("
    UPDATE packages
    SET status = ?
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Update query error: " . $conn->error);
}

$stmt->bind_param(
    "sii",
    $new_status,
    $package_id,
    $provider_id
);

$stmt->execute();

$stmt->close();

header("Location: packages.php");
exit;

?>