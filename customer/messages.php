<?php

session_start();

require_once "../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["active_role"]) ||
    $_SESSION["active_role"] !== "customer"
) {
    header("Location: login.php");
    exit;
}

$customer_id = (int)$_SESSION["user_id"];

$booking_id = 0;

if (isset($_GET["booking_id"])) {
    $booking_id = (int)$_GET["booking_id"];
}

if ($booking_id <= 0 && isset($_GET["id"])) {
    $booking_id = (int)$_GET["id"];
}

if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit;
}

$booking_stmt = $conn->prepare("
    SELECT
        b.id,
        b.customer_id,
        b.provider_id,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status,
        b.payment_status,

        pr.name AS provider_name,
        pr.phone AS provider_phone,

        p.package_name,
        s.service_name,
        e.event_name

    FROM bookings b

    LEFT JOIN providers pr
        ON b.provider_id = pr.id

    LEFT JOIN packages p
        ON b.package_id = p.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN events e
        ON b.event_type_id = e.id

    WHERE b.id = ?
    AND b.customer_id = ?

    LIMIT 1
");

if (!$booking_stmt) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

$booking_stmt->bind_param(
    "ii",
    $booking_id,
    $customer_id
);

$booking_stmt->execute();

$booking_result = $booking_stmt->get_result();

$booking = $booking_result->fetch_assoc();

$booking_stmt->close();

if (!$booking) {
    die("Booking not found or you do not have permission to view this conversation.");
}

$provider_id = (int)$booking["provider_id"];

if ($provider_id <= 0) {
    die("Provider is not assigned to this booking.");
}

$provider_name = !empty($booking["provider_name"])
    ? $booking["provider_name"]
    : "Service Provider";

$provider_phone = !empty($booking["provider_phone"])
    ? $booking["provider_phone"]
    : "";

$package_name = !empty($booking["package_name"])
    ? $booking["package_name"]
    : "";

$service_name = !empty($booking["service_name"])
    ? $booking["service_name"]
    : "";

$event_name = !empty($booking["event_name"])
    ? $booking["event_name"]
    : "";


function createProviderNotification($conn, $provider_id, $booking_id, $title, $message)
{
    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            user_id,
            user_role,
            title,
            message,
            type,
            is_read,
            created_at
        )
        VALUES
        (?, 'provider', ?, ?, 'other', 0, NOW())
    ");

    if ($stmt) {
        $stmt->bind_param(
            "iss",
            $provider_id,
            $title,
            $message
        );

        $stmt->execute();
        $stmt->close();
    }
}


function getUploadCategory($mime)
{
    $mime = strtolower(trim($mime));

    if (strpos($mime, "image/") === 0) {
        return "images";
    }

    if (strpos($mime, "video/") === 0) {
        return "videos";
    }

    if (
        strpos($mime, "audio/") === 0 ||
        $mime === "application/ogg"
    ) {
        return "audio";
    }

    return "files";
}


