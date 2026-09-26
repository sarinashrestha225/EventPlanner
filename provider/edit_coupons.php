<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$coupon_id = (int) ($_GET["id"] ?? 0);

if ($coupon_id <= 0) {
    header("Location: coupons.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM coupons
    WHERE id = ? AND provider_id = ?
");

$stmt->bind_param("ii", $coupon_id, $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$coupon = $result->fetch_assoc();

$stmt->close();

if (!$coupon) {
    header("Location: coupons.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $code = strtoupper(trim($_POST["code"] ?? ""));
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $discount_type = $_POST["discount_type"] ?? "percentage";
    $discount_value = (float) ($_POST["discount_value"] ?? 0);
    $minimum_amount = (float) ($_POST["minimum_amount"] ?? 0);

    $maximum_discount = ($_POST["maximum_discount"] ?? "") !== ""
        ? (float) $_POST["maximum_discount"]
        : null;

    $usage_limit = ($_POST["usage_limit"] ?? "") !== ""
        ? (int) $_POST["usage_limit"]
        : null;

    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";
    $status = $_POST["status"] ?? "active";

    if ($code === "" || $name === "" || $start_date === "" || $end_date === "") {
        $error = "Please fill all required fields.";
    } elseif (!in_array($discount_type, ["percentage", "fixed"], true)) {
        $error = "Invalid discount type.";
    } elseif (!in_array($status, ["active", "inactive"], true)) {
        $error = "Invalid status.";
    } elseif ($discount_value <= 0) {
        $error = "Discount value must be greater than 0.";
    } elseif ($discount_type === "percentage" && $discount_value > 100) {
        $error = "Percentage discount cannot be more than 100%.";
    } elseif ($minimum_amount < 0) {
        $error = "Minimum amount cannot be negative.";
    } elseif ($maximum_discount !== null && $maximum_discount < 0) {
        $error = "Maximum discount cannot be negative.";
    } elseif ($usage_limit !== null && $usage_limit <= 0) {
        $error = "Usage limit must be greater than 0.";
    } elseif ($end_date < $start_date) {
        $error = "End date cannot be before start date.";
    } else {

        $check = $conn->prepare("
            SELECT id
            FROM coupons
            WHERE code = ? AND id != ?
        ");

        $check->bind_param("si", $code, $coupon_id);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "This coupon code already exists.";
        }

        $check->close();

        if ($error === "") {

            $stmt = $conn->prepare("
                UPDATE coupons
                SET
                    code = ?,
                    name = ?,
                    description = ?,
                    discount_type = ?,
                    discount_value = ?,
                    minimum_amount = ?,
                    maximum_discount = ?,
                    usage_limit = ?,
                    start_date = ?,
                    end_date = ?,
                    status = ?
                WHERE id = ? AND provider_id = ?
            ");

            $stmt->bind_param(
                "ssssdddisssii",
                $code,
                $name,
                $description,
                $discount_type,
                $discount_value,
                $minimum_amount,
                $maximum_discount,
                $usage_limit,
                $start_date,
                $end_date,
                $status,
                $coupon_id,
                $provider_id
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: coupons.php");
                exit;
            }

            $error = "Unable to update coupon.";
            $stmt->close();
        }
    }

    $coupon["code"] = $code;
    $coupon["name"] = $name;
    $coupon["description"] = $description;
    $coupon["discount_type"] = $discount_type;
    $coupon["discount_value"] = $discount_value;
    $coupon["minimum_amount"] = $minimum_amount;
    $coupon["maximum_discount"] = $maximum_discount;
    $coupon["usage_limit"] = $usage_limit;
    $coupon["start_date"] = $start_date;
    $coupon["end_date"] = $end_date;
    $coupon["status"] = $status;
}

$current_page = basename($_SERVER["PHP_SELF"]);

require_once __DIR__ . "/includes/header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Coupon</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf2;
            color: #4b3a2a;
        }

        .container {
            margin-left: 250px;
            padding: 35px;
            max-width: 1100px;
        }

        h1 {
            color: #b8860b;
            margin-bottom: 25px;
        }

        .card {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #ead7a0;
            box-shadow: 0 5px 18px rgba(184, 134, 11, 0.08);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .group {
            display: flex;
            flex-direction: column;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            padding: 11px;
            border: 1px solid #dccb9a;
            border-radius: 8px;
            background: #fffdf8;
            font-size: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .error {
            background: #fde2e2;
            color: #a33a3a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .buttons {
            margin-top: 25px;
            display: flex;
            gap: 10px;
        }

        button,
        a {
            padding: 11px 18px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        button {
            background: #d4af37;
            color: #fff;
        }

        .cancel {
            background: #f3d6df;
            color: #8a3150;
        }

        @media (max-width: 900px) {
            .container {
                margin-left: 0;
                padding: 20px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Edit Coupon</h1>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <form method="POST">

            <div class="grid">

                <div class="group">
                    <label>Coupon Code *</label>
                    <input
                        type="text"
                        name="code"
                        maxlength="50"
                        value="<?= htmlspecialchars($coupon["code"]) ?>"
                        required
                    >
                </div>

                <div class="group">
                    <label>Coupon Name *</label>
                    <input
                        type="text"
                        name="name"
                        maxlength="100"
                        value="<?= htmlspecialchars($coupon["name"]) ?>"
                        required
                    >
                </div>

                <div class="group full">
                    <label>Description</label>
                    <textarea name="description"><?= htmlspecialchars($coupon["description"] ?? "") ?></textarea>
                </div>

                <div class="group">
                    <label>Discount Type *</label>
                    <select name="discount_type">
                        <option value="percentage" <?= $coupon["discount_type"] === "percentage" ? "selected" : "" ?>>
                            Percentage (%)
                        </option>
                        <option value="fixed" <?= $coupon["discount_type"] === "fixed" ? "selected" : "" ?>>
                            Fixed Amount (Rs.)
                        </option>
                    </select>
                </div>

                <div class="group">
                    <label>Discount Value *</label>
                    <input
                        type="number"
                        name="discount_value"
                        min="0.01"
                        step="0.01"
                        value="<?= htmlspecialchars($coupon["discount_value"]) ?>"
                        required
                    >
                </div>

                <div class="group">
                    <label>Minimum Amount</label>
                    <input
                        type="number"
                        name="minimum_amount"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars($coupon["minimum_amount"]) ?>"
                    >
                </div>

                <div class="group">
                    <label>Maximum Discount</label>
                    <input
                        type="number"
                        name="maximum_discount"
                        min="0"
                        step="0.01"
                        value="<?= $coupon["maximum_discount"] !== null ? htmlspecialchars($coupon["maximum_discount"]) : "" ?>"
                    >
                </div>

                <div class="group">
                    <label>Usage Limit</label>
                    <input
                        type="number"
                        name="usage_limit"
                        min="1"
                        value="<?= $coupon["usage_limit"] !== null ? htmlspecialchars($coupon["usage_limit"]) : "" ?>"
                    >
                </div>

                <div class="group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $coupon["status"] === "active" ? "selected" : "" ?>>
                            Active
                        </option>
                        <option value="inactive" <?= $coupon["status"] === "inactive" ? "selected" : "" ?>>
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="group">
                    <label>Start Date *</label>
                    <input
                        type="date"
                        name="start_date"
                        value="<?= htmlspecialchars($coupon["start_date"]) ?>"
                        required
                    >
                </div>

                <div class="group">
                    <label>End Date *</label>
                    <input
                        type="date"
                        name="end_date"
                        value="<?= htmlspecialchars($coupon["end_date"]) ?>"
                        required
                    >
                </div>

            </div>

            <div class="buttons">
                <button type="submit">Update Coupon</button>

                <a href="coupons.php" class="cancel">
                    Cancel
                </a>
            </div>

        </form>

    </div>

</div>

</body>
</html>