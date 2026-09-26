
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

if (!isset($_SESSION["notification_csrf_token"])) {
    $_SESSION["notification_csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION["notification_csrf_token"];

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_token =
        $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $csrf_token,
            $posted_token
        )
    ) {

        $error =
            "Invalid request. Please try again.";

    } else {

        $action =
            $_POST["action"] ?? "";

        if ($action === "read") {

            $notification_id =
                (int) (
                    $_POST["notification_id"]
                    ?? 0
                );

            if ($notification_id > 0) {

                $stmt =
                    $conn->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE id = ?
                        AND user_id = ?
                        AND user_role = 'provider'
                    ");

                if ($stmt) {

                    $stmt->bind_param(
                        "ii",
                        $notification_id,
                        $provider_id
                    );

                    $stmt->execute();

                    $stmt->close();
                }
            }

        } elseif ($action === "read_all") {

            $stmt =
                $conn->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE user_id = ?
                    AND user_role = 'provider'
                    AND is_read = 0
                ");

            if ($stmt) {

                $stmt->bind_param(
                    "i",
                    $provider_id
                );

                $stmt->execute();

                $stmt->close();
            }
        }
    }
}

$stmt =
    $conn->prepare("
        SELECT
            id,
            title,
            message,
            type,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = ?
        AND user_role = 'provider'
        ORDER BY id DESC
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
    "i",
    $provider_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$notifications = [];

while (
    $row =
    $result->fetch_assoc()
) {

    $notifications[] =
        $row;
}

$stmt->close();

$stmt =
    $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE user_id = ?
        AND user_role = 'provider'
        AND is_read = 0
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
    "i",
    $provider_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$row =
    $result->fetch_assoc();

$unread_count =
    (int) (
        $row["total"]
        ?? 0
    );

$stmt->close();

$provider_name =
    $_SESSION["provider_name"]
    ?? "Provider";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Provider Notifications</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #fff7f0;
    color: #4b3621;
    min-height: 100vh;
}

.header {
    background: linear-gradient(
        135deg,
        #f7c6d9,
        #fff0c7
    );

    padding: 22px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 3px 15px rgba(
            120,
            80,
            90,
            0.12
        );
}

.header-left h1 {
    color: #8a5a00;
    font-size: 27px;
    margin-bottom: 5px;
}

.header-left p {
    color: #765d64;
    font-size: 14px;
}

.dashboard-btn {
    text-decoration: none;
    background: #b8860b;
    color: white;
    padding: 11px 18px;
    border-radius: 10px;
    font-weight: bold;
    transition: 0.3s;
}

.dashboard-btn:hover {
    background: #946f08;
    transform: translateY(-2px);
}

.container {
    width: 92%;
    max-width: 1000px;
    margin: 35px auto;
}

.notification-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.notification-top h2 {
    font-size: 24px;
    color: #5d4148;
}

.unread-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 5px 11px;
    border-radius: 20px;
    background: #dc3545;
    color: white;
    font-size: 13px;
    vertical-align: middle;
}

.read-all {
    border: 1px solid #d4af37;
    background: #fff;
    color: #a07800;
    padding: 9px 15px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.read-all:hover {
    background: #fff8df;
}

.notification-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 15px;

    display: flex;

    align-items: flex-start;

    gap: 18px;

    box-shadow:
        0 5px 18px rgba(
            100,
            70,
            80,
            0.08
        );

    border: 1px solid #f2e4e8;

    transition: 0.3s;
}

.notification-card:hover {
    transform: translateY(-2px);

    box-shadow:
        0 8px 22px rgba(
            100,
            70,
            80,
            0.12
        );
}

.notification-card.unread {
    border-left: 5px solid #d4af37;
    background: #fffdf6;
}

.notification-icon {
    width: 55px;
    height: 55px;
    min-width: 55px;

    border-radius: 50%;

    background: linear-gradient(
        135deg,
        #f8d3e1,
        #ffe9a8
    );

    display: flex;

    justify-content: center;

    align-items: center;

    font-size: 27px;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 18px;
    font-weight: bold;
    color: #5d4148;
    margin-bottom: 8px;
}

.notification-message {
    color: #6f6265;
    line-height: 1.6;
    font-size: 15px;
}

.notification-date {
    margin-top: 9px;
    color: #999;
    font-size: 13px;
}

.notification-actions {
    display: flex;
    align-items: center;
}

.mark-read {
    border: none;
    background: #d4af37;
    color: white;
    padding: 9px 13px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    white-space: nowrap;
}

.mark-read:hover {
    background: #b8941f;
}

