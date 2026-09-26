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
    exit;
}

$provider_id = (int)$_SESSION["provider_id"];

$booking_id = isset($_GET["booking_id"])
    ? (int)$_GET["booking_id"]
    : (isset($_GET["id"]) ? (int)$_GET["id"] : 0);

$success = "";
$error = "";

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function messageTime($value)
{
    return $value ? date("h:i A", strtotime($value)) : "";
}

function messageDate($value)
{
    return $value ? date("d M Y", strtotime($value)) : "Not set";
}

function initial($name)
{
    $name = trim((string)$name);
    return $name === "" ? "C" : strtoupper(substr($name, 0, 1));
}

function getBooking($conn, $booking_id, $provider_id)
{
    if ($booking_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            b.*,
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,
            p.package_name,
            s.service_name,
            et.event_name
        FROM bookings b
        LEFT JOIN users u ON u.id = b.customer_id
        LEFT JOIN packages p ON p.id = b.package_id
        LEFT JOIN services s ON s.id = b.service_id
        LEFT JOIN event_types et ON et.id = b.event_type_id
        WHERE b.id = ?
        AND b.provider_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $booking_id, $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    $stmt->close();

    return $booking ?: null;
}

function notifyCustomer($conn, $customer_id, $title, $message)
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
        VALUES (?, 'customer', ?, ?, 'other', 0, NOW())
    ");

    if (!$stmt) {
        throw new Exception("Notification error.");
    }

    $stmt->bind_param(
        "iss",
        $customer_id,
        $title,
        $message
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Notification error.");
    }

    $stmt->close();
}

function attachmentCategory($mime)
{
    if (strpos($mime, "image/") === 0) {
        return "images";
    }

    if (strpos($mime, "video/") === 0) {
        return "videos";
    }

    if (strpos($mime, "audio/") === 0) {
        return "audio";
    }

    return "files";
}

function voiceExtension($mime)
{
    $map = [
        "audio/webm" => "webm",
        "video/webm" => "webm",
        "audio/ogg" => "ogg",
        "application/ogg" => "ogg",
        "audio/mp4" => "m4a",
        "audio/mpeg" => "mp3",
        "audio/wav" => "wav",
        "audio/x-wav" => "wav"
    ];

    return $map[$mime] ?? "webm";
}

function fileExtension($mime, $name)
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext !== "") {
        return preg_replace("/[^a-z0-9]/i", "", $ext);
    }

    $map = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/gif" => "gif",
        "image/webp" => "webp",
        "video/mp4" => "mp4",
        "video/webm" => "webm",
        "video/ogg" => "ogv",
        "video/quicktime" => "mov",
        "audio/mpeg" => "mp3",
        "audio/ogg" => "ogg",
        "audio/wav" => "wav",
        "application/pdf" => "pdf",
        "text/plain" => "txt"
    ];

    return $map[$mime] ?? "bin";
}

