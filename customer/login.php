<?php

session_start();

require_once "../database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, phone, password, role
             FROM users
             WHERE email = ?
             AND role = 'customer'
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_phone"] = $user["phone"];
                $_SESSION["user_role"] = "customer";
                $_SESSION["active_role"] = "customer";
                $_SESSION["role"] = "customer";

                header("Location: dashboard.php");
                exit;

            } else {
                $error = "Invalid email or password.";
            }

        } else {
            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customer Login - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #fffaf0, #ffe4ec, #fff4b8);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 25px;
}

.login-container {
    width: 100%;
    max-width: 450px;
}

.login-card {
    background: #ffffff;
    border-radius: 25px;
    padding: 38px 35px;
    box-shadow: 0 15px 45px rgba(180, 120, 80, 0.18);
    border: 1px solid #f5dfb8;
}

.logo {
    text-align: center;
    font-size: 52px;
    margin-bottom: 8px;
}

.brand {
    text-align: center;
    color: #c58b00;
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.subtitle {
    text-align: center;
    color: #777;
    font-size: 14px;
    margin-bottom: 28px;
}

h2 {
    text-align: center;
    color: #b76e79;
    margin: 0 0 25px;
    font-size: 26px;
}

.error {
    background: #ffe1e1;
    color: #b00020;
    border: 1px solid #ffb5b5;
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 14px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    color: #555;
    font-weight: bold;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #e4cfa8;
    border-radius: 12px;
    font-size: 15px;
    outline: none;
    background: #fffdf8;
}

input:focus {
    border-color: #d5a33a;
    box-shadow: 0 0 0 3px rgba(213, 163, 58, 0.12);
}

.forgot {
    text-align: right;
    margin-top: -7px;
    margin-bottom: 22px;
}

.forgot a {
    color: #c27a8a;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
}

.login-btn {
    width: 100%;
    border: none;
    padding: 14px;
    border-radius: 12px;
    background: linear-gradient(90deg, #e8a4b8, #f0c75e);
    color: #ffffff;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.register {
    text-align: center;
    margin-top: 22px;
    color: #666;
    font-size: 14px;
}

.register a {
    color: #bd7182;
    text-decoration: none;
    font-weight: bold;
}

.back {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #777;
    text-decoration: none;
    font-size: 14px;
}

.common-login {
    text-align: center;
    margin-top: 15px;
    font-size: 13px;
    color: #888;
}

.common-login a {
    color: #c58b00;
    font-weight: bold;
    text-decoration: none;
}

@media (max-width: 500px) {

    body {
        padding: 15px;
    }

    .login-card {
        padding: 30px 22px;
    }

    .brand {
        font-size: 24px;
    }

    h2 {
        font-size: 23px;
    }

}

</style>

</head>

<body>

<div class="login-container">

<div class="login-card">

<div class="logo">🎉</div>

<div class="brand">Event Planner</div>

<div class="subtitle">
Plan your perfect event with us
</div>

<h2>Customer Login</h2>

<?php if ($error !== ""): ?>

<div class="error">
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<form method="POST" action="">

<div class="form-group">

<label for="email">Email Address</label>

<input
type="email"
id="email"
name="email"
placeholder="Enter your email"
value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
required
>

</div>

<div class="form-group">

<label for="password">Password</label>

<input
type="password"
id="password"
name="password"
placeholder="Enter your password"
required
>

</div>

<div class="forgot">

<a href="forgot_password.php">Forgot Password?</a>

</div>

<button type="submit" class="login-btn">
🔐 Login
</button>

</form>

<div class="register">

Don't have an account?

<a href="register.php">Register Now</a>

</div>

<div class="common-login">

Want to choose Customer / Provider / Admin?

<a href="../login.php">Common Login</a>

</div>

<a href="../index.php" class="back">
← Back to Home
</a>

</div>

</div>

</body>

</html>
