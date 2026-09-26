<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["user_id"];

$success = "";
$error = "";

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        address
    FROM users
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Customer account not found.");
}

$user = $result->fetch_assoc();

$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if ($name === "") {

        $error = "Please enter your full name.";

    } elseif (strlen($name) < 2) {

        $error = "Name must contain at least 2 characters.";

    } elseif ($phone !== "" && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {

        $error = "Please enter a valid phone number.";

    } else {

        $update_sql = "
            UPDATE users
            SET
                name = ?,
                phone = ?,
                address = ?
            WHERE id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);

        if (!$update_stmt) {

            $error =
                "Database Error: " .
                htmlspecialchars($conn->error);

        } else {

            $update_stmt->bind_param(
                "sssi",
                $name,
                $phone,
                $address,
                $customer_id
            );

            if ($update_stmt->execute()) {

                $success =
                    "Your profile has been updated successfully.";

                $user["name"] = $name;
                $user["phone"] = $phone;
                $user["address"] = $address;

            } else {

                $error =
                    "Unable to update your profile. Please try again.";

            }

            $update_stmt->close();
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
    Edit Profile - Event Planner
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #fff8f2;

    color: #555;
}

.header {

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f3d36a
        );

    padding: 20px 40px;

    color: white;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.header h2 {

    margin: 0;

    font-size: 24px;
}

.back-btn {

    text-decoration: none;

    background: #b8860b;

    color: white;

    padding: 10px 18px;

    border-radius: 8px;

    font-weight: bold;
}

.back-btn:hover {

    background: #8f6908;
}

.container {

    width: 92%;

    max-width: 700px;

    margin: 45px auto;
}

.title {

    text-align: center;

    margin-bottom: 30px;
}

.title h1 {

    color: #b8860b;

    margin-bottom: 8px;
}

.title p {

    color: #777;

    margin: 0;
}

.card {

    background: white;

    border-radius: 18px;

    padding: 35px;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,0.08);
}

.alert {

    padding: 14px 17px;

    border-radius: 9px;

    margin-bottom: 22px;

    line-height: 1.5;

    font-weight: bold;
}

.success {

    background: #dff5df;

    color: #287a28;

    border-left: 4px solid #287a28;
}

.error {

    background: #ffe1e1;

    color: #b00020;

    border-left: 4px solid #b00020;
}

.form-group {

    margin-bottom: 22px;
}

.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #555;

    font-weight: bold;
}

.form-group input,
.form-group textarea {

    width: 100%;

    padding: 13px 15px;

    border: 1px solid #ddd;

    border-radius: 9px;

    font-size: 15px;

    font-family: Arial, sans-serif;

    outline: none;

    transition: 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {

    border-color: #d4af37;

    box-shadow:
        0 0 0 3px
        rgba(212,175,55,0.12);
}

.form-group textarea {

    min-height: 100px;

    resize: vertical;
}

.readonly {

    background: #f5f5f5;

    color: #888;

    cursor: not-allowed;
}

.helper {

    font-size: 12px;

    color: #999;

    margin-top: 6px;
}

.required {

    color: #b00020;
}

.actions {

    display: flex;

    gap: 12px;

    margin-top: 28px;
}

.btn {

    flex: 1;

    padding: 13px 20px;

    border-radius: 9px;

    text-decoration: none;

    text-align: center;

    font-weight: bold;

    border: none;

    cursor: pointer;

    font-size: 15px;
}

.save-btn {

    background: #d4af37;

    color: white;
}

.save-btn:hover {

    background: #b8860b;
}

.cancel-btn {

    background: #f1f1f1;

    color: #666;

    border: 1px solid #ddd;
}

.cancel-btn:hover {

    background: #e5e5e5;
}

.security-note {

    margin-top: 25px;

    padding: 15px;

    background: #fff8e1;

    border-left: 4px solid #d4af37;

    border-radius: 8px;

    color: #777;

    font-size: 13px;

    line-height: 1.6;
}

@media (max-width: 600px) {

    .header {

        padding: 18px 20px;

        flex-direction: column;

        align-items: flex-start;
    }

    .container {

        width: 94%;

        margin: 25px auto;
    }

    .card {

        padding: 25px 20px;
    }

    .actions {

        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="header">

    <h2>
        Event Planner
    </h2>

    <a
        href="profile.php"
        class="back-btn"
    >
        ← My Profile
    </a>

</div>

<div class="container">

    <div class="title">

        <h1>
            ✏️ Edit Profile
        </h1>

        <p>
            Update your personal information
        </p>

    </div>

    <div class="card">

        <?php if ($success !== ""): ?>

            <div class="alert success">

                ✓ <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>

        <?php if ($error !== ""): ?>

            <div class="alert error">

                ⚠ <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
        >

            <div class="form-group">

                <label for="name">

                    Full Name
                    <span class="required">*</span>

                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars(
                        $user["name"] ?? ""
                    ) ?>"
                    placeholder="Enter your full name"
                    required
                >

            </div>

            <div class="form-group">

                <label for="email">

                    Email Address

                </label>

                <input
                    type="email"
                    id="email"
                    value="<?= htmlspecialchars(
                        $user["email"] ?? ""
                    ) ?>"
                    class="readonly"
                    readonly
                >

                <div class="helper">

                    Email address cannot be changed here.

                </div>

            </div>

            <div class="form-group">

                <label for="phone">

                    Phone Number

                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?= htmlspecialchars(
                        $user["phone"] ?? ""
                    ) ?>"
                    placeholder="Enter your phone number"
                >

            </div>

            <div class="form-group">

                <label for="address">

                    Address

                </label>

                <textarea
                    id="address"
                    name="address"
                    placeholder="Enter your address"
                ><?= htmlspecialchars(
                    $user["address"] ?? ""
                ) ?></textarea>

            </div>

            <div class="actions">

                <a
                    href="profile.php"
                    class="btn cancel-btn"
                >

                    Cancel

                </a>

                <button
                    type="submit"
                    class="btn save-btn"
                >

                    ✓ Save Changes

                </button>

            </div>

        </form>

        <div class="security-note">

            🔒 <strong>Security:</strong>

            Your email address is kept unchanged from this page.
            To change your password, use the
            <strong>Change Password</strong> option from your profile.

        </div>

    </div>

</div>

</body>

</html>