function previewText($message)
{
    if ($message === "[Voice message]") {
        return "🎤 Voice message";
    }

    if ($message === "[Image]") {
        return "🖼️ Photo";
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

    return $message ?: "No messages yet";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "send_voice") {

        header("Content-Type: application/json; charset=UTF-8");

        $post_booking_id = (int)($_POST["booking_id"] ?? 0);

        $booking = getBooking(
            $conn,
            $post_booking_id,
            $provider_id
        );

        if (!$booking) {
            echo json_encode([
                "success" => false,
                "message" => "Booking not found."
            ]);
            exit;
        }

        if (
            !isset($_FILES["voice_file"]) ||
            $_FILES["voice_file"]["error"] !== UPLOAD_ERR_OK
        ) {
            echo json_encode([
                "success" => false,
                "message" => "Voice recording was not received."
            ]);
            exit;
        }

        $voice = $_FILES["voice_file"];

        if ($voice["size"] > 10 * 1024 * 1024) {
            echo json_encode([
                "success" => false,
                "message" => "Voice message must be under 10 MB."
            ]);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($voice["tmp_name"]);

        $allowed = [
            "audio/webm",
            "video/webm",
            "audio/ogg",
            "application/ogg",
            "audio/mp4",
            "audio/mpeg",
            "audio/wav",
            "audio/x-wav"
        ];

        if (!in_array($mime, $allowed, true)) {
            echo json_encode([
                "success" => false,
                "message" => "Unsupported voice format."
            ]);
            exit;
        }

        $folder = __DIR__ . "/../uploads/messages/audio/";

        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $filename =
            date("YmdHis") .
            "_" .
            bin2hex(random_bytes(12)) .
            "." .
            voiceExtension($mime);

        $target = $folder . $filename;

        $relative =
            "../uploads/messages/audio/" . $filename;

        if (!move_uploaded_file($voice["tmp_name"], $target)) {
            echo json_encode([
                "success" => false,
                "message" => "Could not save voice message."
            ]);
            exit;
        }

        try {

            $conn->begin_transaction();

            $customer_id = (int)$booking["customer_id"];

            $text = "[Voice message]";

            $stmt = $conn->prepare("
                INSERT INTO messages
                (
                    booking_id,
                    sender_id,
                    sender_role,
                    receiver_id,
                    receiver_role,
                    message,
                    is_read,
                    created_at
                )
                VALUES (?, ?, 'provider', ?, 'customer', ?, 0, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Message error.");
            }

            $stmt->bind_param(
                "iiis",
                $post_booking_id,
                $provider_id,
                $customer_id,
                $text
            );

            if (!$stmt->execute()) {
                throw new Exception("Message error.");
            }

            $message_id = (int)$stmt->insert_id;

            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO message_attachments
                (
                    message_id,
                    file_name,
                    file_path,
                    file_type,
                    file_size,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Attachment error.");
            }

            $original_name = $voice["name"];
            $size = (int)$voice["size"];

            $stmt->bind_param(
                "isssi",
                $message_id,
                $original_name,
                $relative,
                $mime,
                $size
            );

            if (!$stmt->execute()) {
                throw new Exception("Attachment error.");
            }

            $stmt->close();

            notifyCustomer(
                $conn,
                $customer_id,
                "New Voice Message",
                "You received a voice message from the provider."
            );

            $conn->commit();

            echo json_encode([
                "success" => true
            ]);
            exit;

        } catch (Throwable $ex) {

            $conn->rollback();

            if (file_exists($target)) {
                unlink($target);
            }

            echo json_encode([
                "success" => false,
                "message" => $ex->getMessage()
            ]);
            exit;
        }
    }

    if ($action === "send_attachment") {

        header("Content-Type: application/json; charset=UTF-8");

        $post_booking_id = (int)($_POST["booking_id"] ?? 0);

        $booking = getBooking(
            $conn,
            $post_booking_id,
            $provider_id
        );

        if (!$booking) {
            echo json_encode([
                "success" => false,
                "message" => "Booking not found."
            ]);
            exit;
        }

        if (
            !isset($_FILES["attachment"]) ||
            $_FILES["attachment"]["error"] !== UPLOAD_ERR_OK
        ) {
            echo json_encode([
                "success" => false,
                "message" => "Please select a file."
            ]);
            exit;
        }

        $upload = $_FILES["attachment"];

        if ($upload["size"] > 25 * 1024 * 1024) {
            echo json_encode([
                "success" => false,
                "message" => "File must be under 25 MB."
            ]);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload["tmp_name"]);

        $allowed = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
            "video/mp4",
            "video/webm",
            "video/ogg",
            "video/quicktime",
            "audio/mpeg",
            "audio/ogg",
            "audio/wav",
            "audio/x-wav",
            "audio/webm",
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "text/plain",
            "application/zip",
            "application/x-zip-compressed"
        ];

        if (!in_array($mime, $allowed, true)) {
            echo json_encode([
                "success" => false,
                "message" => "This file type is not allowed."
            ]);
            exit;
        }

        $category = attachmentCategory($mime);

        $folder =
            __DIR__ .
            "/../uploads/messages/" .
            $category .
            "/";

        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $filename =
            date("YmdHis") .
            "_" .
            bin2hex(random_bytes(12)) .
            "." .
            fileExtension(
                $mime,
                $upload["name"]
            );

        $target = $folder . $filename;

        $relative =
            "../uploads/messages/" .
            $category .
            "/" .
            $filename;

        if (!move_uploaded_file($upload["tmp_name"], $target)) {
            echo json_encode([
                "success" => false,
                "message" => "Could not upload file."
            ]);
            exit;
        }

        try {

            $conn->begin_transaction();

            $customer_id = (int)$booking["customer_id"];

            if (strpos($mime, "image/") === 0) {

                $text = "[Image]";
                $title = "New Photo";
                $notice = "You received a photo from the provider.";

            } elseif (strpos($mime, "video/") === 0) {

                $text = "[Video]";
                $title = "New Video";
                $notice = "You received a video from the provider.";

            } elseif (strpos($mime, "audio/") === 0) {

                $text = "[Audio]";
                $title = "New Audio";
                $notice = "You received an audio file from the provider.";

            } else {

                $text = "[File]";
                $title = "New File";
                $notice = "You received a file from the provider.";
            }

            $stmt = $conn->prepare("
                INSERT INTO messages
                (
                    booking_id,
                    sender_id,
                    sender_role,
                    receiver_id,
                    receiver_role,
                    message,
                    is_read,
                    created_at
                )
                VALUES (?, ?, 'provider', ?, 'customer', ?, 0, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Message error.");
            }

            $stmt->bind_param(
                "iiis",
                $post_booking_id,
                $provider_id,
                $customer_id,
                $text
            );

            if (!$stmt->execute()) {
                throw new Exception("Message error.");
            }

            $message_id = (int)$stmt->insert_id;

            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO message_attachments
                (
                    message_id,
                    file_name,
                    file_path,
                    file_type,
                    file_size,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Attachment error.");
            }

            $original_name = $upload["name"];
            $size = (int)$upload["size"];

            $stmt->bind_param(
                "isssi",
                $message_id,
                $original_name,
                $relative,
                $mime,
                $size
            );

            if (!$stmt->execute()) {
                throw new Exception("Attachment error.");
            }

            $stmt->close();

            notifyCustomer(
                $conn,
                $customer_id,
                $title,
                $notice
            );

            $conn->commit();

            echo json_encode([
                "success" => true
            ]);
            exit;

        } catch (Throwable $ex) {

            $conn->rollback();

            if (file_exists($target)) {
                unlink($target);
            }

            echo json_encode([
                "success" => false,
                "message" => $ex->getMessage()
            ]);
            exit;
        }
    }

    if ($action === "") {

        $post_booking_id =
            (int)($_POST["booking_id"] ?? 0);

        $message =
            trim($_POST["message"] ?? "");

        if ($post_booking_id <= 0) {

            $error = "Invalid booking.";

        } elseif ($message === "") {

            $error = "Please enter a message.";

        } else {

            $booking = getBooking(
                $conn,
                $post_booking_id,
                $provider_id
            );

            if (!$booking) {

                $error = "Booking not found.";

            } else {

                $customer_id =
                    (int)$booking["customer_id"];

                $stmt = $conn->prepare("
                    INSERT INTO messages
                    (
                        booking_id,
                        sender_id,
                        sender_role,
                        receiver_id,
                        receiver_role,
                        message,
                        is_read,
                        created_at
                    )
                    VALUES (?, ?, 'provider', ?, 'customer', ?, 0, NOW())
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        "iiis",
                        $post_booking_id,
                        $provider_id,
                        $customer_id,
                        $message
                    );

                    if ($stmt->execute()) {

                        try {

                            notifyCustomer(
                                $conn,
                                $customer_id,
                                "New Message",
                                $message
                            );

                        } catch (Throwable $ex) {
                        }

                        $success = "Message sent.";

                    } else {

                        $error = "Message could not be sent.";
                    }

                    $stmt->close();

                } else {

                    $error = "Message system error.";
                }
            }
        }

        if ($post_booking_id > 0) {
            $booking_id = $post_booking_id;
        }
    }
}

$provider_name = "Provider";

$stmt = $conn->prepare("
    SELECT name
    FROM providers
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $provider_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $provider = $result->fetch_assoc();

    if ($provider && $provider["name"] !== "") {
        $provider_name = $provider["name"];
    }

    $stmt->close();
}

$conversations = [];

$stmt = $conn->prepare("
    SELECT
        b.id AS booking_id,
        b.booking_date,
        b.status,
        b.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        (
            SELECT m.message
            FROM messages m
            WHERE m.booking_id = b.id
            ORDER BY m.id DESC
            LIMIT 1
        ) AS last_message,
        (
            SELECT m.created_at
            FROM messages m
            WHERE m.booking_id = b.id
            ORDER BY m.id DESC
            LIMIT 1
        ) AS last_message_time,
        (
            SELECT COUNT(*)
            FROM messages um
            WHERE um.booking_id = b.id
            AND um.receiver_id = ?
            AND um.receiver_role = 'provider'
            AND um.sender_role = 'customer'
            AND um.is_read = 0
        ) AS unread_count
    FROM bookings b
    LEFT JOIN users u ON u.id = b.customer_id
    WHERE b.provider_id = ?
    ORDER BY
        COALESCE(last_message_time, b.created_at) DESC,
        b.id DESC
");

if ($stmt) {

    $stmt->bind_param(
        "ii",
        $provider_id,
        $provider_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }

    $stmt->close();
}

if ($booking_id <= 0 && !empty($conversations)) {
    $booking_id =
        (int)$conversations[0]["booking_id"];
}

$selected_booking = getBooking(
    $conn,
    $booking_id,
    $provider_id
);

if (!$selected_booking && !empty($conversations)) {

    $booking_id =
        (int)$conversations[0]["booking_id"];

    $selected_booking = getBooking(
        $conn,
        $booking_id,
        $provider_id
    );
}

if ($selected_booking) {

    $stmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE booking_id = ?
        AND receiver_id = ?
        AND receiver_role = 'provider'
        AND sender_role = 'customer'
    ");

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $booking_id,
            $provider_id
        );

        $stmt->execute();
        $stmt->close();
    }
}

