<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$booking_id = isset($_GET["booking_id"]) ? intval($_GET["booking_id"]) : 0;

if ($booking_id <= 0) {
    die("Invalid booking ID.");
}

$user_id = intval($_SESSION["user_id"]);

$sql = "
    SELECT 
        b.id,
        b.customer_id,
        b.amount,
        b.status,
        b.payment_status,
        e.name AS event_name,
        s.name AS service_name
    FROM bookings b
    LEFT JOIN events e 
        ON b.event_type_id = e.id
    LEFT JOIN services s 
        ON b.service_id = s.id
    WHERE b.id = ?
    AND b.customer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();

if (!$booking) {
    die("Booking not found or you do not have permission to pay for this booking.");
}

$amount = floatval($booking["amount"]);

if ($amount <= 0) {
    die("Invalid booking or payment amount.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Make Payment - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f5;
}

.container {
    width: 90%;
    max-width: 700px;
    margin: 50px auto;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 18px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.10);
}

h1 {
    text-align: center;
    color: #b8860b;
    margin-bottom: 25px;
}

.info {
    background: #fff4dc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.info p {
    margin: 10px 0;
    font-size: 16px;
}

.amount {
    font-size: 28px;
    font-weight: bold;
    color: #d4af37;
}

label {
    display: block;
    margin-top: 15px;
    margin-bottom: 8px;
    font-weight: bold;
}

select,
input {
    width: 100%;
    padding: 13px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
}

button {
    width: 100%;
    margin-top: 25px;
    padding: 14px;
    border: none;
    border-radius: 8px;
    background: #d4af37;
    color: white;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #b8860b;
}

.back {
    display: block;
    text-align: center;
    margin-top: 20px;
    text-decoration: none;
    color: #b8860b;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>💳 Make Payment</h1>

        <div class="info">

            <p>
                <strong>Booking ID:</strong>
                #<?= htmlspecialchars($booking["id"]) ?>
            </p>

            <p>
                <strong>Event:</strong>
                <?= htmlspecialchars($booking["event_name"] ?? "Event") ?>
            </p>

            <p>
                <strong>Service:</strong>
                <?= htmlspecialchars($booking["service_name"] ?? "Service") ?>
            </p>

            <p>
                <strong>Booking Status:</strong>
                <?= htmlspecialchars($booking["status"]) ?>
            </p>

            <p>
                <strong>Payment Status:</strong>
                <?= htmlspecialchars($booking["payment_status"]) ?>
            </p>

            <p>
                <strong>Total Amount:</strong>
                <span class="amount">
                    Rs. <?= number_format($amount, 2) ?>
                </span>
            </p>

        </div>

        <form action="process_payment.php" method="POST">

            <input 
                type="hidden"
                name="booking_id"
                value="<?= $booking["id"] ?>"
            >

            <input 
                type="hidden"
                name="amount"
                value="<?= $amount ?>"
            >

            <label>Payment Method</label>

            <select name="payment_method" required>

                <option value="">-- Select Payment Method --</option>

                <option value="esewa">
                    eSewa
                </option>

                <option value="khalti">
                    Khalti
                </option>

                <option value="bank">
                    Bank Transfer
                </option>

                <option value="cash">
                    Cash
                </option>

            </select>

            <button type="submit">
                Pay Rs. <?= number_format($amount, 2) ?>
            </button>

        </form>

        <a class="back" href="../customer/dashboard.php">
            ← Back to Dashboard
        </a>

    </div>

</div>

</body>

</html>