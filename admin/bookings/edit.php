<?php

require_once "../../database.php";

require_once "../includes/auth.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php");
    exit();

}

$booking_id = (int) $_GET["id"];

$sql = "
    SELECT
        id,
        customer_id,
        event_type_id,
        service_id,
        provider_id,
        booking_date,
        booking_time,
        amount,
        status,
        payment_status,
        customer_note,
        created_at,
        updated_at
    FROM bookings
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param("i", $booking_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: index.php");
    exit();

}

$booking = $result->fetch_assoc();

$stmt->close();

$error = "";

$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $booking_date = trim(
        $_POST["booking_date"] ?? ""
    );

    $booking_time = trim(
        $_POST["booking_time"] ?? ""
    );

    $amount = trim(
        $_POST["amount"] ?? ""
    );

    $status = trim(
        $_POST["status"] ?? ""
    );

    $payment_status = trim(
        $_POST["payment_status"] ?? ""
    );

    $customer_note = trim(
        $_POST["customer_note"] ?? ""
    );

    if ($booking_date === "") {

        $error = "Booking date is required.";

    }

    elseif (
        $amount === ""
        ||
        !is_numeric($amount)
        ||
        $amount < 0
    ) {

        $error = "Please enter a valid amount.";

    }

    elseif (
        !in_array(
            $status,
            [
                "pending",
                "confirmed",
                "completed",
                "cancelled"
            ],
            true
        )
    ) {

        $error = "Invalid booking status.";

    }

    elseif (
        !in_array(
            $payment_status,
            [
                "unpaid",
                "paid",
                "partial",
                "refunded"
            ],
            true
        )
    ) {

        $error = "Invalid payment status.";

    }

    else {

        $update_sql = "
            UPDATE bookings
            SET
                booking_date = ?,
                booking_time = NULLIF(?, ''),
                amount = ?,
                status = ?,
                payment_status = ?,
                customer_note = NULLIF(?, '')
            WHERE id = ?
        ";

        $update = $conn->prepare(
            $update_sql
        );

        if (!$update) {

            $error =
                "Database Error: " .
                $conn->error;

        } else {

            $amount_value = (float)$amount;

            $update->bind_param(
                "ssdsssi",
                $booking_date,
                $booking_time,
                $amount_value,
                $status,
                $payment_status,
                $customer_note,
                $booking_id
            );

            if ($update->execute()) {

                $success =
                    "Booking updated successfully.";

                $booking["booking_date"] =
                    $booking_date;

                $booking["booking_time"] =
                    $booking_time;

                $booking["amount"] =
                    $amount_value;

                $booking["status"] =
                    $status;

                $booking["payment_status"] =
                    $payment_status;

                $booking["customer_note"] =
                    $customer_note;

            } else {

                $error =
                    "Failed to update booking: " .
                    $update->error;

            }

            $update->close();

        }

    }

}

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
        Edit Booking | Event Planner Admin
    </title>

    <style>

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }

        body {

            background: #fffaf0;

            color: #5a4630;

        }

        .main-content {

            margin-left: 250px;

            padding: 30px;

            min-height: 100vh;

        }

        .page-header {

            background:
                linear-gradient(
                    135deg,
                    #fff2a8,
                    #ffd75e,
                    #f8c1d4
                );

            padding: 25px 30px;

            border-radius: 20px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 20px
                rgba(218,165,32,0.15);

        }

        .page-header h1 {

            color: #704800;

            font-size: 30px;

            margin-bottom: 7px;

        }

        .page-header p {

            color: #765d40;

            font-size: 14px;

        }

        .form-card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 5px 20px
                rgba(218,165,32,0.10);

        }

        .booking-id {

            background: #fff5c7;

            border: 1px solid #e9c85d;

            color: #795600;

            padding: 14px;

            border-radius: 10px;

            margin-bottom: 25px;

            font-weight: bold;

        }

        .error {

            background: #ffe0e0;

            border: 1px solid #efaaaa;

            color: #9b2929;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

        }

        .success {

            background: #fff0b0;

            border: 1px solid #e6c65c;

            color: #765400;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

        }

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }

        .form-group {

            display: flex;

            flex-direction: column;

        }

        .full {

            grid-column: 1 / -1;

        }

        label {

            color: #704800;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 8px;

        }

        input,
        select,
        textarea {

            width: 100%;

            padding: 12px 14px;

            border: 1px solid #e2c875;

            border-radius: 9px;

            background: #fffdf5;

            color: #5a4630;

            outline: none;

            font-size: 14px;

        }

        input:focus,
        select:focus,
        textarea:focus {

            border-color: #d4a017;

            box-shadow:
                0 0 0 3px
                rgba(212,160,23,0.12);

        }

        textarea {

            min-height: 120px;

            resize: vertical;

        }

        .readonly {

            background: #f5eee0;

            color: #8b7a62;

            cursor: not-allowed;

        }

        .help {

            margin-top: 5px;

            font-size: 11px;

            color: #9a876b;

        }

        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 25px;

            border-top:
                1px solid #f0d88a;

        }

        .btn {

            border: none;

            text-decoration: none;

            padding: 12px 20px;

            border-radius: 9px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

        }

        .save-btn {

            background: #d4a017;

            color: white;

        }

        .save-btn:hover {

            background: #b8860b;

        }

        .back-btn {

            background: #ffd2df;

            color: #a23d5d;

        }

        .back-btn:hover {

            background: #f3abc2;

        }

        @media (max-width: 800px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

            .form-grid {

                grid-template-columns: 1fr;

            }

            .full {

                grid-column: auto;

            }

        }

    </style>