$messages = [];

if ($selected_booking) {

    $stmt = $conn->prepare("
        SELECT
            m.id,
            m.sender_id,
            m.sender_role,
            m.receiver_id,
            m.receiver_role,
            m.message,
            m.is_read,
            m.created_at,
            a.id AS attachment_id,
            a.file_name,
            a.file_path,
            a.file_type,
            a.file_size
        FROM messages m
        LEFT JOIN message_attachments a
            ON a.message_id = m.id
        WHERE m.booking_id = ?
        ORDER BY m.id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $booking_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        $stmt->close();
    }
}

$customer_name =
    $selected_booking["customer_name"] ?? "Customer";

$customer_email =
    $selected_booking["customer_email"] ?? "";

$customer_phone =
    $selected_booking["customer_phone"] ?? "";

$customer_initial =
    initial($customer_name);

$tel_phone =
    preg_replace(
        "/[^0-9+]/",
        "",
        $customer_phone
    );

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Messages</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    height: 100%;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f5f7;
    color: #222;
    overflow: hidden;
}

a {
    text-decoration: none;
    color: inherit;
}

.topbar {
    height: 58px;
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    display: flex;
    align-items: center;
    padding: 0 20px;
    font-size: 19px;
    font-weight: 700;
}

.topbar a {
    margin-left: auto;
    font-size: 13px;
    font-weight: 600;
    color: #666;
}

.page {
    height: calc(100vh - 58px);
    display: flex;
    padding: 12px;
    gap: 12px;
}

