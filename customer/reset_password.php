<?php
session_start();
require_once "../database.php";

$error = "";
$success = "";

$token = $_GET["token"] ?? $_POST["token"] ?? "";

$validToken = false;

if (
    $token !== "" &&
    isset($_SESSION["password_reset_token"]) &&
    isset($_SESSION["password_reset_user_id"]) &&
    isset($_SESSION["password_reset_time"])
) {

    if (
        hash_equals(
            $_SESSION["password_reset_token"],
            $token
        )
    ) {

        $resetTime = $_SESSION["password_reset_time"];

        if ((time() - $resetTime) <= 900) {
            $validToken = true;
        } else {
            $error = "This password reset link has expired. Please request a new one.";
        }

    } else {
        $error = "Invalid password reset link.";
    }

} else {
    $error = "Invalid password reset link.";
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && $validToken) {

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($password === "" || $confirmPassword === "") {

        $error = "Please enter both password fields.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters long.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } else {

        $userId = $_SESSION["password_reset_user_id"];

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
            AND role = 'customer'
        ");

        $stmt->bind_param(
            "si",
            $hashedPassword,
            $userId
        );

        if ($stmt->execute()) {

            unset($_SESSION["password_reset_token"]);
            unset($_SESSION["password_reset_user_id"]);
            unset($_SESSION["password_reset_email"]);
            unset($_SESSION["password_reset_time"]);

            $success = "Your password has been changed successfully.";

            $validToken = false;

        } else {

            $error = "Unable to update your password. Please try again.";
        }

        $stmt->close();
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

    <title>Reset Password - Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            font-family: Arial, sans-serif;

            background: linear-gradient(
                135deg,
                #fffaf0,
                #ffe4ec,
                #fff4c4
            );

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;
        }

