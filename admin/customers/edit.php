<?php

require_once "../../database.php";

require_once "../includes/auth.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php");
    exit();

}

$customer_id = (int) $_GET["id"];

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: index.php");
    exit();

}

$customer = $result->fetch_assoc();

$stmt->close();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");

    $email = trim($_POST["email"] ?? "");

    $phone = trim($_POST["phone"] ?? "");

    $status = trim($_POST["status"] ?? "active");

    if ($name === "") {

        $error = "Customer name is required.";

    } elseif ($email === "") {

        $error = "Email address is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($phone === "") {

        $error = "Phone number is required.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid status selected.";

    }

    if ($error === "") {

        $update = $conn->prepare("
            UPDATE customers
            SET
                name = ?,
                email = ?,
                phone = ?,
                status = ?
            WHERE id = ?
        ");

        $update->bind_param(
            "ssssi",
            $name,
            $email,
            $phone,
            $status,
            $customer_id
        );

        if ($update->execute()) {

            $success = "Customer updated successfully.";

            $customer["name"] = $name;

            $customer["email"] = $email;

            $customer["phone"] = $phone;

            $customer["status"] = $status;

        } else {

            $error =
                "Failed to update customer: "
                . $conn->error;

        }

        $update->close();

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
        Edit Customer | Event Planner Admin
    </title>

    <style>

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: Arial, Helvetica, sans-serif;

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
                0 5px 20px rgba(218,165,32,0.15);

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

            max-width: 850px;

            box-shadow:
                0 5px 20px rgba(218,165,32,0.10);

        }

        .customer-id {

            background: #fff5c7;

            border: 1px solid #e9c85d;

            padding: 12px 15px;

            border-radius: 10px;

            margin-bottom: 25px;

            color: #795600;

            font-size: 14px;

            font-weight: bold;

        }

        .error {

            background: #ffe0e0;

            border: 1px solid #efaaaa;

            color: #9b2929;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 14px;

        }

        .success {

            background: #fff0b0;

            border: 1px solid #e6c65c;

            color: #765400;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 14px;

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

        label {

            margin-bottom: 8px;

            color: #704800;

            font-size: 14px;

            font-weight: bold;

        }

        input,
        select {

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
        select:focus {

            border-color: #d4a017;

            box-shadow:
                0 0 0 3px
                rgba(212,160,23,0.12);

        }

        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 25px;

            border-top: 1px solid #f0d88a;

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

        @media (max-width: 700px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

            .form-grid {

                grid-template-columns: 1fr;

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
            ✏️ Edit Customer
        </h1>

        <p>
            Update customer information
        </p>

    </div>

    <div class="form-card">

        <div class="customer-id">

            Customer ID:

            #<?= (int)$customer["id"] ?>

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

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label for="name">

                        Customer Name

                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars(
                            $customer["name"] ?? ""
                        ) ?>"
                        placeholder="Enter customer name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="email">

                        Email Address

                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $customer["email"] ?? ""
                        ) ?>"
                        placeholder="Enter email address"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="phone">

                        Phone Number

                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars(
                            $customer["phone"] ?? ""
                        ) ?>"
                        placeholder="Enter phone number"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="status">

                        Account Status

                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="active"
                            <?= ($customer["status"] ?? "") === "active"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= ($customer["status"] ?? "") === "inactive"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="btn save-btn"
                >

                    💾 Update Customer

                </button>

                <a
                    href="index.php"
                    class="btn back-btn"
                >

                    ← Cancel

                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>