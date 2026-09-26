<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];

$message = "";
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
            WHERE code = ?
        ");

        $check->bind_param("s", $code);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "This coupon code already exists.";
        }

        $check->close();

        if ($error === "") {

            $stmt = $conn->prepare("
                INSERT INTO coupons
                (
                    provider_id,
                    code,
                    name,
                    description,
                    discount_type,
                    discount_value,
                    minimum_amount,
                    maximum_discount,
                    usage_limit,
                    start_date,
                    end_date,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssdddi sss",
                $provider_id,
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
                $status
            );

            $stmt->close();
        }
    }
}

$stmt = $conn->prepare("
    SELECT
        id,
        code,
        name,
        description,
        discount_type,
        discount_value,
        minimum_amount,
        maximum_discount,
        usage_limit,
        used_count,
        start_date,
        end_date,
        status,
        created_at
    FROM coupons
    WHERE provider_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$coupons = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

$current_page = basename($_SERVER["PHP_SELF"]);

require_once __DIR__ . "/includes/header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons - Provider Panel</title>

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

        .coupon-container {
            margin-left: 250px;
            padding: 35px;
        }

        .page-title {
            color: #b8860b;
            margin-bottom: 25px;
        }

        .form-card,
        .coupon-card {
            background: #fff;
            border: 1px solid #ead7a0;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 18px rgba(184, 134, 11, 0.08);
        }

        .form-card h2,
        .coupon-card h3 {
            color: #b8860b;
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #dccb9a;
            border-radius: 8px;
            background: #fffdf8;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #d4af37;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-create {
            background: #d4af37;
            color: #fff;
            margin-top: 20px;
        }

        .btn-edit {
            background: #f3d6df;
            color: #8a3150;
        }

        .btn-toggle {
            background: #f5e5a8;
            color: #806500;
        }

        .btn-delete {
            background: #f5c6c6;
            color: #9b2222;
        }

        .message {
            background: #e5f6e9;
            color: #26733c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #fde2e2;
            color: #a33a3a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .coupon-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .coupon-code {
            display: inline-block;
            background: #fff3c4;
            color: #8a6800;
            padding: 7px 12px;
            border-radius: 7px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .coupon-info {
            margin: 8px 0;
        }

        .coupon-info strong {
            color: #765600;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background: #dff3e3;
            color: #28733b;
        }

        .status-inactive {
            background: #f1dddd;
            color: #9a3333;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .empty {
            background: #fff;
            border: 1px dashed #d8c38a;
            border-radius: 12px;
            padding: 35px;
            text-align: center;
            color: #8a7657;
        }

        @media (max-width: 900px) {
            .coupon-container {
                margin-left: 0;
                padding: 20px;
            }

            .form-grid,
            .coupon-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="coupon-container">

    <h1 class="page-title">Coupons</h1>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="form-card">

        <h2>Create New Coupon</h2>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Coupon Code *</label>
                    <input
                        type="text"
                        name="code"
                        placeholder="SAVE20"
                        maxlength="50"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Coupon Name *</label>
                    <input
                        type="text"
                        name="name"
                        placeholder="Wedding Special Discount"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label>Description</label>
                    <textarea
                        name="description"
                        placeholder="Describe your coupon..."
                    ></textarea>
                </div>

                <div class="form-group">
                    <label>Discount Type *</label>
                    <select name="discount_type" required>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (Rs.)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Discount Value *</label>
                    <input
                        type="number"
                        name="discount_value"
                        min="0.01"
                        step="0.01"
                        placeholder="20"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Minimum Amount</label>
                    <input
                        type="number"
                        name="minimum_amount"
                        min="0"
                        step="0.01"
                        value="0"
                        placeholder="5000"
                    >
                </div>

                <div class="form-group">
                    <label>Maximum Discount</label>
                    <input
                        type="number"
                        name="maximum_discount"
                        min="0"
                        step="0.01"
                        placeholder="5000"
                    >
                </div>

                <div class="form-group">
                    <label>Usage Limit</label>
                    <input
                        type="number"
                        name="usage_limit"
                        min="1"
                        placeholder="100"
                    >
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date *</label>
                    <input
                        type="date"
                        name="start_date"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>End Date *</label>
                    <input
                        type="date"
                        name="end_date"
                        required
                    >
                </div>

            </div>

            <button type="submit" class="btn btn-create">
                Create Coupon
            </button>

        </form>

    </div>

    <h2 class="page-title">My Coupons</h2>

    <?php if (count($coupons) === 0): ?>

        <div class="empty">
            <h3>No coupons yet</h3>
            <p>Create your first coupon using the form above.</p>
        </div>

    <?php else: ?>

        <div class="coupon-list">

            <?php foreach ($coupons as $coupon): ?>

                <div class="coupon-card">

                    <span class="coupon-code">
                        <?= htmlspecialchars($coupon["code"]) ?>
                    </span>

                    <h3>
                        <?= htmlspecialchars($coupon["name"]) ?>
                    </h3>

                    <?php if (!empty($coupon["description"])): ?>
                        <p>
                            <?= nl2br(htmlspecialchars($coupon["description"])) ?>
                        </p>
                    <?php endif; ?>

                    <div class="coupon-info">
                        <strong>Discount:</strong>
                        <?php if ($coupon["discount_type"] === "percentage"): ?>
                            <?= number_format((float) $coupon["discount_value"], 2) ?>%
                        <?php else: ?>
                            Rs. <?= number_format((float) $coupon["discount_value"], 2) ?>
                        <?php endif; ?>
                    </div>

                    <div class="coupon-info">
                        <strong>Minimum Amount:</strong>
                        Rs. <?= number_format((float) $coupon["minimum_amount"], 2) ?>
                    </div>

                    <?php if ($coupon["maximum_discount"] !== null): ?>
                        <div class="coupon-info">
                            <strong>Maximum Discount:</strong>
                            Rs. <?= number_format((float) $coupon["maximum_discount"], 2) ?>
                        </div>
                    <?php endif; ?>

                    <div class="coupon-info">
                        <strong>Usage:</strong>
                        <?= (int) $coupon["used_count"] ?>

                        <?php if ($coupon["usage_limit"] !== null): ?>
                            / <?= (int) $coupon["usage_limit"] ?>
                        <?php endif; ?>
                    </div>

                    <div class="coupon-info">
                        <strong>Valid:</strong>
                        <?= htmlspecialchars($coupon["start_date"]) ?>
                        to
                        <?= htmlspecialchars($coupon["end_date"]) ?>
                    </div>

                    <div class="coupon-info">
                        <strong>Status:</strong>

                        <?php if ($coupon["status"] === "active"): ?>
                            <span class="status status-active">Active</span>
                        <?php else: ?>
                            <span class="status status-inactive">Inactive</span>
                        <?php endif; ?>
                    </div>

                    <div class="actions">

                        <a
                            href="edit_coupon.php?id=<?= (int) $coupon["id"] ?>"
                            class="btn btn-edit"
                        >
                            Edit
                        </a>

                        <a
                            href="toggle_coupon.php?id=<?= (int) $coupon["id"] ?>"
                            class="btn btn-toggle"
                        >
                            <?= $coupon["status"] === "active" ? "Deactivate" : "Activate" ?>
                        </a>

                        <a
                            href="delete_coupon.php?id=<?= (int) $coupon["id"] ?>"
                            class="btn btn-delete"
                            onclick="return confirm('Delete this coupon?')"
                        >
                            Delete
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>