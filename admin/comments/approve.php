<?php

require_once "../includes/auth.php";
require_once "../../database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("
    UPDATE comments
    SET status = 'Approved'
    WHERE id = ?
");

$stmt->bind_param("i", $id);

$stmt->execute();

$stmt->close();

header("Location: index.php");
exit;

?>