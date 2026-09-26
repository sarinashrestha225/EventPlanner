<?php
session_start();
require_once "../database.php";

$error = "";
$success = "";

$token = $_GET["token"] ?? "";

$sessionToken = $_SESSION["admin_password_reset_token"] ?? "";
$adminId = $_SESSION["admin_password_reset_user_id"] ?? 0;
$resetTime = $_SESSION["admin_password_reset_time"] ?? 0;

$validToken = false;

if (
    $token !== "" &&
    $sessionToken !== "" &&
    $adminId > 0 &&
    hash_equals($sessionToken, $token)
) {
    if (time() - $resetTime <= 900) {
        $validToken = true;
    }
}

if (!$validToken) {
    $error = "This password reset link is invalid or has expired.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $validToken) {
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($password === "" || $confirmPassword === "") {
        $error = "Please enter both password fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "UPDATE admins
             SET password = ?
             WHERE id = ?
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("si", $hashedPassword, $adminId);

            if ($stmt->execute()) {
                unset(
                    $_SESSION["admin_password_reset_token"],
                    $_SESSION["admin_password_reset_user_id"],
                    $_SESSION["admin_password_reset_email"],
                    $_SESSION["admin_password_reset_time"]
                );

                $success = "Your admin password has been changed successfully.";
                $validToken = false;
            } else {
                $error = "Unable to change password. Please try again.";
            }

            $stmt->close();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #fffaf0, #fff0f5, #fff8dc);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .reset-wrapper {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 15px 45px rgba(80, 50, 20, 0.15);
            overflow: hidden;
        }

        .top {
            background: linear-gradient(135deg, #f8c471, #f5a9c8);
            padding: 38px 30px;
            text-align: center;
        }

        .logo {
            width: 78px;
            height: 78px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #fffaf0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .top h1 {
            margin: 0;
            color: #4a2c20;
            font-size: 28px;
        }

        .top p {
            margin: 10px 0 0;
            color: #633f35;
            font-size: 14px;
        }

        .form-section {
            padding: 35px;
        }

        .form-section h2 {
            margin: 0 0 8px;
            color: #4a2c20;
            font-size: 24px;
        }

        .description {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .error {
            background: #ffe8e8;
            color: #b42318;
            border: 1px solid #f5b5b5;
        }

        .success {
            background: #e8f8ed;
            color: #187a3d;
            border: 1px solid #b9e5c7;
        }

        .field {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #4a2c20;
            font-weight: 600;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #ddd;
            border-radius: 12px;
            outline: none;
            font-size: 15px;
            background: #fffdf9;
        }

        input:focus {
            border-color: #e7ad54;
            box-shadow: 0 0 0 3px rgba(231, 173, 84, 0.15);
        }

        .button {
            width: 100%;
            margin-top: 5px;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #e9b44c, #f4c95d);
            color: #4a2c20;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .button:hover {
            opacity: 0.92;
        }

        .back {
            text-align: center;
            margin-top: 22px;
        }

        .back a {
            color: #a56a16;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="reset-wrapper">

    <div class="top">
        <div class="logo">🔑</div>
        <h1>Event Planner</h1>
        <p>Admin Password Recovery</p>
    </div>

    <div class="form-section">

        <h2>Create New Password</h2>

        <div class="description">
            Enter your new password below. Your password must contain at least 6 characters.
        </div>

        <?php if ($error !== ""): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>

            <div class="back">
                <a href="login.php">Go to Admin Login →</a>
            </div>

        <?php elseif ($validToken): ?>

            <form method="POST">

                <div class="field">
                    <label for="password">New Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        minlength="6"
                        required
                    >
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter new password"
                        minlength="6"
                        required
                    >
                </div>

                <button type="submit" class="button">
                    Reset Password
                </button>

            </form>

            <div class="back">
                <a href="login.php">← Back to Admin Login</a>
            </div>

        <?php else: ?>

            <div class="back">
                <a href="forgot_password.php">Request a New Reset Link</a>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>