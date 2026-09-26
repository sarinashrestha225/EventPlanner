<?php

require_once "../../database.php";

require_once "../includes/auth.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php");
    exit();

}

$customer_id = (int) $_GET["id"];

$check = $conn->prepare("
    SELECT id
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$check->bind_param("i", $customer_id);

$check->execute();

$result = $check->get_result();

if ($result->num_rows === 0) {

    $check->close();

    header("Location: index.php");
    exit();

}

$check->close();

$delete = $conn->prepare("
    DELETE FROM customers
    WHERE id = ?
");

$delete->bind_param("i", $customer_id);

if ($delete->execute()) {

    $delete->close();

    header("Location: index.php?deleted=1");
    exit();

}

$error = $delete->error;

$delete->close();

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
        Delete Customer | Event Planner Admin
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

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

        }

        .error-card {

            width: 90%;

            max-width: 500px;

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 35px;

            text-align: center;

            box-shadow:
                0 8px 30px rgba(218,165,32,0.15);

        }

        .icon {

            font-size: 50px;

            margin-bottom: 15px;

        }

        h2 {

            color: #8b4d00;

            margin-bottom: 12px;

        }

        p {

            color: #765d40;

            margin-bottom: 25px;

            line-height: 1.6;

        }

        .back-btn {

            display: inline-block;

            background: #d4a017;

            color: white;

            text-decoration: none;

            padding: 11px 20px;

            border-radius: 9px;

            font-weight: bold;

        }

        .back-btn:hover {

            background: #b8860b;

        }

    </style>

</head>

<body>

<div class="error-card">

    <div class="icon">

        ⚠️

    </div>

    <h2>

        Unable to Delete Customer

    </h2>

    <p>

        <?= htmlspecialchars($error) ?>

    </p>

    <a
        href="index.php"
        class="back-btn"
    >

        ← Back to Customers

    </a>

</div>

</body>

</html>