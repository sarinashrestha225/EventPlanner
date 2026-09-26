<?php

require_once "../../database.php";

require_once "../includes/auth.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php");
    exit();

}

$booking_id = (int) $_GET["id"];

$check_sql = "
    SELECT id
    FROM bookings
    WHERE id = ?
    LIMIT 1
";

$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$check_stmt->bind_param(
    "i",
    $booking_id
);

$check_stmt->execute();

$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {

    $check_stmt->close();

    header("Location: index.php");
    exit();

}

$check_stmt->close();

$delete_sql = "
    DELETE FROM bookings
    WHERE id = ?
";

$delete_stmt = $conn->prepare($delete_sql);

if (!$delete_stmt) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$delete_stmt->bind_param(
    "i",
    $booking_id
);

if ($delete_stmt->execute()) {

    $delete_stmt->close();

    header("Location: index.php?deleted=1");
    exit();

} else {

    $error = $delete_stmt->error;

    $delete_stmt->close();

    die(
        "Unable to delete booking: " .
        htmlspecialchars($error)
    );

}

?>