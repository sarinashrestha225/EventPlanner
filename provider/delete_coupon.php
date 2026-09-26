<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$coupon_id = (int) ($_GET["id"] ?? 0);

if ($coupon_id <= 0) {
    header("Location: coupons.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id
    FROM coupons
    WHERE id = ? AND provider_id = ?
");

$stmt->bind_param(
    "ii",
    $coupon_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();
$coupon = $result->fetch_assoc();

$stmt->close();

if (!$coupon) {
    header("Location: coupons.php");
    exit;
}

$stmt = $conn->prepare("
    DELETE FROM coupons
    WHERE id = ? AND provider_id = ?
");

$stmt->bind_param(
    "ii",
    $coupon_id,
    $provider_id
);

$stmt->execute();

$stmt->close();

header("Location: coupons.php");
exit;
?>