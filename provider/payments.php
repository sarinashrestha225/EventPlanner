<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["provider_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["provider_id"];

if ($provider_id <= 0) {
    header("Location: login.php");
    exit();
}

$success = "";
$error = "";

$commission_rate = 20.00;

if (!isset($_SESSION["payment_csrf_token"])) {
    $_SESSION["payment_csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION["payment_csrf_token"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_token =
        $_POST["csrf_token"] ?? "";

    $payment_id =
        (int) ($_POST["payment_id"] ?? 0);

    $action =
        trim($_POST["action"] ?? "");

    if (
        !hash_equals(
            $csrf_token,
            $posted_token
        )
    ) {

        $error =
            "Invalid request. Please try again.";

    } elseif ($payment_id <= 0) {

        $error =
            "Invalid payment ID.";

    } elseif (
        !in_array(
            $action,
            ["paid", "failed"],
            true
        )
    ) {

        $error =
            "Invalid payment action.";

    } else {

        $payment_stmt =
            $conn->prepare("
                SELECT
                    p.id,
                    p.booking_id,
                    p.amount,
                    p.payment_status,
                    b.customer_id
                FROM payments p
                INNER JOIN bookings b
                    ON b.id = p.booking_id
                WHERE p.id = ?
                AND p.provider_id = ?
                AND b.provider_id = ?
                LIMIT 1
            ");

        if (!$payment_stmt) {

            $error =
                "Database Error: " .
                htmlspecialchars(
                    $conn->error,
                    ENT_QUOTES,
                    "UTF-8"
                );

        } else {

            $payment_stmt->bind_param(
                "iii",
                $payment_id,
                $provider_id,
                $provider_id
            );

            $payment_stmt->execute();

            $payment_result =
                $payment_stmt->get_result();

            if (
                !$payment_result ||
                $payment_result->num_rows !== 1
            ) {

                $error =
                    "Payment not found.";

            } else {

                $payment =
                    $payment_result->fetch_assoc();

                $current_status =
                    strtolower(
                        trim(
                            $payment["payment_status"] ?? ""
                        )
                    );

                if (
                    $current_status !==
                    "pending"
                ) {

                    $error =
                        "Only pending payments can be verified.";

                } else {

                    $new_status =
                        $action === "paid"
                        ? "paid"
                        : "failed";

                    $booking_payment_status =
                        $action === "paid"
                        ? "paid"
                        : "unpaid";

                    $update_stmt =
                        $conn->prepare("
                            UPDATE payments
                            SET
                                payment_status = ?,
                                payment_date = CASE
                                    WHEN ? = 'paid'
                                    THEN NOW()
                                    ELSE payment_date
                                END
                            WHERE id = ?
                            AND provider_id = ?
                            AND payment_status = 'pending'
                        ");

                    if (!$update_stmt) {

                        $error =
                            "Payment update error: " .
                            htmlspecialchars(
                                $conn->error,
                                ENT_QUOTES,
                                "UTF-8"
                            );

                    } else {

                        $update_stmt->bind_param(
                            "ssii",
                            $new_status,
                            $new_status,
                            $payment_id,
                            $provider_id
                        );

                        if (
                            $update_stmt->execute() &&
                            $update_stmt->affected_rows === 1
                        ) {

                            $booking_id =
                                (int) $payment["booking_id"];

                            $customer_id =
                                (int) $payment["customer_id"];

                            $booking_stmt =
                                $conn->prepare("
                                    UPDATE bookings
                                    SET payment_status = ?
                                    WHERE id = ?
                                    AND provider_id = ?
                                ");

                            if ($booking_stmt) {

                                $booking_stmt->bind_param(
                                    "sii",
                                    $booking_payment_status,
                                    $booking_id,
                                    $provider_id
                                );

                                $booking_stmt->execute();

                                $booking_stmt->close();
                            }

                            if ($customer_id > 0) {

                                $notification_stmt =
                                    $conn->prepare("
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
                                            'booking',
                                            0,
                                            NOW()
                                        )
                                    ");

                                if ($notification_stmt) {

                                    if (
                                        $action === "paid"
                                    ) {

                                        $notification_title =
                                            "Payment Verified";

                                        $notification_message =
                                            "Your payment of Rs. " .
                                            number_format(
                                                (float) $payment["amount"],
                                                2
                                            ) .
                                            " for booking #" .
                                            $booking_id .
                                            " has been verified and marked as paid.";

                                    } else {

                                        $notification_title =
                                            "Payment Failed";

                                        $notification_message =
                                            "Your payment for booking #" .
                                            $booking_id .
                                            " could not be verified. Please contact the provider.";
                                    }

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
                                $action === "paid"
                            ) {

                                $success =
                                    "Payment #" .
                                    $payment_id .
                                    " has been marked as paid successfully.";

                            } else {

                                $success =
                                    "Payment #" .
                                    $payment_id .
                                    " has been marked as failed.";
                            }

                        } else {

                            $error =
                                "Could not update payment. It may already have been processed.";
                        }

                        $update_stmt->close();
                    }
                }
            }

            $payment_stmt->close();
        }
    }
}

$stmt =
    $conn->prepare("
        SELECT
            p.id,
            p.booking_id,
            b.customer_id,
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
            u.phone AS customer_phone
        FROM payments p
        INNER JOIN bookings b
            ON b.id = p.booking_id
        LEFT JOIN users u
            ON u.id = b.customer_id
        WHERE p.provider_id = ?
        AND b.provider_id = ?
        ORDER BY p.id DESC
    ");

if (!$stmt) {

    die(
        "SQL Error: " .
        htmlspecialchars(
            $conn->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$stmt->bind_param(
    "ii",
    $provider_id,
    $provider_id
);

if (!$stmt->execute()) {

    die(
        "Execute Error: " .
        htmlspecialchars(
            $stmt->error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$result =
    $stmt->get_result();

$payments = [];

while (
    $row =
    $result->fetch_assoc()
) {

    $payments[] = $row;
}

$stmt->close();

$total_paid = 0;
$total_pending = 0;
$total_failed = 0;
$total_refunded = 0;

$total_company_commission = 0;
$total_provider_amount = 0;

foreach (
    $payments as $payment
) {

    $amount =
        (float) $payment["amount"];

    $status =
        strtolower(
            trim(
                $payment["payment_status"] ?? ""
            )
        );

    if ($status === "paid") {

        $total_paid += $amount;

        $commission =
            $amount * ($commission_rate / 100);

        $provider_amount =
            $amount - $commission;

        $total_company_commission +=
            $commission;

        $total_provider_amount +=
            $provider_amount;

    } elseif (
        $status === "pending"
    ) {

        $total_pending += $amount;

        $commission =
            $amount * ($commission_rate / 100);

        $provider_amount =
            $amount - $commission;

        $total_company_commission +=
            $commission;

        $total_provider_amount +=
            $provider_amount;

    } elseif (
        $status === "failed"
    ) {

        $total_failed += $amount;

    } elseif (
        $status === "refunded"
    ) {

        $total_refunded += $amount;
    }
}

$provider_name =
    $_SESSION["provider_name"] ??
    "Provider";

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
    Payments | Provider
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f4;
    color: #4b3621;
}

.header {
    background: linear-gradient(
        135deg,
        #f8c8dc,
        #f4d58d
    );
    padding: 22px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow:
        0 3px 15px rgba(
            0,
            0,
            0,
            0.08
        );
}

.header-left h1 {
    margin: 0;
    font-size: 27px;
    color: #5a3d34;
}

.header-left p {
    margin: 6px 0 0;
    color: #75594f;
    font-size: 14px;
}

.dashboard-btn {
    text-decoration: none;
    background: white;
    color: #805d12;
    padding: 11px 18px;
    border-radius: 10px;
    font-weight: bold;
}

.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

.message {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: bold;
}

.success {
    background: #dff3e2;
    color: #237638;
}

.error {
    background: #ffe0e0;
    color: #a53232;
}

.summary {
    display: grid;
    grid-template-columns: repeat(
        3,
        1fr
    );
    gap: 18px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 22px;
    border-radius: 16px;
    box-shadow:
        0 5px 18px rgba(
            0,
            0,
            0,
            0.07
        );
    border-top: 4px solid #e6c15a;
}

.summary-card.paid {
    border-top-color: #77b982;
}

.summary-card.pending {
    border-top-color: #e6a83c;
}

.summary-card.commission {
    border-top-color: #d8a93a;
}

.summary-card.provider {
    border-top-color: #bd7b9b;
}

.summary-card.failed {
    border-top-color: #df7777;
}

.summary-icon {
    font-size: 28px;
    margin-bottom: 12px;
}

.summary-card h3 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #806f67;
    font-weight: normal;
}

.amount {
    font-size: 22px;
    font-weight: bold;
    color: #c39828;
}

.paid .amount {
    color: #25813b;
}

.pending .amount {
    color: #b87900;
}

.commission .amount {
    color: #a06d00;
}

.provider .amount {
    color: #a34e78;
}

.failed .amount {
    color: #b23b3b;
}

.main-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow:
        0 5px 18px rgba(
            0,
            0,
            0,
            0.07
        );
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-header h2 {
    margin: 0;
    color: #65463c;
    font-size: 21px;
}

.record-count {
    color: #8a7770;
    font-size: 13px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1500px;
    border-collapse: collapse;
}

th {
    background: #fff4da;
    color: #624b3f;
    padding: 14px 12px;
    text-align: left;
    border-bottom: 2px solid #ead7a5;
    font-size: 14px;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid #f0e8e3;
    vertical-align: middle;
    font-size: 14px;
}

tr:hover td {
    background: #fffaf7;
}

.payment-id,
.booking-id {
    color: #8a6b1d;
    font-weight: bold;
}

.customer-name {
    color: #65463c;
    font-weight: bold;
}

.small {
    color: #83736d;
    font-size: 12px;
    margin-top: 4px;
}

.amount-cell {
    color: #21813a;
    font-weight: bold;
    white-space: nowrap;
}

.commission-cell {
    color: #a06d00;
    font-weight: bold;
    white-space: nowrap;
}

.provider-cell {
    color: #a34e78;
    font-weight: bold;
    white-space: nowrap;
}

.method {
    text-transform: capitalize;
    color: #65463c;
}

.transaction {
    color: #6d5c55;
    font-size: 13px;
}

.status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: capitalize;
}

.status-paid {
    background: #dff3e2;
    color: #237638;
}

.status-pending {
    background: #fff0c9;
    color: #946c00;
}

.status-failed {
    background: #ffe0e0;
    color: #a53232;
}

.status-refunded {
    background: #e7e7e7;
    color: #555;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.action-btn {
    border: none;
    border-radius: 7px;
    padding: 8px 11px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.paid-btn {
    background: #2e9d49;
    color: white;
}

.paid-btn:hover {
    background: #237638;
}

.failed-btn {
    background: #d9534f;
    color: white;
}

.failed-btn:hover {
    background: #b52b27;
}

.no-action {
    color: #888;
    font-size: 12px;
}

.payment-date {
    white-space: nowrap;
    color: #75645d;
}

.note {
    margin-top: 18px;
    padding: 15px;
    background: #fff8e8;
    border-left: 4px solid #e4bd4f;
    border-radius: 8px;
    color: #705d40;
    font-size: 13px;
    line-height: 1.6;
}

.commission-info {
    margin-top: 18px;
    padding: 16px;
    background: #fff5df;
    border-left: 4px solid #d8a93a;
    border-radius: 8px;
    color: #6f5525;
    font-size: 13px;
    line-height: 1.6;
}

.commission-info strong {
    color: #9a6a00;
}

.empty {
    text-align: center;
    padding: 65px 20px;
}

.empty-icon {
    font-size: 55px;
    margin-bottom: 15px;
}

.empty h2 {
    margin: 0 0 8px;
    color: #65463c;
}

.empty p {
    margin: 0;
    color: #8a7770;
}

@media (max-width: 1100px) {

    .summary {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .header {
        padding: 18px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .dashboard-btn {
        width: 100%;
        text-align: center;
    }

    .container {
        padding: 0 15px;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .main-card {
        padding: 18px;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="header-left">

        <h1>
            💳 Provider Payments
        </h1>

        <p>

            Welcome,

            <?= htmlspecialchars(
                $provider_name,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </p>

    </div>

    <a
        href="dashboard.php"
        class="dashboard-btn"
    >
        ← Dashboard
    </a>

</header>

<div class="container">

<?php if ($success !== ""): ?>

    <div class="message success">

        ✅

        <?= htmlspecialchars(
            $success,
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </div>

<?php endif; ?>

<?php if ($error !== ""): ?>

    <div class="message error">

        ❌

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </div>

<?php endif; ?>

<div class="summary">

    <div class="summary-card">

        <div class="summary-icon">
            💳
        </div>

        <h3>
            Total Payments
        </h3>

        <div class="amount">
            <?= count($payments) ?>
        </div>

    </div>

    <div class="summary-card paid">

        <div class="summary-icon">
            ✅
        </div>

        <h3>
            Paid Amount
        </h3>

        <div class="amount">

            Rs.

            <?= number_format(
                $total_paid,
                2
            ) ?>

        </div>

    </div>

    <div class="summary-card commission">

        <div class="summary-icon">
            🏢
        </div>

        <h3>
            Company Commission (20%)
        </h3>

        <div class="amount">

            Rs.

            <?= number_format(
                $total_company_commission,
                2
            ) ?>

        </div>

    </div>

    <div class="summary-card provider">

        <div class="summary-icon">
            💰
        </div>

        <h3>
            Provider Amount
        </h3>

        <div class="amount">

            Rs.

            <?= number_format(
                $total_provider_amount,
                2
            ) ?>

        </div>

    </div>

    <div class="summary-card pending">

        <div class="summary-icon">
            ⏳
        </div>

        <h3>
            Pending Amount
        </h3>

        <div class="amount">

            Rs.

            <?= number_format(
                $total_pending,
                2
            ) ?>

        </div>

    </div>

    <div class="summary-card failed">

        <div class="summary-icon">
            ❌
        </div>

        <h3>
            Failed / Refunded
        </h3>

        <div class="amount">

            Rs.

            <?= number_format(
                $total_failed +
                $total_refunded,
                2
            ) ?>

        </div>

    </div>

</div>

<div class="main-card">

    <div class="card-header">

        <h2>
            📋 Payment History
        </h2>

        <span class="record-count">

            <?= count($payments) ?>

            record<?= count($payments) == 1 ? "" : "s" ?>

        </span>

    </div>

    <?php if (empty($payments)): ?>

        <div class="empty">

            <div class="empty-icon">
                💳
            </div>

            <h2>
                No Payments Yet
            </h2>

            <p>
                Customer payment information will appear here.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Payment ID
                        </th>

                        <th>
                            Booking
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Company Commission
                        </th>

                        <th>
                            Provider Amount
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Transaction
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Payment Date
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $payments as $payment
                ): ?>

                    <?php

                    $status =
                        strtolower(
                            trim(
                                $payment[
                                    "payment_status"
                                ] ?? ""
                            )
                        );

                    $status_class =
                        preg_replace(
                            "/[^a-z0-9_-]/",
                            "",
                            $status
                        );

                    $payment_amount =
                        (float) $payment["amount"];

                    $row_commission = 0;
                    $row_provider_amount = 0;

                    if (
                        $status === "paid" ||
                        $status === "pending"
                    ) {

                        $row_commission =
                            $payment_amount *
                            ($commission_rate / 100);

                        $row_provider_amount =
                            $payment_amount -
                            $row_commission;
                    }

                    ?>

                    <tr>

                        <td class="payment-id">

                            #<?= (int)
                                $payment["id"] ?>

                        </td>

                        <td class="booking-id">

                            #<?= (int)
                                $payment["booking_id"] ?>

                        </td>

                        <td>

                            <div class="customer-name">

                                <?= htmlspecialchars(
                                    $payment[
                                        "customer_name"
                                    ] ??
                                    "Unknown",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                            <?php if (
                                !empty(
                                    $payment[
                                        "customer_email"
                                    ]
                                )
                            ): ?>

                                <div class="small">

                                    <?= htmlspecialchars(
                                        $payment[
                                            "customer_email"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            <?php endif; ?>

                            <?php if (
                                !empty(
                                    $payment[
                                        "customer_phone"
                                    ]
                                )
                            ): ?>

                                <div class="small">

                                    📞

                                    <?= htmlspecialchars(
                                        $payment[
                                            "customer_phone"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>

                        <td class="amount-cell">

                            Rs.

                            <?= number_format(
                                $payment_amount,
                                2
                            ) ?>

                        </td>

                        <td class="commission-cell">

                            <?php if (
                                $row_commission > 0
                            ): ?>

                                Rs.

                                <?= number_format(
                                    $row_commission,
                                    2
                                ) ?>

                                <div class="small">
                                    <?= number_format(
                                        $commission_rate,
                                        0
                                    ) ?>% company
                                </div>

                            <?php else: ?>

                                <span class="small">
                                    N/A
                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="provider-cell">

                            <?php if (
                                $row_provider_amount > 0
                            ): ?>

                                Rs.

                                <?= number_format(
                                    $row_provider_amount,
                                    2
                                ) ?>

                                <div class="small">
                                    <?= number_format(
                                        100 -
                                        $commission_rate,
                                        0
                                    ) ?>% provider
                                </div>

                            <?php else: ?>

                                <span class="small">
                                    N/A
                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="method">

                            <?= htmlspecialchars(
                                str_replace(
                                    "_",
                                    " ",
                                    $payment[
                                        "payment_method"
                                    ] ??
                                    "Not specified"
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                            <?php if (
                                !empty(
                                    $payment[
                                        "bank_name"
                                    ]
                                )
                            ): ?>

                                <div class="small">

                                    <?= htmlspecialchars(
                                        $payment[
                                            "bank_name"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>

                        <td class="transaction">

                            <?php if (
                                !empty(
                                    $payment[
                                        "transaction_id"
                                    ]
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $payment[
                                        "transaction_id"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            <?php elseif (
                                !empty(
                                    $payment[
                                        "reference_number"
                                    ]
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $payment[
                                        "reference_number"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            <?php else: ?>

                                <span class="small">
                                    Not available
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <span
                                class="status status-<?= htmlspecialchars(
                                    $status_class,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    ucfirst(
                                        $status ?:
                                        "Pending"
                                    ),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </span>

                        </td>

                        <td class="payment-date">

                            <?php if (
                                !empty(
                                    $payment[
                                        "payment_date"
                                    ]
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $payment[
                                        "payment_date"
                                    ],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            <?php else: ?>

                                <span class="small">
                                    Not paid yet
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (
                                $status ===
                                "pending"
                            ): ?>

                                <div class="actions">

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Mark this payment as PAID?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $csrf_token,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int)
                                                $payment["id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="paid"
                                        >

                                        <button
                                            type="submit"
                                            class="action-btn paid-btn"
                                        >
                                            ✓ Mark Paid
                                        </button>

                                    </form>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Mark this payment as FAILED?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $csrf_token,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?= (int)
                                                $payment["id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="failed"
                                        >

                                        <button
                                            type="submit"
                                            class="action-btn failed-btn"
                                        >
                                            ✕ Failed
                                        </button>

                                    </form>

                                </div>

                            <?php else: ?>

                                <span class="no-action">
                                    No action
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <div class="commission-info">

            🏢 <strong>Company Commission:</strong>
            <?= number_format(
                $commission_rate,
                0
            ) ?>%

            &nbsp; | &nbsp;

            💰 <strong>Provider Share:</strong>
            <?= number_format(
                100 - $commission_rate,
                0
            ) ?>%

            <br>

            Company commission is automatically calculated
            from each pending/paid payment.
            The remaining amount is the provider's amount.

        </div>

        <div class="note">

            💡 Pending payments can be verified from this page.
            Marking a payment as paid automatically updates the
            customer's booking payment status.

        </div>

    <?php endif; ?>

</div>

</div>

</body>

</html>

<?php

$conn->close();

?>