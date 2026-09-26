<?php

session_start();

require_once "../database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $password === "") {

        $error = "Please enter admin name and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM admins
             WHERE name = ? AND status = 'active'
             LIMIT 1"
        );

        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param("s", $name);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {

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

                if ($password_valid) {

                    session_regenerate_id(true);

                    $_SESSION = [];

                    $_SESSION["user_id"] = (int)$admin["id"];
                    $_SESSION["user_role"] = "admin";
                    $_SESSION["admin_logged_in"] = true;
                    $_SESSION["admin_id"] = (int)$admin["id"];
                    $_SESSION["admin_name"] = $admin["name"];
                    $_SESSION["admin_username"] = $admin["name"];
                    $_SESSION["admin_email"] = $admin["email"] ?? "";
                    $_SESSION["active_role"] = "admin";
                    $_SESSION["role"] = "admin";

                    header("Location: dashboard.php");
                    exit();

                } else {

                    $error = "Incorrect password.";
                }

            } else {

                $error = "Admin account not found.";
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

<title>Event Planner - Admin Login</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family: "Segoe UI", Arial, sans-serif;

    background:
        radial-gradient(circle at 10% 20%, #ffd6e7 0%, transparent 28%),
        radial-gradient(circle at 90% 80%, #ffe58a 0%, transparent 30%),
        linear-gradient(135deg, #fff8ed, #fff1f6);

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 20px;
}

.login-wrapper {

    width: 950px;

    min-height: 560px;

    display: flex;

    overflow: hidden;

    border-radius: 30px;

    background: #fffdf8;

    box-shadow:
        0 25px 70px rgba(184, 132, 42, 0.18),
        0 5px 20px rgba(255, 182, 193, 0.25);

    border: 2px solid #f5d98b;
}

.left-section {

    width: 48%;

    background:
        linear-gradient(
            145deg,
            #ffd6e7,
            #ffe9a8,
            #fff3d6
        );

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    text-align: center;

    padding: 50px;

    position: relative;

    overflow: hidden;
}

.left-section::before {

    content: "";

    position: absolute;

    width: 220px;

    height: 220px;

    border-radius: 50%;

    background: rgba(255,255,255,0.35);

    top: -80px;

    left: -70px;
}

.left-section::after {

    content: "";

    position: absolute;

    width: 180px;

    height: 180px;

    border-radius: 50%;

    background: rgba(255,255,255,0.3);

    bottom: -70px;

    right: -60px;
}

.logo-circle {

    width: 110px;

    height: 110px;

    border-radius: 50%;

    background: #fffdf8;

    border: 4px solid #d6a62c;

    display: flex;

    justify-content: center;

    align-items: center;

    font-size: 45px;

    margin-bottom: 25px;

    box-shadow: 0 8px 25px rgba(180,130,30,0.18);

    position: relative;

    z-index: 2;
}

.brand {

    font-family: Georgia, serif;

    font-size: 38px;

    color: #b8860b;

    font-weight: bold;

    position: relative;

    z-index: 2;
}

.tagline {

    margin-top: 12px;

    color: #704c56;

    font-size: 16px;

    line-height: 1.6;

    position: relative;

    z-index: 2;
}

.decor-line {

    width: 90px;

    height: 3px;

    background: #d6a62c;

    margin: 22px auto;

    border-radius: 10px;
}

.right-section {

    width: 52%;

    padding: 60px 70px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background: #fffdf8;
}

.login-title {

    font-family: Georgia, serif;

    font-size: 32px;

    color: #7d4b5c;

    margin-bottom: 8px;
}

.login-subtitle {

    color: #999;

    margin-bottom: 32px;

    font-size: 14px;
}

.error {

    background: #ffe1e8;

    color: #a33d55;

    border: 1px solid #f3aabd;

    padding: 12px 15px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 14px;
}

.form-group {

    margin-bottom: 20px;
}

.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #684754;

    font-weight: 600;

    font-size: 14px;
}

.input-wrapper {

    position: relative;
}

.input-wrapper span {

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    font-size: 18px;
}

input {

    width: 100%;

    padding: 14px 15px 14px 45px;

    border: 1.5px solid #edc9a6;

    border-radius: 12px;

    background: #fffaf1;

    color: #4b3a3e;

    font-size: 15px;

    transition: 0.3s;
}

input:focus {

    outline: none;

    border-color: #e5b83f;

    box-shadow: 0 0 0 4px rgba(255, 214, 231, 0.5);

    background: white;
}

.forgot {

    text-align: right;

    margin-top: -8px;

    margin-bottom: 20px;
}

.forgot a {

    color: #c98b00;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;
}

.forgot a:hover {

    text-decoration: underline;
}

.login-btn {

    width: 100%;

    padding: 15px;

    margin-top: 5px;

    border: none;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #f5c84c,
            #d9a62e
        );

    color: #fffdf5;

    font-size: 16px;

    font-weight: bold;

    letter-spacing: 1px;

    cursor: pointer;

    box-shadow: 0 8px 20px rgba(208,160,42,0.3);

    transition: 0.3s;
}

.login-btn:hover {

    transform: translateY(-2px);

    box-shadow: 0 12px 25px rgba(208,160,42,0.4);
}

.back-link {

    text-align: center;

    margin-top: 22px;
}

.back-link a {

    color: #b8860b;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;
}

.back-link a:hover {

    text-decoration: underline;
}

@media (max-width: 800px) {

    .login-wrapper {

        width: 100%;

        max-width: 500px;
    }

    .left-section {

        display: none;
    }

    .right-section {

        width: 100%;

        padding: 45px 35px;
    }
}

</style>

</head>

<body>

<div class="login-wrapper">

    <div class="left-section">

        <div class="logo-circle">
            ✦
        </div>

        <div class="brand">
            Event Planner
        </div>

        <div class="decor-line"></div>

        <div class="tagline">
            Plan Beautiful.<br>
            Celebrate Perfectly.<br>
            Make Every Moment Special.
        </div>

    </div>

    <div class="right-section">

        <div class="login-title">
            Welcome Back
        </div>

        <div class="login-subtitle">
            Sign in to your Event Planner Admin Panel
        </div>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-group">

                <label for="name">
                    Admin Name
                </label>

                <div class="input-wrapper">

                    <span>👤</span>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter admin name"
                        value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <span>🔐</span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter password"
                        autocomplete="current-password"
                        required
                    >

                </div>

            </div>

            <div class="forgot">

                <a href="forgot_password.php">
                    Forgot Password?
                </a>

            </div>

            <button
                type="submit"
                class="login-btn"
            >
                LOGIN
            </button>

        </form>

        <div class="back-link">

            <a href="../index.php">
                ← Back to Event Planner
            </a>

        </div>

    </div>

</div>

</body>

</html>

