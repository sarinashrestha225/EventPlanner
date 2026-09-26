<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$citizenship_dir = "../uploads/customers/citizenship/";
$selfie_dir      = "../uploads/customers/selfie/";

if (!is_dir($citizenship_dir)) {
    mkdir($citizenship_dir, 0777, true);
}

if (!is_dir($selfie_dir)) {
    mkdir($selfie_dir, 0777, true);
}

$upload_message = "";
$upload_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_type"])) {

    $upload_type = $_POST["upload_type"];

    if ($upload_type === "citizenship") {

        if (!isset($_FILES["citizenship_file"]) ||
            $_FILES["citizenship_file"]["error"] !== UPLOAD_ERR_OK) {

            $upload_error = "Please select a citizenship document.";

        } else {

            $file = $_FILES["citizenship_file"];

            $original_name = $file["name"];
            $tmp_name      = $file["tmp_name"];
            $file_size     = $file["size"];

            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png",
                "pdf"
            ];

            $extension = strtolower(
                pathinfo($original_name, PATHINFO_EXTENSION)
            );

            if ($file_size > 5 * 1024 * 1024) {

                $upload_error =
                    "Citizenship file must be less than 5MB.";

            }

            elseif (!in_array($extension, $allowed_extensions)) {

                $upload_error =
                    "Only JPG, JPEG, PNG and PDF files are allowed.";

            }

            else {

                $stmt_old = $conn->prepare(
                    "SELECT citizenship_file FROM users WHERE id = ? LIMIT 1"
                );

                $stmt_old->bind_param("i", $user_id);
                $stmt_old->execute();

                $old_result = $stmt_old->get_result();
                $old_data = $old_result->fetch_assoc();

                $old_file = $old_data["citizenship_file"] ?? "";

                $stmt_old->close();

                $new_filename =
                    "citizenship_" .
                    $user_id .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $destination =
                    $citizenship_dir .
                    $new_filename;

                if (move_uploaded_file($tmp_name, $destination)) {

                    $database_path =
                        "uploads/customers/citizenship/" .
                        $new_filename;

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET citizenship_file = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "si",
                        $database_path,
                        $user_id
                    );

                    if ($stmt->execute()) {

                        if (!empty($old_file)) {

                            $old_path = "../" . $old_file;

                            if (file_exists($old_path)) {
                                @unlink($old_path);
                            }
                        }

                        $upload_message =
                            "Citizenship document uploaded successfully.";

                    } else {

                        @unlink($destination);

                        $upload_error =
                            "Database update failed.";
                    }

                    $stmt->close();

                } else {

                    $upload_error =
                        "Failed to upload citizenship document.";
                }
            }
        }
    }

    if ($upload_type === "selfie") {

        if (!isset($_FILES["selfie_file"]) ||
            $_FILES["selfie_file"]["error"] !== UPLOAD_ERR_OK) {

            $upload_error =
                "Please select a selfie.";

        } else {

            $file = $_FILES["selfie_file"];

            $original_name = $file["name"];
            $tmp_name      = $file["tmp_name"];
            $file_size     = $file["size"];

            $allowed_extensions = [
                "jpg",
                "jpeg",
                "png"
            ];

            $extension = strtolower(
                pathinfo($original_name, PATHINFO_EXTENSION)
            );

            if ($file_size > 5 * 1024 * 1024) {

                $upload_error =
                    "Selfie must be less than 5MB.";

            }

            elseif (!in_array($extension, $allowed_extensions)) {

                $upload_error =
                    "Selfie must be JPG, JPEG or PNG.";

            }

            else {

                $stmt_old = $conn->prepare(
                    "SELECT selfie_file FROM users WHERE id = ? LIMIT 1"
                );

                $stmt_old->bind_param("i", $user_id);
                $stmt_old->execute();

                $old_result = $stmt_old->get_result();
                $old_data = $old_result->fetch_assoc();

                $old_file = $old_data["selfie_file"] ?? "";

                $stmt_old->close();

                $new_filename =
                    "selfie_" .
                    $user_id .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $destination =
                    $selfie_dir .
                    $new_filename;

                if (move_uploaded_file($tmp_name, $destination)) {

                    $database_path =
                        "uploads/customers/selfie/" .
                        $new_filename;

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET selfie_file = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "si",
                        $database_path,
                        $user_id
                    );

                    if ($stmt->execute()) {

                        if (!empty($old_file)) {

                            $old_path = "../" . $old_file;

                            if (file_exists($old_path)) {
                                @unlink($old_path);
                            }
                        }

                        $upload_message =
                            "Selfie uploaded successfully.";

                    } else {

                        @unlink($destination);

                        $upload_error =
                            "Database update failed.";
                    }

                    $stmt->close();

                } else {

                    $upload_error =
                        "Failed to upload selfie.";
                }
            }
        }
    }
}

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        address,
        citizenship_file,
        selfie_file,
        role,
        status
    FROM users
    WHERE id = ?
    AND role IN ('customer','both')
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    session_destroy();

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:60px;
        '>

        <h2 style='color:#b8860b;'>
            Customer Account Not Found
        </h2>

        <p>Your customer account could not be found.</p>

        <a href='login.php'
           style='
                display:inline-block;
                margin-top:15px;
                padding:10px 20px;
                background:#f2c94c;
                color:#5b4300;
                text-decoration:none;
                border-radius:20px;
                font-weight:bold;
           '>
            Login Again
        </a>

        </div>
    ");

}

