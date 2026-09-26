
<?php

session_start();

require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["admin_id"]) &&
    !isset($_SESSION["admin_logged_in"])
) {
    header("Location: login.php");
    exit;
}

$selected_booking_id = isset($_GET["booking_id"]) ? (int)$_GET["booking_id"] : 0;
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";

$conversations = [];

$sql = "
    SELECT
        m.booking_id,
        MAX(m.id) AS last_message_id,
        MAX(m.created_at) AS last_message_time,
        COUNT(CASE WHEN m.is_read = 0 AND m.receiver_role = 'provider' THEN 1 END) AS unread_provider,
        COUNT(CASE WHEN m.is_read = 0 AND m.receiver_role = 'customer' THEN 1 END) AS unread_customer
    FROM messages m
    GROUP BY m.booking_id
    ORDER BY last_message_id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {

        $booking_id = (int)$row["booking_id"];

        $booking_sql = "
            SELECT
                b.id,
                b.customer_id,
                b.provider_id,
                u.name AS customer_name,
                u.email AS customer_email,
                p.name AS provider_name,
                p.email AS provider_email
            FROM bookings b
            LEFT JOIN users u ON u.id = b.customer_id
            LEFT JOIN providers p ON p.id = b.provider_id
            WHERE b.id = ?
            LIMIT 1
        ";

        $booking_stmt = $conn->prepare($booking_sql);

        if (!$booking_stmt) {
            continue;
        }

        $booking_stmt->bind_param("i", $booking_id);
        $booking_stmt->execute();

        $booking_result = $booking_stmt->get_result();
        $booking = $booking_result->fetch_assoc();

        $booking_stmt->close();

        if (!$booking) {
            continue;
        }

        $last_sql = "
            SELECT message, sender_role
            FROM messages
            WHERE booking_id = ?
            ORDER BY id DESC
            LIMIT 1
        ";

        $last_stmt = $conn->prepare($last_sql);

        $last_message = "";

        if ($last_stmt) {
            $last_stmt->bind_param("i", $booking_id);
            $last_stmt->execute();

            $last_result = $last_stmt->get_result();
            $last_row = $last_result->fetch_assoc();

            if ($last_row) {
                $last_message = $last_row["message"];
            }

            $last_stmt->close();
        }

        $conversation_name =
            ($booking["customer_name"] ?: "Customer") .
            " ↔ " .
            ($booking["provider_name"] ?: "Provider");

        if (
            $search !== "" &&
            stripos($conversation_name, $search) === false &&
            stripos((string)$booking_id, $search) === false &&
            stripos($last_message, $search) === false
        ) {
            continue;
        }

        $row["booking"] = $booking;
        $row["last_message"] = $last_message;
        $row["conversation_name"] = $conversation_name;

        $conversations[] = $row;
    }
}

$messages = [];
$selected_booking = null;

if ($selected_booking_id > 0) {

    $booking_sql = "
        SELECT
            b.id,
            b.customer_id,
            b.provider_id,
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,
            p.name AS provider_name,
            p.email AS provider_email,
            p.phone AS provider_phone
        FROM bookings b
        LEFT JOIN users u ON u.id = b.customer_id
        LEFT JOIN providers p ON p.id = b.provider_id
        WHERE b.id = ?
        LIMIT 1
    ";

    $booking_stmt = $conn->prepare($booking_sql);

    if ($booking_stmt) {
        $booking_stmt->bind_param("i", $selected_booking_id);
        $booking_stmt->execute();

        $booking_result = $booking_stmt->get_result();
        $selected_booking = $booking_result->fetch_assoc();

        $booking_stmt->close();
    }

    if ($selected_booking) {

        $message_sql = "
            SELECT
                id,
                sender_id,
                sender_role,
                receiver_id,
                receiver_role,
                message,
                is_read,
                created_at
            FROM messages
            WHERE booking_id = ?
            ORDER BY id ASC
        ";

        $message_stmt = $conn->prepare($message_sql);

        if ($message_stmt) {
            $message_stmt->bind_param("i", $selected_booking_id);
            $message_stmt->execute();

            $message_result = $message_stmt->get_result();

            while ($message = $message_result->fetch_assoc()) {
                $messages[] = $message;
            }

            $message_stmt->close();
        }
    }
}

