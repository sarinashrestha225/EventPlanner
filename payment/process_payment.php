<?php

session_start();

require_once "../database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: payment.php");
    exit;
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

$booking_id = isset($_POST['booking_id'])
    ? (int)$_POST['booking_id']
    : 0;

$amount = isset($_POST['amount'])
    ? (float)$_POST['amount']
    : 0;

$payment_method = $_POST['payment_method'] ?? '';

$bank_name = !empty($_POST['bank_name'])
    ? trim($_POST['bank_name'])
    : null;

$transaction_id = !empty($_POST['transaction_id'])
    ? trim($_POST['transaction_id'])
    : null;

$payment_note = !empty($_POST['payment_note'])
    ? trim($_POST['payment_note'])
    : null;


// Validation
if ($booking_id <= 0 || $amount <= 0) {
    die("Invalid booking or payment amount.");
}

if (empty($payment_method)) {
    die("Please select a payment method.");
}


// Check booking exists
$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $booking_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found.");
}

$stmt->close();


// Insert payment
$stmt = $conn->prepare("
    INSERT INTO payments
    (
        booking_id,
        customer_id,
        amount,
        payment_method,
        bank_name,
        transaction_id,
        payment_status,
        payment_note,
        payment_date
    )
    VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, NOW())
");

$stmt->bind_param(
    "iidssss",
    $booking_id,
    $customer_id,
    $amount,
    $payment_method,
    $bank_name,
    $transaction_id,
    $payment_note
);

if ($stmt->execute()) {

    $payment_id = $stmt->insert_id;

    $stmt->close();

    header(
        "Location: payment_success.php?payment_id=" .
        $payment_id
    );

    exit;

} else {

    die("Payment failed: " . $stmt->error);

}

?>