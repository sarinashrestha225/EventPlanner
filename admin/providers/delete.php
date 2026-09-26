<?php

session_start();

require_once "database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $message = "Please fill all required fields.";
        $message_type = "error";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    }

    elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    }

    elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $message_type = "error";
    }

    elseif (
        !isset($_FILES["citizenship"]) ||
        $_FILES["citizenship"]["error"] !== UPLOAD_ERR_OK
    ) {
        $message = "Citizenship document is compulsory.";
        $message_type = "error";
    }

    elseif (
        !isset($_FILES["selfie"]) ||
        $_FILES["selfie"]["error"] !== UPLOAD_ERR_OK
    ) {
        $message = "Selfie is compulsory.";
        $message_type = "error";
    }

    else {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "This email is already registered.";
            $message_type = "error";

        } else {

            $citizenship_dir = "uploads/citizenship/";
            $selfie_dir = "uploads/selfie/";

            if (!is_dir($citizenship_dir)) {
                mkdir($citizenship_dir, 0777, true);
            }

            if (!is_dir($selfie_dir)) {
                mkdir($selfie_dir, 0777, true);
            }

            $citizenship_name = $_FILES["citizenship"]["name"];
            $citizenship_tmp = $_FILES["citizenship"]["tmp_name"];

            $selfie_name = $_FILES["selfie"]["name"];
            $selfie_tmp = $_FILES["selfie"]["tmp_name"];

            $citizenship_ext = strtolower(
                pathinfo($citizenship_name, PATHINFO_EXTENSION)
            );

            $selfie_ext = strtolower(
                pathinfo($selfie_name, PATHINFO_EXTENSION)
            );

            $allowed_documents = [
                "jpg",
                "jpeg",
                "png",
                "pdf"
            ];

            $allowed_images = [
                "jpg",
                "jpeg",
                "png"
            ];

            if (!in_array($citizenship_ext, $allowed_documents)) {

                $message = "Citizenship must be JPG, JPEG, PNG or PDF.";
                $message_type = "error";

            }

            elseif (!in_array($selfie_ext, $allowed_images)) {

                $message = "Selfie must be JPG, JPEG or PNG.";
                $message_type = "error";

            }

            else {

                $unique_id = time() . "_" . bin2hex(random_bytes(4));

                $citizenship_file =
                    $unique_id . "_citizenship." . $citizenship_ext;

                $selfie_file =
                    $unique_id . "_selfie." . $selfie_ext;

                $citizenship_path =
                    $citizenship_dir . $citizenship_file;

                $selfie_path =
                    $selfie_dir . $selfie_file;

                $citizenship_uploaded = move_uploaded_file(
                    $citizenship_tmp,
                    $citizenship_path
                );

                $selfie_uploaded = move_uploaded_file(
                    $selfie_tmp,
                    $selfie_path
                );

                if (!$citizenship_uploaded || !$selfie_uploaded) {

                    $message = "File upload failed. Please try again.";
                    $message_type = "error";

                } else {

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "INSERT INTO users
                        (
                            name,
                            email,
                            phone,
                            password,
                            citizenship_file,
                            selfie_file,
                            role,
                            provider_status,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, 'customer', 'none', 'active')"
                    );

                    $stmt->bind_param(
                        "ssssss",
                        $name,
                        $email,
                        $phone,
                        $hashed_password,
                        $citizenship_path,
                        $selfie_path
                    );

                    if ($stmt->execute()) {

                        $message = "Registration successful! You can now login.";
                        $message_type = "success";

                    } else {

                        $message = "Registration failed. Please try again.";
                        $message_type = "error";

                        if (file_exists($citizenship_path)) {
                            unlink($citizenship_path);
                        }

                        if (file_exists($selfie_path)) {
                            unlink($selfie_path);
                        }
                    }

                    $stmt->close();
                }
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

    <title>Register - Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff8f0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px;
        }

        .register-box {
            width: 100%;
            max-width: 600px;
            background: #ffffff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.12);
            border-top: 6px solid #d4af37;
        }

        .logo {
            text-align: center;
            color: #c2185b;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #444;
        }

        .required {
            color: red;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #d4af37;
        }

        .file-box {
            background: #fff7fb;
            border: 1px dashed #d4af37;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .file-box input {
            margin-bottom: 0;
        }

        .note {
            font-size: 13px;
            color: #777;
            margin-top: -12px;
            margin-bottom: 18px;
        }

        button {
            width: 100%;
            border: none;
            padding: 14px;
            border-radius: 9px;
            background: linear-gradient(
                135deg,
                #d4af37,
                #f1d36b
            );
            color: #3d2b00;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.9;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #e8f8ed;
            color: #1b6b35;
        }

        .error {
            background: #ffe8e8;
            color: #a00000;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: #c2185b;
            font-weight: bold;
            text-decoration: none;
        }

        .security {
            margin-top: 20px;
            padding: 12px;
            background: #fff8e1;
            border-radius: 8px;
            font-size: 13px;
            color: #6b5700;
            text-align: center;
        }

    </style>

</head>

<body>

<div class="register-box">

    <div class="logo">
        Event Planner
    </div>

    <div class="subtitle">
        Create your account
    </div>

    <?php if (!empty($message)): ?>

        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>
            Full Name <span class="required">*</span>
        </label>

        <input
            type="text"
            name="name"
            placeholder="Enter your full name"
            required
            value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
        >

        <label>
            Email <span class="required">*</span>
        </label>

        <input
            type="email"
            name="email"
            placeholder="Enter your email"
            required
            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
        >

        <label>
            Phone Number <span class="required">*</span>
        </label>

        <input
            type="text"
            name="phone"
            placeholder="98XXXXXXXX"
            required
            value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>"
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

        <label>
            Citizenship <span class="required">*</span>
        </label>

        <div class="file-box">

            <input
                type="file"
                name="citizenship"
                accept=".jpg,.jpeg,.png,.pdf"
                required
            >

        </div>

        <div class="note">
            JPG, JPEG, PNG or PDF only.
        </div>

        <label>
            Selfie <span class="required">*</span>
        </label>

        <div class="file-box">

            <input
                type="file"
                name="selfie"
                accept=".jpg,.jpeg,.png"
                required
            >

        </div>

        <div class="note">
            Upload a clear face photo.
        </div>

        <button type="submit">
            Create Account
        </button>

    </form>

    <div class="security">
        🔐 Your citizenship and selfie are required for account verification.
    </div>

    <div class="login-link">

        Already have an account?

        <a href="login.php">
            Login
        </a>

    </div>

</div>

</body>

</html>