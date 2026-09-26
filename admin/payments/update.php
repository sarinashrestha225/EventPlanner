<?php

session_start();

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

$payment_id = isset($_GET["id"]) && is_numeric($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

$status = strtolower(trim($_GET["status"] ?? ""));

$allowed_statuses = [
    "paid",
    "failed",
    "refunded"
];

if (
    $payment_id <= 0 ||
    !in_array($status, $allowed_statuses, true)
) {
    header("Location: index.php");
    exit;
}


$sql = "
    SELECT
        p.id,
        p.booking_id,
        p.customer_id,
        p.provider_id,
        p.amount,
        p.payment_status,

        b.payment_status AS booking_payment_status,
        b.service_id

    FROM payments p

    LEFT JOIN bookings b
        ON p.booking_id = b.id

    WHERE p.id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "i",
    $payment_id
);

$stmt->execute();

$result = $stmt->get_result();

$payment = $result->fetch_assoc();

$stmt->close();


if (!$payment) {
    die("Payment not found.");
}


$booking_id = (int)$payment["booking_id"];
$customer_id = (int)$payment["customer_id"];
$provider_id = (int)$payment["provider_id"];

$service_id = !empty($payment["service_id"])
    ? (int)$payment["service_id"]
    : 0;

$payment_amount = (float)$payment["amount"];

$old_payment_status =
    strtolower(
        trim(
            $payment["payment_status"]
        )
    );


$conn->begin_transaction();


try {

    /*
     * PAYMENT STATUS
     */

    if ($status === "paid") {

        $payment_sql = "
            UPDATE payments
            SET
                payment_status = 'paid',
                payment_date = COALESCE(
                    payment_date,
                    NOW()
                )
            WHERE id = ?
        ";

        $booking_payment_status = "paid";

        $notification_title =
            "Payment Approved";

        $notification_message =
            "Your payment for booking #{$booking_id} has been approved successfully.";

    } elseif ($status === "failed") {

        $payment_sql = "
            UPDATE payments
            SET
                payment_status = 'failed'
            WHERE id = ?
        ";

        $booking_payment_status = "unpaid";

        $notification_title =
            "Payment Rejected";

        $notification_message =
            "Your payment for booking #{$booking_id} was rejected. Please check your payment details and try again.";

    } else {

        $payment_sql = "
            UPDATE payments
            SET
                payment_status = 'refunded'
            WHERE id = ?
        ";

        $booking_payment_status = "unpaid";

        $notification_title =
            "Payment Refunded";

        $notification_message =
            "Your payment for booking #{$booking_id} has been marked as refunded.";
    }


    $update_payment =
        $conn->prepare($payment_sql);

    if (!$update_payment) {
        throw new Exception(
            $conn->error
        );
    }

    $update_payment->bind_param(
        "i",
        $payment_id
    );

    if (!$update_payment->execute()) {
        throw new Exception(
            $update_payment->error
        );
    }

    $update_payment->close();


    /*
     * BOOKING PAYMENT STATUS
     */

    if ($booking_id > 0) {

        $update_booking =
            $conn->prepare("
                UPDATE bookings
                SET payment_status = ?
                WHERE id = ?
            ");

        if (!$update_booking) {
            throw new Exception(
                $conn->error
            );
        }

        $update_booking->bind_param(
            "si",
            $booking_payment_status,
            $booking_id
        );

        if (!$update_booking->execute()) {
            throw new Exception(
                $update_booking->error
            );
        }

        $update_booking->close();
    }


    /*
     * CREATE COMMISSION
     */

    if ($status === "paid") {

        $commission_rate = 10.00;

        $commission_amount =
            round(
                $payment_amount *
                $commission_rate /
                100,
                2
            );

        $provider_amount =
            round(
                $payment_amount -
                $commission_amount,
                2
            );


        /*
         * Check duplicate commission
         */

        $check_commission =
            $conn->prepare("
                SELECT id
                FROM commissions
                WHERE payment_id = ?
                LIMIT 1
            ");

        if (!$check_commission) {
            throw new Exception(
                $conn->error
            );
        }

        $check_commission->bind_param(
            "i",
            $payment_id
        );

        if (!$check_commission->execute()) {
            throw new Exception(
                $check_commission->error
            );
        }

        $commission_result =
            $check_commission->get_result();

        $existing_commission =
            $commission_result->fetch_assoc();

        $check_commission->close();


        /*
         * Insert only if commission
         * does not already exist
         */

        if (!$existing_commission) {

            $insert_commission =
                $conn->prepare("
                    INSERT INTO commissions
                    (
                        booking_id,
                        payment_id,
                        customer_id,
                        provider_id,
                        service_id,
                        total_amount,
                        commission_rate,
                        commission_amount,
                        provider_amount,
                        status,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        NULLIF(?, 0),
                        ?,
                        ?,
                        ?,
                        ?,
                        'pending',
                        NOW()
                    )
                ");

            if (!$insert_commission) {
                throw new Exception(
                    "Commission Prepare Error: " .
                    $conn->error
                );
            }


            /*
             * 5 integers + 4 decimal values
             *
             * This is the important fix:
             * iiiii dddd
             */

            $insert_commission->bind_param(
                "iiiiidddd",
                $booking_id,
                $payment_id,
                $customer_id,
                $provider_id,
                $service_id,
                $payment_amount,
                $commission_rate,
                $commission_amount,
                $provider_amount
            );


            if (!$insert_commission->execute()) {
                throw new Exception(
                    "Commission Insert Error: " .
                    $insert_commission->error
                );
            }

            $insert_commission->close();
        }
    }


    /*
     * REFUND
     */

    if (
        $status === "refunded" &&
        $old_payment_status === "paid"
    ) {

        $delete_commission =
            $conn->prepare("
                DELETE FROM commissions
                WHERE payment_id = ?
            ");

        if (!$delete_commission) {
            throw new Exception(
                $conn->error
            );
        }

        $delete_commission->bind_param(
            "i",
            $payment_id
        );

        if (!$delete_commission->execute()) {
            throw new Exception(
                $delete_commission->error
            );
        }

        $delete_commission->close();
    }


    /*
     * CUSTOMER NOTIFICATION
     */

    if ($customer_id > 0) {

        $customer_notification =
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
                    'payment',
                    0,
                    NOW()
                )
            ");

        if (!$customer_notification) {
            throw new Exception(
                $conn->error
            );
        }

        $customer_notification->bind_param(
            "iss",
            $customer_id,
            $notification_title,
            $notification_message
        );

        if (!$customer_notification->execute()) {
            throw new Exception(
                $customer_notification->error
            );
        }

        $customer_notification->close();
    }


    /*
     * PROVIDER NOTIFICATION
     */

    if (
        $provider_id > 0 &&
        $status === "paid"
    ) {

        $provider_title =
            "Payment Received";

        $provider_message =
            "Payment for booking #{$booking_id} has been approved.";

        $provider_notification =
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
                    'provider',
                    ?,
                    ?,
                    'payment',
                    0,
                    NOW()
                )
            ");

        if (!$provider_notification) {
            throw new Exception(
                $conn->error
            );
        }

        $provider_notification->bind_param(
            "iss",
            $provider_id,
            $provider_title,
            $provider_message
        );

        if (!$provider_notification->execute()) {
            throw new Exception(
                $provider_notification->error
            );
        }

        $provider_notification->close();
    }


    /*
     * COMMIT
     */

    $conn->commit();


    header(
        "Location: view.php?id=" .
        $payment_id .
        "&success=" .
        urlencode($status)
    );

    exit;


} catch (Exception $e) {

    $conn->rollback();

    die(
        "Payment update failed: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

?>