.read-label {
    color: #5a9b72;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.empty-card {
    background: white;
    border-radius: 18px;
    padding: 60px 25px;
    text-align: center;

    box-shadow:
        0 5px 18px rgba(
            100,
            70,
            80,
            0.08
        );
}

.empty-icon {
    font-size: 60px;
    margin-bottom: 15px;
}

.empty-card h3 {
    color: #5d4148;
    font-size: 22px;
    margin-bottom: 8px;
}

.empty-card p {
    color: #888;
}

.summary-card {
    background: linear-gradient(
        135deg,
        #fff,
        #fffaf0
    );

    border: 1px solid #f0dfae;

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 22px;

    display: flex;

    align-items: center;

    gap: 15px;
}

.summary-icon {
    font-size: 35px;
}

.summary-text strong {
    display: block;
    color: #8a6500;
    font-size: 22px;
    margin-bottom: 4px;
}

.summary-text span {
    color: #777;
    font-size: 14px;
}

.error {
    background: #ffe2e2;
    color: #a33a3a;
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

@media (max-width: 700px) {

    .header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
    }

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .notification-top {
        flex-direction: column;
        align-items: flex-start;
    }

    .notification-card {
        flex-direction: column;
    }

    .notification-actions {
        width: 100%;
    }

    .mark-read {
        display: inline-block;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="header-left">

        <h1>
            🔔 Notifications
        </h1>

        <p>
            Welcome,
            <?= htmlspecialchars(
                $provider_name,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>

    </div>

    <a
        href="dashboard.php"
        class="dashboard-btn"
    >
        ← Dashboard
    </a>

</header>

<div class="container">

    <div class="notification-top">

        <h2>

            All Notifications

            <?php if ($unread_count > 0): ?>

                <span class="unread-badge">
                    <?= $unread_count ?> New
                </span>

            <?php endif; ?>

        </h2>

        <?php if ($unread_count > 0): ?>

            <form
                method="POST"
                style="margin:0;"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="read_all"
                >

                <button
                    type="submit"
                    class="read-all"
                >
                    ✓ Mark All as Read
                </button>

            </form>

        <?php endif; ?>

    </div>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </div>

    <?php endif; ?>

    <?php if ($unread_count > 0): ?>

        <div class="summary-card">

            <div class="summary-icon">
                🔔
            </div>

            <div class="summary-text">

                <strong>
                    <?= $unread_count ?>
                </strong>

                <span>
                    Unread notification<?= $unread_count > 1 ? "s" : "" ?>
                </span>

            </div>

        </div>

    <?php endif; ?>

    <?php if (empty($notifications)): ?>

        <div class="empty-card">

            <div class="empty-icon">
                🔔
            </div>

            <h3>
                No Notifications
            </h3>

            <p>
                You don't have any notifications yet.
            </p>

        </div>

    <?php else: ?>

        <?php foreach ($notifications as $notification): ?>

            <?php

            $icon = "🔔";

            switch (
                $notification["type"]
            ) {

                case "booking":
                    $icon = "📅";
                    break;

                case "payment":
                    $icon = "💰";
                    break;

                case "review":
                    $icon = "⭐";
                    break;

                case "offer":
                    $icon = "🎁";
                    break;

                case "message":
                    $icon = "💬";
                    break;

                case "verification":
                    $icon = "📄";
                    break;

                default:
                    $icon = "🔔";
                    break;
            }

            ?>

            <div
                class="notification-card
                <?= (
                    (int) $notification["is_read"] === 0
                )
                    ? "unread"
                    : "" ?>"
            >

                <div class="notification-icon">
                    <?= $icon ?>
                </div>

                <div class="notification-content">

                    <div class="notification-title">

                        <?= htmlspecialchars(
                            $notification["title"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>

                    <div class="notification-message">

                        <?= htmlspecialchars(
                            $notification["message"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>

                    <div class="notification-date">

                        🕒

                        <?= htmlspecialchars(
                            $notification["created_at"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>

                </div>

                <div class="notification-actions">

                    <?php if (
                        (int) $notification["is_read"] === 0
                    ): ?>

                        <form
                            method="POST"
                            style="margin:0;"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $csrf_token,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="read"
                            >

                            <input
                                type="hidden"
                                name="notification_id"
                                value="<?= (int) $notification["id"] ?>"
                            >

                            <button
                                type="submit"
                                class="mark-read"
                            >
                                ✓ Mark Read
                            </button>

                        </form>

                    <?php else: ?>

                        <span class="read-label">
                            ✓ Read
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>

</html>

