<?php

session_start();

require_once "../database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter email and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                email,
                phone,
                password,
                role
            FROM users
            WHERE email = ?
            AND role = 'customer'
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database query error: " . $conn->error;

        } else {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if (
                    !empty($user["password"]) &&
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {

                    session_regenerate_id(true);

                    $_SESSION["user_id"] =
                        (int) $user["id"];

                    $_SESSION["user_name"] =
                        $user["name"];

                    $_SESSION["user_email"] =
                        $user["email"];

                    $_SESSION["user_phone"] =
                        $user["phone"] ?? "";

                    $_SESSION["active_role"] =
                        "customer";

                    $_SESSION["role"] =
                        "customer";

                    header(
                        "Location: dashboard.php"
                    );

                    exit;

                } else {

                    $error = "Incorrect password.";

                }

            } else {

                $error =
                    "Customer account not found with this email.";

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

<title>
    Customer Login | Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family:
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #fff8f2,
            #fff1f7
        );

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 20px;
}

.login-box {

    width: 100%;

    max-width: 430px;

    background: white;

    padding: 40px;

    border-radius: 20px;

    box-shadow:
        0 10px 35px
        rgba(0,0,0,0.12);

    border-top:
        5px solid #d4af37;
}

.logo {

    text-align: center;

    font-size: 28px;

    font-weight: bold;

    color: #b8860b;

    margin-bottom: 8px;
}

.subtitle {

    text-align: center;

    color: #777;

    margin-bottom: 30px;

    font-size: 15px;
}

.error {

    background: #ffe5e5;

    color: #b00020;

    border:
        1px solid #ffbcbc;

    padding: 12px;

    border-radius: 10px;

    text-align: center;

    margin-bottom: 20px;

    font-size: 14px;
}

label {

    display: block;

    margin-bottom: 7px;

    color: #555;

    font-weight: bold;

    font-size: 14px;
}

.input-group {

    margin-bottom: 20px;
}

input {

    width: 100%;

    padding: 13px 14px;

    border:
        1px solid #ddd;

    border-radius: 10px;

    font-size: 15px;

    outline: none;

    transition: 0.3s;
}

input:focus {

    border-color: #d4af37;

    box-shadow:
        0 0 0 3px
        rgba(212,175,55,0.12);
}

button {

    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 10px;

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #e8c95a
        );

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.3s;
}

button:hover {

    background:
        linear-gradient(
            90deg,
            #b8860b,
            #d4af37
        );

    transform:
        translateY(-2px);
}

.register {

    text-align: center;

    margin-top: 25px;

    color: #777;

    font-size: 14px;
}

.register a {

    color: #b8860b;

    font-weight: bold;

    text-decoration: none;
}

.register a:hover {

    text-decoration: underline;
}

.back {

    text-align: center;

    margin-top: 15px;
}

.back a {

    color: #777;

    text-decoration: none;

    font-size: 13px;
}

.back a:hover {

    color: #b8860b;
}

</style>

</head>

<body>

<div class="login-box">

    <div class="logo">

        🎉 Event Planner

    </div>

    <div class="subtitle">

        Customer Login

    </div>

    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="input-group">

            <label>
                Email
            </label>

            <input
                type="email"
                name="email"
                placeholder="Enter your email"
                value="<?= htmlspecialchars(
                    $_POST["email"] ?? ""
                ) ?>"
                required
            >

        </div>

        <div class="input-group">

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

        <button type="submit">

            🔐 Login

        </button>

    </form>

    <div class="register">

        Don't have an account?

        <a href="register.php">

            Create Account

        </a>

    </div>

    <div class="back">

        <a href="../index.php">

            ← Back to Event Planner

        </a>

    </div>

</div>

</body>

</html>