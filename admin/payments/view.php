
<?php

session_start();

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$payment_id = (int)$_GET["id"];

$sql = "
    SELECT
        p.*,
        b.customer_id AS booking_customer_id,
        b.provider_id AS booking_provider_id,
        b.event_type_id,
        b.service_id,
        b.package_id,
        b.guests,
        b.booking_date,
        b.booking_time,
        b.amount AS booking_amount,
        b.status AS booking_status,
        b.payment_status AS booking_payment_status,
        b.customer_note,

        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,

        pr.name AS provider_name,

        et.event_name AS event_name,

        s.service_name AS service_name,

        pkg.package_name AS package_name

    FROM payments p

    LEFT JOIN bookings b
        ON p.booking_id = b.id

    LEFT JOIN users u
        ON p.customer_id = u.id

    LEFT JOIN providers pr
        ON p.provider_id = pr.id

    LEFT JOIN event_types et
        ON b.event_type_id = et.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN packages pkg
        ON b.package_id = pkg.id

    WHERE p.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $payment_id);
$stmt->execute();

$result = $stmt->get_result();
$payment = $result->fetch_assoc();

$stmt->close();

if (!$payment) {
    die("Payment not found.");
}

function showValue($value)
{
    if ($value === null || $value === "") {
        return "Not provided";
    }

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function money($amount)
{
    return number_format((float)$amount, 2);
}

function statusClass($status)
{
    switch ($status) {
        case "paid":
            return "paid";

        case "failed":
            return "failed";

        case "refunded":
            return "refunded";

        default:
            return "pending";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Payment Details - Event Planner Admin</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #fff8f0;
    color: #4b3621;
}

.container {
    max-width: 1100px;
    margin: 40px auto;
    padding: 20px;
}

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.back-btn {
    text-decoration: none;
    color: #6b4f2a;
    font-weight: bold;
}

.back-btn:hover {
    color: #b8860b;
}

h1 {
    margin: 0 0 6px;
    color: #4b3621;
}

.subtitle {
    color: #777;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.card h2 {
    margin-top: 0;
    color: #b8860b;
    border-bottom: 1px solid #eee;
    padding-bottom: 12px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.info {
    padding: 10px;
}

.label {
    display: block;
    color: #888;
    font-size: 14px;
    margin-bottom: 5px;
}

.value {
    font-size: 16px;
    font-weight: bold;
    word-break: break-word;
}

.amount {
    color: #b8860b;
    font-size: 22px;
}

.status {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.paid {
    background: #d4edda;
    color: #155724;
}

.failed {
    background: #f8d7da;
    color: #721c24;
}

.refunded {
    background: #e2e3e5;
    color: #383d41;
}

.note {
    background: #fff8e7;
    border-left: 4px solid #d4af37;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.action-btn {
    border: none;
    text-decoration: none;
    padding: 12px 22px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    display: inline-block;
}

.approve {
    background: #28a745;
    color: white;
}

.reject {
    background: #dc3545;
    color: white;
}

.refund {
    background: #6c757d;
    color: white;
}

.back {
    background: #d4af37;
    color: white;
}

.action-btn:hover {
    opacity: 0.9;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
}

@media(max-width: 700px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .container {
        margin: 15px auto;
        padding: 12px;
    }

    .card {
        padding: 18px;
    }

    .top-bar {
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="top-bar">

        <a href="index.php" class="back-btn">
            ← Payments
        </a>

    </div>

    <h1>💳 Payment Details</h1>

    <div class="subtitle">
        View and verify customer payment information
    </div>

    <?php if (isset($_GET["success"])): ?>

        <div class="success">

            <?php if ($_GET["success"] === "approved"): ?>

                ✅ Payment approved successfully.

            <?php elseif ($_GET["success"] === "rejected"): ?>

                ❌ Payment rejected successfully.

            <?php elseif ($_GET["success"] === "refunded"): ?>

                🔄 Payment refunded successfully.

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <div class="card">

        <h2>💳 Payment Information</h2>

        <div class="grid">

            <div class="info">
                <span class="label">Payment ID</span>
                <span class="value">
                    #<?= (int)$payment["id"] ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Booking ID</span>
                <span class="value">
                    #<?= (int)$payment["booking_id"] ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Service / Package</span>
                <span class="value">

                    <?php if (!empty($payment["package_name"])): ?>

                        <?= showValue($payment["package_name"]) ?>

                    <?php elseif (!empty($payment["service_name"])): ?>

                        <?= showValue($payment["service_name"]) ?>

                    <?php else: ?>

                        Not provided

                    <?php endif; ?>

                </span>
            </div>

            <div class="info">
                <span class="label">Event</span>
                <span class="value">
                    <?= showValue($payment["event_name"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Amount</span>
                <span class="value amount">
                    Rs. <?= money($payment["amount"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Payment Method</span>
                <span class="value">
                    <?= !empty($payment["payment_method"])
                        ? showValue(
                            ucfirst(
                                str_replace(
                                    "_",
                                    " ",
                                    $payment["payment_method"]
                                )
                            )
                        )
                        : "Not provided"
                    ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Payment Status</span>
                <span class="status <?= statusClass($payment["payment_status"]) ?>">
                    <?= ucfirst(showValue($payment["payment_status"])) ?>
                </span>
            </div>

        </div>

    </div>

    <div class="card">

        <h2>👤 Customer Information</h2>

        <div class="grid">

            <div class="info">
                <span class="label">Customer ID</span>
                <span class="value">
                    #<?= (int)$payment["customer_id"] ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Customer Name</span>
                <span class="value">
                    <?= showValue($payment["customer_name"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Email</span>
                <span class="value">
                    <?= showValue($payment["customer_email"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Phone</span>
                <span class="value">
                    <?= showValue($payment["customer_phone"]) ?>
                </span>
            </div>

        </div>

    </div>

    <div class="card">

        <h2>👨‍💼 Provider Information</h2>

        <div class="grid">

            <div class="info">
                <span class="label">Provider ID</span>
                <span class="value">
                    <?= !empty($payment["provider_id"])
                        ? "#" . (int)$payment["provider_id"]
                        : "Not provided"
                    ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Provider Name</span>
                <span class="value">
                    <?= showValue($payment["provider_name"]) ?>
                </span>
            </div>

        </div>

    </div>

    <div class="card">

        <h2>📅 Booking Information</h2>

        <div class="grid">

            <div class="info">
                <span class="label">Booking Status</span>
                <span class="value">
                    <?= showValue($payment["booking_status"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Event</span>
                <span class="value">
                    <?= showValue($payment["event_name"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Service</span>
                <span class="value">
                    <?= showValue($payment["service_name"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Package</span>
                <span class="value">
                    <?= showValue($payment["package_name"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Guests</span>
                <span class="value">
                    <?= !empty($payment["guests"])
                        ? number_format((int)$payment["guests"])
                        : "Not provided"
                    ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Booking Date</span>
                <span class="value">
                    <?= showValue($payment["booking_date"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Booking Time</span>
                <span class="value">
                    <?= showValue($payment["booking_time"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Booking Amount</span>
                <span class="value amount">
                    Rs. <?= money($payment["booking_amount"]) ?>
                </span>
            </div>

        </div>

    </div>

    <div class="card">

        <h2>🔢 Payment Reference</h2>

        <div class="grid">

            <div class="info">
                <span class="label">Bank Name</span>
                <span class="value">
                    <?= showValue($payment["bank_name"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Transaction ID</span>
                <span class="value">
                    <?= showValue($payment["transaction_id"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Reference Number</span>
                <span class="value">
                    <?= showValue($payment["reference_number"]) ?>
                </span>
            </div>

            <div class="info">
                <span class="label">Payment Date</span>
                <span class="value">

                    <?= !empty($payment["payment_date"])
                        ? date(
                            "d M Y h:i A",
                            strtotime($payment["payment_date"])
                        )
                        : "Not provided"
                    ?>

                </span>
            </div>

        </div>

    </div>

    <?php if (!empty($payment["payment_note"])): ?>

        <div class="card">

            <h2>📝 Customer Payment Note</h2>

            <div class="note">

                <?= nl2br(
                    htmlspecialchars(
                        $payment["payment_note"],
                        ENT_QUOTES,
                        "UTF-8"
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>

    <?php if (!empty($payment["customer_note"])): ?>

        <div class="card">

            <h2>📝 Customer Booking Note</h2>

            <div class="note">

                <?= nl2br(
                    htmlspecialchars(
                        $payment["customer_note"],
                        ENT_QUOTES,
                        "UTF-8"
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>

    <div class="card">

        <h2>⚙️ Payment Verification</h2>

        <div class="actions">

            <?php if ($payment["payment_status"] === "pending"): ?>

                <a
                    href="update.php?id=<?= (int)$payment["id"] ?>&status=paid"
                    class="action-btn approve"
                    onclick="return confirm('Approve this payment?');"
                >
                    ✅ Approve Payment
                </a>

                <a
                    href="update.php?id=<?= (int)$payment["id"] ?>&status=failed"
                    class="action-btn reject"
                    onclick="return confirm('Reject this payment?');"
                >
                    ❌ Reject Payment
                </a>

            <?php elseif ($payment["payment_status"] === "paid"): ?>

                <a
                    href="update.php?id=<?= (int)$payment["id"] ?>&status=refunded"
                    class="action-btn refund"
                    onclick="return confirm('Refund this payment?');"
                >
                    🔄 Refund Payment
                </a>

            <?php elseif ($payment["payment_status"] === "failed"): ?>

                <span class="status failed">
                    ❌ Payment Failed
                </span>

            <?php elseif ($payment["payment_status"] === "refunded"): ?>

                <span class="status refunded">
                    🔄 Payment Refunded
                </span>

            <?php endif; ?>

            <a href="index.php" class="action-btn back">
                ← Back to Payments
            </a>

        </div>

    </div>

</div>

</body>

</html>

<?php

$conn->close();

?>

