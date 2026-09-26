<?php
session_start();
require_once "database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || $phone === "" || $email === "" || $password === "" || $confirm_password === "") {
        $message = "Please fill all required fields.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";

    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";

    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $message_type = "error";

    } else {

        // Check existing email
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "This email is already registered.";
            $message_type = "error";

        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            /*
             * One account can be used as both
             * Customer and Provider.
             */
            $role = "customer";

            $stmt = $conn->prepare(
                "INSERT INTO users (name, phone, email, password, role)
                 VALUES (?, ?, ?, ?, ?)"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "sssss",
                    $name,
                    $phone,
                    $email,
                    $hashed_password,
                    $role
                );

                if ($stmt->execute()) {

                    $_SESSION["user_id"] = $stmt->insert_id;
                    $_SESSION["user_name"] = $name;
                    $_SESSION["user_email"] = $email;
                    $_SESSION["user_role"] = $role;

                    header("Location: index.php");
                    exit;

                } else {
                    $message = "Registration failed. Please try again.";
                    $message_type = "error";
                }

                $stmt->close();

            } else {
                $message = "Database error: " . $conn->error;
                $message_type = "error";
            }
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account - Event Planner</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #fff8e7, #ffe4ec);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px;
        }

        .container {
            width: 100%;
            max-width: 480px;
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
            box-shadow: 0 10px 35px rgba(0,0,0,0.12);
        }

        .card h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            color: #444;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd;
            border-radius: 9px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #d4af37, #b8860b);
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 8px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            text-align: center;
        }

        .error {
            background: #ffe5e5;
            color: #c62828;
        }

        .login {
            text-align: center;
            margin-top: 22px;
            color: #666;
        }

        .login a {
            color: #b8860b;
            font-weight: bold;
            text-decoration: none;
        }

        .login a:hover {
            text-decoration: underline;
        }

        .required {
            color: red;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="logo">
        <h1>Event Planner</h1>
        <p>Plan your perfect event with us</p>
    </div>

    <div class="card">

        <h2>Create your account</h2>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label>
                    Full Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>
                    Phone Number <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="phone"
                    placeholder="98XXXXXXXX"
                    value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>
                    Email <span class="required">*</span>
                </label>

                <input
                    type="email"
                    name="email"
                    placeholder="example@gmail.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>
                    Password <span class="required">*</span>
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Minimum 6 characters"
                    required
                >
            </div>

            <div class="form-group">
                <label>
                    Confirm Password <span class="required">*</span>
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    required
                >
            </div>

            <button type="submit" class="btn">
                Create Account
            </button>

        </form>

        <div class="login">
            Already have an account?
            <a href="login.php">Login</a>
        </div>

    </div>

</div>

</body>
</html>