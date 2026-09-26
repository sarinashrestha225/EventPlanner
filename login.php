<?php

session_start();

require_once "database.php";

$message = "";

$redirect = $_GET["redirect"] ?? $_POST["redirect"] ?? "";

if ($redirect === "") {
    $redirect = "index.php";
}

if (
    strpos($redirect, "://") !== false ||
    strpos($redirect, "//") === 0 ||
    strpos($redirect, "\\") !== false
) {
    $redirect = "index.php";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $message = "Please enter email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if ($stmt) {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if (password_verify($password, $user["password"])) {

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = $user["role"];

                    if (
                        $user["role"] === "provider" &&
                        empty($_SESSION["provider_id"])
                    ) {

                        $provider_stmt = $conn->prepare("
                            SELECT id, name
                            FROM providers
                            WHERE email = ?
                            LIMIT 1
                        ");

                        if ($provider_stmt) {

                            $provider_stmt->bind_param(
                                "s",
                                $user["email"]
                            );

                            $provider_stmt->execute();

                            $provider_result =
                                $provider_stmt->get_result();

                            if (
                                $provider_result &&
                                $provider_result->num_rows === 1
                            ) {

                                $provider =
                                    $provider_result->fetch_assoc();

                                $_SESSION["provider_id"] =
                                    (int)$provider["id"];

                                $_SESSION["provider_name"] =
                                    $provider["name"];
                            }

                            $provider_stmt->close();
                        }
                    }

                    header(
                        "Location: " .
                        str_replace(["\r", "\n"], "", $redirect)
                    );

                    exit;

                } else {

                    $message = "Incorrect password.";
                }

            } else {

                $message = "No account found with this email.";
            }

            $stmt->close();

        } else {

            $message = "Unable to process login.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login - Event Planner</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    min-height: 100vh;
    background: linear-gradient(135deg,#fff8e7,#ffe4ec);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px;
}

.container {
    width: 100%;
    max-width: 450px;
}

.logo {
    text-align: center;
    margin-bottom: 20px;
}

.logo h1 {
    color: #b8860b;
    font-size: 32px;
}

.logo p {
    color: #777;
    margin-top: 5px;
}

.card {
    background: white;
    padding: 35px;
    border-radius: 18px;
    box-shadow: 0 10px 35px rgba(0,0,0,.12);
}

.card h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
}

.message {
    background: #ffe5e5;
    color: #c62828;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 18px;
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
    padding: 13px;
    border: 1px solid #ddd;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #d4af37;
}

.btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg,#d4af37,#b8860b);
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.register {
    text-align: center;
    margin-top: 22px;
    color: #666;
    line-height: 1.8;
}

.register a {
    color: #b8860b;
    font-weight: bold;
    text-decoration: none;
}

.back-home {
    display: block;
    text-align: center;
    margin-top: 12px;
    color: #777;
    text-decoration: none;
}

</style>

</head>

<body>

<div class="container">

    <div class="logo">

        <h1>
            🎉 Event Planner
        </h1>

        <p>
            Login to continue booking
        </p>

    </div>

    <div class="card">

        <h2>
            Login
        </h2>

        <?php if ($message !== ""): ?>

            <div class="message">
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="redirect"
                value="<?= htmlspecialchars(
                    $redirect,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <div class="form-group">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn"
            >
                Login
            </button>

        </form>

        <div class="register">

            Don't have an account?

            <a href="register.php">
                Create Account
            </a>

        </div>

        <a
            href="index.php"
            class="back-home"
        >
            ← Back to Home
        </a>

    </div>

</div>

</body>
</html>