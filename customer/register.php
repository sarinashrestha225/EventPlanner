
<?php
session_start();
require_once "../database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validation
    if ($name === "" || $email === "" || $password === "") {

        $error = "Please fill all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        // Check email in CUSTOMERS table
        $check = $conn->prepare("
            SELECT id
            FROM customers
            WHERE email = ?
            LIMIT 1
        ");

        if (!$check) {
            $error = "Database error: " . $conn->error;
        } else {

            $check->bind_param("s", $email);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $error = "This email is already registered.";

            } else {

                // Hash password
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert customer
                $stmt = $conn->prepare("
                    INSERT INTO customers
                    (name, email, phone, password, address, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");

                if (!$stmt) {

                    $error = "Database error: " . $conn->error;

                } else {

                    $stmt->bind_param(
                        "sssss",
                        $name,
                        $email,
                        $phone,
                        $hashed_password,
                        $address
                    );

                    if ($stmt->execute()) {

                        $success = "Registration successful! You can login now.";

                        // Clear form values
                        $name = "";
                        $email = "";
                        $phone = "";
                        $address = "";

                    } else {

                        $error = "Registration failed. Please try again.";

                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Customer Registration - Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff8f2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .container {
            width: 100%;
            max-width: 450px;
            background: #ffffff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.10);
        }

        h2 {
            text-align: center;
            color: #c49a00;
            margin: 0 0 8px;
        }

        h3 {
            text-align: center;
            color: #555;
            margin: 0 0 25px;
            font-weight: normal;
        }

        label {
            display: block;
            margin-top: 14px;
            font-weight: bold;
            color: #555;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #d4af37;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 24px;
            background: #d4af37;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #b8860b;
        }

        .error {
            background: #ffe1e1;
            color: #b00020;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .success {
            background: #e1f5d8;
            color: #397326;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .login {
            text-align: center;
            margin-top: 20px;
            color: #555;
        }

        a {
            color: #b8860b;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }

        .required {
            color: #c49a00;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Event Planner</h2>

    <h3>Customer Registration</h3>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label>
            Name <span class="required">*</span>
        </label>

        <input
            type="text"
            name="name"
            value="<?= htmlspecialchars($name ?? "") ?>"
            placeholder="Enter your full name"
            required
        >

        <label>
            Email <span class="required">*</span>
        </label>

        <input
            type="email"
            name="email"
            value="<?= htmlspecialchars($email ?? "") ?>"
            placeholder="Enter your email"
            required
        >

        <label>
            Phone
        </label>

        <input
            type="text"
            name="phone"
            value="<?= htmlspecialchars($phone ?? "") ?>"
            placeholder="Enter your phone number"
        >

        <label>
            Address
        </label>

        <input
            type="text"
            name="address"
            value="<?= htmlspecialchars($address ?? "") ?>"
            placeholder="Enter your address"
        >

        <label>
            Password <span class="required">*</span>
        </label>

        <input
            type="password"
            name="password"
            placeholder="Minimum 6 characters"
            required
        >

        <label>
            Confirm Password <span class="required">*</span>
        </label>

        <input
            type="password"
            name="confirm_password"
            placeholder="Re-enter password"
            required
        >

        <button type="submit">
            Create Account
        </button>

    </form>

    <div class="login">

        Already have an account?

        <a href="login.php">
            Login
        </a>

    </div>

</div>

</body>
</html>

