<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$payment_id =
    isset($_GET["payment_id"])
    ? intval($_GET["payment_id"])
    : 0;

if ($payment_id <= 0) {
    die("Invalid payment.");
}

$customer_id = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("
    SELECT
        id,
        booking_id,
        amount,
        payment_method,
        transaction_id,
        reference_number,
        payment_status,
        payment_date
    FROM payments
    WHERE id = ?
    AND customer_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $payment_id,
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

$payment = $result->fetch_assoc();

$stmt->close();

if (!$payment) {
    die("Payment record not found.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Payment Successful</title>

    <style>

        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .success-box {
            background: white;
            width: 500px;
            max-width: 90%;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        }

        .icon {
            width: 70px;
            height: 70px;
            margin: auto;
            border-radius: 50%;
            background: #28a745;
            color: white;
            font-size: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        h1 {
            margin-top: 20px;
            color: #333;
        }

        .status {
            display: inline-block;
            margin: 15px 0;
            padding: 8px 18px;
            border-radius: 20px;
            background: #fff3cd;
            color: #856404;
            font-weight: bold;
        }

        .details {
            text-align: left;
            margin-top: 25px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
        }

        .amount {
            color: #c2185b;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 25px;
            background: #c2185b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        .btn:hover {
            background: #a3154d;
        }

    </style>

</head>

<body>

<div class="success-box">

    <div class="icon">
        ✓
    </div>

    <h1>Payment Submitted!</h1>

    <div class="status">
        <?= htmlspecialchars(
            ucfirst($payment["payment_status"])
        ) ?>
    </div>

    <div class="details">

        <div class="row">
            <span class="label">
                Payment ID
            </span>

            <span class="value">
                #<?= $payment["id"] ?>
            </span>
        </div>

        <div class="row">
            <span class="label">
                Booking ID
            </span>

            <span class="value">
                #<?= $payment["booking_id"] ?>
            </span>
        </div>

        <div class="row">
            <span class="label">
                Amount
            </span>

            <span class="value amount">
                Rs. <?= number_format(
                    $payment["amount"],
                    2
                ) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">
                Payment Method
            </span>

            <span class="value">
                <?= htmlspecialchars(
                    ucfirst(
                        str_replace(
                            "_",
                            " ",
                            $payment["payment_method"]
                        )
                    )
                ) ?>
            </span>
        </div>

        <?php if (!empty($payment["transaction_id"])): ?>

        <div class="row">

            <span class="label">
                Transaction ID
            </span>

            <span class="value">
                <?= htmlspecialchars(
                    $payment["transaction_id"]
                ) ?>
            </span>

        </div>

        <?php endif; ?>

        <?php if (!empty($payment["reference_number"])): ?>

        <div class="row">

            <span class="label">
                Reference Number
            </span>

            <span class="value">
                <?= htmlspecialchars(
                    $payment["reference_number"]
                ) ?>
            </span>

        </div>

        <?php endif; ?>

        <div class="row">

            <span class="label">
                Payment Date
            </span>

            <span class="value">
                <?= htmlspecialchars(
                    $payment["payment_date"]
                ) ?>
            </span>

        </div>

    </div>

    <a href="../customer/" class="btn">
        Back to Dashboard
    </a>

</div>

</body>

</html>