$customer = $result->fetch_assoc();

$stmt->close();

$name = $customer["name"] ?? "";
$email = $customer["email"] ?? "";
$phone = $customer["phone"] ?? "";
$address = $customer["address"] ?? "";
$role = $customer["role"] ?? "customer";
$status = $customer["status"] ?? "active";

$citizenship = $customer["citizenship_file"] ?? "";
$selfie = $customer["selfie_file"] ?? "";

$citizenship_uploaded = !empty($citizenship);
$selfie_uploaded = !empty($selfie);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>My Profile | Event Planner</title>

<style>

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body {

    font-family:Arial, sans-serif;

    background:#fffaf2;

    color:#444;

}

.navbar {

    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #f7d774
        );

    padding:15px 40px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    gap:15px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.12);

}

.logo {

    font-size:25px;

    font-weight:bold;

    color:#8b6508;

}

.nav-links {

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.nav-btn {

    text-decoration:none;

    background:white;

    color:#8b6508;

    padding:9px 15px;

    border-radius:20px;

    font-weight:bold;

    font-size:14px;

    transition:0.3s;

}

.nav-btn:hover {

    background:#fff1b8;

    transform:translateY(-2px);

}

.container {

    width:92%;

    max-width:1050px;

    margin:40px auto;

}

.profile-card {

    background:white;

    border-radius:22px;

    padding:35px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,0.10);

}

.profile-header {

    text-align:center;

    margin-bottom:30px;

}

.profile-icon {

    width:100px;

    height:100px;

    margin:auto;

    border-radius:50%;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:48px;

    background:
        linear-gradient(
            135deg,
            #f8c8dc,
            #f7d774
        );

}

.profile-header h1 {

    margin-top:15px;

    color:#8b6508;

}

.profile-header p {

    color:#777;

    margin-top:6px;

}

.info-grid {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:18px;

}

.info-box {

    background:#fffaf2;

    border:1px solid #f0d98c;

    border-radius:15px;

    padding:18px;

}

.info-label {

    font-size:13px;

    color:#999;

    margin-bottom:7px;

}

.info-value {

    font-size:16px;

    font-weight:bold;

    color:#6b4e00;

    word-break:break-word;

}

.document-section {

    margin-top:35px;

}

.section-title {

    color:#8b6508;

    margin-bottom:15px;

    font-size:21px;

}

.documents {

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:20px;

}

.document-card {

    border:1px solid #f0d98c;

    background:#fffaf2;

    border-radius:18px;

    padding:25px;

    text-align:center;

}

.document-icon {

    font-size:40px;

    margin-bottom:10px;

}

.document-card h3 {

    color:#6b4e00;

    margin-bottom:8px;

}

.document-status {

    margin:10px 0;

    font-weight:bold;

}

.uploaded {

    color:#198754;

}

.not-uploaded {

    color:#dc3545;

}

.upload-form {

    margin-top:15px;

}

.file-input {

    width:100%;

    padding:10px;

    border:1px solid #e5cf8a;

    border-radius:10px;

    background:white;

    font-size:13px;

}

.upload-btn {

    display:inline-block;

    margin-top:10px;

    padding:10px 18px;

    border:none;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            #f4c542,
            #f6b6d2
        );

    color:#5b4300;

    text-decoration:none;

    font-weight:bold;

    cursor:pointer;

}

