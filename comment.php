<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';

$service_id = isset($_GET['service_id'])
    ? (int) $_GET['service_id']
    : 0;

$service_name = $_GET['service_name'] ?? '';

$service_name = htmlspecialchars($service_name);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Write Comment | Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf2;
        }

        .container {
            width: 500px;
            max-width: 95%;
            margin: 60px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.10);
        }

        h2 {
            text-align: center;
            color: #b8860b;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 7px;
            font-weight: bold;
            color: #555;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #d4af37;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        .service {
            background: #fff0f5;
            padding: 12px;
            border-radius: 8px;
            color: #8b5e5e;
            font-weight: bold;
        }

        button {
            width: 100%;
            margin-top: 25px;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #d4af37;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #b8860b;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>💬 Write a Comment</h2>

    <form action="submit_comment.php" method="POST">

        <input
            type="hidden"
            name="user_id"
            value="<?php echo (int)$user_id; ?>"
        >

        <input
            type="hidden"
            name="user_name"
            value="<?php echo htmlspecialchars($user_name); ?>"
        >

        <input
            type="hidden"
            name="service_id"
            value="<?php echo $service_id; ?>"
        >

        <input
            type="hidden"
            name="service_name"
            value="<?php echo $service_name; ?>"
        >

        <label>User</label>

        <input
            type="text"
            value="<?php echo htmlspecialchars($user_name); ?>"
            readonly
        >

        <label>Related Service</label>

        <div class="service">

            📌
            <?php
            echo !empty($service_name)
                ? $service_name
                : "General Service";
            ?>

        </div>

        <label>Your Comment</label>

        <textarea
            name="comment"
            placeholder="Write your comment..."
            required
        ></textarea>

        <button type="submit">
            💬 Submit Comment
        </button>

    </form>

</div>

</body>

</html>