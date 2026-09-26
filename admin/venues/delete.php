<?php

session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT image
     FROM venues
     WHERE id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

$venue = $result->fetch_assoc();

$stmt->close();

if (!$venue) {
    header("Location: index.php?error=notfound");
    exit();
}

$stmt = $conn->prepare(
    "DELETE FROM venues
     WHERE id = ?"
);

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    if (!empty($venue["image"])) {

        $image_path =
            "../../uploads/venues/"
            . $venue["image"];

        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    header(
        "Location: index.php?deleted=1"
    );

    exit();

} else {

    header(
        "Location: index.php?error=delete"
    );

    exit();
}

$stmt->close();

?>