.upload-btn:hover {

    opacity:0.85;

}

.upload-success {

    margin-bottom:20px;

    padding:14px;

    border-radius:12px;

    background:#e8f8ee;

    border:1px solid #a8dfba;

    color:#198754;

    text-align:center;

    font-weight:bold;

}

.upload-error {

    margin-bottom:20px;

    padding:14px;

    border-radius:12px;

    background:#fff0f0;

    border:1px solid #f0b5b5;

    color:#dc3545;

    text-align:center;

    font-weight:bold;

}

.quick-section {

    margin-top:35px;

}

.quick-grid {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:15px;

}

.quick-card {

    text-decoration:none;

    background:#fffaf2;

    border:1px solid #f0d98c;

    border-radius:16px;

    padding:20px 10px;

    text-align:center;

    color:#6b4e00;

    font-weight:bold;

    transition:0.3s;

}

.quick-card:hover {

    transform:translateY(-4px);

    background:#fff1d0;

}

.quick-icon {

    display:block;

    font-size:32px;

    margin-bottom:8px;

}

.actions {

    margin-top:30px;

    display:flex;

    justify-content:center;

    gap:15px;

    flex-wrap:wrap;

}

.action-btn {

    text-decoration:none;

    padding:12px 22px;

    border-radius:25px;

    font-weight:bold;

    background:
        linear-gradient(
            90deg,
            #f4c542,
            #f6b6d2
        );

    color:#5b4300;

}

footer {

    margin-top:50px;

    background:#f8c8dc;

    padding:25px;

    text-align:center;

    color:#6b4e00;

    line-height:1.8;

}

@media(max-width:800px) {

    .info-grid,
    .documents {

        grid-template-columns:1fr;

    }

    .quick-grid {

        grid-template-columns:
            repeat(2,1fr);

    }

}

@media(max-width:600px) {

    .navbar {

        padding:15px;

        justify-content:center;

    }

    .nav-links {

        justify-content:center;

    }

    .profile-card {

        padding:22px 15px;

    }

}

</style>

</head>

<body>

<div class="navbar">

    <div class="logo">
        🎉 Event Planner
    </div>

    <div class="nav-links">

        <a href="dashboard.php"
           class="nav-btn">
            🏠 Dashboard
        </a>

        <a href="my_bookings.php"
           class="nav-btn">
            📋 My Bookings
        </a>

        <a href="notifications.php"
           class="nav-btn">
            🔔 Notifications
        </a>

        <a href="messages.php"
           class="nav-btn">
            💬 Messages
        </a>

        <a href="change_password.php"
           class="nav-btn">
            🔐 Password
        </a>

        <a href="logout.php"
           class="nav-btn">
            🚪 Logout
        </a>

    </div>

</div>

<div class="container">

<div class="profile-card">

<?php if (!empty($upload_message)): ?>

    <div class="upload-success">
        ✅ <?= htmlspecialchars($upload_message) ?>
    </div>

<?php endif; ?>

<?php if (!empty($upload_error)): ?>

    <div class="upload-error">
        ❌ <?= htmlspecialchars($upload_error) ?>
    </div>

<?php endif; ?>

<div class="profile-header">

    <div class="profile-icon">
        👤
    </div>

    <h1>
        My Profile
    </h1>

    <p>
        Manage your Event Planner account
    </p>

</div>

<h2 class="section-title">
    👤 Personal Information
</h2>

<div class="info-grid">

<div class="info-box">

    <div class="info-label">
        Full Name
    </div>

    <div class="info-value">
        <?= htmlspecialchars($name) ?>
    </div>

</div>

<div class="info-box">

    <div class="info-label">
        Email Address
    </div>

    <div class="info-value">
        <?= htmlspecialchars($email) ?>
    </div>

</div>

<div class="info-box">

    <div class="info-label">
        Phone Number
    </div>

    <div class="info-value">

        <?= !empty($phone)
            ? htmlspecialchars($phone)
            : "Not provided"
        ?>

    </div>

</div>

<div class="info-box">

    <div class="info-label">
        Address
    </div>

    <div class="info-value">

        <?= !empty($address)
            ? htmlspecialchars($address)
            : "Not provided"
        ?>

    </div>

</div>

