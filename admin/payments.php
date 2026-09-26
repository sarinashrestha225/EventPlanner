<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $payment_id = (int)($_POST["payment_id"] ?? 0);
    $action = trim($_POST["action"] ?? "");

    if ($payment_id <= 0) {
        $error = "Invalid payment ID.";
    } elseif (!in_array($action, ["mark_paid", "mark_failed"], true)) {
        $error = "Invalid action.";
    } else {

        $stmt = $conn->prepare("
            SELECT 
                p.id,
                p.booking_id,
                p.customer_id,
                p.provider_id,
                p.amount,
                p.payment_status,
                b.payment_status AS booking_payment_status
            FROM payments p
            LEFT JOIN bookings b ON p.booking_id = b.id
            WHERE p.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {

            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();

            $stmt->close();

            if (!$payment) {

                $error = "Payment not found.";

            } elseif (strtolower($payment["payment_status"]) !== "pending") {

                $error = "Only pending payments can be verified.";

            } else {

                $new_payment_status = "";
                $new_booking_status = "";
                $notification_title = "";
                $notification_message = "";

                if ($action === "mark_paid") {

                    $new_payment_status = "paid";
                    $new_booking_status = "paid";

                    $notification_title = "Payment Verified";
                    $notification_message =
                        "Your payment for booking #" .
                        $payment["booking_id"] .
                        " has been verified and marked as paid.";

                } elseif ($action === "mark_failed") {

                    $new_payment_status = "failed";
                    $new_booking_status = "unpaid";

                    $notification_title = "Payment Failed";
                    $notification_message =
                        "Your payment for booking #" .
                        $payment["booking_id"] .
                        " could not be verified. Please check your payment details and try again.";
                }

                $conn->begin_transaction();

                try {

                    $update_payment = $conn->prepare("
                        UPDATE payments
                        SET payment_status = ?,
                            payment_date = CASE
                                WHEN ? = 'paid' THEN NOW()
                                ELSE payment_date
                            END
                        WHERE id = ?
                        AND payment_status = 'pending'
                    ");

                    if (!$update_payment) {
                        throw new Exception($conn->error);
                    }

                    $update_payment->bind_param(
                        "ssi",
                        $new_payment_status,
                        $new_payment_status,
                        $payment_id
                    );

                    if (!$update_payment->execute()) {
                        throw new Exception($update_payment->error);
                    }

                    $update_payment->close();

                    $update_booking = $conn->prepare("
                        UPDATE bookings
                        SET payment_status = ?
                        WHERE id = ?
                    ");

                    if (!$update_booking) {
                        throw new Exception($conn->error);
                    }

                    $booking_id = (int)$payment["booking_id"];

                    $update_booking->bind_param(
                        "si",
                        $new_booking_status,
                        $booking_id
                    );

                    if (!$update_booking->execute()) {
                        throw new Exception($update_booking->error);
                    }

                    $update_booking->close();

                    $customer_id = (int)$payment["customer_id"];

                    if ($customer_id > 0) {

                        $notification_stmt = $conn->prepare("
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
                            (
                                ?,
                                'customer',
                                ?,
                                ?,
                                'payment',
                                0,
                                NOW()
                            )
                        ");

                        if ($notification_stmt) {

                            $notification_stmt->bind_param(
                                "iss",
                                $customer_id,
                                $notification_title,
                                $notification_message
                            );

                            $notification_stmt->execute();
                            $notification_stmt->close();
                        }
                    }

                    if (
                        $action === "mark_paid" &&
                        !empty($payment["provider_id"])
                    ) {

                        $provider_id = (int)$payment["provider_id"];

                        if ($provider_id > 0) {

                            $provider_title = "Payment Received";
                            $provider_message =
                                "Payment for booking #" .
                                $payment["booking_id"] .
                                " has been verified and marked as paid.";

                            $provider_notification = $conn->prepare("
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
                                (
                                    ?,
                                    'provider',
                                    ?,
                                    ?,
                                    'payment',
                                    0,
                                    NOW()
                                )
                            ");

                            if ($provider_notification) {

                                $provider_notification->bind_param(
                                    "iss",
                                    $provider_id,
                                    $provider_title,
                                    $provider_message
                                );

                                $provider_notification->execute();
                                $provider_notification->close();
                            }
                        }
                    }

                    $conn->commit();

                    if ($action === "mark_paid") {
                        $success = "Payment #".$payment_id." has been marked as paid.";
                    } else {
                        $success = "Payment #".$payment_id." has been marked as failed.";
                    }

                } catch (Exception $e) {

                    $conn->rollback();
                    $error = "Unable to update payment: " . $e->getMessage();
                }
            }
        }
    }
}

$total_payments = 0;
$pending_amount = 0;
$paid_amount = 0;
$failed_amount = 0;

$summary_query = "
    SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
        COALESCE(SUM(CASE WHEN payment_status = 'failed' THEN amount ELSE 0 END), 0) AS failed_amount
    FROM payments
";

$summary_result = $conn->query($summary_query);

if ($summary_result) {

    $summary = $summary_result->fetch_assoc();

    $total_payments = (int)($summary["total_payments"] ?? 0);
    $pending_amount = (float)($summary["pending_amount"] ?? 0);
    $paid_amount = (float)($summary["paid_amount"] ?? 0);
    $failed_amount = (float)($summary["failed_amount"] ?? 0);
}

$payments = [];

$sql = "
    SELECT
        p.id,
        p.booking_id,
        p.customer_id,
        p.provider_id,
        p.amount,
        p.payment_method,
        p.bank_name,
        p.transaction_id,
        p.reference_number,
        p.payment_status,
        p.payment_note,
        p.payment_date,
        p.created_at,

        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,

        pr.name AS provider_name,

        b.status AS booking_status,
        b.payment_status AS booking_payment_status,
        b.booking_date,
        b.booking_time,

        s.service_name,

        pk.package_name,

        e.event_name

    FROM payments p

    LEFT JOIN users u
        ON p.customer_id = u.id

    LEFT JOIN providers pr
        ON p.provider_id = pr.id

    LEFT JOIN bookings b
        ON p.booking_id = b.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN packages pk
        ON b.package_id = pk.id

    LEFT JOIN events e
        ON b.event_type_id = e.id

    ORDER BY p.id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function money($value)
{
    return "Rs. " . number_format((float)$value, 2);
}

function status_class($status)
{
    $status = strtolower(trim((string)$status));

    if ($status === "paid") {
        return "paid";
    }

    if ($status === "pending") {
        return "pending";
    }

    if ($status === "failed") {
        return "failed";
    }

    if ($status === "refunded") {
        return "refunded";
    }

    return "other";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Payment Management</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f2;
    color: #333;
}

.container {
    width: 95%;
    max-width: 1500px;
    margin: 30px auto;
}

.header {
    background: linear-gradient(135deg, #fff1f5, #fff8e7);
    border: 1px solid #ead9a8;
    border-radius: 18px;
    padding: 25px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0 0 8px;
    color: #b8860b;
}

.header p {
    margin: 0;
    color: #777;
}

.alert {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: bold;
}

.success {
    background: #e9f8ee;
    color: #227a3c;
    border: 1px solid #a8dfb8;
}

.error {
    background: #fff0f0;
    color: #b52b2b;
    border: 1px solid #efb0b0;
}

.summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 15px;
    padding: 22px;
    border: 1px solid #ead9a8;
    box-shadow: 0 5px 18px rgba(0,0,0,0.06);
}

.card h3 {
    margin: 0 0 10px;
    color: #777;
    font-size: 14px;
}

.card strong {
    font-size: 24px;
    color: #b8860b;
}

.table-box {
    background: white;
    border-radius: 18px;
    padding: 20px;
    border: 1px solid #ead9a8;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1250px;
}

th {
    background: #fff5d6;
    color: #8b6914;
    padding: 14px 12px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #ead9a8;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #f0e8d5;
    vertical-align: top;
    font-size: 13px;
}

tr:hover {
    background: #fffaf3;
}

.payment-id {
    font-weight: bold;
    color: #b8860b;
}

.amount {
    font-weight: bold;
    color: #333;
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status.paid {
    background: #e4f7ea;
    color: #237b3b;
}

.status.pending {
    background: #fff2c7;
    color: #9a7100;
}

.status.failed {
    background: #ffe4e4;
    color: #b52b2b;
}

.status.refunded {
    background: #eee5ff;
    color: #7042b5;
}

.status.other {
    background: #eee;
    color: #555;
}

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.actions form {
    margin: 0;
}

.btn {
    border: none;
    padding: 8px 12px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

.paid-btn {
    background: #d9f3df;
    color: #237b3b;
}

.paid-btn:hover {
    background: #bce7c6;
}

.failed-btn {
    background: #ffe0e0;
    color: #b52b2b;
}

.failed-btn:hover {
    background: #ffcaca;
}

.info-line {
    margin: 3px 0;
}

.muted {
    color: #888;
}

.empty {
    text-align: center;
    padding: 50px;
    color: #888;
}

@media (max-width: 900px) {

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 550px) {

    .summary {
        grid-template-columns: 1fr;
    }

    .container {
        width: 92%;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Payment Management</h1>

        <p>Review and verify customer payment submissions.</p>

    </div>

    <?php if ($success !== ""): ?>

        <div class="alert success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <div class="summary">

        <div class="card">
            <h3>Total Payments</h3>
            <strong><?= $total_payments ?></strong>
        </div>

        <div class="card">
            <h3>Pending Amount</h3>
            <strong><?= money($pending_amount) ?></strong>
        </div>

        <div class="card">
            <h3>Paid Amount</h3>
            <strong><?= money($paid_amount) ?></strong>
        </div>

        <div class="card">
            <h3>Failed Amount</h3>
            <strong><?= money($failed_amount) ?></strong>
        </div>

    </div>

    <div class="table-box">

        <?php if (empty($payments)): ?>

            <div class="empty">
                No payment records found.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>
                        <th>Payment</th>
                        <th>Customer</th>
                        <th>Provider</th>
                        <th>Booking</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($payments as $payment): ?>

                    <tr>

                        <td>

                            <div class="payment-id">
                                #<?= e($payment["id"]) ?>
                            </div>

                        </td>

                        <td>

                            <div class="info-line">
                                <strong><?= e($payment["customer_name"] ?? "Unknown") ?></strong>
                            </div>

                            <div class="info-line muted">
                                <?= e($payment["customer_email"] ?? "") ?>
                            </div>

                            <div class="info-line muted">
                                <?= e($payment["customer_phone"] ?? "") ?>
                            </div>

                        </td>

                        <td>

                            <?= e($payment["provider_name"] ?? "Not assigned") ?>

                        </td>

                        <td>

                            <div class="info-line">
                                <strong>#<?= e($payment["booking_id"]) ?></strong>
                            </div>

                            <?php if (!empty($payment["service_name"])): ?>

                                <div class="info-line">
                                    <?= e($payment["service_name"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($payment["package_name"])): ?>

                                <div class="info-line muted">
                                    Package: <?= e($payment["package_name"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($payment["event_name"])): ?>

                                <div class="info-line muted">
                                    Event: <?= e($payment["event_name"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($payment["booking_date"])): ?>

                                <div class="info-line muted">
                                    <?= e($payment["booking_date"]) ?>

                                    <?php if (!empty($payment["booking_time"])): ?>

                                        <?= e($payment["booking_time"]) ?>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="amount">
                                <?= money($payment["amount"]) ?>
                            </div>

                        </td>

                        <td>

                            <?= e(ucwords(str_replace("_", " ", $payment["payment_method"] ?? ""))) ?>

                            <?php if (!empty($payment["bank_name"])): ?>

                                <div class="info-line muted">
                                    <?= e($payment["bank_name"]) ?>
                                </div>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (!empty($payment["transaction_id"])): ?>

                                <div class="info-line">
                                    <strong>TXN:</strong>
                                    <?= e($payment["transaction_id"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($payment["reference_number"])): ?>

                                <div class="info-line">
                                    <strong>REF:</strong>
                                    <?= e($payment["reference_number"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (!empty($payment["payment_note"])): ?>

                                <div class="info-line muted">
                                    <?= e($payment["payment_note"]) ?>
                                </div>

                            <?php endif; ?>

                            <?php if (
                                empty($payment["transaction_id"]) &&
                                empty($payment["reference_number"])
                            ): ?>

                                <span class="muted">No transaction/reference</span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <span class="status <?= e(status_class($payment["payment_status"])) ?>">
                                <?= e(ucfirst($payment["payment_status"])) ?>
                            </span>

                        </td>

                        <td>

                            <?php if (!empty($payment["payment_date"])): ?>

                                <?= e($payment["payment_date"]) ?>

                            <?php else: ?>

                                <span class="muted">
                                    Submitted:
                                    <?= e($payment["created_at"]) ?>
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (strtolower($payment["payment_status"]) === "pending"): ?>

                                <div class="actions">

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= e($payment["id"]) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="mark_paid"
                                        >

                                        <button
                                            type="submit"
                                            class="btn paid-btn"
                                            onclick="return confirm('Mark this payment as PAID?');"
                                        >
                                            ✓ Mark as Paid
                                        </button>

                                    </form>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= e($payment["id"]) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="mark_failed"
                                        >

                                        <button
                                            type="submit"
                                            class="btn failed-btn"
                                            onclick="return confirm('Mark this payment as FAILED?');"
                                        >
                                            ✕ Mark as Failed
                                        </button>

                                    </form>

                                </div>

                            <?php elseif (strtolower($payment["payment_status"]) === "paid"): ?>

                                <span class="status paid">
                                    ✓ Verified
                                </span>

                            <?php elseif (strtolower($payment["payment_status"]) === "failed"): ?>

                                <span class="status failed">
                                    ✕ Failed
                                </span>

                            <?php elseif (strtolower($payment["payment_status"]) === "refunded"): ?>

                                <span class="status refunded">
                                    Refunded
                                </span>

                            <?php else: ?>

                                <span class="muted">No action</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>