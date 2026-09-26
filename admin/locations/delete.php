<?php

session_start();

require_once "../../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

if (
    !isset($_GET["id"]) ||
    !ctype_digit((string)$_GET["id"])
) {
    header("Location: index.php?error=invalid_id");
    exit();
}

$id = (int)$_GET["id"];

$stmt = $conn->prepare(
    "DELETE FROM locations WHERE id = ?"
);

if (!$stmt) {

    header("Location: index.php?error=delete_failed");
    exit();
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    $stmt->close();

    header("Location: index.php?success=deleted");
    exit();
}

$stmt->close();

header("Location: index.php?error=delete_failed");
exit();

?>