
<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["provider_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit();
}

$provider_id =
    (int) $_SESSION["provider_id"];

if ($provider_id <= 0) {
    header("Location: login.php");
    exit();
}

$portfolio_id =
    isset($_GET["id"])
        ? (int) $_GET["id"]
        : 0;

if ($portfolio_id <= 0) {
    $conn->close();

    header("Location: portfolio.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT
        id,
        provider_id,
        image,
        title
    FROM portfolio
    WHERE id = ?
    AND provider_id = ?
    LIMIT 1
");

if (!$stmt) {

    $error_message =
        $conn->error;

    $conn->close();

    die(
        "SQL Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$stmt->bind_param(
    "ii",
    $portfolio_id,
    $provider_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$portfolio =
    $result
        ? $result->fetch_assoc()
        : null;

$stmt->close();

if (!$portfolio) {

    $conn->close();

    header("Location: portfolio.php");
    exit();
}

$delete = $conn->prepare("
    DELETE FROM portfolio
    WHERE id = ?
    AND provider_id = ?
");

if (!$delete) {

    $error_message =
        $conn->error;

    $conn->close();

    die(
        "SQL Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$delete->bind_param(
    "ii",
    $portfolio_id,
    $provider_id
);

if ($delete->execute()) {

    $delete->close();

    if (!empty($portfolio["image"])) {

        $file_name =
            basename(
                $portfolio["image"]
            );

        $file_path =
            __DIR__ .
            "/uploads/portfolio/" .
            $file_name;

        if (
            file_exists($file_path) &&
            is_file($file_path)
        ) {

            unlink($file_path);
        }
    }

    $notification_title =
        "Portfolio Deleted";

    $notification_message =
        "Your portfolio item \"" .
        ($portfolio["title"] ?? "Portfolio item") .
        "\" has been deleted.";

    $notification_type =
        "system";

    $notification =
        $conn->prepare("
            INSERT INTO notifications
            (
                user_id,
                user_role,
                title,
                message,
                type,
                is_read
            )
            VALUES
            (
                ?,
                'provider',
                ?,
                ?,
                ?,
                0
            )
        ");

    if ($notification) {

        $notification->bind_param(
            "isss",
            $provider_id,
            $notification_title,
            $notification_message,
            $notification_type
        );

        $notification->execute();

        $notification->close();
    }

    $conn->close();

    header(
        "Location: portfolio.php?success=deleted"
    );

    exit();

}

$delete->close();

$conn->close();

header(
    "Location: portfolio.php?error=delete_failed"
);

exit();

