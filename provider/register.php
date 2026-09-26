<?php

session_start();

require_once __DIR__ . '/../database.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $service_type = trim($_POST['service_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (
        $name === '' ||
        $email === '' ||
        $password === '' ||
        $confirm_password === ''
    ) {

        $error = "Please fill all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    }

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM providers
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {
            die("SQL Error: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param(
            "s",
            $email
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error =
                "This email is already registered as a provider.";

        }

        $stmt->close();
    }

    $citizenship_file = null;
    $selfie_file = null;

    $uploaded_files = [];

    $upload_dir =
        __DIR__ .
        '/uploads/verification/';

    if (
        $error === ''
        &&
        !is_dir($upload_dir)
    ) {

        mkdir(
            $upload_dir,
            0777,
            true
        );
    }

    if (
        $error === ''
        &&
        isset($_FILES['citizenship'])
        &&
        $_FILES['citizenship']['error']
        !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['citizenship']['error']
            !== UPLOAD_ERR_OK
        ) {

            $error =
                "Citizenship upload failed.";

        } else {

            $allowed_extensions = [
                'jpg',
                'jpeg',
                'png',
                'webp',
                'pdf'
            ];

            $extension = strtolower(
                pathinfo(
                    $_FILES['citizenship']['name'],
                    PATHINFO_EXTENSION
                )
            );

            if (
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {

                $error =
                    "Citizenship must be JPG, PNG, WEBP or PDF.";

            } elseif (
                $_FILES['citizenship']['size']
                > 5 * 1024 * 1024
            ) {

                $error =
                    "Citizenship file must be smaller than 5MB.";

            } else {

                $citizenship_file =
                    'citizenship_' .
                    time() .
                    '_' .
                    uniqid() .
                    '.' .
                    $extension;

                $target =
                    $upload_dir .
                    $citizenship_file;

                if (
                    move_uploaded_file(
                        $_FILES['citizenship']['tmp_name'],
                        $target
                    )
                ) {

                    $uploaded_files[] =
                        $target;

                } else {

                    $error =
                        "Could not save citizenship file.";

                    $citizenship_file = null;
                }
            }
        }
    }

    if (
        $error === ''
        &&
        isset($_FILES['selfie'])
        &&
        $_FILES['selfie']['error']
        !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['selfie']['error']
            !== UPLOAD_ERR_OK
        ) {

            $error =
                "Selfie upload failed.";

        } else {

            $allowed_extensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            $extension = strtolower(
                pathinfo(
                    $_FILES['selfie']['name'],
                    PATHINFO_EXTENSION
                )
            );

            if (
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {

                $error =
                    "Selfie must be JPG, PNG or WEBP.";

            } elseif (
                $_FILES['selfie']['size']
                > 5 * 1024 * 1024
            ) {

                $error =
                    "Selfie must be smaller than 5MB.";

            } else {

                $selfie_file =
                    'selfie_' .
                    time() .
                    '_' .
                    uniqid() .
                    '.' .
                    $extension;

                $target =
                    $upload_dir .
                    $selfie_file;

                if (
                    move_uploaded_file(
                        $_FILES['selfie']['tmp_name'],
                        $target
                    )
                ) {

                    $uploaded_files[] =
                        $target;

                } else {

                    $error =
                        "Could not save selfie.";

                    $selfie_file = null;
                }
            }
        }
    }

    if ($error === '') {

        $status = "pending";

        $stmt = $conn->prepare("
            INSERT INTO providers
            (
                name,
                email,
                phone,
                password,
                service_type,
                address,
                bio,
                profile_photo,
                status,
                citizenship_file,
                selfie_file
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)
        ");

        if (!$stmt) {

            die(
                "SQL Error: " .
                htmlspecialchars($conn->error)
            );
        }

        $stmt->bind_param(
            "ssssssssss",
            $name,
            $email,
            $phone,
            $password,
            $service_type,
            $address,
            $bio,
            $status,
            $citizenship_file,
            $selfie_file
        );

        if ($stmt->execute()) {

            $stmt->close();

            $success =
                "Registration successful! Your account is pending admin verification.";

            $_POST = [];

        } else {

            $error =
                "Registration failed: " .
                $stmt->error;

            $stmt->close();

            foreach (
                $uploaded_files
                as $file
            ) {

                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    if (
        $error !== ''
        &&
        !empty($uploaded_files)
    ) {

        foreach (
            $uploaded_files
            as $file
        ) {

            if (file_exists($file)) {
                unlink($file);
            }
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
    Provider Registration - Event Planner
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #fff8f0;

    color: #4b3621;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}

.container {

    width: 100%;

    max-width: 850px;

    margin: 35px auto;

    padding: 20px;
}

.card {

    background: white;

    padding: 35px;

    border-radius: 16px;

    box-shadow:
        0 8px 30px
        rgba(0,0,0,0.10);
}

.logo {

    text-align: center;

    font-size: 50px;

    margin-bottom: 10px;
}

h1 {

    text-align: center;

    margin: 0;
}

.subtitle {

    text-align: center;

    color: #777;

    margin-top: 8px;

    margin-bottom: 28px;

    line-height: 1.5;
}

.error {

    background: #f8d7da;

    color: #721c24;

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;
}

.success {

    background: #d4edda;

    color: #155724;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;

    line-height: 1.6;
}

.form-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 18px;
}

.form-group {

    margin-bottom: 5px;
}

.full {

    grid-column: 1 / -1;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 7px;
}

input,
textarea,
select {

    width: 100%;

    padding: 12px;

    border:
        1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    font-family: Arial, sans-serif;
}

textarea {

    min-height: 110px;

    resize: vertical;
}

input:focus,
textarea:focus,
select:focus {

    outline: none;

    border-color: #d4af37;

    box-shadow:
        0 0 0 2px
        rgba(212,175,55,0.15);
}

.help {

    color: #777;

    font-size: 12px;

    margin-top: 5px;

    line-height: 1.5;
}

button {

    width: 100%;

    border: none;

    background: #d4af37;

    color: white;

    padding: 14px;

    border-radius: 8px;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    margin-top: 25px;
}

button:hover {

    background: #b8860b;
}

.login-link {

    text-align: center;

    margin-top: 22px;
}

.login-link a {

    color: #b8860b;

    font-weight: bold;

    text-decoration: none;
}

.note {

    background: #fff8e8;

    padding: 13px;

    border-radius: 8px;

    margin-top: 20px;

    color: #6b4f2a;

    font-size: 13px;

    line-height: 1.6;
}

@media(max-width:650px) {

    .container {

        margin: 15px auto;

        padding: 12px;
    }

    .card {

        padding: 22px;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .full {

        grid-column: auto;
    }

}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="logo">
    👨‍💼
</div>

<h1>
    Provider Registration
</h1>

<div class="subtitle">

    Create your Event Planner provider account
    and offer your services to customers.

</div>

<?php if ($error !== ''): ?>

<div class="error">

    ❌
    <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) ?>

</div>

<?php endif; ?>

<?php if ($success !== ''): ?>

<div class="success">

    ✅
    <?= htmlspecialchars(
        $success,
        ENT_QUOTES,
        'UTF-8'
    ) ?>

    <br><br>

    Please wait for admin approval before logging in.

</div>

<?php endif; ?>

<?php if ($success === ''): ?>

<form
    method="POST"
    enctype="multipart/form-data"
>

<div class="form-grid">

<div class="form-group">

<label for="name">
    Full Name *
</label>

<input
    type="text"
    id="name"
    name="name"
    placeholder="Enter your full name"
    value="<?= htmlspecialchars(
        $_POST['name'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    required
>

</div>

<div class="form-group">

<label for="email">
    Email *
</label>

<input
    type="email"
    id="email"
    name="email"
    placeholder="Enter your email"
    value="<?= htmlspecialchars(
        $_POST['email'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    required
>

</div>

<div class="form-group">

<label for="phone">
    Phone
</label>

<input
    type="text"
    id="phone"
    name="phone"
    placeholder="98XXXXXXXX"
    value="<?= htmlspecialchars(
        $_POST['phone'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

</div>

<div class="form-group">

<label for="service_type">
    Service Type
</label>

<input
    type="text"
    id="service_type"
    name="service_type"
    placeholder="Example: Catering, DJ, Makeup"
    value="<?= htmlspecialchars(
        $_POST['service_type'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

</div>

<div class="form-group full">

<label for="address">
    Address
</label>

<input
    type="text"
    id="address"
    name="address"
    placeholder="Enter your address"
    value="<?= htmlspecialchars(
        $_POST['address'] ?? '',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

</div>

<div class="form-group full">

<label for="bio">
    About Your Service
</label>

<textarea
    id="bio"
    name="bio"
    placeholder="Tell customers about your experience and services..."
><?= htmlspecialchars(
    $_POST['bio'] ?? '',
    ENT_QUOTES,
    'UTF-8'
) ?></textarea>

</div>

<div class="form-group">

<label for="password">
    Password *
</label>

<input
    type="password"
    id="password"
    name="password"
    placeholder="Minimum 6 characters"
    required
>

</div>

<div class="form-group">

<label for="confirm_password">
    Confirm Password *
</label>

<input
    type="password"
    id="confirm_password"
    name="confirm_password"
    placeholder="Enter password again"
    required
>

</div>

<div class="form-group">

<label for="citizenship">
    🪪 Citizenship
</label>

<input
    type="file"
    id="citizenship"
    name="citizenship"
    accept=".jpg,.jpeg,.png,.webp,.pdf"
>

<div class="help">

JPG, JPEG, PNG, WEBP or PDF.
Maximum 5MB.

</div>

</div>

<div class="form-group">

<label for="selfie">
    🤳 Selfie
</label>

<input
    type="file"
    id="selfie"
    name="selfie"
    accept=".jpg,.jpeg,.png,.webp"
>

<div class="help">

Clear selfie photo.
Maximum 5MB.

</div>

</div>

</div>

<div class="note">

<strong>Important:</strong>

After registration, your provider account will have
<strong>Pending</strong> status.

Admin must verify your citizenship and selfie
before your account becomes active.

</div>

<button type="submit">

    📝 Create Provider Account

</button>

</form>

<?php endif; ?>

<div class="login-link">

    Already have a provider account?

    <a href="login.php">
        Login here
    </a>

</div>

</div>

</div>

</body>

</html>