.sidebar {
    width: 285px;
    min-width: 285px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.sidebar-title {
    padding: 14px 16px 9px;
    font-size: 18px;
    font-weight: 700;
}

.search-box {
    padding: 8px 12px 11px;
}

.search-box input {
    width: 100%;
    height: 36px;
    border: 1px solid #ddd;
    border-radius: 18px;
    padding: 0 13px;
    outline: none;
    font-size: 12px;
    background: #f7f7f8;
}

.conversations {
    flex: 1;
    overflow-y: auto;
}

.conversation {
    min-height: 66px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-top: 1px solid #f1f1f1;
}

.conversation:hover {
    background: #faf3f6;
}

.conversation.active {
    background: #f8eaf0;
}

.avatar {
    width: 39px;
    height: 39px;
    min-width: 39px;
    border-radius: 50%;
    background: linear-gradient(135deg, #efabc5, #df7fa5);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.conversation-content {
    min-width: 0;
    flex: 1;
}

.conversation-top {
    display: flex;
    justify-content: space-between;
    gap: 5px;
}

.conversation-name {
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-time {
    font-size: 9px;
    color: #999;
    white-space: nowrap;
}

.conversation-sub {
    font-size: 9px;
    color: #999;
    margin-top: 2px;
}

.conversation-message {
    font-size: 11px;
    color: #777;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 3px;
}

.unread {
    width: 18px;
    height: 18px;
    min-width: 18px;
    border-radius: 50%;
    background: #e9799f;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
}

.chat {
    flex: 1;
    min-width: 0;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.chat-header {
    height: 66px;
    min-height: 66px;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    gap: 9px;
    border-bottom: 1px solid #ededed;
}

.chat-header .avatar {
    width: 42px;
    height: 42px;
    min-width: 42px;
}

.chat-header-info {
    min-width: 0;
    flex: 1;
}

.chat-header-name {
    font-size: 15px;
    font-weight: 700;
}

.chat-header-email {
    font-size: 10px;
    color: #999;
    margin-top: 2px;
}

.chat-header-phone {
    font-size: 10px;
    color: #777;
}

.chat-actions {
    display: flex;
    gap: 5px;
}

.call-btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 50%;
    background: #f7f2f4;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.call-btn:hover {
    background: #f1dce5;
}

.booking-info {
    min-height: 44px;
    padding: 6px 12px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
}

.booking-card {
    padding: 5px 9px;
    background: #f8f8f8;
    border-radius: 7px;
    white-space: nowrap;
}

.booking-card span {
    font-size: 7px;
    color: #aaa;
    text-transform: uppercase;
}

.booking-card strong {
    margin-left: 3px;
    font-size: 10px;
}

.alert {
    padding: 7px 12px;
    margin: 5px 12px;
    border-radius: 7px;
    font-size: 11px;
}

.alert.success {
    background: #eaf8ef;
    color: #267442;
}

.alert.error {
    background: #fff0f0;
    color: #a72c2c;
}

.messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 18px;
    background: #fafbfc;
}

.message-row {
    display: flex;
    margin-bottom: 8px;
}

.message-row.mine {
    justify-content: flex-end;
}

.message-bubble {
    max-width: 65%;
    min-width: 45px;
    padding: 7px 10px 5px;
    border-radius: 13px;
    background: #e9e9eb;
    font-size: 13px;
    line-height: 1.35;
    word-break: break-word;
}

.message-row.mine .message-bubble {
    background: #f4bfd3;
    border-bottom-right-radius: 4px;
}

.message-row:not(.mine) .message-bubble {
    border-bottom-left-radius: 4px;
}

.message-time {
    margin-top: 3px;
    text-align: right;
    font-size: 8px;
    color: #777;
}

.message-status {
    margin-left: 3px;
}

.empty-chat,
.no-booking {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #aaa;
    font-size: 13px;
}

.attachment-image {
    display: block;
    max-width: 240px;
    max-height: 230px;
    border-radius: 10px;
}

.attachment-video {
    display: block;
    width: 270px;
    max-width: 100%;
    border-radius: 10px;
}

.attachment-audio {
    width: 245px;
    max-width: 100%;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(255,255,255,.6);
    font-size: 11px;
    font-weight: 600;
}

.file-size {
    margin-top: 3px;
    font-size: 8px;
    color: #777;
}

.composer {
    position: relative;
    padding: 7px 10px 9px;
    background: #fff;
    border-top: 1px solid #eee;
}

.composer-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.tool-btn {
    width: 34px;
    height: 34px;
    min-width: 34px;
    border: 0;
    background: transparent;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.tool-btn:hover {
    background: #f4edf0;
}

.message-form {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.message-input {
    flex: 1;
    min-width: 0;
    height: 38px;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 0 14px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    background: #f8f8f9;
}

.message-input:focus {
    border-color: #e5a1bb;
    background: #fff;
}

.send-btn {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border: 0;
    border-radius: 50%;
    cursor: pointer;
    background: linear-gradient(135deg, #ef9fbd, #dc6f99);
    color: #fff;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.send-btn:hover {
    transform: scale(1.05);
}

.emoji-picker {
    display: none;
    position: absolute;
    bottom: 57px;
    left: 8px;
    width: 285px;
    max-height: 250px;
    overflow-y: auto;
    padding: 9px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 13px;
    box-shadow: 0 7px 25px rgba(0,0,0,.14);
    z-index: 100;
}

.emoji-picker.show {
    display: block;
}

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 2px;
}

.emoji-item {
    border: 0;
    background: transparent;
    cursor: pointer;
    font-size: 20px;
    padding: 5px;
    border-radius: 7px;
}

.emoji-item:hover {
    background: #f6edf1;
}

.attachment-panel,
.voice-panel {
    display: none;
    margin-bottom: 7px;
    padding: 9px 11px;
    border-radius: 10px;
    background: #faf7f8;
    border: 1px solid #eee;
}

.attachment-panel.show,
.voice-panel.show {
    display: block;
}

.panel-title {
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 6px;
}

.preview-area {
    font-size: 11px;
}

.preview-area img {
    max-width: 140px;
    max-height: 100px;
    border-radius: 8px;
}

.preview-area video {
    max-width: 200px;
    max-height: 110px;
    border-radius: 8px;
}

.preview-area audio {
    width: 240px;
    max-width: 100%;
}

.panel-actions {
    display: flex;
    gap: 5px;
    margin-top: 7px;
}

.small-btn {
    border: 0;
    border-radius: 7px;
    padding: 6px 10px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.send-small {
    background: #df7d9f;
    color: #fff;
}

.cancel-small {
    background: #e9e9e9;
    color: #555;
}

.recording-status {
    font-size: 10px;
    font-weight: 700;
    color: #d74e7d;
}

.recording-timer {
    font-size: 16px;
    font-weight: 700;
    margin: 3px 0;
}

.hidden-file {
    display: none;
}

@media (max-width: 800px) {

    .sidebar {
        width: 245px;
        min-width: 245px;
    }

    .message-bubble {
        max-width: 78%;
    }

}

@media (max-width: 600px) {

    body {
        overflow: auto;
    }

    .page {
        height: calc(100vh - 58px);
    }

    .sidebar {
        width: 100%;
        min-width: 0;
    }

    .chat {
        display: none;
    }

}

</style>

</head>

<body>

<div class="topbar">

    💬 Messages

    <a href="dashboard.php">
        Dashboard
    </a>

</div>

<div class="page">

    <aside class="sidebar">

        <div class="sidebar-title">
            Messages
        </div>

        <div class="search-box">

            <input
                type="text"
                id="conversationSearch"
                placeholder="Search customer..."
            >

        </div>

        <div class="conversations">

            <?php if (empty($conversations)): ?>

                <div style="padding:18px;color:#999;font-size:12px;">
                    No conversations yet.
                </div>

            <?php else: ?>

                <?php foreach ($conversations as $conversation): ?>

                    <?php

                    $cid =
                        (int)$conversation["booking_id"];

                    $active =
                        $cid === (int)$booking_id
                            ? "active"
                            : "";

                    $name =
                        $conversation["customer_name"]
                            ?: "Customer";

                    ?>

                    <a
                        href="messages.php?booking_id=<?= $cid ?>"
                        class="conversation <?= $active ?>"
                        data-name="<?= e($name) ?>"
                    >

                        <div class="avatar">
                            <?= e(initial($name)) ?>
                        </div>

                        <div class="conversation-content">

                            <div class="conversation-top">

                                <div class="conversation-name">
                                    <?= e($name) ?>
                                </div>

                                <?php if ($conversation["last_message_time"]): ?>

                                    <div class="conversation-time">
                                        <?= e(messageTime($conversation["last_message_time"])) ?>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="conversation-sub">
                                Booking #<?= $cid ?>
                            </div>

                            <div class="conversation-message">
                                <?= e(previewText($conversation["last_message"])) ?>
                            </div>

                        </div>

                        <?php if ((int)$conversation["unread_count"] > 0): ?>

                            <div class="unread">
                                <?= (int)$conversation["unread_count"] ?>
                            </div>

                        <?php endif; ?>

                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </aside>

    <main class="chat">

        <?php if (!$selected_booking): ?>

            <div class="no-booking">
                Select a conversation.
            </div>

        <?php else: ?>

            <div class="chat-header">

                <div class="avatar">
                    <?= e($customer_initial) ?>
                </div>

                <div class="chat-header-info">

                    <div class="chat-header-name">
                        <?= e($customer_name) ?>
                    </div>

                    <?php if ($customer_email !== ""): ?>

                        <div class="chat-header-email">
                            <?= e($customer_email) ?>
                        </div>

                    <?php endif; ?>

                    <?php if ($customer_phone !== ""): ?>

                        <div class="chat-header-phone">
                            📱 <?= e($customer_phone) ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="booking-info">

                <div class="booking-card">
                    <span>Booking</span>
                    <strong>#<?= (int)$selected_booking["id"] ?></strong>
                </div>

                <div class="booking-card">
                    <span>Event</span>
                    <strong>
                        <?= e($selected_booking["event_name"] ?: "Not set") ?>
                    </strong>
                </div>

                <div class="booking-card">
                    <span>Service</span>
                    <strong>
                        <?= e($selected_booking["service_name"] ?: "Not set") ?>
                    </strong>
                </div>

                <div class="booking-card">
                    <span>Package</span>
                    <strong>
                        <?= e($selected_booking["package_name"] ?: "Not set") ?>
                    </strong>
                </div>

                <div class="booking-card">
                    <span>Date</span>
                    <strong>
                        <?= e(messageDate($selected_booking["booking_date"])) ?>
                    </strong>
                </div>

                <div class="booking-card">
                    <span>Status</span>
                    <strong>
                        <?= e(ucfirst($selected_booking["status"] ?: "Pending")) ?>
                    </strong>
                </div>

            </div>

            <?php if ($success !== ""): ?>

                <div
                    class="alert success"
                    id="successAlert"
                >
                    <?= e($success) ?>
                </div>

            <?php endif; ?>

            <?php if ($error !== ""): ?>

                <div
                    class="alert error"
                    id="errorAlert"
                >
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <div
                class="messages"
                id="messagesBox"
            >

                <?php if (empty($messages)): ?>

                    <div class="empty-chat">
                        No messages yet.
                    </div>

                <?php else: ?>

                    <?php foreach ($messages as $msg): ?>

                        <?php

                        $mine =
                            $msg["sender_role"] === "provider";

                        $mime =
                            (string)($msg["file_type"] ?? "");

                        $path =
                            (string)($msg["file_path"] ?? "");

                        $filename =
                            (string)($msg["file_name"] ?? "");

                        $filesize =
                            (int)($msg["file_size"] ?? 0);

                        ?>

                        <div
                            class="message-row <?= $mine ? "mine" : "" ?>"
                        >

                            <div class="message-bubble">

                                <?php if (!empty($msg["attachment_id"])): ?>

                                    <?php if (strpos($mime, "image/") === 0): ?>

                                        <a
                                            href="<?= e($path) ?>"
                                            target="_blank"
                                        >

                                            <img
                                                src="<?= e($path) ?>"
                                                class="attachment-image"
                                                alt="<?= e($filename) ?>"
                                            >

                                        </a>

                                    <?php elseif (strpos($mime, "video/") === 0): ?>

                                        <video
                                            class="attachment-video"
                                            controls
                                            preload="metadata"
                                        >

                                            <source
                                                src="<?= e($path) ?>"
                                                type="<?= e($mime) ?>"
                                            >

                                        </video>

                                        <div style="font-size:9px;margin-top:3px;">
                                            🎥 <?= e($filename) ?>
                                        </div>

                                    <?php elseif (
                                        strpos($mime, "audio/") === 0 ||
                                        $mime === "application/ogg" ||
                                        $mime === "video/webm"
                                    ): ?>

                                        <div
                                            style="font-size:9px;font-weight:700;margin-bottom:3px;"
                                        >
                                            🎤 Voice message
                                        </div>

                                        <audio
                                            class="attachment-audio"
                                            controls
                                            preload="metadata"
                                        >

                                            <source
                                                src="<?= e($path) ?>"
                                                type="<?= e($mime) ?>"
                                            >

                                        </audio>

                                    <?php else: ?>

                                        <a
                                            href="<?= e($path) ?>"
                                            target="_blank"
                                            download
                                            class="file-link"
                                        >
                                            📎 <?= e($filename ?: "File") ?>
                                        </a>

                                        <?php if ($filesize > 0): ?>

                                            <div class="file-size">
                                                <?= e(number_format($filesize / 1024, 1)) ?> KB
                                            </div>

                                        <?php endif; ?>

                                    <?php endif; ?>

                                <?php elseif ($msg["message"] !== ""): ?>

                                    <?= nl2br(e($msg["message"])) ?>

                                <?php endif; ?>

                                <div class="message-time">

                                    <?= e(messageTime($msg["created_at"])) ?>

                                    <?php if ($mine): ?>

                                        <span class="message-status">
                                            <?= (int)$msg["is_read"] === 1
                                                ? "Seen ✓✓"
                                                : "Sent ✓" ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <div class="composer">

                <div
                    class="emoji-picker"
                    id="emojiPicker"
                >

                    <div class="emoji-grid">

                        <?php

                        $emojis = [
                            "😀","😃","😄","😁","😆","😅","😂","🤣",
                            "😊","🙂","🙃","😉","😌","😍","🥰","😘",
                            "😗","😙","😚","😋","😛","😝","😜","🤪",
                            "🤩","🥳","😎","🤗","🥹","😭","😢","😡",
                            "😱","😴","🤔","🙄","😇","❤️","🩷","🧡",
                            "💛","💚","💙","💜","🖤","🤍","🤎","💗",
                            "💖","💘","💕","💞","💓","💝","💟","💯",
                            "👍","👎","👏","🙏","🙌","👌","🤝","✨",
                            "🔥","🎉","🎊","🌸","🌹","💐","🥰","😍"
                        ];

                        ?>

                        <?php foreach ($emojis as $emoji): ?>

                            <button
                                type="button"
                                class="emoji-item"
                                data-emoji="<?= e($emoji) ?>"
                            >
                                <?= $emoji ?>
                            </button>

                        <?php endforeach; ?>

                    </div>

                </div>

                <div
                    class="attachment-panel"
                    id="attachmentPanel"
                >

                    <div class="panel-title">
                        📎 Photo / Video / File
                    </div>

                    <div
                        class="preview-area"
                        id="attachmentPreview"
                    ></div>

                    <div class="panel-actions">

                        <button
                            type="button"
                            class="small-btn send-small"
                            id="sendAttachmentBtn"
                        >
                            Send
                        </button>

                        <button
                            type="button"
                            class="small-btn cancel-small"
                            id="cancelAttachmentBtn"
                        >
                            Cancel
                        </button>

                    </div>

                </div>

                <div
                    class="voice-panel"
                    id="voicePanel"
                >

                    <div class="panel-title">
                        🎤 Voice message
                    </div>

                    <div
                        class="recording-status"
                        id="recordingStatus"
                    >
                        Ready
                    </div>

                    <div
                        class="recording-timer"
                        id="recordingTimer"
                    >
                        00:00
                    </div>

                    <div
                        class="preview-area"
                        id="voicePreview"
                    ></div>

                    <div class="panel-actions">

                        <button
                            type="button"
                            class="small-btn send-small"
                            id="voiceRecordBtn"
                        >
                            🎤 Record
                        </button>

                        <button
                            type="button"
                            class="small-btn send-small"
                            id="sendVoiceBtn"
                            style="display:none;"
                        >
                            Send Voice
                        </button>

                        <button
                            type="button"
                            class="small-btn cancel-small"
                            id="cancelVoiceBtn"
                        >
                            Cancel
                        </button>

                    </div>

                </div>

                <div class="composer-row">

                    <button
                        type="button"
                        class="tool-btn"
                        id="emojiBtn"
                        title="Emoji"
                    >
                        😊
                    </button>

                    <button
                        type="button"
                        class="tool-btn"
                        id="voiceBtn"
                        title="Voice message"
                    >
                        🎤
                    </button>

                    <button
                        type="button"
                        class="tool-btn"
                        id="attachmentBtn"
                        title="Photo, video or file"
                    >
                        📎
                    </button>

                    <input
                        type="file"
                        id="attachmentInput"
                        class="hidden-file"
                        accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                    >

                    <form
                        method="POST"
                        class="message-form"
                        id="messageForm"
                    >

                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?= (int)$selected_booking["id"] ?>"
                        >

                        <input
                            type="text"
                            name="message"
                            id="messageInput"
                            class="message-input"
                            placeholder="Write a message..."
                            autocomplete="off"
                        >

                        <button
                            type="submit"
                            class="send-btn"
                            title="Send"
                        >
                            ➤
                        </button>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<script>

const bookingId =
    <?= $selected_booking ? (int)$selected_booking["id"] : 0 ?>;

const emojiBtn =
    document.getElementById("emojiBtn");

const emojiPicker =
    document.getElementById("emojiPicker");

const messageInput =
    document.getElementById("messageInput");

const attachmentBtn =
    document.getElementById("attachmentBtn");

const attachmentInput =
    document.getElementById("attachmentInput");

const attachmentPanel =
    document.getElementById("attachmentPanel");

const attachmentPreview =
    document.getElementById("attachmentPreview");

const sendAttachmentBtn =
    document.getElementById("sendAttachmentBtn");

const cancelAttachmentBtn =
    document.getElementById("cancelAttachmentBtn");

const voiceBtn =
    document.getElementById("voiceBtn");

const voicePanel =
    document.getElementById("voicePanel");

const voiceRecordBtn =
    document.getElementById("voiceRecordBtn");

const sendVoiceBtn =
    document.getElementById("sendVoiceBtn");

const cancelVoiceBtn =
    document.getElementById("cancelVoiceBtn");

const recordingStatus =
    document.getElementById("recordingStatus");

const recordingTimer =
    document.getElementById("recordingTimer");

const voicePreview =
    document.getElementById("voicePreview");

const messagesBox =
    document.getElementById("messagesBox");

let selectedAttachment = null;

let mediaRecorder = null;

let audioChunks = [];

let audioStream = null;

let voiceBlob = null;

let timerInterval = null;

let recordingSeconds = 0;

if (emojiBtn) {

    emojiBtn.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            emojiPicker.classList.toggle(
                "show"
            );

        }
    );

}

document
    .querySelectorAll(".emoji-item")
    .forEach(
        function(button) {

            button.addEventListener(
                "click",
                function() {

                    if (!messageInput) {
                        return;
                    }

                    const emoji =
                        button.getAttribute(
                            "data-emoji"
                        );

                    messageInput.focus();

                    const start =
                        messageInput.selectionStart;

                    const end =
                        messageInput.selectionEnd;

                    const value =
                        messageInput.value;

                    messageInput.value =
                        value.substring(0, start) +
                        emoji +
                        value.substring(end);

                    const position =
                        start + emoji.length;

                    messageInput.selectionStart =
                        position;

                    messageInput.selectionEnd =
                        position;

                    emojiPicker.classList.remove(
                        "show"
                    );

                }
            );

        }
    );

document.addEventListener(
    "click",
    function(event) {

        if (
            emojiPicker &&
            emojiBtn &&
            !emojiPicker.contains(event.target) &&
            !emojiBtn.contains(event.target)
        ) {

            emojiPicker.classList.remove(
                "show"
            );

        }

    }
);

if (attachmentBtn) {

    attachmentBtn.addEventListener(
        "click",
        function() {

            voicePanel.classList.remove(
                "show"
            );

            attachmentInput.click();

        }
    );

}

if (attachmentInput) {

    attachmentInput.addEventListener(
        "change",
        function() {

            const file =
                attachmentInput.files[0];

            if (!file) {
                return;
            }

            selectedAttachment = file;

            attachmentPanel.classList.add(
                "show"
            );

            attachmentPreview.innerHTML = "";

            if (
                file.type.startsWith("image/")
            ) {

                const img =
                    document.createElement(
                        "img"
                    );

                img.src =
                    URL.createObjectURL(file);

                attachmentPreview.appendChild(
                    img
                );

            } else if (
                file.type.startsWith("video/")
            ) {

                const video =
                    document.createElement(
                        "video"
                    );

                video.src =
                    URL.createObjectURL(file);

                video.controls = true;

                attachmentPreview.appendChild(
                    video
                );

            } else {

                const div =
                    document.createElement(
                        "div"
                    );

                div.textContent =
                    "📎 " +
                    file.name +
                    " (" +
                    formatSize(file.size) +
                    ")";

                attachmentPreview.appendChild(
                    div
                );

            }

        }
    );

}

if (cancelAttachmentBtn) {

    cancelAttachmentBtn.addEventListener(
        "click",
        function() {

            selectedAttachment = null;

            attachmentInput.value = "";

            attachmentPreview.innerHTML = "";

            attachmentPanel.classList.remove(
                "show"
            );

        }
    );

}

if (sendAttachmentBtn) {

    sendAttachmentBtn.addEventListener(
        "click",
        async function() {

            if (!selectedAttachment) {

                alert(
                    "Please select a file."
                );

                return;
            }

            sendAttachmentBtn.disabled =
                true;

            sendAttachmentBtn.textContent =
                "Sending...";

            const formData =
                new FormData();

            formData.append(
                "action",
                "send_attachment"
            );

            formData.append(
                "booking_id",
                bookingId
            );

            formData.append(
                "attachment",
                selectedAttachment
            );

            try {

                const response =
                    await fetch(
                        "messages.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );

                const data =
                    await response.json();

                if (data.success) {

                    window.location.href =
                        "messages.php?booking_id=" +
                        bookingId;

                } else {

                    alert(
                        data.message ||
                        "File could not be sent."
                    );

                }

            } catch (error) {

                alert(
                    "Upload error."
                );

            } finally {

                sendAttachmentBtn.disabled =
                    false;

                sendAttachmentBtn.textContent =
                    "Send";

            }

        }
    );

}

if (voiceBtn) {

    voiceBtn.addEventListener(
        "click",
        function() {

            attachmentPanel.classList.remove(
                "show"
            );

            voicePanel.classList.toggle(
                "show"
            );

        }
    );

}

if (voiceRecordBtn) {

    voiceRecordBtn.addEventListener(
        "click",
        async function() {

            if (
                mediaRecorder &&
                mediaRecorder.state === "recording"
            ) {

                mediaRecorder.stop();

                return;
            }

            try {

                audioStream =
                    await navigator.mediaDevices
                        .getUserMedia({
                            audio: true
                        });

                audioChunks = [];

                voiceBlob = null;

                const types = [
                    "audio/webm;codecs=opus",
                    "audio/webm",
                    "audio/ogg;codecs=opus"
                ];

                let mimeType = "";

                for (
                    const type of types
                ) {

                    if (
                        MediaRecorder.isTypeSupported &&
                        MediaRecorder.isTypeSupported(type)
                    ) {

                        mimeType = type;

                        break;
                    }

                }

                if (mimeType) {

                    mediaRecorder =
                        new MediaRecorder(
                            audioStream,
                            {
                                mimeType:
                                    mimeType
                            }
                        );

                } else {

                    mediaRecorder =
                        new MediaRecorder(
                            audioStream
                        );

                }

                mediaRecorder.ondataavailable =
                    function(event) {

                        if (
                            event.data.size > 0
                        ) {

                            audioChunks.push(
                                event.data
                            );

                        }

                    };

                mediaRecorder.onstop =
                    function() {

                        const type =
                            mediaRecorder.mimeType ||
                            "audio/webm";

                        voiceBlob =
                            new Blob(
                                audioChunks,
                                {
                                    type: type
                                }
                            );

                        const url =
                            URL.createObjectURL(
                                voiceBlob
                            );

                        voicePreview.innerHTML =
                            "";

                        const audio =
                            document.createElement(
                                "audio"
                            );

                        audio.controls = true;

                        audio.src = url;

                        audio.className =
                            "attachment-audio";

                        voicePreview.appendChild(
                            audio
                        );

                        voiceRecordBtn.style.display =
                            "none";

                        sendVoiceBtn.style.display =
                            "inline-block";

                        recordingStatus.textContent =
                            "Recording ready";

                        stopTimer();

                        if (audioStream) {

                            audioStream
                                .getTracks()
                                .forEach(
                                    function(track) {
                                        track.stop();
                                    }
                                );

                        }

                    };

                mediaRecorder.start();

                recordingSeconds = 0;

                recordingTimer.textContent =
                    "00:00";

                recordingStatus.textContent =
                    "🔴 Recording...";

                voiceRecordBtn.textContent =
                    "⏹ Stop";

                startTimer();

            } catch (error) {

                alert(
                    "Please allow microphone permission."
                );

            }

        }
    );

}

if (sendVoiceBtn) {

    sendVoiceBtn.addEventListener(
        "click",
        async function() {

            if (!voiceBlob) {

                alert(
                    "Please record first."
                );

                return;
            }

            sendVoiceBtn.disabled =
                true;

            sendVoiceBtn.textContent =
                "Sending...";

            const formData =
                new FormData();

            formData.append(
                "action",
                "send_voice"
            );

            formData.append(
                "booking_id",
                bookingId
            );

            let ext = "webm";

            if (
                voiceBlob.type.includes("ogg")
            ) {

                ext = "ogg";

            } else if (
                voiceBlob.type.includes("mp4")
            ) {

                ext = "m4a";
            }

            formData.append(
                "voice_file",
                voiceBlob,
                "voice." + ext
            );

            try {

                const response =
                    await fetch(
                        "messages.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );

                const data =
                    await response.json();

                if (data.success) {

                    window.location.href =
                        "messages.php?booking_id=" +
                        bookingId;

                } else {

                    alert(
                        data.message ||
                        "Voice message failed."
                    );

                }

            } catch (error) {

                alert(
                    "Voice upload error."
                );

            } finally {

                sendVoiceBtn.disabled =
                    false;

                sendVoiceBtn.textContent =
                    "Send Voice";

            }

        }
    );

}

if (cancelVoiceBtn) {

    cancelVoiceBtn.addEventListener(
        "click",
        function() {

            resetVoice();

        }
    );

}

function resetVoice()
{

    if (
        mediaRecorder &&
        mediaRecorder.state === "recording"
    ) {

        mediaRecorder.stop();

    }

    if (audioStream) {

        audioStream
            .getTracks()
            .forEach(
                function(track) {
                    track.stop();
                }
            );

    }

    stopTimer();

    mediaRecorder = null;

    audioStream = null;

    audioChunks = [];

    voiceBlob = null;

    voicePreview.innerHTML = "";

    recordingStatus.textContent =
        "Ready";

    recordingTimer.textContent =
        "00:00";

    voiceRecordBtn.textContent =
        "🎤 Record";

    voiceRecordBtn.style.display =
        "inline-block";

    sendVoiceBtn.style.display =
        "none";

    voicePanel.classList.remove(
        "show"
    );

}

function startTimer()
{

    stopTimer();

    timerInterval =
        setInterval(
            function() {

                recordingSeconds++;

                const minutes =
                    Math.floor(
                        recordingSeconds / 60
                    );

                const seconds =
                    recordingSeconds % 60;

                recordingTimer.textContent =
                    String(minutes).padStart(2, "0") +
                    ":" +
                    String(seconds).padStart(2, "0");

            },
            1000
        );

}

function stopTimer()
{

    if (timerInterval) {

        clearInterval(
            timerInterval
        );

        timerInterval = null;

    }

}

function formatSize(bytes)
{

    if (bytes < 1024) {
        return bytes + " B";
    }

    if (bytes < 1024 * 1024) {
        return (
            bytes / 1024
        ).toFixed(1) + " KB";
    }

    return (
        bytes / (1024 * 1024)
    ).toFixed(1) + " MB";

}

const conversationSearch =
    document.getElementById(
        "conversationSearch"
    );

if (conversationSearch) {

    conversationSearch.addEventListener(
        "input",
        function() {

            const value =
                conversationSearch.value
                    .toLowerCase()
                    .trim();

            document
                .querySelectorAll(
                    ".conversation"
                )
                .forEach(
                    function(item) {

                        const name =
                            (
                                item.getAttribute(
                                    "data-name"
                                ) || ""
                            ).toLowerCase();

                        item.style.display =
                            name.includes(value)
                                ? "flex"
                                : "none";

                    }
                );

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

                    document
                        .getElementById(
                            "messageForm"
                        )
                        .submit();

                }

            }

        }
    );

}

if (messagesBox) {

    messagesBox.scrollTop =
        messagesBox.scrollHeight;

}

setTimeout(
    function() {

        const success =
            document.getElementById(
                "successAlert"
            );

        const error =
            document.getElementById(
                "errorAlert"
            );

        if (success) {
            success.style.display = "none";
        }

        if (error) {
            error.style.display = "none";
        }

    },
    3000
);

</script>

</body>
</html>