<div class="info-box">

    <div class="info-label">
        Account Type
    </div>

    <div class="info-value">
        <?= htmlspecialchars(ucfirst($role)) ?>
    </div>

</div>

<div class="info-box">

    <div class="info-label">
        Account Status
    </div>

    <div class="info-value">
        <?= htmlspecialchars(ucfirst($status)) ?>
    </div>

</div>

</div>

<div class="document-section">

<h2 class="section-title">
    🪪 Identity Verification
</h2>

<div class="documents">

<div class="document-card">

    <div class="document-icon">
        🪪
    </div>

    <h3>
        Citizenship Document
    </h3>

    <?php if ($citizenship_uploaded): ?>

        <div class="document-status uploaded">
            ✅ Uploaded
        </div>

        <a href="../<?= htmlspecialchars($citizenship) ?>"
           target="_blank"
           class="upload-btn">

            👁️ View Document

        </a>

        <form
            method="POST"
            enctype="multipart/form-data"
            class="upload-form">

            <input
                type="hidden"
                name="upload_type"
                value="citizenship"
            >

            <input
                type="file"
                name="citizenship_file"
                class="file-input"
                accept=".jpg,.jpeg,.png,.pdf"
                required
            >

            <button
                type="submit"
                class="upload-btn">

                🔄 Replace Citizenship

            </button>

        </form>

    <?php else: ?>

        <div class="document-status not-uploaded">
            ❌ Not Uploaded
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            class="upload-form">

            <input
                type="hidden"
                name="upload_type"
                value="citizenship"
            >

            <input
                type="file"
                name="citizenship_file"
                class="file-input"
                accept=".jpg,.jpeg,.png,.pdf"
                required
            >

            <button
                type="submit"
                class="upload-btn">

                📤 Upload Citizenship

            </button>

        </form>

    <?php endif; ?>

</div>

<div class="document-card">

    <div class="document-icon">
        🤳
    </div>

    <h3>
        Selfie Verification
    </h3>

    <?php if ($selfie_uploaded): ?>

        <div class="document-status uploaded">
            ✅ Uploaded
        </div>

        <a href="../<?= htmlspecialchars($selfie) ?>"
           target="_blank"
           class="upload-btn">

            👁️ View Selfie

        </a>

        <form
            method="POST"
            enctype="multipart/form-data"
            class="upload-form">

            <input
                type="hidden"
                name="upload_type"
                value="selfie"
            >

            <input
                type="file"
                name="selfie_file"
                class="file-input"
                accept=".jpg,.jpeg,.png"
                required
            >

            <button
                type="submit"
                class="upload-btn">

                🔄 Replace Selfie

            </button>

        </form>

    <?php else: ?>

        <div class="document-status not-uploaded">
            ❌ Not Uploaded
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            class="upload-form">

            <input
                type="hidden"
                name="upload_type"
                value="selfie"
            >

            <input
                type="file"
                name="selfie_file"
                class="file-input"
                accept=".jpg,.jpeg,.png"
                required
            >

            <button
                type="submit"
                class="upload-btn">

                📤 Upload Selfie

            </button>

        </form>

    <?php endif; ?>

</div>

</div>

</div>

<div class="quick-section">

<h2 class="section-title">
    ⚡ Quick Access
</h2>

<div class="quick-grid">

<a href="my_bookings.php"
   class="quick-card">

    <span class="quick-icon">
        📋
    </span>

    My Bookings

</a>

<a href="my_bookings.php"
   class="quick-card">

    <span class="quick-icon">
        💳
    </span>

    Payments

</a>

<a href="messages.php"
   class="quick-card">

    <span class="quick-icon">
        💬
    </span>

    Messages

</a>

<a href="notifications.php"
   class="quick-card">

    <span class="quick-icon">
        🔔
    </span>

    Notifications

</a>

</div>

</div>

<div class="actions">

<a href="edit_profile.php"
   class="action-btn">

    ✏️ Edit Profile

</a>

<a href="change_password.php"
   class="action-btn">

    🔐 Change Password

</a>

<a href="dashboard.php"
   class="action-btn">

    🏠 Back to Dashboard

</a>

</div>

</div>

</div>

<footer>

    🎉 <strong>Event Planner</strong>

    <br>

    Plan • Book • Celebrate

    <br>

    © <?= date("Y") ?> Event Planner. All Rights Reserved.

</footer>

</body>

</html>

<?php

$stmt->close();

?>