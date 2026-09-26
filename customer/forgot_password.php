<?php
session_start();
require_once "../database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        $stmt = $conn->prepare("
            SELECT id, name, email
            FROM users
            WHERE email = ?
            AND role = 'customer'
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            $token = bin2hex(random_bytes(32));

            $_SESSION["password_reset_token"] = $token;
            $_SESSION["password_reset_user_id"] = $user["id"];
            $_SESSION["password_reset_email"] = $user["email"];
            $_SESSION["password_reset_time"] = time();

            header(
                "Location: reset_password.php?token=" .
                urlencode($token)
            );
            exit;

        } else {
            $error = "No customer account was found with this email.";
        }

        $stmt->close();
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

    <title>Forgot Password - Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;

            background: linear-gradient(
                135deg,
                #fffaf0,
                #ffe4ec,
                #fff4c4
            );

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;
        }

        .forgot-card {
            width: 100%;
            max-width: 430px;

            background: #ffffff;

            padding: 40px;

            border-radius: 22px;

            box-shadow:
                0 10px 35px
                rgba(0, 0, 0, 0.12);
        }

        .logo {
            text-align: center;

            font-size: 30px;

            font-weight: bold;

            color: #b8860b;

            margin-bottom: 10px;
        }

        h2 {
            text-align: center;

            margin: 0 0 12px;

            color: #333;
        }

        .description {
            text-align: center;

            color: #666;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            font-weight: bold;

            color: #444;
        }

        input {
            width: 100%;

            padding: 14px;

            border: 1px solid #ddd;

            border-radius: 10px;

            font-size: 15px;

            outline: none;
        }

        input:focus {
            border-color: #d4af37;

            box-shadow:
                0 0 0 3px
                rgba(212, 175, 55, 0.12);
        }

        .reset-btn {
            width: 100%;

            border: none;

            padding: 14px;

            border-radius: 10px;

            background: linear-gradient(
                90deg,
                #d4af37,
                #e8b4b8
            );

            color: white;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;
        }

        .reset-btn:hover {
            opacity: 0.9;
        }

        .error {
            background: #ffe5e5;

            color: #b00020;

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 20px;

            text-align: center;

            font-size: 14px;
        }

        .back-login {
            display: block;

            text-align: center;

            margin-top: 22px;

            color: #b8860b;

            font-weight: bold;

            text-decoration: none;

            font-size: 14px;
        }

        .back-login:hover {
            text-decoration: underline;
        }

        .back-home {
            display: block;

            text-align: center;

            margin-top: 15px;

            color: #555;

            text-decoration: none;

            font-size: 14px;
        }

        .back-home:hover {
            color: #b8860b;
        }

    </style>

</head>

<body>

<div class="forgot-card">

    <div class="logo">
        🎉 Event Planner
    </div>

    <h2>Forgot Password?</h2>

    <div class="description">
        Enter your registered customer email address
        to reset your password.
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label>Email</label>

            <input
                type="email"
                name="email"
                placeholder="Enter your registered email"
                required
            >

        </div>

        <button
            type="submit"
            class="reset-btn"
        >
            🔑 Reset Password
        </button>

    </form>

    <a
        href="login.php"
        class="back-login"
    >
        ← Back to Login
    </a>

    <a
        href="../index.php"
        class="back-home"
    >
        ← Back to Event Planner
    </a>

</div>

</body>
</html>

