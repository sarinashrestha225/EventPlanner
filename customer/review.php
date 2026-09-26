<?php

session_start();
require_once "../database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    die("Invalid booking ID.");
}

$booking_id = (int)$_GET['booking_id'];

$sql = "
    SELECT *
    FROM bookings
    WHERE id = ?
      AND customer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL ERROR: " . $conn->error);
}

$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Booking not found or this booking does not belong to you.");
}

if (strtolower($booking['status']) !== 'completed') {
    die(
        "This booking is not completed. Current status: "
        . htmlspecialchars($booking['status'])
    );
}

?>

<!DOCTYPE html>
<html>
<head>

    <title>Give Review</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #fff8f2;
            margin: 0;
            padding: 40px;
        }

        .box {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #b8860b;
        }

        .info {
            background: #fff5d6;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        .stars {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .stars input {
            display: none;
        }

        .stars label {
            font-size: 35px;
            color: #ddd;
            cursor: pointer;
            margin: 0;
        }

        .stars input:checked ~ label {
            color: #f4c430;
        }

        textarea {
            width: 100%;
            height: 120px;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 13px;
            background: #d4af37;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #b8941f;
        }

    </style>

</head>

<body>

<div class="box">

    <h1>⭐ Give Review</h1>

    <div class="info">

        <p>
            <strong>Booking ID:</strong>
            <?= htmlspecialchars($booking['id']) ?>
        </p>

        <p>
            <strong>Provider ID:</strong>
            <?= htmlspecialchars($booking['provider_id'] ?? 'N/A') ?>
        </p>

        <p>
            <strong>Service ID:</strong>
            <?= htmlspecialchars($booking['service_id'] ?? 'N/A') ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars($booking['status']) ?>
        </p>

    </div>

    <form action="submit_review.php" method="POST">

        <input
            type="hidden"
            name="booking_id"
            value="<?= $booking_id ?>"
        >

        <label>Rating</label>

        <div class="stars">

            <input
                type="radio"
                id="star5"
                name="rating"
                value="5"
                required
            >
            <label for="star5">★</label>

            <input
                type="radio"
                id="star4"
                name="rating"
                value="4"
            >
            <label for="star4">★</label>

            <input
                type="radio"
                id="star3"
                name="rating"
                value="3"
            >
            <label for="star3">★</label>

            <input
                type="radio"
                id="star2"
                name="rating"
                value="2"
            >
            <label for="star2">★</label>

            <input
                type="radio"
                id="star1"
                name="rating"
                value="1"
            >
            <label for="star1">★</label>

        </div>

        <label for="comment">
            Your Comment
        </label>

        <textarea
            name="comment"
            id="comment"
            placeholder="Write your experience..."
            required
        ></textarea>

        <button type="submit">
            Submit Review
        </button>

    </form>

</div>

</body>
</html>