$total_conversations = count($conversations);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Messages</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f8;
    color: #333;
}

.page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.header {
    background: linear-gradient(135deg, #f7d6df, #fff1c9);
    padding: 18px 25px;
    border-bottom: 1px solid #ead0d7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    margin: 0;
    color: #7b4b58;
    font-size: 25px;
}

.header small {
    color: #765d64;
}

.back {
    text-decoration: none;
    background: #c99a3d;
    color: white;
    padding: 9px 15px;
    border-radius: 8px;
    font-size: 14px;
}

.content {
    flex: 1;
    display: flex;
    min-height: calc(100vh - 75px);
}

.sidebar {
    width: 350px;
    background: white;
    border-right: 1px solid #eadfe2;
    overflow-y: auto;
}

.sidebar-top {
    padding: 18px;
    border-bottom: 1px solid #eee;
}

.sidebar-top h2 {
    margin: 0 0 12px;
    color: #6d4652;
    font-size: 20px;
}

.search {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #ddd;
    border-radius: 9px;
    outline: none;
}

.search:focus {
    border-color: #c99a3d;
}

.count {
    margin-top: 10px;
    font-size: 13px;
    color: #777;
}

.conversation {
    display: block;
    text-decoration: none;
    color: inherit;
    padding: 15px 18px;
    border-bottom: 1px solid #f0e9eb;
    transition: 0.2s;
}

.conversation:hover {
    background: #fff5f7;
}

.conversation.active {
    background: #fff0f3;
    border-left: 4px solid #c99a3d;
}

.conversation-title {
    font-weight: bold;
    color: #684650;
    margin-bottom: 6px;
}

.booking {
    font-size: 12px;
    color: #a07882;
    margin-bottom: 6px;
}

.last-message {
    font-size: 13px;
    color: #777;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.time {
    margin-top: 6px;
    font-size: 11px;
    color: #999;
}

.chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fffafa;
}

.chat-header {
    background: white;
    border-bottom: 1px solid #eadfe2;
    padding: 18px 22px;
}

.chat-header h2 {
    margin: 0 0 7px;
    color: #684650;
    font-size: 20px;
}

.chat-header p {
    margin: 3px 0;
    color: #777;
    font-size: 13px;
}

.messages {
    flex: 1;
    padding: 25px;
    overflow-y: auto;
}

.message-row {
    display: flex;
    margin-bottom: 15px;
}

.message-row.customer {
    justify-content: flex-start;
}

.message-row.provider {
    justify-content: flex-end;
}

.message-box {
    max-width: 65%;
    padding: 11px 15px;
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}

.customer .message-box {
    background: white;
    border: 1px solid #eee;
}

.provider .message-box {
    background: #f8df9c;
}

.sender {
    font-size: 11px;
    font-weight: bold;
    margin-bottom: 5px;
    color: #76515d;
}

.text {
    font-size: 14px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}

.message-time {
    font-size: 10px;
    color: #888;
    margin-top: 6px;
    text-align: right;
}

.empty {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: #999;
    padding: 30px;
}

.empty-box {
    max-width: 400px;
}

.empty-box h2 {
    color: #76515d;
}

@media (max-width: 800px) {
    .sidebar {
        width: 280px;
    }

    .message-box {
        max-width: 82%;
    }

    .header {
        padding: 14px;
    }

    .header h1 {
        font-size: 20px;
    }
}

@media (max-width: 600px) {
    .content {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        max-height: 300px;
    }

    .chat {
        min-height: 500px;
    }
}
</style>
</head>

<body>

