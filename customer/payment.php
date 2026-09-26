
<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["user_id"];

if (!isset($_GET["booking_id"]) || !is_numeric($_GET["booking_id"])) {
    header("Location: my_bookings.php");
    exit;
}

$booking_id = (int) $_GET["booking_id"];

$error = "";
$success = "";

$sql = "
    SELECT
        b.id,
        b.customer_id,
        b.provider_id,
        b.service_id,
        b.package_id,
        b.guests,
        b.event_type_id,
        b.booking_date,
        b.booking_time,
        b.amount,
        b.status,
        b.payment_status,
        b.customer_note,
        s.service_name,
        p.package_name,
        e.event_name,
        pr.name AS provider_name,
        pr.phone AS provider_phone
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    LEFT JOIN packages p ON b.package_id = p.id
    LEFT JOIN events e ON b.event_type_id = e.id
    LEFT JOIN providers pr ON b.provider_id = pr.id
    WHERE b.id = ?
    AND b.customer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $stmt->close();

    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>❌ Booking Not Found</h2>
            <p>This booking does not exist or does not belong to you.</p>
            <a href='my_bookings.php'>← Back to My Bookings</a>
        </div>
    ");
}

$booking = $result->fetch_assoc();

$stmt->close();

$is_package = !empty($booking["package_id"]);

$booking_title = $is_package
    ? ($booking["package_name"] ?: "Package")
    : ($booking["service_name"] ?: "Service");

$booking_status = strtolower(
    trim($booking["status"] ?? "")
);

$booking_payment_status = strtolower(
    trim($booking["payment_status"] ?? "unpaid")
);

if ($booking_status !== "confirmed") {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>⚠️ Payment Not Available</h2>
            <p>Payment is available only after the booking is confirmed.</p>
            <a href='my_bookings.php'>← Back to My Bookings</a>
        </div>
    ");
}

