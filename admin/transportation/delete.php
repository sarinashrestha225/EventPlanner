<?php
session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT image
    FROM transportation
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row) {

    $delete = $conn->prepare("
        DELETE FROM transportation
        WHERE id = ?
    ");

    $delete->bind_param("i", $id);
    $delete->execute();

    if ($delete->affected_rows > 0) {

        if (!empty($row["image"])) {

            $image_path = "../../uploads/transportation/" . $row["image"];

            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }
}

header("Location: index.php");
exit();