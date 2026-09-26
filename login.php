<?php

session_start();

require_once __DIR__ . "/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";

    if ($email === "" || $password === "" || $role === "") {

        $error = "Please enter all required information.";

    } else {

        if ($role === "customer") {

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

            if ($stmt) {

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows === 1) {

                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user["password"])) {

                        session_regenerate_id(true);

                        $_SESSION["user_id"] = $user["id"];
                        $_SESSION["user_name"] = $user["name"];
                        $_SESSION["user_email"] = $user["email"];
                        $_SESSION["user_phone"] = $user["phone"];
                        $_SESSION["active_role"] = "customer";
                        $_SESSION["role"] = "customer";

                        header("Location: customer/dashboard.php");
                        exit();

                    } else {

                        $error = "Incorrect password.";
                    }

                } else {

                    $error = "Customer account not found.";
                }

                $stmt->close();

            } else {

                $error = "Unable to process login.";
            }
        }

        elseif ($role === "provider") {

            $stmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    email,
                    phone,
                    password,
                    service_type,
                    address,
                    status
                FROM providers
                WHERE email = ?
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows === 1) {

                    $provider = $result->fetch_assoc();

                    if ($provider["status"] !== "active") {

                        $error = "Your provider account is not active.";

                    } else {

                        $password_valid = false;

                        if (
                            password_get_info($provider["password"])["algo"] !== 0 &&
                            password_verify($password, $provider["password"])
                        ) {
                            $password_valid = true;
                        }

                        if ($password === $provider["password"]) {
                            $password_valid = true;
                        }

                        if (!$password_valid) {

                            $error = "Incorrect password.";

                        } else {

                            session_regenerate_id(true);

                            $_SESSION["user_id"] = $provider["id"];
                            $_SESSION["user_role"] = "provider";

                            $_SESSION["provider_id"] = $provider["id"];
                            $_SESSION["provider_name"] = $provider["name"];
                            $_SESSION["provider_email"] = $provider["email"];
                            $_SESSION["provider_phone"] = $provider["phone"];
                            $_SESSION["provider_service_type"] = $provider["service_type"];
                            $_SESSION["provider_address"] = $provider["address"];

                            $_SESSION["active_role"] = "provider";
                            $_SESSION["role"] = "provider";

                            header("Location: provider/dashboard.php");
                            exit();
                        }
                    }

                } else {

                    $error = "Provider account not found.";
                }

                $stmt->close();

            } else {

                $error = "Unable to process login.";
            }
        }

        elseif ($role === "admin") {

            $stmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    email,
                    password,
                    status
                FROM admins
                WHERE email = ?
                AND status = 'active'
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows === 1) {

                    $admin = $result->fetch_assoc();

                    $password_valid = false;

                    if (
                        password_get_info($admin["password"])["algo"] !== 0 &&
                        password_verify($password, $admin["password"])
                    ) {
                        $password_valid = true;
                    }

                    if ($password === $admin["password"]) {
                        $password_valid = true;
                    }

                    if (!$password_valid) {

                        $error = "Incorrect password.";

                    } else {

                        session_regenerate_id(true);

                        $_SESSION["user_id"] = $admin["id"];
                        $_SESSION["user_role"] = "admin";

                        $_SESSION["admin_logged_in"] = true;
                        $_SESSION["admin_id"] = $admin["id"];
                        $_SESSION["admin_name"] = $admin["name"];
                        $_SESSION["admin_username"] = $admin["name"];

                        $_SESSION["active_role"] = "admin";
                        $_SESSION["role"] = "admin";

                        header("Location: admin/dashboard.php");
                        exit();
                    }

                } else {

                    $error = "Admin account not found or inactive.";
                }

                $stmt->close();

            } else {

                $error = "Unable to process login.";
            }
        }

        else {

            $error = "Please select your account type.";
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

        .login-box {
            width: 420px;
            max-width: 92%;
            background: #ffffff;
            padding: 38px;
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.12);
        }

        .brand-icon {
            text-align: center;
            font-size: 42px;
            margin-bottom: 8px;
        }

        .brand-name {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            color: #4b3621;
            margin-bottom: 5px;
        }

        .tagline {
            text-align: center;
            color: #a67c00;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 28px;
        }

        .welcome {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #4b3621;
            margin-bottom: 7px;
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
            font-size: 14px;
            font-weight: bold;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #4b3621;
        }

        .input-group {
            position: relative;
            margin-bottom: 18px;
        }

        .input-icon {
            position: absolute;
            left: 13px;
            top: 13px;
            font-size: 17px;
        }

        input,
        select {
            width: 100%;
            padding: 13px 13px 13px 42px;
            border: 1px solid #ddd;
            border-radius: 9px;
            font-size: 15px;
            background: #fff;
        }

        select {
            padding-left: 42px;
            cursor: pointer;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
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
            transition: 0.2s;
        }

        button:hover {
            background: #b8860b;
        }

        .forgot {
            text-align: right;
            margin-top: -8px;
            margin-bottom: 18px;
        }

        .forgot a {
            color: #b8860b;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
        }

        .forgot a:hover {
            text-decoration: underline;
        }

        .register {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #777;
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
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #6b4f2a;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .back:hover {
            color: #b8860b;
        }

        @media (max-width: 500px) {

            .login-box {
                padding: 28px 22px;
            }

            .brand-name {
                font-size: 26px;
            }

            .welcome {
                font-size: 21px;
            }
        }

    </style>

</head>

<body>

    <div class="login-box">

        <div class="brand-icon">
            ✦
        </div>

        <div class="brand-name">
            Event Planner
        </div>

        <div class="tagline">
            Plan Beautiful.<br>
            Celebrate Perfectly.<br>
            Make Every Moment Special.
        </div>

        <div class="welcome">
            Welcome Back
        </div>

        <div class="description">
            Sign in to your Event Planner account
        </div>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <label>Login As</label>

            <div class="input-group">

                <span class="input-icon">👤</span>

                <select name="role" required>

                    <option value="">Select Account Type</option>

                    <option value="customer"
                        <?= (($_POST["role"] ?? "") === "customer") ? "selected" : "" ?>>
                        Customer
                    </option>

                    <option value="provider"
                        <?= (($_POST["role"] ?? "") === "provider") ? "selected" : "" ?>>
                        Provider
                    </option>

                    <option value="admin"
                        <?= (($_POST["role"] ?? "") === "admin") ? "selected" : "" ?>>
                        Admin
                    </option>

                </select>

            </div>

            <label>Email Address</label>

            <div class="input-group">

                <span class="input-icon">📧</span>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    required
                >

            </div>

            <label>Password</label>

            <div class="input-group">

                <span class="input-icon">🔒</span>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <div class="forgot">

                <a href="#" onclick="showForgotMessage(); return false;">
                    Forgot Password?
                </a>

            </div>

            <button type="submit">
                Login
            </button>

        </form>

        <div class="register">

            Don't have a customer account?
            <a href="register.php">Create Account</a>

        </div>

        <a href="index.php" class="back">
            ← Back to Event Planner
        </a>

    </div>

    <script>

        function showForgotMessage() {

            alert(
                "Please use the Forgot Password page for your account type."
            );

        }

    </script>

</body>

</html>