</head>

<body>

<?php

include "../includes/sidebar.php";

?>

<div class="main-content">

    <div class="page-header">

        <h1>
            ✏️ Edit Booking
        </h1>

        <p>
            Update booking information
        </p>

    </div>

    <div class="form-card">

        <div class="booking-id">

            📋 Booking ID:

            #<?= (int)$booking["id"] ?>

        </div>

        <?php if ($error !== ""): ?>

            <div class="error">

                ❌

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="success">

                ✅

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
        >

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Customer ID
                    </label>

                    <input
                        type="text"
                        value="#<?= (int)$booking["customer_id"] ?>"
                        class="readonly"
                        readonly
                    >

                    <div class="help">
                        Customer cannot be changed from this page.
                    </div>

                </div>

                <div class="form-group">

                    <label>
                        Event Type ID
                    </label>

                    <input
                        type="text"
                        value="#<?= (int)$booking["event_type_id"] ?>"
                        class="readonly"
                        readonly
                    >

                    <div class="help">
                        Event type cannot be changed from this page.
                    </div>

                </div>

                <div class="form-group">

                    <label>
                        Service ID
                    </label>

                    <input
                        type="text"
                        value="<?php
                            if (
                                $booking["service_id"] !== null
                            ) {
                                echo "#" .
                                    (int)$booking["service_id"];
                            } else {
                                echo "N/A";
                            }
                        ?>"
                        class="readonly"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>
                        Provider ID
                    </label>

                    <input
                        type="text"
                        value="<?php
                            if (
                                $booking["provider_id"] !== null
                            ) {
                                echo "#" .
                                    (int)$booking["provider_id"];
                            } else {
                                echo "N/A";
                            }
                        ?>"
                        class="readonly"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label for="booking_date">

                        Booking Date *

                    </label>

                    <input
                        type="date"
                        name="booking_date"
                        id="booking_date"
                        value="<?= htmlspecialchars(
                            $booking["booking_date"]
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="booking_time">

                        Booking Time

                    </label>

                    <input
                        type="time"
                        name="booking_time"
                        id="booking_time"
                        value="<?= htmlspecialchars(
                            substr(
                                (string)$booking["booking_time"],
                                0,
                                5
                            )
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="amount">

                        Amount (Rs.) *

                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            (string)$booking["amount"]
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="status">

                        Booking Status *

                    </label>

                    <select
                        name="status"
                        id="status"
                        required
                    >

                        <option
                            value="pending"
                            <?= $booking["status"] === "pending"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Pending
                        </option>

                        <option
                            value="confirmed"
                            <?= $booking["status"] === "confirmed"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Confirmed
                        </option>

                        <option
                            value="completed"
                            <?= $booking["status"] === "completed"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Completed
                        </option>

                        <option
                            value="cancelled"
                            <?= $booking["status"] === "cancelled"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Cancelled
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="payment_status">

                        Payment Status *

                    </label>

                    <select
                        name="payment_status"
                        id="payment_status"
                        required
                    >

                        <option
                            value="unpaid"
                            <?= $booking["payment_status"] === "unpaid"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Unpaid
                        </option>

                        <option
                            value="partial"
                            <?= $booking["payment_status"] === "partial"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Partial
                        </option>

                        <option
                            value="paid"
                            <?= $booking["payment_status"] === "paid"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Paid
                        </option>

                        <option
                            value="refunded"
                            <?= $booking["payment_status"] === "refunded"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Refunded
                        </option>

                    </select>

                </div>

                <div class="form-group full">

                    <label for="customer_note">

                        Customer Note

                    </label>

                    <textarea
                        name="customer_note"
                        id="customer_note"
                        placeholder="Enter customer note..."
                    ><?= htmlspecialchars(
                        (string)$booking["customer_note"]
                    ) ?></textarea>

                </div>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="btn save-btn"
                >

                    💾 Update Booking

                </button>

                <a
                    href="index.php"
                    class="btn back-btn"
                >

                    ← Back to Bookings

                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>