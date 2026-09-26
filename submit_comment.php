<?php

session_start();

require_once "database.php";

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: comment.php");
    exit;
}

$user_id = isset($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;

$user_name = trim($_POST['user_name'] ?? '');

$comment = trim($_POST['comment'] ?? '');

$service_id = isset($_POST['service_id'])
    ? (int) $_POST['service_id']
    : 0;

$service_name = trim($_POST['service_name'] ?? '');

$review_id = isset($_POST['review_id'])
    ? (int) $_POST['review_id']
    : 0;

if ($user_id <= 0) {
    die("Invalid user.");
}

if ($user_name === '') {
    die("User name is required.");
}

if ($comment === '') {
    die("Please write a comment.");
}

$service_id_value = ($service_id > 0)
    ? $service_id
    : null;

$service_name_value = ($service_name !== '')
    ? $service_name
    : null;

$review_id_value = ($review_id > 0)
    ? $review_id
    : null;

$sql = "
    INSERT INTO comments 
    (
        user_id, 
        user_name, 
        comment, 
        service_id, 
        service_name, 
        review_id, 
        status
    ) 
    VALUES 
    (?, ?, ?, ?, ?, ?, 'Pending')
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Error: " . $conn->error);
}

$stmt->bind_param(
    "issisi",
    $user_id,
    $user_name,
    $comment,
    $service_id_value,
    $service_name_value,
    $review_id_value
);

if ($stmt->execute()) {

    echo "
    <!DOCTYPE html>

    <html>

    <head>

        <meta charset='UTF-8'>

        <meta name='viewport' 
              content='width=device-width, initial-scale=1.0'>

        <title>Comment Submitted</title>

        <style>

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #fffaf2;
            }

            .box {
                width: 450px;
                max-width: 90%;
                margin: 100px auto;
                background: white;
                padding: 35px;
                text-align: center;
                border-radius: 15px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.10);
            }

            h2 {
                color: #b8860b;
            }

            p {
                color: #666;
                line-height: 1.6;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #d4af37;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
            }

            a:hover {
                background: #b8860b;
            }

        </style>

    </head>

    <body>

        <div class='box'>

            <h2>✅ Comment Submitted!</h2>

            <p>
                Your comment has been submitted successfully.
            </p>

            <p>
                It is currently waiting for admin approval.
            </p>

            <a href='index.php'>
                ← Back
            </a>

        </div>

    </body>

    </html>
    ";

} else {

    die(
        "Failed to submit comment: " 
        . htmlspecialchars($stmt->error)
    );
}

$stmt->close();

$conn->close();

?>