if ($booking_payment_status === "paid") {
    die("
        <div style='font-family:Arial;text-align:center;margin-top:100px;'>
            <h2>✅ Already Paid</h2>
            <p>This booking has already been paid.</p>
            <a href='my_bookings.php'>← Back to My Bookings</a>
        </div>
    ");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payment_method = trim($_POST["payment_method"] ?? "");
    $bank_name = trim($_POST["bank_name"] ?? "");
    $transaction_id = trim($_POST["transaction_id"] ?? "");
    $reference_number = trim($_POST["reference_number"] ?? "");
    $payment_note = trim($_POST["payment_note"] ?? "");

    $allowed_methods = [
        "esewa",
        "khalti",
        "mobile_banking",
        "bank_transfer",
        "cash"
    ];

    $digital_methods = [
        "esewa",
        "khalti",
        "mobile_banking",
        "bank_transfer"
    ];

    if ($payment_method === "") {
        $error = "Please select a payment method.";
    } elseif (!in_array($payment_method, $allowed_methods, true)) {
        $error = "Invalid payment method.";
    } elseif (
        in_array($payment_method, $digital_methods, true) &&
        $transaction_id === "" &&
        $reference_number === ""
    ) {
        $error = "Please enter Transaction ID or Reference Number.";
    }

    if ($payment_method === "cash") {
        $transaction_id = "";
        $reference_number = "";
    }

    if ($error === "") {

        $check_sql = "
            SELECT id
            FROM payments
            WHERE booking_id = ?
            AND customer_id = ?
            AND payment_status = 'pending'
            LIMIT 1
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            $error = "Database Error: " . htmlspecialchars($conn->error);
        } else {

            $check_stmt->bind_param(
                "ii",
                $booking_id,
                $customer_id
            );

            $check_stmt->execute();

            $check_result = $check_stmt->get_result();

            if ($check_result && $check_result->num_rows > 0) {
                $error = "A payment for this booking is already pending verification.";
            }

            $check_stmt->close();
        }
    }

    if ($error === "") {

        $amount = (float) ($booking["amount"] ?? 0);
        $provider_id = (int) ($booking["provider_id"] ?? 0);

        if ($provider_id <= 0) {
            $error = "This booking does not have a valid provider.";
        }
    }

    if ($error === "") {

        $payment_status = "pending";
        $payment_date = null;

        $insert_sql = "
            INSERT INTO payments
            (
                booking_id,
                customer_id,
                provider_id,
                amount,
                payment_method,
                bank_name,
                transaction_id,
                reference_number,
                payment_status,
                payment_note,
                payment_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $insert_stmt = $conn->prepare($insert_sql);

        if (!$insert_stmt) {

            $error = "Payment Database Error: " .
                htmlspecialchars($conn->error);

        } else {

            $insert_stmt->bind_param(
                "iiidsssssss",
                $booking_id,
                $customer_id,
                $provider_id,
                $amount,
                $payment_method,
                $bank_name,
                $transaction_id,
                $reference_number,
                $payment_status,
                $payment_note,
                $payment_date
            );

            if ($insert_stmt->execute()) {

                $update_sql = "
                    UPDATE bookings
                    SET payment_status = 'partial'
                    WHERE id = ?
                    AND customer_id = ?
                ";

                $update_stmt = $conn->prepare($update_sql);

                if ($update_stmt) {

                    $update_stmt->bind_param(
                        "ii",
                        $booking_id,
                        $customer_id
                    );

                    $update_stmt->execute();
                    $update_stmt->close();
                }

                $notification_sql = "
                    INSERT INTO notifications
                    (
                        user_id,
                        user_role,
                        title,
                        message,
                        type,
                        is_read,
                        created_at
                    )
                    VALUES
                    (?, 'provider', ?, ?, 'booking', 0, NOW())
                ";

                $notification_stmt =
                    $conn->prepare($notification_sql);

                if ($notification_stmt) {

                    $notification_title =
                        "Payment Submitted";

                    $notification_message =
                        "Customer submitted a payment of Rs. " .
                        number_format($amount, 2) .
                        " for booking #" .
                        $booking_id .
                        ". Please verify the payment.";

                    $notification_stmt->bind_param(
                        "iss",
                        $provider_id,
                        $notification_title,
                        $notification_message
                    );

                    $notification_stmt->execute();
                    $notification_stmt->close();
                }

                $success =
                    "Payment submitted successfully.";

            } else {

                $error =
                    "Payment failed: " .
                    htmlspecialchars($insert_stmt->error);
            }

            $insert_stmt->close();
        }
    }
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
    background: #fff8f2;
    color: #555;
}

.header {
    background: linear-gradient(90deg, #d4af37, #f3d36a);
    padding: 20px 40px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h2 {
    margin: 0;
}

.back-btn {
    text-decoration: none;
    background: #b8860b;
    color: white;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: bold;
}

.container {
    width: 92%;
    max-width: 900px;
    margin: 40px auto;
}

.card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

.title {
    text-align: center;
    color: #b8860b;
    margin-bottom: 30px;
}

.booking-info {
    background: #fff8e1;
    border-left: 5px solid #d4af37;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.booking-info h2 {
    margin-top: 0;
    color: #444;
}

.booking-info p {
    margin: 9px 0;
}

.booking-type {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 20px;
    background: #f8c8dc;
    color: #7a4b00;
    font-size: 13px;
    font-weight: bold;
}

.amount {
    color: #b8860b;
    font-size: 24px;
    font-weight: bold;
}

.provider {
    margin-top: 15px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    border: 1px solid #ead9a6;
}

.error {
    background: #ffe1e1;
    color: #b00020;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}

.success {
    background: #e2f6d9;
    color: #397328;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}

.form-label {
    display: block;
    margin-top: 20px;
    margin-bottom: 8px;
    font-weight: bold;
}

input,
select,
textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: #d4af37;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 10px;
}

.method {
    border: 2px solid #eee;
    border-radius: 12px;
    padding: 18px;
    cursor: pointer;
}

.method:hover {
    border-color: #d4af37;
    background: #fffdf0;
}

.method input {
    width: auto;
    margin-right: 8px;
}

.method-title {
    font-weight: bold;
}

.method-desc {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #888;
}

.submit-btn {
    width: 100%;
    padding: 15px;
    margin-top: 25px;
    background: #d4af37;
    border: none;
    border-radius: 9px;
    color: white;
    font-size: 17px;
    font-weight: bold;
    cursor: pointer;
}

.submit-btn:hover {
    background: #b8860b;
}

.note {
    margin-top: 15px;
    background: #fafafa;
    padding: 12px;
    border-radius: 7px;
    font-size: 13px;
    color: #777;
}

.success-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.success-btn {
    display: inline-block;
    padding: 10px 18px;
    background: #397328;
    color: white;
    text-decoration: none;
    border-radius: 8px;
}

.booking-status {
    display: inline-block;
    background: #dff5df;
    color: #287a28;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

@media (max-width: 650px) {

    .header {
        padding: 18px 20px;
    }

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .card {
        padding: 22px;
    }

    .payment-methods {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="header">

    <h2>✦ Event Planner</h2>

    <a href="my_bookings.php" class="back-btn">
        ← My Bookings
    </a>

</div>

<div class="container">

<div class="card">

<h1 class="title">
    💳 Make Payment
</h1>

<?php if ($success !== ""): ?>

<div class="success">

    ✅ <?= htmlspecialchars(
        $success,
        ENT_QUOTES,
        "UTF-8"
    ) ?>

    <br><br>

    Your payment is now
    <strong>Pending Verification</strong>.

    <div class="success-actions">

        <a href="my_bookings.php" class="success-btn">
            ← My Bookings
        </a>

        <a
            href="messages.php?booking_id=<?= $booking_id ?>"
            class="success-btn"
        >
            💬 Chat with Provider
        </a>

    </div>

</div>

<?php endif; ?>

<?php if ($error !== ""): ?>

<div class="error">

    ❌ <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        "UTF-8"
    ) ?>

</div>

<?php endif; ?>

<div class="booking-info">

    <span class="booking-type">

        <?= $is_package
            ? "📦 Package Booking"
            : "🧩 Service Booking"
        ?>

    </span>

    <h2>
        <?= htmlspecialchars(
            $booking_title,
            ENT_QUOTES,
            "UTF-8"
        ) ?>
    </h2>

    <p>
        <strong>🎉 Event:</strong>
        <?= htmlspecialchars(
            $booking["event_name"] ?? "N/A",
            ENT_QUOTES,
            "UTF-8"
        ) ?>
    </p>

    <p>
        <strong>🆔 Booking ID:</strong>
        #<?= $booking_id ?>
    </p>

    <?php if (!empty($booking["provider_name"])): ?>

    <div class="provider">

        <strong>Provider:</strong>
        <?= htmlspecialchars(
            $booking["provider_name"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>

        <?php if (!empty($booking["provider_phone"])): ?>

            <br>

            <strong>Phone:</strong>
            <?= htmlspecialchars(
                $booking["provider_phone"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        <?php endif; ?>

    </div>

    <?php endif; ?>

    <?php if ($is_package): ?>

    <p>
        <strong>👥 Guests:</strong>
        <?= number_format(
            (int)($booking["guests"] ?? 0)
        ) ?>
    </p>

    <?php endif; ?>

    <p>
        <strong>📅 Booking Date:</strong>
        <?= !empty($booking["booking_date"])
            ? date(
                "d M Y",
                strtotime($booking["booking_date"])
            )
            : "Not specified"
        ?>
    </p>

    <p>
        <strong>⏰ Booking Time:</strong>
        <?= !empty($booking["booking_time"])
            ? date(
                "h:i A",
                strtotime($booking["booking_time"])
            )
            : "Not specified"
        ?>
    </p>

    <p>
        <strong>📌 Booking Status:</strong>

        <span class="booking-status">
            Confirmed
        </span>
    </p>

    <p>
        <strong>💰 Amount:</strong>

        <span class="amount">
            Rs.
            <?= number_format(
                (float)$booking["amount"],
                2
            ) ?>
        </span>
    </p>

</div>

<?php if ($success === ""): ?>

<form method="POST">

    <label class="form-label">
        💳 Select Payment Method
    </label>

    <div class="payment-methods">

        <label class="method">
            <input
                type="radio"
                name="payment_method"
                value="esewa"
                required
            >
            <span class="method-title">💚 eSewa</span>
            <span class="method-desc">Pay through eSewa</span>
        </label>

        <label class="method">
            <input
                type="radio"
                name="payment_method"
                value="khalti"
            >
            <span class="method-title">💜 Khalti</span>
            <span class="method-desc">Pay through Khalti</span>
        </label>

        <label class="method">
            <input
                type="radio"
                name="payment_method"
                value="mobile_banking"
            >
            <span class="method-title">📱 Mobile Banking</span>
            <span class="method-desc">Mobile banking payment</span>
        </label>

        <label class="method">
            <input
                type="radio"
                name="payment_method"
                value="bank_transfer"
            >
            <span class="method-title">🏦 Bank Transfer</span>
            <span class="method-desc">
                Transfer to Event Planner bank account
            </span>
        </label>

        <label class="method">
            <input
                type="radio"
                name="payment_method"
                value="cash"
            >
            <span class="method-title">💵 Cash</span>
            <span class="method-desc">
                Cash payment
            </span>
        </label>

    </div>

    <label class="form-label">
        🏦 Bank Name
    </label>

    <input
        type="text"
        name="bank_name"
        placeholder="Example: Nabil Bank"
    >

    <label class="form-label">
        🔢 Transaction ID
    </label>

    <input
        type="text"
        name="transaction_id"
        placeholder="Enter transaction ID"
    >

    <label class="form-label">
        🔖 Reference Number
    </label>

    <input
        type="text"
        name="reference_number"
        placeholder="Enter reference number"
    >

    <label class="form-label">
        📝 Payment Note
    </label>

    <textarea
        name="payment_note"
        placeholder="Write any payment related note..."
    ></textarea>

    <button
        type="submit"
        class="submit-btn"
    >
        💳 Submit Payment
    </button>

    <div class="note">

        Your payment will first be marked as
        <strong>Pending</strong>.

        <br>

        The provider can verify the submitted payment
        and update the payment status.

    </div>

</form>

<?php endif; ?>

</div>

</div>

</body>

</html>

<?php

$conn->close();

?>

