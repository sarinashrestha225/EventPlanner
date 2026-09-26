<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["user_id"];

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        empty($current_password) ||
        empty($new_password) ||
        empty($confirm_password)
    ) {

        $message = "Please fill in all fields.";
        $message_type = "error";

    }

    elseif (strlen($new_password) < 6) {

        $message = "New password must be at least 6 characters.";
        $message_type = "error";

    }

    elseif ($new_password !== $confirm_password) {

        $message = "New password and confirm password do not match.";
        $message_type = "error";

    }

    elseif ($current_password === $new_password) {

        $message = "New password must be different from your current password.";
        $message_type = "error";

    }

    else {

        $sql = "
            SELECT password
            FROM users
            WHERE id = ?
            AND role IN ('customer', 'both')
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $message = "Database error.";
            $message_type = "error";

        } else {

            $stmt->bind_param("i", $customer_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {

                $message = "Customer account not found.";
                $message_type = "error";

            } else {

                $user = $result->fetch_assoc();

                if (!password_verify(
                    $current_password,
                    $user["password"]
                )) {

                    $message = "Current password is incorrect.";
                    $message_type = "error";

                } else {

                    $hashed_password = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $update_sql = "
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ";

                    $update_stmt = $conn->prepare($update_sql);

                    if (!$update_stmt) {

                        $message = "Unable to update password.";
                        $message_type = "error";

                    } else {

                        $update_stmt->bind_param(
                            "si",
                            $hashed_password,
                            $customer_id
                        );

                        if ($update_stmt->execute()) {

                            $message = "Password changed successfully.";
                            $message_type = "success";

                        } else {

                            $message = "Failed to change password.";
                            $message_type = "error";
                        }

                        $update_stmt->close();
                    }
                }
            }

            $stmt->close();
        }
    }
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

<title>Change Password - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #fff8f2;

    color: #555;
}

.header {

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f3d36a
        );

    padding: 20px 40px;

    color: white;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.header h2 {

    margin: 0;
}

.back-btn {

    text-decoration: none;

    background: #b8860b;

    color: white;

    padding: 10px 18px;

    border-radius: 8px;

    font-weight: bold;
}

.back-btn:hover {

    background: #8f6908;
}

.container {

    width: 92%;

    max-width: 550px;

    margin: 50px auto;
}

.card {

    background: white;

    padding: 35px;

    border-radius: 18px;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,0.08);
}

.title {

    text-align: center;

    margin-bottom: 30px;
}

.title-icon {

    font-size: 50px;

    margin-bottom: 10px;
}

.title h1 {

    margin: 0;

    color: #b8860b;

    font-size: 28px;
}

.title p {

    color: #888;

    margin-top: 8px;
}

.message {

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;

    line-height: 1.5;
}

.success {

    background: #dff5df;

    color: #287a28;

    border-left: 4px solid #28a745;
}

.error {

    background: #ffe1e1;

    color: #b00020;

    border-left: 4px solid #dc3545;
}

.form-group {

    margin-bottom: 20px;
}

.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #555;

    font-weight: bold;

    font-size: 14px;
}

.form-group input {

    width: 100%;

    padding: 13px 14px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    outline: none;

    transition: 0.2s;
}

.form-group input:focus {

    border-color: #d4af37;

    box-shadow:
        0 0 0 3px
        rgba(212,175,55,0.12);
}

.password-note {

    background: #fff8e1;

    border-left: 4px solid #d4af37;

    padding: 13px 15px;

    border-radius: 7px;

    margin-bottom: 22px;

    font-size: 13px;

    color: #777;

    line-height: 1.5;
}

.btn {

    width: 100%;

    border: none;

    padding: 14px;

    border-radius: 9px;

    background: #d4af37;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;
}

.btn:hover {

    background: #b8860b;
}

.links {

    text-align: center;

    margin-top: 22px;
}

.links a {

    color: #b8860b;

    text-decoration: none;

    font-weight: bold;

    font-size: 14px;
}

.links a:hover {

    text-decoration: underline;
}

@media (max-width: 600px) {

    .header {

        padding: 18px 20px;
    }

    .container {

        width: 94%;

        margin: 30px auto;
    }

    .card {

        padding: 25px 20px;
    }

    .header h2 {

        font-size: 18px;
    }

}

</style>

</head>

<body>

<div class="header">

    <h2>
        Event Planner
    </h2>

    <a
        href="profile.php"
        class="back-btn"
    >
        ← Profile
    </a>

</div>

<div class="container">

    <div class="card">

        <div class="title">

            <div class="title-icon">
                🔐
            </div>

            <h1>
                Change Password
            </h1>

            <p>
                Keep your Event Planner account secure
            </p>

        </div>

        <?php if (!empty($message)): ?>

            <div
                class="message <?= $message_type ?>"
            >

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>

        <div class="password-note">

            <strong>Password requirement:</strong>

            Your new password must contain at least
            6 characters.

        </div>

        <form
            method="POST"
            action=""
        >

            <div class="form-group">

                <label>
                    Current Password
                </label>

                <input
                    type="password"
                    name="current_password"
                    placeholder="Enter your current password"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    New Password
                </label>

                <input
                    type="password"
                    name="new_password"
                    placeholder="Enter new password"
                    minlength="6"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Confirm New Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Confirm new password"
                    minlength="6"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn"
            >

                🔒 Change Password

            </button>

        </form>

        <div class="links">

            <a href="profile.php">
                ← Back to Profile
            </a>

        </div>

    </div>

</div>

</body>

</html>