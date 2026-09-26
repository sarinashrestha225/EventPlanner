<?php
session_start();
require_once "../database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $error = "Please enter your admin email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, status
             FROM admins
             WHERE email = ? AND status = 'active'
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            $stmt->close();

            if (!$admin) {
                $error = "No active admin account found with that email.";
            } else {
                $token = bin2hex(random_bytes(32));

                $_SESSION["admin_password_reset_token"] = $token;
                $_SESSION["admin_password_reset_user_id"] = (int)$admin["id"];
                $_SESSION["admin_password_reset_email"] = $admin["email"];
                $_SESSION["admin_password_reset_time"] = time();

                header("Location: reset_password.php?token=" . urlencode($token));
                exit();
            }
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
    <title>Forgot Password - Admin</title>

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

        .forgot-wrapper {
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
            margin-top: 20px;
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

<div class="forgot-wrapper">

    <div class="top">
        <div class="logo">🔐</div>
        <h1>Event Planner</h1>
        <p>Admin Password Recovery</p>
    </div>

    <div class="form-section">

        <h2>Forgot Password?</h2>

        <div class="description">
            Enter your admin email address to continue and create a new password.
        </div>

        <?php if ($error !== ""): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <label for="email">Admin Email</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your admin email"
                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                required
            >

            <button type="submit" class="button">
                Continue
            </button>

        </form>

        <div class="back">
            <a href="login.php">← Back to Admin Login</a>
        </div>

    </div>

</div>

</body>
</html>