<div class="page">

    <div class="header">
        <div>
            <h1>💬 Admin Messages</h1>
            <small>Monitor customer and provider conversations</small>
        </div>

        <a href="dashboard.php" class="back">← Dashboard</a>
    </div>

    <div class="content">

        <aside class="sidebar">

            <div class="sidebar-top">

                <h2>Conversations</h2>

                <form method="GET">
                    <?php if ($selected_booking_id > 0): ?>
                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?php echo $selected_booking_id; ?>"
                        >
                    <?php endif; ?>

                    <input
                        type="text"
                        name="search"
                        class="search"
                        placeholder="Search customer, provider or booking..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </form>

                <div class="count">
                    <?php echo $total_conversations; ?> conversation(s)
                </div>

            </div>

            <?php if (empty($conversations)): ?>

                <div style="padding:25px;color:#888;text-align:center;">
                    No conversations found.
                </div>

            <?php else: ?>

                <?php foreach ($conversations as $conversation): ?>

                    <a
                        href="?booking_id=<?php echo (int)$conversation["booking_id"]; ?>&search=<?php echo urlencode($search); ?>"
                        class="conversation <?php echo $selected_booking_id === (int)$conversation["booking_id"] ? "active" : ""; ?>"
                    >

                        <div class="conversation-title">
                            <?php
                            echo htmlspecialchars(
                                $conversation["conversation_name"]
                            );
                            ?>
                        </div>

                        <div class="booking">
                            Booking #<?php echo (int)$conversation["booking_id"]; ?>
                        </div>

                        <div class="last-message">
                            <?php
                            echo htmlspecialchars(
                                $conversation["last_message"]
                            );
                            ?>
                        </div>

                        <div class="time">
                            <?php
                            echo htmlspecialchars(
                                date(
                                    "d M Y, h:i A",
                                    strtotime($conversation["last_message_time"])
                                )
                            );
                            ?>
                        </div>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </aside>


        <main class="chat">

            <?php if ($selected_booking): ?>

                <div class="chat-header">

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $selected_booking["customer_name"] ?: "Customer"
                        );
                        ?>
                        ↔
                        <?php
                        echo htmlspecialchars(
                            $selected_booking["provider_name"] ?: "Provider"
                        );
                        ?>
                    </h2>

                    <p>
                        Booking #<?php echo (int)$selected_booking["id"]; ?>
                    </p>

                    <p>
                        Customer:
                        <?php
                        echo htmlspecialchars(
                            $selected_booking["customer_email"] ?: "-"
                        );
                        ?>
                    </p>

                    <p>
                        Provider:
                        <?php
                        echo htmlspecialchars(
                            $selected_booking["provider_email"] ?: "-"
                        );
                        ?>
                    </p>

                </div>


                <div class="messages" id="messages">

                    <?php if (empty($messages)): ?>

                        <div class="empty">
                            <div class="empty-box">
                                <h2>No messages</h2>
                                <p>
                                    There are no messages in this booking conversation yet.
                                </p>
                            </div>
                        </div>

                    <?php else: ?>

                        <?php foreach ($messages as $message): ?>

                            <div
                                class="message-row <?php echo $message["sender_role"] === "customer" ? "customer" : "provider"; ?>"
                            >

                                <div class="message-box">

                                    <div class="sender">
                                        <?php
                                        echo $message["sender_role"] === "customer"
                                            ? "Customer"
                                            : "Provider";
                                        ?>
                                    </div>

                                    <div class="text">
                                        <?php
                                        echo htmlspecialchars(
                                            $message["message"]
                                        );
                                        ?>
                                    </div>

                                    <div class="message-time">
                                        <?php
                                        echo htmlspecialchars(
                                            date(
                                                "d M Y, h:i A",
                                                strtotime($message["created_at"])
                                            )
                                        );
                                        ?>
                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-box">

                        <h2>💬 Select a conversation</h2>

                        <p>
                            Select any customer ↔ provider conversation
                            from the left side to view the complete chat.
                        </p>

                    </div>

                </div>

            <?php endif; ?>

        </main>

    </div>

</div>

<script>
const messagesBox = document.getElementById("messages");

if (messagesBox) {
    messagesBox.scrollTop = messagesBox.scrollHeight;
}
</script>

</body>
</html>

