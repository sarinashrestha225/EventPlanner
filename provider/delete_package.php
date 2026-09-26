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
    SELECT id
    FROM packages
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Package query error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $package_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    header("Location: packages.php");
    exit;
}

$stmt = $conn->prepare("
    DELETE FROM package_services
    WHERE package_id = ?
");

if (!$stmt) {
    die("Package services query error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $package_id
);

$stmt->execute();

$stmt->close();

$stmt = $conn->prepare("
    DELETE FROM package_pricing
    WHERE package_id = ?
");

if (!$stmt) {
    die("Package pricing query error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $package_id
);

$stmt->execute();

$stmt->close();

$stmt = $conn->prepare("
    DELETE FROM packages
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Delete package query error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $package_id,
    $provider_id
);

$stmt->execute();

$stmt->close();

header("Location: packages.php");
exit;

?>