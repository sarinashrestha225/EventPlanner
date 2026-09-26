<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($id <= 0) {

    header("Location: index.php");
    exit();

}

$error = "";

$code = "";
$name = "";
$description = "";

$discount_type = "percentage";
$discount_value = "";

$minimum_amount = "0";
$maximum_discount = "";

$usage_limit = "";

$start_date = "";
$end_date = "";

$status = "active";

$sql = "
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
        status
    FROM coupons
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

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php");
    exit();

}

$coupon = $result->fetch_assoc();

$stmt->close();

$code =
    $coupon["code"];

$name =
    $coupon["name"];

$description =
    $coupon["description"];

$discount_type =
    $coupon["discount_type"];

$discount_value =
    $coupon["discount_value"];

$minimum_amount =
    $coupon["minimum_amount"];

$maximum_discount =
    $coupon["maximum_discount"];

$usage_limit =
    $coupon["usage_limit"];

$start_date =
    $coupon["start_date"];

$end_date =
    $coupon["end_date"];

$status =
    $coupon["status"];

$used_count =
    (int)$coupon["used_count"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $code = strtoupper(
        trim($_POST["code"] ?? "")
    );

    $name = trim(
        $_POST["name"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $discount_type =
        $_POST["discount_type"] ?? "percentage";

    $discount_value =
        trim(
            $_POST["discount_value"] ?? ""
        );

    $minimum_amount =
        trim(
            $_POST["minimum_amount"] ?? "0"
        );

    $maximum_discount =
        trim(
            $_POST["maximum_discount"] ?? ""
        );

    $usage_limit =
        trim(
            $_POST["usage_limit"] ?? ""
        );

    $start_date =
        $_POST["start_date"] ?? "";

    $end_date =
        $_POST["end_date"] ?? "";

    $status =
        $_POST["status"] ?? "active";

    if (
        $code === "" ||
        $name === "" ||
        $discount_value === "" ||
        $start_date === "" ||
        $end_date === ""
    ) {

        $error =
            "Please fill all required fields.";

    }

    elseif (
        !in_array(
            $discount_type,
            [
                "percentage",
                "fixed"
            ],
            true
        )
    ) {

        $error =
            "Invalid discount type.";

    }

    elseif (
        !in_array(
            $status,
            [
                "active",
                "inactive"
            ],
            true
        )
    ) {

        $error =
            "Invalid coupon status.";

    }

    elseif (
        !is_numeric($discount_value) ||
        (float)$discount_value <= 0
    ) {

        $error =
            "Discount value must be greater than 0.";

    }

    elseif (
        $discount_type === "percentage" &&
        (float)$discount_value > 100
    ) {

        $error =
            "Percentage discount cannot be greater than 100%.";

    }

    elseif (
        !is_numeric($minimum_amount) ||
        (float)$minimum_amount < 0
    ) {

        $error =
            "Minimum amount is invalid.";

    }

    elseif (
        $maximum_discount !== "" &&
        (
            !is_numeric($maximum_discount) ||
            (float)$maximum_discount < 0
        )
    ) {

        $error =
            "Maximum discount is invalid.";

    }

    elseif (
        $usage_limit !== "" &&
        (
            !ctype_digit($usage_limit) ||
            (int)$usage_limit <= 0
        )
    ) {

        $error =
            "Usage limit must be a positive number.";

    }

    elseif (
        $usage_limit !== "" &&
        (int)$usage_limit < $used_count
    ) {

        $error =
            "Usage limit cannot be less than the current used count (" .
            $used_count .
            ").";

    }

    elseif (
        $end_date < $start_date
    ) {

        $error =
            "End date cannot be before start date.";

    }

    if ($error === "") {

        $check_sql = "
            SELECT id
            FROM coupons
            WHERE code = ?
            AND id != ?
            LIMIT 1
        ";

        $check_stmt =
            $conn->prepare(
                $check_sql
            );

        if (!$check_stmt) {

            $error =
                "Database Error: " .
                htmlspecialchars(
                    $conn->error
                );

        } else {

            $check_stmt->bind_param(
                "si",
                $code,
                $id
            );

            $check_stmt->execute();

            $check_result =
                $check_stmt->get_result();

            if (
                $check_result->num_rows > 0
            ) {

                $error =
                    "This coupon code already exists.";

            }

            $check_stmt->close();

        }

    }

    if ($error === "") {

        $discount_value =
            (float)$discount_value;

        $minimum_amount =
            (float)$minimum_amount;

        $maximum_discount_value =
            $maximum_discount === ""
                ? null
                : (float)$maximum_discount;

        $usage_limit_value =
            $usage_limit === ""
                ? null
                : (int)$usage_limit;

        $update_sql = "
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
            WHERE id = ?
        ";

        $update_stmt =
            $conn->prepare(
                $update_sql
            );

        if (!$update_stmt) {

            $error =
                "Database Error: " .
                htmlspecialchars(
                    $conn->error
                );

        } else {

            $update_stmt->bind_param(
                "ssssdddiissi",
                $code,
                $name,
                $description,
                $discount_type,
                $discount_value,
                $minimum_amount,
                $maximum_discount_value,
                $usage_limit_value,
                $start_date,
                $end_date,
                $status,
                $id
            );

            if (
                $update_stmt->execute()
            ) {

                $update_stmt->close();

                header(
                    "Location: index.php?updated=1"
                );

                exit();

            } else {

                $error =
                    "Failed to update coupon: " .
                    htmlspecialchars(
                        $update_stmt->error
                    );

            }

            $update_stmt->close();

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
        Edit Coupon | Event Planner Admin
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
                rgba(
                    218,
                    165,
                    32,
                    0.15
                );

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

        .card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );

            max-width: 1000px;

        }

        .current-info {

            background: #fff3f6;

            border: 1px solid #f0c7d4;

            padding: 15px;

            border-radius: 12px;

            margin-bottom: 20px;

            color: #704800;

            font-size: 13px;

        }

        .current-info strong {

            color: #8a3f5d;

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

        .required {

            color: #c45b77;

        }

        input,
        select,
        textarea {

            width: 100%;

            border: 1px solid #e5ce8b;

            background: #fffaf0;

            color: #5a4630;

            border-radius: 10px;

            padding: 12px;

            font-size: 13px;

            outline: none;

        }

        input:focus,
        select:focus,
        textarea:focus {

            border-color: #d8ad2f;

            box-shadow:
                0 0 0 3px
                rgba(
                    246,
                    213,
                    104,
                    0.25
                );

        }

        textarea {

            min-height: 100px;

            resize: vertical;

        }

        .help {

            margin-top: 6px;

            color: #927750;

            font-size: 11px;

        }

        .error {

            background: #ffe0e8;

            border: 1px solid #e5a5b7;

            color: #9a3655;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

        }

        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 25px;

        }

        .btn {

            border: none;

            text-decoration: none;

            padding: 12px 20px;

            border-radius: 10px;

            font-size: 13px;

            font-weight: bold;

            cursor: pointer;

        }

        .update {

            background: #704800;

            color: #fffdf7;

        }

        .update:hover {

            background: #8a6200;

        }

        .cancel {

            background: #f8c1d4;

            color: #8a3f5d;

        }

        .cancel:hover {

            background: #f3afc7;

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
            ✏️ Edit Coupon
        </h1>

        <p>
            Update coupon information
        </p>

    </div>

    <div class="card">

        <div class="current-info">

            🎟️ Editing Coupon:

            <strong>
                <?= htmlspecialchars($code) ?>
            </strong>

            &nbsp; | &nbsp;

            Used:

            <strong>
                <?= $used_count ?>
            </strong>

            times

        </div>

        <?php if ($error !== ""): ?>

            <div class="error">

                ⚠️

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            action=""
        >

            <div class="form-grid">

                <div class="form-group">

                    <label>

                        Coupon Code

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="text"
                        name="code"
                        value="<?= htmlspecialchars($code) ?>"
                        maxlength="50"
                        required
                    >

                    <div class="help">

                        Coupon code will automatically be converted to uppercase.

                    </div>

                </div>

                <div class="form-group">

                    <label>

                        Coupon Name

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($name) ?>"
                        maxlength="100"
                        required
                    >

                </div>

                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                    ><?= htmlspecialchars($description) ?></textarea>

                </div>

                <div class="form-group">

                    <label>

                        Discount Type

                        <span class="required">
                            *
                        </span>

                    </label>

                    <select
                        name="discount_type"
                        id="discount_type"
                        required
                    >

                        <option
                            value="percentage"
                            <?= $discount_type === "percentage"
                                ? "selected"
                                : "" ?>
                        >

                            Percentage (%)

                        </option>

                        <option
                            value="fixed"
                            <?= $discount_type === "fixed"
                                ? "selected"
                                : "" ?>
                        >

                            Fixed Amount (Rs.)

                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>

                        Discount Value

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="number"
                        name="discount_value"
                        id="discount_value"
                        value="<?= htmlspecialchars($discount_value) ?>"
                        min="0.01"
                        step="0.01"
                        required
                    >

                    <div
                        class="help"
                        id="discount-help"
                    ></div>

                </div>

                <div class="form-group">

                    <label>
                        Minimum Booking Amount
                    </label>

                    <input
                        type="number"
                        name="minimum_amount"
                        value="<?= htmlspecialchars($minimum_amount) ?>"
                        min="0"
                        step="0.01"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Maximum Discount
                    </label>

                    <input
                        type="number"
                        name="maximum_discount"
                        value="<?= htmlspecialchars($maximum_discount ?? "") ?>"
                        min="0"
                        step="0.01"
                    >

                    <div class="help">

                        Leave empty for no maximum limit.

                    </div>

                </div>

                <div class="form-group">

                    <label>
                        Usage Limit
                    </label>

                    <input
                        type="number"
                        name="usage_limit"
                        value="<?= htmlspecialchars($usage_limit ?? "") ?>"
                        min="1"
                        step="1"
                    >

                    <div class="help">

                        Current used count:
                        <?= $used_count ?>

                    </div>

                </div>

                <div class="form-group">

                    <label>

                        Start Date

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="date"
                        name="start_date"
                        value="<?= htmlspecialchars($start_date) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>

                        End Date

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="date"
                        name="end_date"
                        value="<?= htmlspecialchars($end_date) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option
                            value="active"
                            <?= $status === "active"
                                ? "selected"
                                : "" ?>
                        >

                            Active

                        </option>

                        <option
                            value="inactive"
                            <?= $status === "inactive"
                                ? "selected"
                                : "" ?>
                        >

                            Inactive

                        </option>

                    </select>

                </div>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="btn update"
                >

                    💾 Update Coupon

                </button>

                <a
                    href="index.php"
                    class="btn cancel"
                >

                    ← Cancel

                </a>

            </div>

        </form>

    </div>

</div>

<script>

    const discountType =
        document.getElementById(
            "discount_type"
        );

    const discountValue =
        document.getElementById(
            "discount_value"
        );

    const discountHelp =
        document.getElementById(
            "discount-help"
        );

    function updateDiscountHelp() {

        if (
            discountType.value ===
            "percentage"
        ) {

            discountValue.max = "100";

            discountValue.placeholder =
                "Example: 20";

            discountHelp.innerText =
                "Enter percentage such as 20 for 20%.";

        } else {

            discountValue.removeAttribute(
                "max"
            );

            discountValue.placeholder =
                "Example: 5000";

            discountHelp.innerText =
                "Enter fixed discount amount in Nepali Rupees.";

        }

    }

    discountType.addEventListener(
        "change",
        updateDiscountHelp
    );

    updateDiscountHelp();

</script>

</body>

</html>