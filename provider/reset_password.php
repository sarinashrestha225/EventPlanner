<?php

session_start();

require_once __DIR__ . "/../database.php";

$error = "";
$success = "";

$token = $_GET["token"] ?? $_POST["token"] ?? "";

$sessionToken = $_SESSION["provider_password_reset_token"] ?? "";
$providerId = $_SESSION["provider_password_reset_user_id"] ?? "";
$resetTime = $_SESSION["provider_password_reset_time"] ?? 0;

$validToken = false;

if (
    $token !== "" &&
    $sessionToken !== "" &&
    $providerId !== "" &&
    hash_equals($sessionToken, $token)
) {

    if ((time() - $resetTime) <= 900) {

        $validToken = true;

    } else {

        $error = "This password reset link has expired.";
    }

} else {

    $error = "Invalid password reset link.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $validToken) {

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($password === "" || $confirmPassword === "") {

        $error = "Please enter both passwords.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } else {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
            UPDATE providers
            SET password = ?
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Unable to reset password.";

        } else {

            $stmt->bind_param(
                "si",
                $hashedPassword,
                $providerId
            );

            if ($stmt->execute()) {

                unset($_SESSION["provider_password_reset_token"]);
                unset($_SESSION["provider_password_reset_user_id"]);
                unset($_SESSION["provider_password_reset_email"]);
                unset($_SESSION["provider_password_reset_time"]);

                $success = "Your password has been changed successfully.";

                $validToken = false;

            } else {

                $error = "Unable to reset password.";
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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password - Provider</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #fff8f0, #ffeef4);
            color: #4b3621;
        }

        .box {
            width: 420px;
            max-width: 92%;
            background: white;
            padding: 38px;
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.12);
        }

        .icon {
            text-align: center;
            font-size: 45px;
            margin-bottom: 10px;
        }

        h1 {
            text-align: center;
            margin: 0 0 10px;
            font-size: 27px;
        }

        .description {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 9px;
            margin-bottom: 18px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 14px;
            border-radius: 9px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 9px;
            font-size: 15px;
            margin-bottom: 18px;
        }

        input:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: #d4af37;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #b8860b;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #b8860b;
            text-decoration: none;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="box">

    <div class="icon">🔑</div>

    <h1>Reset Password</h1>

    <div class="description">
        Create a new password for your provider account.
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>

        <a href="login.php" class="back">
            Go to Provider Login
        </a>

    <?php elseif ($validToken): ?>

        <form method="POST">

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token) ?>"
            >

            <label>New Password</label>

            <input
                type="password"
                name="password"
                placeholder="Enter new password"
                minlength="6"
                required
            >

            <label>Confirm Password</label>

            <input
                type="password"
                name="confirm_password"
                placeholder="Confirm new password"
                minlength="6"
                required
            >

            <button type="submit">
                Reset Password
            </button>

        </form>

    <?php else: ?>

        <a href="forgot_password.php" class="back">
            Request a New Reset Link
        </a>

    <?php endif; ?>

</div>

</body>

</html>

