<?php

session_start();

require_once __DIR__ . "/../database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $error = "Please enter your provider email.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare("
            SELECT id, name, email, status
            FROM providers
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Unable to process request.";

        } else {

            $stmt->bind_param("s", $email);

            if (!$stmt->execute()) {

                $error = "Unable to process request.";

            } else {

                $result = $stmt->get_result();

                if ($result->num_rows === 1) {

                    $provider = $result->fetch_assoc();

                    if ($provider["status"] !== "active") {

                        $error = "Your provider account is not active.";

                    } else {

                        $token = bin2hex(random_bytes(32));

                        $_SESSION["provider_password_reset_token"] = $token;
                        $_SESSION["provider_password_reset_user_id"] = $provider["id"];
                        $_SESSION["provider_password_reset_email"] = $provider["email"];
                        $_SESSION["provider_password_reset_time"] = time();

                        header(
                            "Location: reset_password.php?token=" .
                            urlencode($token)
                        );

                        exit();
                    }

                } else {

                    $error = "No provider account found with this email.";
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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password - Provider</title>

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
            background: #fff;
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
            color: #4b3621;
            font-size: 27px;
        }

        .description {
            text-align: center;
            color: #777;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 9px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
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
            font-size: 14px;
        }

        .back:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="box">

    <div class="icon">🔐</div>

    <h1>Forgot Password?</h1>

    <div class="description">
        Enter your provider email address to reset your password.
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label>Email Address</label>

        <input
            type="email"
            name="email"
            placeholder="Enter provider email"
            required
        >

        <button type="submit">
            Continue
        </button>

    </form>

    <a href="login.php" class="back">
        ← Back to Provider Login
    </a>

</div>

</body>

</html>

