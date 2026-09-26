<?php

function createNotification(
    $conn,
    $user_id,
    $user_role,
    $title,
    $message,
    $type = 'system'
) {

    $sql = "
        INSERT INTO notifications
        (
            user_id,
            user_role,
            title,
            message,
            type,
            is_read
        )
        VALUES (?, ?, ?, ?, ?, 0)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "issss",
        $user_id,
        $user_role,
        $title,
        $message,
        $type
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}

?>