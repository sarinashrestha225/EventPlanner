<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($id <= 0) {

    header(
        "Location: index.php?error=invalid_id"
    );

    exit();

}

$check_sql = "
    SELECT id
    FROM notifications
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
    $id
);

$check_stmt->execute();

$result = $check_stmt->get_result();

if ($result->num_rows !== 1) {

    $check_stmt->close();

    header(
        "Location: index.php?error=not_found"
    );

    exit();

}

$check_stmt->close();

$delete_sql = "
    DELETE FROM notifications
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
    $id
);

if ($delete_stmt->execute()) {

    $delete_stmt->close();

    header(
        "Location: index.php?deleted=1"
    );

    exit();

}

$error = $delete_stmt->error;

$delete_stmt->close();

die(
    "Unable to delete notification: " .
    htmlspecialchars($error)
);

?>