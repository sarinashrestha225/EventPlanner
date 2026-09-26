<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$offer_id = (int) ($_GET["id"] ?? 0);

if ($offer_id <= 0) {
    header("Location: offers.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id
    FROM offers
    WHERE id = ? AND provider_id = ?
");

$stmt->bind_param("ii", $offer_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$offer = $result->fetch_assoc();

$stmt->close();

if (!$offer) {
    header("Location: offers.php");
    exit;
}

$stmt = $conn->prepare("
    DELETE FROM offers
    WHERE id = ? AND provider_id = ?
");

$stmt->bind_param("ii", $offer_id, $provider_id);
$stmt->execute();

$stmt->close();

header("Location: offers.php");
exit;
?>