function getMessagePreview($message)
{
    if ($message === null || trim((string)$message) === "") {
        return "No messages yet";
    }

    $message = trim((string)$message);

    if ($message === "[Voice message]") {
        return "🎤 Voice message";
    }

    if ($message === "[Image]") {
        return "📷 Photo";
    }

    if ($message === "[Video]") {
        return "🎥 Video";
    }

    if ($message === "[Audio]") {
        return "🎵 Audio";
    }

    if ($message === "[File]") {
        return "📎 File";
    }

    if (strlen($message) > 32) {
        return substr($message, 0, 32) . "...";
    }

    return $message;
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    if ($action === "send_voice") {

        if (
            !isset($_FILES["voice"]) ||
            $_FILES["voice"]["error"] !== UPLOAD_ERR_OK
        ) {
            die("Voice message upload failed.");
        }

        $voice = $_FILES["voice"];

        $max_size = 10 * 1024 * 1024;

        if ((int)$voice["size"] > $max_size) {
            die("Voice message is too large. Maximum size is 10 MB.");
        }

        $file_type = strtolower(
            trim(
                $voice["type"] ?? ""
            )
        );

        $allowed_types = [
            "audio/webm",
            "audio/ogg",
            "audio/mpeg",
            "audio/mp4",
            "audio/wav",
            "audio/x-wav",
            "video/webm"
        ];

        if (!in_array($file_type, $allowed_types, true)) {
            die("Invalid voice message format.");
        }

        $verify_stmt = $conn->prepare("
            SELECT id
            FROM bookings
            WHERE id = ?
            AND customer_id = ?
            AND provider_id = ?
            LIMIT 1
        ");

        if (!$verify_stmt) {
            die("Booking verification error.");
        }

        $verify_stmt->bind_param(
            "iii",
            $booking_id,
            $customer_id,
            $provider_id
        );

        $verify_stmt->execute();

        $verify_result = $verify_stmt->get_result();

        $valid_booking = $verify_result->fetch_assoc();

        $verify_stmt->close();

        if (!$valid_booking) {
            die("You do not have permission to send a voice message for this booking.");
        }

        $audio_folder = __DIR__ . "/../uploads/messages/audio/";

        if (!is_dir($audio_folder)) {
            mkdir($audio_folder, 0777, true);
        }

        $extension = "webm";

        if ($file_type === "audio/ogg") {
            $extension = "ogg";
        } elseif ($file_type === "audio/mpeg") {
            $extension = "mp3";
        } elseif ($file_type === "audio/mp4") {
            $extension = "m4a";
        } elseif (
            $file_type === "audio/wav" ||
            $file_type === "audio/x-wav"
        ) {
            $extension = "wav";
        }

        try {
            $random_name = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $random_name = uniqid();
        }

        $file_name =
            "voice_" .
            $random_name .
            "." .
            $extension;

        $full_path =
            $audio_folder .
            $file_name;

        if (!move_uploaded_file(
            $voice["tmp_name"],
            $full_path
        )) {
            die("Could not save voice message.");
        }

        $relative_path =
            "../uploads/messages/audio/" .
            $file_name;

        $message_text = "[Voice message]";

        $insert_stmt = $conn->prepare("
            INSERT INTO messages
            (
                booking_id,
                sender_id,
                sender_role,
                receiver_id,
                receiver_role,
                message,
                is_read
            )
            VALUES
            (?, ?, 'customer', ?, 'provider', ?, 0)
        ");

        if (!$insert_stmt) {
            @unlink($full_path);
            die("Message Database Error: " . htmlspecialchars($conn->error));
        }

        $insert_stmt->bind_param(
            "iiis",
            $booking_id,
            $customer_id,
            $provider_id,
            $message_text
        );

        if (!$insert_stmt->execute()) {
            @unlink($full_path);
            $insert_stmt->close();
            die("Could not create voice message.");
        }

        $message_id = (int)$insert_stmt->insert_id;

        $insert_stmt->close();

        $attachment_type = $file_type;

        if ($file_type === "video/webm") {
            $attachment_type = "audio/webm";
        }

        $attachment_stmt = $conn->prepare("
            INSERT INTO message_attachments
            (
                message_id,
                file_name,
                file_path,
                file_type,
                file_size
            )
            VALUES
            (?, ?, ?, ?, ?)
        ");

        if (!$attachment_stmt) {
            @unlink($full_path);
            die("Voice attachment database error.");
        }

        $file_size = (int)$voice["size"];

        $attachment_stmt->bind_param(
            "isssi",
            $message_id,
            $file_name,
            $relative_path,
            $attachment_type,
            $file_size
        );

        if (!$attachment_stmt->execute()) {
            @unlink($full_path);
            $attachment_stmt->close();
            die("Could not save voice attachment.");
        }

        $attachment_stmt->close();

        createProviderNotification(
            $conn,
            $provider_id,
            $booking_id,
            "New Voice Message",
            "You have a new voice message from customer for booking #" . $booking_id
        );

        header(
            "Location: messages.php?booking_id=" .
            $booking_id
        );

        exit;
    }


    if ($action === "send_attachment") {

        if (
            !isset($_FILES["attachment"]) ||
            $_FILES["attachment"]["error"] !== UPLOAD_ERR_OK
        ) {
            die("File upload failed.");
        }

        $file = $_FILES["attachment"];

        $max_size = 25 * 1024 * 1024;

        if ((int)$file["size"] > $max_size) {
            die("File is too large. Maximum size is 25 MB.");
        }

        $mime_type = strtolower(
            trim(
                $file["type"] ?? ""
            )
        );

        $allowed_types = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",

            "video/mp4",
            "video/webm",
            "video/quicktime",
            "video/x-msvideo",

            "audio/webm",
            "audio/ogg",
            "audio/mpeg",
            "audio/mp4",
            "audio/wav",
            "audio/x-wav",

            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "text/plain",
            "application/zip",
            "application/x-zip-compressed"
        ];

        if (!in_array($mime_type, $allowed_types, true)) {
            die("This file type is not allowed.");
        }

        $verify_stmt = $conn->prepare("
            SELECT id
            FROM bookings
            WHERE id = ?
            AND customer_id = ?
            AND provider_id = ?
            LIMIT 1
        ");

        if (!$verify_stmt) {
            die("Booking verification error.");
        }

        $verify_stmt->bind_param(
            "iii",
            $booking_id,
            $customer_id,
            $provider_id
        );

        $verify_stmt->execute();

        $verify_result = $verify_stmt->get_result();

        $valid_booking = $verify_result->fetch_assoc();

        $verify_stmt->close();

        if (!$valid_booking) {
            die("You do not have permission to send this file.");
        }

        $category = getUploadCategory($mime_type);

        $folder = __DIR__ .
            "/../uploads/messages/" .
            $category .
            "/";

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $original_name = basename($file["name"]);

        $extension = strtolower(
            pathinfo(
                $original_name,
                PATHINFO_EXTENSION
            )
        );

        if ($extension === "") {
            $extension = "bin";
        }

        try {
            $random_name = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $random_name = uniqid();
        }

        $saved_name =
            "file_" .
            $random_name .
            "." .
            $extension;

        $full_path =
            $folder .
            $saved_name;

        if (!move_uploaded_file(
            $file["tmp_name"],
            $full_path
        )) {
            die("Could not save uploaded file.");
        }

        $relative_path =
            "../uploads/messages/" .
            $category .
            "/" .
            $saved_name;

        if (strpos($mime_type, "image/") === 0) {
            $message_text = "[Image]";
        } elseif (strpos($mime_type, "video/") === 0) {
            $message_text = "[Video]";
        } elseif (
            strpos($mime_type, "audio/") === 0
        ) {
            $message_text = "[Audio]";
        } else {
            $message_text = "[File]";
        }

        $insert_stmt = $conn->prepare("
            INSERT INTO messages
            (
                booking_id,
                sender_id,
                sender_role,
                receiver_id,
                receiver_role,
                message,
                is_read
            )
            VALUES
            (?, ?, 'customer', ?, 'provider', ?, 0)
        ");

        if (!$insert_stmt) {
            @unlink($full_path);
            die("Message Database Error: " . htmlspecialchars($conn->error));
        }

        $insert_stmt->bind_param(
            "iiis",
            $booking_id,
            $customer_id,
            $provider_id,
            $message_text
        );

        if (!$insert_stmt->execute()) {
            @unlink($full_path);
            $insert_stmt->close();
            die("Could not create file message.");
        }

        $message_id = (int)$insert_stmt->insert_id;

        $insert_stmt->close();

        $attachment_stmt = $conn->prepare("
            INSERT INTO message_attachments
            (
                message_id,
                file_name,
                file_path,
                file_type,
                file_size
            )
            VALUES
            (?, ?, ?, ?, ?)
        ");

        if (!$attachment_stmt) {
            @unlink($full_path);
            die("Attachment database error.");
        }

        $file_size = (int)$file["size"];

        $attachment_stmt->bind_param(
            "isssi",
            $message_id,
            $original_name,
            $relative_path,
            $mime_type,
            $file_size
        );

        if (!$attachment_stmt->execute()) {
            @unlink($full_path);
            $attachment_stmt->close();
            die("Could not save attachment.");
        }

        $attachment_stmt->close();

        createProviderNotification(
            $conn,
            $provider_id,
            $booking_id,
            "New Attachment",
            "You have a new attachment from customer for booking #" . $booking_id
        );

        header(
            "Location: messages.php?booking_id=" .
            $booking_id
        );

        exit;
    }


    $message = trim(
        $_POST["message"] ?? ""
    );

    if ($message !== "") {

        $insert_stmt = $conn->prepare("
            INSERT INTO messages
            (
                booking_id,
                sender_id,
                sender_role,
                receiver_id,
                receiver_role,
                message,
                is_read
            )
            VALUES
            (?, ?, 'customer', ?, 'provider', ?, 0)
        ");

        if (!$insert_stmt) {
            die(
                "Message Database Error: " .
                htmlspecialchars($conn->error)
            );
        }

        $insert_stmt->bind_param(
            "iiis",
            $booking_id,
            $customer_id,
            $provider_id,
            $message
        );

        if (!$insert_stmt->execute()) {
            $insert_stmt->close();
            die("Could not send message.");
        }

        $insert_stmt->close();

        createProviderNotification(
            $conn,
            $provider_id,
            $booking_id,
            "New Message",
            "You have a new message from customer for booking #" . $booking_id
        );
    }

    header(
        "Location: messages.php?booking_id=" .
        $booking_id
    );

    exit;
}


$read_stmt = $conn->prepare("
    UPDATE messages

    SET is_read = 1

    WHERE booking_id = ?

    AND sender_id = ?
    AND sender_role = 'provider'

    AND receiver_id = ?
    AND receiver_role = 'customer'
");

if ($read_stmt) {

    $read_stmt->bind_param(
        "iii",
        $booking_id,
        $provider_id,
        $customer_id
    );

    $read_stmt->execute();

    $read_stmt->close();
}


$message_stmt = $conn->prepare("
    SELECT
        m.id,
        m.sender_id,
        m.sender_role,
        m.receiver_id,
        m.receiver_role,
        m.message,
        m.is_read,
        m.created_at,

        a.file_path,
        a.file_type,
        a.file_name

    FROM messages m

    LEFT JOIN message_attachments a
        ON m.id = a.message_id

    WHERE m.booking_id = ?

    AND
    (
        (
            m.sender_id = ?
            AND m.sender_role = 'customer'
            AND m.receiver_id = ?
            AND m.receiver_role = 'provider'
        )

        OR

        (
            m.sender_id = ?
            AND m.sender_role = 'provider'
            AND m.receiver_id = ?
            AND m.receiver_role = 'customer'
        )
    )

    ORDER BY m.id ASC
");

if (!$message_stmt) {
    die(
        "Message Query Error: " .
        htmlspecialchars($conn->error)
    );
}

$message_stmt->bind_param(
    "iiiii",
    $booking_id,
    $customer_id,
    $provider_id,
    $provider_id,
    $customer_id
);

$message_stmt->execute();

$messages = $message_stmt->get_result();


$last_message_text = "";

$last_message_stmt = $conn->prepare("
    SELECT message
    FROM messages
    WHERE booking_id = ?
    ORDER BY id DESC
    LIMIT 1
");

if ($last_message_stmt) {

    $last_message_stmt->bind_param(
        "i",
        $booking_id
    );

    $last_message_stmt->execute();

    $last_message_result =
        $last_message_stmt->get_result();

    $last_message_row =
        $last_message_result->fetch_assoc();

    if ($last_message_row) {
        $last_message_text =
            getMessagePreview(
                $last_message_row["message"]
            );
    }

    $last_message_stmt->close();
}

if ($last_message_text === "") {
    $last_message_text = "No messages yet";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Messages - <?= htmlspecialchars($provider_name) ?>
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f6f1f4;
    overflow: hidden;
}

.messenger {
    width: 96%;
    max-width: 1250px;
    height: 94vh;
    margin: 3vh auto;
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    display: flex;
    box-shadow: 0 12px 45px rgba(0,0,0,0.10);
}

.sidebar {
    width: 290px;
    min-width: 290px;
    background: #fff;
    border-right: 1px solid #eeeeee;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    height: 62px;
    min-height: 62px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #eeeeee;
}

.sidebar-header h2 {
    font-size: 19px;
    color: #333;
}

.back-link {
    text-decoration: none;
    color: #b45b79;
    font-size: 12px;
    font-weight: bold;
}

.search-area {
    padding: 10px 12px;
}

.search-input {
    width: 100%;
    height: 38px;
    border: 0;
    outline: none;
    border-radius: 20px;
    background: #f5f5f6;
    padding: 0 15px;
    font-size: 13px;
}

.conversation-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.conversation {
    min-height: 67px;
    padding: 9px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: inherit;
    border-left: 3px solid transparent;
    border-bottom: 1px solid #f4f4f4;
}

.conversation.active {
    background: #fff5f8;
    border-left-color: #df88a8;
}

.conversation:hover {
    background: #faf7f8;
}

.avatar {
    width: 43px;
    height: 43px;
    min-width: 43px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg,#f3bfd3,#f5dfa0);
    color: #704356;
    font-weight: bold;
    font-size: 17px;
}

.conversation-info {
    min-width: 0;
    flex: 1;
}

.conversation-top {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.conversation-name {
    font-size: 13px;
    font-weight: bold;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-time {
    color: #aaa;
    font-size: 9px;
    white-space: nowrap;
}

.conversation-preview {
    margin-top: 4px;
    color: #888;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-number {
    margin-top: 3px;
    color: #b56576;
    font-size: 9px;
}

.chat-area {
    flex: 1;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #fffafd;
}

.chat-header {
    height: 65px;
    min-height: 65px;
    padding: 8px 15px;
    background: #fff;
    border-bottom: 1px solid #eeeeee;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-user {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.chat-avatar {
    width: 43px;
    height: 43px;
    min-width: 43px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg,#f3bfd3,#f5dfa0);
    color: #704356;
    font-size: 17px;
    font-weight: bold;
}

.chat-name {
    font-size: 15px;
    font-weight: bold;
    color: #333;
}

.chat-status {
    margin-top: 2px;
    font-size: 10px;
    color: #7f7f7f;
}

.actions {
    display: flex;
    gap: 6px;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border: 0;
    background: #fff0f5;
    color: #ad5877;
    font-size: 16px;
    cursor: pointer;
}

.action-btn.call {
    background: #eaf8ef;
    color: #24944b;
}

.action-btn.video {
    background: #f4edff;
    color: #7950a8;
}

.booking-info {
    height: 37px;
    min-height: 37px;
    padding: 0 15px;
    background: linear-gradient(90deg,#fff9e9,#fff3f7);
    border-bottom: 1px solid #eeeeee;
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    white-space: nowrap;
    font-size: 10px;
    color: #777;
}

.booking-pill {
    padding: 4px 8px;
    background: rgba(255,255,255,0.75);
    border-radius: 10px;
}

.booking-pill strong {
    color: #555;
}

.chat-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 16px 20px;
    background: linear-gradient(180deg,#fffafd,#fff8fb);
}

.message-row {
    width: 100%;
    display: flex;
    margin-bottom: 6px;
}

.message-row.customer {
    justify-content: flex-end;
}

.message-row.provider {
    justify-content: flex-start;
}

.message-box {
    max-width: min(62%, 500px);
}

.message {
    padding: 8px 12px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

.customer .message {
    background: linear-gradient(135deg,#e79ab9,#d981a4);
    color: #fff;
    border-radius: 16px 16px 3px 16px;
}

.provider .message {
    background: #fff;
    color: #333;
    border: 1px solid #eeeeee;
    border-radius: 16px 16px 16px 3px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.message-meta {
    margin-top: 3px;
    padding: 0 4px;
    font-size: 8px;
    color: #999;
}

.customer .message-meta {
    text-align: right;
}

.provider .message-meta {
    text-align: left;
}

.seen {
    color: #a45c78;
}

.date-divider {
    text-align: center;
    margin: 8px 0 12px;
    color: #aaa;
    font-size: 9px;
}

.date-divider span {
    padding: 4px 10px;
    border-radius: 12px;
    background: #f0edef;
}

.voice-message {
    min-width: 230px;
}

.voice-label {
    font-size: 10px;
    font-weight: bold;
    margin-bottom: 3px;
}

.customer .voice-label {
    color: #fff;
}

.provider .voice-label {
    color: #a65372;
}

.voice-message audio {
    width: 250px;
    max-width: 100%;
    height: 38px;
}

.attachment-image {
    display: block;
    max-width: 240px;
    max-height: 220px;
    border-radius: 10px;
    object-fit: cover;
    cursor: pointer;
}

.attachment-video {
    width: 280px;
    max-width: 100%;
    max-height: 230px;
    border-radius: 10px;
    display: block;
}

.attachment-file {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px;
}

.attachment-file a {
    color: inherit;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}

.empty-chat {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-content {
    text-align: center;
    color: #999;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
}

.empty-icon {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff0f5;
    font-size: 24px;
}

.empty-content strong {
    color: #666;
    font-size: 14px;
}

.empty-content span {
    font-size: 11px;
}

.composer-area {
    position: relative;
    flex-shrink: 0;
    background: #fff;
    border-top: 1px solid #eeeeee;
    padding: 8px 12px;
}

.chat-form {
    width: 100%;
    height: 50px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.composer-btn {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #a65372;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.composer-btn:hover {
    background: #fff0f5;
}

.chat-input {
    flex: 1;
    min-width: 0;
    height: 40px;
    border: 1px solid #e5e5e5;
    outline: none;
    border-radius: 21px;
    background: #f7f7f8;
    padding: 0 15px;
    font-size: 13px;
}

.chat-input:focus {
    background: #fff;
    border-color: #e3a0b8;
}

.send-btn {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border: 0;
    border-radius: 50%;
    background: linear-gradient(135deg,#ed9fbd,#d8799d);
    color: #fff;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(213,121,157,0.25);
}

.send-btn:hover {
    transform: scale(1.05);
}

.send-btn:disabled {
    opacity: 0.7;
}

.composer-popover {
    display: none;
    position: absolute;
    z-index: 20;
    bottom: 62px;
    background: #fff;
    border: 1px solid #eeeeee;
    border-radius: 15px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.13);
}

.emoji-popover {
    left: 10px;
    width: 330px;
    padding: 10px;
}

.emoji-title {
    font-size: 11px;
    color: #888;
    padding: 2px 4px 8px;
}

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 3px;
    max-height: 245px;
    overflow-y: auto;
}

.emoji-item {
    width: 34px;
    height: 34px;
    border: 0;
    background: transparent;
    border-radius: 8px;
    font-size: 21px;
    cursor: pointer;
}

.emoji-item:hover {
    background: #fff0f5;
}

.attach-popover {
    right: 55px;
    width: 300px;
    padding: 12px;
}

.attach-title {
    font-size: 12px;
    font-weight: bold;
    color: #555;
    margin-bottom: 9px;
}

.attach-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 7px;
}

.attach-option {
    border: 1px solid #eeeeee;
    background: #fff;
    border-radius: 10px;
    padding: 9px 5px;
    cursor: pointer;
    text-align: center;
    font-size: 11px;
    color: #666;
}

.attach-option span {
    display: block;
    font-size: 20px;
    margin-bottom: 3px;
}

.attach-option:hover {
    background: #fff5f8;
}

.voice-popover {
    left: 45px;
    width: 300px;
    padding: 12px;
}

.voice-record-title {
    font-size: 12px;
    font-weight: bold;
    color: #555;
}

.recording-status {
    margin-top: 7px;
    color: #e74c3c;
    font-size: 11px;
    display: none;
}

.recording-status.active {
    display: block;
}

.voice-preview {
    margin-top: 8px;
    display: none;
    align-items: center;
    gap: 6px;
}

.voice-preview audio {
    flex: 1;
    min-width: 0;
    height: 34px;
}

.voice-small-btn {
    border: 0;
    border-radius: 15px;
    padding: 7px 10px;
    cursor: pointer;
    font-size: 10px;
}

.cancel-voice {
    background: #eeeeee;
    color: #555;
}

.send-voice {
    background: #e592b0;
    color: white;
}

.file-name {
    margin-top: 8px;
    font-size: 10px;
    color: #888;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hidden-file {
    display: none;
}

@media (max-width: 800px) {

    .messenger {
        width: 100%;
        height: 100vh;
        margin: 0;
        border-radius: 0;
    }

    .sidebar {
        display: none;
    }

    .message-box {
        max-width: 80%;
    }

    .chat-body {
        padding: 12px;
    }

    .emoji-popover {
        left: 5px;
        width: 300px;
    }

    .attach-popover {
        right: 5px;
        width: 280px;
    }

    .voice-popover {
        left: 5px;
        width: 280px;
    }

}

</style>

</head>

<body>

<div class="messenger">

    <aside class="sidebar">

        <div class="sidebar-header">

            <h2>Messages</h2>

            <a
                href="my_bookings.php"
                class="back-link"
            >
                Back
            </a>

        </div>


        <div class="search-area">

            <input
                type="text"
                id="searchInput"
                class="search-input"
                placeholder="Search conversations..."
                autocomplete="off"
            >

        </div>


        <div
            class="conversation-list"
            id="conversationList"
        >

            <a
                href="messages.php?booking_id=<?= $booking_id ?>"
                class="conversation active"
                data-name="<?= htmlspecialchars(strtolower($provider_name)) ?>"
            >

                <div class="avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $provider_name,
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>


                <div class="conversation-info">

                    <div class="conversation-top">

                        <div class="conversation-name">
                            <?= htmlspecialchars($provider_name) ?>
                        </div>

                        <div class="conversation-time">
                            <?= date("h:i A") ?>
                        </div>

                    </div>


                    <div class="conversation-preview">
                        <?= htmlspecialchars($last_message_text) ?>
                    </div>


                    <div class="booking-number">
                        Booking #<?= $booking_id ?>
                    </div>

                </div>

            </a>

        </div>

    </aside>


    <main class="chat-area">


        <div class="chat-header">

            <div class="chat-user">

                <div class="chat-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $provider_name,
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>


                <div>

                    <div class="chat-name">
                        <?= htmlspecialchars($provider_name) ?>
                    </div>

                    <div class="chat-status">
                        ● Service Provider
                    </div>

                    <?php if ($provider_phone): ?>

                        <div
                            style="
                                margin-top:3px;
                                font-size:10px;
                                color:#777;
                            "
                        >
                            📱 <?= htmlspecialchars($provider_phone) ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div class="booking-info">

            <span class="booking-pill">
                #<?= $booking_id ?>
            </span>


            <?php if ($event_name): ?>

                <span class="booking-pill">
                    Event:
                    <strong>
                        <?= htmlspecialchars($event_name) ?>
                    </strong>
                </span>

            <?php endif; ?>


            <?php if ($package_name): ?>

                <span class="booking-pill">
                    Package:
                    <strong>
                        <?= htmlspecialchars($package_name) ?>
                    </strong>
                </span>

            <?php elseif ($service_name): ?>

                <span class="booking-pill">
                    Service:
                    <strong>
                        <?= htmlspecialchars($service_name) ?>
                    </strong>
                </span>

            <?php endif; ?>


            <span class="booking-pill">
                Date:
                <strong>
                    <?= !empty($booking["booking_date"])
                        ? date(
                            "d M Y",
                            strtotime(
                                $booking["booking_date"]
                            )
                        )
                        : "N/A"
                    ?>
                </strong>
            </span>


            <span class="booking-pill">
                Status:
                <strong>
                    <?= htmlspecialchars(
                        ucfirst(
                            $booking["status"] ?? "Pending"
                        )
                    ) ?>
                </strong>
            </span>

        </div>


        <div
            class="chat-body"
            id="chatBody"
        >

            <?php if ($messages->num_rows === 0): ?>

                <div class="empty-chat">

                    <div class="empty-content">

                        <div class="empty-icon">
                            💬
                        </div>

                        <strong>
                            Start a conversation
                        </strong>

                        <span>
                            Send a message to
                            <?= htmlspecialchars($provider_name) ?>
                        </span>

                    </div>

                </div>

            <?php else: ?>

                <?php

                $last_date = "";

                while ($row = $messages->fetch_assoc()):

                    $is_customer =
                        $row["sender_role"] === "customer";

                    $message_class =
                        $is_customer
                        ? "customer"
                        : "provider";

                    $timestamp =
                        strtotime(
                            $row["created_at"]
                        );

                    $message_date =
                        date(
                            "Y-m-d",
                            $timestamp
                        );

                    $display_date =
                        date(
                            "d M Y",
                            $timestamp
                        );

                    if (
                        $message_date !==
                        $last_date
                    ):

                        $last_date =
                            $message_date;

                ?>

                    <div class="date-divider">

                        <span>
                            <?= htmlspecialchars($display_date) ?>
                        </span>

                    </div>

                <?php endif; ?>


                    <div
                        class="message-row <?= $message_class ?>"
                    >

                        <div class="message-box">

                            <div class="message">

                                <?php if (
                                    $row["message"] === "[Voice message]" &&
                                    !empty($row["file_path"])
                                ): ?>

                                    <div class="voice-message">

                                        <div class="voice-label">
                                            🎤 Voice message
                                        </div>

                                        <audio
                                            controls
                                            preload="metadata"
                                        >

                                            <source
                                                src="<?= htmlspecialchars($row["file_path"]) ?>"
                                                type="<?= htmlspecialchars($row["file_type"] ?: "audio/webm") ?>"
                                            >

                                            Your browser does not support audio playback.

                                        </audio>

                                    </div>


                                <?php elseif (
                                    $row["message"] === "[Image]" &&
                                    !empty($row["file_path"])
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars($row["file_path"]) ?>"
                                        target="_blank"
                                    >

                                        <img
                                            src="<?= htmlspecialchars($row["file_path"]) ?>"
                                            class="attachment-image"
                                            alt="<?= htmlspecialchars($row["file_name"] ?: "Photo") ?>"
                                        >

                                    </a>


                                <?php elseif (
                                    $row["message"] === "[Video]" &&
                                    !empty($row["file_path"])
                                ): ?>

                                    <video
                                        class="attachment-video"
                                        controls
                                        preload="metadata"
                                    >

                                        <source
                                            src="<?= htmlspecialchars($row["file_path"]) ?>"
                                            type="<?= htmlspecialchars($row["file_type"]) ?>"
                                        >

                                        Your browser does not support video playback.

                                    </video>


                                <?php elseif (
                                    $row["message"] === "[Audio]" &&
                                    !empty($row["file_path"])
                                ): ?>

                                    <div class="voice-message">

                                        <div class="voice-label">
                                            🎵 Audio
                                        </div>

                                        <audio
                                            controls
                                            preload="metadata"
                                        >

                                            <source
                                                src="<?= htmlspecialchars($row["file_path"]) ?>"
                                                type="<?= htmlspecialchars($row["file_type"]) ?>"
                                            >

                                        </audio>

                                    </div>


                                <?php elseif (
                                    $row["message"] === "[File]" &&
                                    !empty($row["file_path"])
                                ): ?>

                                    <div class="attachment-file">

                                        <span>
                                            📎
                                        </span>

                                        <a
                                            href="<?= htmlspecialchars($row["file_path"]) ?>"
                                            target="_blank"
                                            download="<?= htmlspecialchars($row["file_name"]) ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $row["file_name"] ?: "Download file"
                                            ) ?>
                                        </a>

                                    </div>


                                <?php else: ?>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $row["message"]
                                        )
                                    ) ?>

                                <?php endif; ?>

                            </div>


                            <div class="message-meta">

                                <?= date(
                                    "h:i A",
                                    $timestamp
                                ) ?>


                                <?php if ($is_customer): ?>

                                    <?php if (
                                        (int)$row["is_read"] === 1
                                    ): ?>

                                        <span class="seen">
                                            ✓✓ Seen
                                        </span>

                                    <?php else: ?>

                                        ✓ Sent

                                    <?php endif; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php endif; ?>

        </div>


        <div class="composer-area">


            <div
                class="composer-popover emoji-popover"
                id="emojiPopover"
            >

                <div class="emoji-title">
                    Choose an emoji
                </div>


                <div class="emoji-grid">

                    <?php

                    $emojis = [
                        "😀","😃","😄","😁","😆","😅","😂","🤣",
                        "😊","😇","🙂","🙃","😉","😌","😍","🥰",
                        "😘","😗","😙","😚","😋","😛","😝","😜",
                        "🤪","🤨","🧐","🤓","😎","🤩","🥳","😏",
                        "😢","😭","😤","😠","😡","🤬","😱","😨",
                        "😴","🤤","😪","🤔","🤭","🤫","😶","🙄",
                        "❤️","🩷","🧡","💛","💚","💙","💜","🖤",
                        "🤍","🤎","💔","❣️","💕","💞","💓","💗",
                        "💖","💘","💝","💟","💌","💋","🌹","🌸",
                        "🌷","🌺","🌻","✨","⭐","🌟","🔥","🎉",
                        "🎊","🎁","🥰","😍","😘","😻","🙈","💯"
                    ];

                    foreach ($emojis as $emoji):

                    ?>

                        <button
                            type="button"
                            class="emoji-item"
                            data-emoji="<?= htmlspecialchars($emoji) ?>"
                        >
                            <?= $emoji ?>
                        </button>

                    <?php endforeach; ?>

                </div>

            </div>


            <div
                class="composer-popover attach-popover"
                id="attachPopover"
            >

                <div class="attach-title">
                    Send photo, video or file
                </div>


                <div class="attach-options">

                    <button
                        type="button"
                        class="attach-option"
                        id="photoOption"
                    >
                        <span>📷</span>
                        Photo
                    </button>


                    <button
                        type="button"
                        class="attach-option"
                        id="videoOption"
                    >
                        <span>🎥</span>
                        Video
                    </button>


                    <button
                        type="button"
                        class="attach-option"
                        id="fileOption"
                    >
                        <span>📎</span>
                        File
                    </button>

                </div>


                <div
                    class="file-name"
                    id="selectedFileName"
                ></div>

            </div>


            <div
                class="composer-popover voice-popover"
                id="voicePopover"
            >

                <div class="voice-record-title">
                    🎤 Voice message
                </div>


                <div
                    class="recording-status"
                    id="recordingStatus"
                >
                    🔴 Recording...
                </div>


                <div
                    class="voice-preview"
                    id="voicePreview"
                >

                    <audio
                        id="recordedAudio"
                        controls
                    ></audio>


                    <button
                        type="button"
                        class="voice-small-btn cancel-voice"
                        id="cancelVoice"
                    >
                        Cancel
                    </button>


                    <button
                        type="button"
                        class="voice-small-btn send-voice"
                        id="sendVoice"
                    >
                        Send
                    </button>

                </div>

            </div>


            <form
                method="POST"
                class="chat-form"
                id="chatForm"
            >

                <button
                    type="button"
                    class="composer-btn"
                    id="emojiButton"
                    title="Emoji"
                >
                    😊
                </button>


                <button
                    type="button"
                    class="composer-btn"
                    id="voiceButton"
                    title="Voice message"
                >
                    🎤
                </button>


                <button
                    type="button"
                    class="composer-btn"
                    id="attachButton"
                    title="Photo, video or file"
                >
                    📎
                </button>


                <input
                    type="text"
                    name="message"
                    id="messageInput"
                    class="chat-input"
                    placeholder="Write a message..."
                    maxlength="2000"
                    autocomplete="off"
                >


                <button
                    type="submit"
                    class="send-btn"
                    id="sendButton"
                    title="Send message"
                >
                    ➤
                </button>

            </form>


            <input
                type="file"
                id="photoInput"
                class="hidden-file"
                accept="image/jpeg,image/png,image/gif,image/webp"
            >


            <input
                type="file"
                id="videoInput"
                class="hidden-file"
                accept="video/mp4,video/webm,video/quicktime,video/x-msvideo"
            >


            <input
                type="file"
                id="fileInput"
                class="hidden-file"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
            >

        </div>

    </main>

</div>


<script>

const bookingId =
    <?= (int)$booking_id ?>;


const chatBody =
    document.getElementById("chatBody");


if (chatBody) {
    chatBody.scrollTop =
        chatBody.scrollHeight;
}


const messageInput =
    document.getElementById("messageInput");


if (messageInput) {
    messageInput.focus();
}


const chatForm =
    document.getElementById("chatForm");


const sendButton =
    document.getElementById("sendButton");


const emojiButton =
    document.getElementById("emojiButton");


const voiceButton =
    document.getElementById("voiceButton");


const attachButton =
    document.getElementById("attachButton");


const emojiPopover =
    document.getElementById("emojiPopover");


const voicePopover =
    document.getElementById("voicePopover");


const attachPopover =
    document.getElementById("attachPopover");


function closePopovers() {

    if (emojiPopover) {
        emojiPopover.style.display = "none";
    }

    if (voicePopover) {
        voicePopover.style.display = "none";
    }

    if (attachPopover) {
        attachPopover.style.display = "none";
    }
}


if (emojiButton) {

    emojiButton.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            const isOpen =
                emojiPopover.style.display === "block";

            closePopovers();

            if (!isOpen) {
                emojiPopover.style.display = "block";
            }

        }
    );
}


if (attachButton) {

    attachButton.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            const isOpen =
                attachPopover.style.display === "block";

            closePopovers();

            if (!isOpen) {
                attachPopover.style.display = "block";
            }

        }
    );
}


if (voiceButton) {

    voiceButton.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            const isOpen =
                voicePopover.style.display === "block";

            closePopovers();

            if (!isOpen) {
                voicePopover.style.display = "block";
            }

        }
    );
}


document.querySelectorAll(".emoji-item")
    .forEach(function(button) {

        button.addEventListener(
            "click",
            function() {

                const emoji =
                    this.dataset.emoji;

                if (!messageInput) {
                    return;
                }

                const start =
                    messageInput.selectionStart ??
                    messageInput.value.length;

                const end =
                    messageInput.selectionEnd ??
                    messageInput.value.length;

                const before =
                    messageInput.value.substring(
                        0,
                        start
                    );

                const after =
                    messageInput.value.substring(
                        end
                    );

                messageInput.value =
                    before +
                    emoji +
                    after;

                const cursorPosition =
                    start +
                    emoji.length;

                messageInput.focus();

                messageInput.setSelectionRange(
                    cursorPosition,
                    cursorPosition
                );

            }
        );

    });


document.addEventListener(
    "click",
    function(event) {

        if (
            !event.target.closest(".composer-popover") &&
            !event.target.closest(".composer-btn")
        ) {
            closePopovers();
        }

    }
);


if (chatForm) {

    chatForm.addEventListener(
        "submit",
        function(event) {

            if (
                messageInput &&
                messageInput.value.trim() !== ""
            ) {

                if (sendButton) {

                    sendButton.disabled = true;

                    sendButton.innerHTML = "✓";
                }

            }

        }
    );
}


if (messageInput) {

    messageInput.addEventListener(
        "keydown",
        function(event) {

            if (
                event.key === "Enter"
            ) {

                event.preventDefault();

                if (
                    messageInput.value.trim() !== ""
                ) {
                    chatForm.requestSubmit();
                }

            }

        }
    );

}


const searchInput =
    document.getElementById("searchInput");


if (searchInput) {

    searchInput.addEventListener(
        "input",
        function() {

            const search =
                this.value
                    .toLowerCase()
                    .trim();

            document
                .querySelectorAll(".conversation")
                .forEach(function(item) {

                    const name =
                        item.dataset.name || "";

                    item.style.display =
                        name.includes(search)
                        ? "flex"
                        : "none";

                });

        }
    );

}


const photoInput =
    document.getElementById("photoInput");


const videoInput =
    document.getElementById("videoInput");


const fileInput =
    document.getElementById("fileInput");


const photoOption =
    document.getElementById("photoOption");


const videoOption =
    document.getElementById("videoOption");


const fileOption =
    document.getElementById("fileOption");


if (photoOption) {

    photoOption.addEventListener(
        "click",
        function() {
            photoInput.click();
        }
    );

}


if (videoOption) {

    videoOption.addEventListener(
        "click",
        function() {
            videoInput.click();
        }
    );

}


if (fileOption) {

    fileOption.addEventListener(
        "click",
        function() {
            fileInput.click();
        }
    );

}


async function uploadAttachment(input) {

    if (
        !input ||
        !input.files ||
        !input.files.length
    ) {
        return;
    }

    const selectedFile =
        input.files[0];

    const maxSize =
        25 * 1024 * 1024;

    if (selectedFile.size > maxSize) {

        alert(
            "Maximum file size is 25 MB."
        );

        input.value = "";

        return;
    }


    const fileName =
        document.getElementById(
            "selectedFileName"
        );

    if (fileName) {

        fileName.textContent =
            "Uploading: " +
            selectedFile.name;

    }


    const formData =
        new FormData();

    formData.append(
        "action",
        "send_attachment"
    );

    formData.append(
        "attachment",
        selectedFile
    );


    try {

        const response =
            await fetch(
                "messages.php?booking_id=" +
                bookingId,
                {
                    method: "POST",
                    body: formData
                }
            );


        if (!response.ok) {
            throw new Error(
                "Upload failed"
            );
        }


        window.location.href =
            "messages.php?booking_id=" +
            bookingId;


    } catch (error) {

        alert(
            "Could not upload the file. Please try again."
        );

        if (fileName) {
            fileName.textContent = "";
        }

    }


    input.value = "";
}


if (photoInput) {

    photoInput.addEventListener(
        "change",
        function() {
            uploadAttachment(this);
        }
    );

}


if (videoInput) {

    videoInput.addEventListener(
        "change",
        function() {
            uploadAttachment(this);
        }
    );

}


if (fileInput) {

    fileInput.addEventListener(
        "change",
        function() {
            uploadAttachment(this);
        }
    );

}


let mediaRecorder = null;

let audioChunks = [];

let recordedBlob = null;

let microphoneStream = null;


const recordedAudio =
    document.getElementById(
        "recordedAudio"
    );


const voicePreview =
    document.getElementById(
        "voicePreview"
    );


const cancelVoice =
    document.getElementById(
        "cancelVoice"
    );


const sendVoice =
    document.getElementById(
        "sendVoice"
    );


const recordingStatus =
    document.getElementById(
        "recordingStatus"
    );


if (voiceButton) {

    voiceButton.addEventListener(
        "click",
        async function(event) {

            event.stopPropagation();

            if (
                mediaRecorder &&
                mediaRecorder.state === "recording"
            ) {

                mediaRecorder.stop();

                return;
            }


            closePopovers();

            if (voicePopover) {
                voicePopover.style.display = "block";
            }


            if (
                !navigator.mediaDevices ||
                !navigator.mediaDevices.getUserMedia
            ) {

                alert(
                    "Your browser does not support microphone recording."
                );

                return;
            }


            try {

                microphoneStream =
                    await navigator.mediaDevices.getUserMedia(
                        {
                            audio: true
                        }
                    );


                audioChunks = [];

                recordedBlob = null;


                let options = {};


                if (
                    MediaRecorder.isTypeSupported(
                        "audio/webm;codecs=opus"
                    )
                ) {

                    options = {
                        mimeType:
                            "audio/webm;codecs=opus"
                    };

                } else if (
                    MediaRecorder.isTypeSupported(
                        "audio/webm"
                    )
                ) {

                    options = {
                        mimeType:
                            "audio/webm"
                    };

                }


                mediaRecorder =
                    new MediaRecorder(
                        microphoneStream,
                        options
                    );


                mediaRecorder.addEventListener(
                    "dataavailable",
                    function(event) {

                        if (
                            event.data &&
                            event.data.size > 0
                        ) {

                            audioChunks.push(
                                event.data
                            );

                        }

                    }
                );


                mediaRecorder.addEventListener(
                    "stop",
                    function() {

                        if (microphoneStream) {

                            microphoneStream
                                .getTracks()
                                .forEach(
                                    function(track) {
                                        track.stop();
                                    }
                                );

                        }


                        const mimeType =
                            mediaRecorder.mimeType ||
                            "audio/webm";


                        recordedBlob =
                            new Blob(
                                audioChunks,
                                {
                                    type: mimeType
                                }
                            );


                        if (recordedAudio) {

                            recordedAudio.src =
                                URL.createObjectURL(
                                    recordedBlob
                                );

                        }


                        if (voicePreview) {
                            voicePreview.style.display =
                                "flex";
                        }


                        if (recordingStatus) {
                            recordingStatus.classList.remove(
                                "active"
                            );
                        }

                    }
                );


                mediaRecorder.start();


                if (recordingStatus) {
                    recordingStatus.classList.add(
                        "active"
                    );
                }


            } catch (error) {

                if (voicePopover) {
                    voicePopover.style.display =
                        "none";
                }

                alert(
                    "Microphone permission was denied or microphone is unavailable."
                );

            }

        }
    );

}


if (cancelVoice) {

    cancelVoice.addEventListener(
        "click",
        function() {

            if (
                recordedAudio &&
                recordedAudio.src
            ) {

                URL.revokeObjectURL(
                    recordedAudio.src
                );

            }


            if (
                mediaRecorder &&
                mediaRecorder.state === "recording"
            ) {

                mediaRecorder.stop();

            }


            if (microphoneStream) {

                microphoneStream
                    .getTracks()
                    .forEach(
                        function(track) {
                            track.stop();
                        }
                    );

            }


            recordedAudio.src = "";

            recordedBlob = null;

            audioChunks = [];

            if (voicePreview) {
                voicePreview.style.display =
                    "none";
            }

            if (recordingStatus) {
                recordingStatus.classList.remove(
                    "active"
                );
            }

            if (voicePopover) {
                voicePopover.style.display =
                    "none";
            }

        }
    );

}


if (sendVoice) {

    sendVoice.addEventListener(
        "click",
        async function() {

            if (!recordedBlob) {

                alert(
                    "Please record a voice message first."
                );

                return;
            }


            sendVoice.disabled = true;

            sendVoice.innerHTML =
                "Sending...";


            const formData =
                new FormData();


            formData.append(
                "action",
                "send_voice"
            );


            formData.append(
                "voice",
                recordedBlob,
                "voice_message.webm"
            );


            try {

                const response =
                    await fetch(
                        "messages.php?booking_id=" +
                        bookingId,
                        {
                            method: "POST",
                            body: formData
                        }
                    );


                if (!response.ok) {

                    throw new Error(
                        "Upload failed"
                    );

                }


                window.location.href =
                    "messages.php?booking_id=" +
                    bookingId;


            } catch (error) {

                alert(
                    "Could not send voice message. Please try again."
                );


                sendVoice.disabled =
                    false;


                sendVoice.innerHTML =
                    "Send";

            }

        }
    );

}

</script>

</body>

</html>