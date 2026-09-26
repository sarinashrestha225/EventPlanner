<?php

require_once "../includes/auth.php";
require_once "../../database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM providers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$provider = $result->fetch_assoc();

$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $service_type = trim($_POST['service_type']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];

    $allowed_status = [
        "pending",
        "active",
        "inactive",
        "rejected"
    ];

    if (!in_array($status, $allowed_status)) {
        $status = "pending";
    }

    $stmt = $conn->prepare("
        UPDATE providers
        SET
            name = ?,
            email = ?,
            phone = ?,
            service_type = ?,
            address = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssssi",
        $name,
        $email,
        $phone,
        $service_type,
        $address,
        $status,
        $id
    );

    if ($stmt->execute()) {

        $stmt->close();

        header("Location: view.php?id=" . $id . "&updated=1");
        exit;

    } else {

        $error = "Provider update failed: " . $conn->error;

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Provider | Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff8f2;
            color: #4b3b2f;
        }

        .container {
            width: 92%;
            max-width: 900px;
            margin: 30px auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0;
            color: #b8860b;
        }

        .back-btn {
            text-decoration: none;
            background: #f8d7da;
            color: #7a3b42;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .back-btn:hover {
            background: #f3c2c7;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-top: 5px solid #d4af37;
        }

        .card h2 {
            margin-top: 0;
            color: #6b4f3a;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            color: #6b4f3a;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfcfbd;
            border-radius: 8px;
            font-size: 15px;
            background: #fffdf9;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #d4af37;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
        }

        .save-btn {
            background: #d4af37;
            color: white;
        }

        .save-btn:hover {
            background: #b99620;
        }

        .cancel-btn {
            background: #f8d7da;
            color: #7a3b42;
        }

        .cancel-btn:hover {
            background: #f3c2c7;
        }

        .provider-id {
            background: #fff8e7;
            border: 1px solid #f0dfb0;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 25px;
            color: #795900;
            font-weight: bold;
        }

        @media (max-width: 700px) {

            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .buttons {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <h1>✏️ Edit Provider</h1>

        <a href="index.php" class="back-btn">
            ← Back to Providers
        </a>

    </div>

    <div class="card">

        <h2>Provider Information</h2>

        <div class="provider-id">
            Provider ID: #<?= htmlspecialchars($provider['id']) ?>
        </div>

        <?php if (!empty($error)): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="row">

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($provider['name']) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($provider['email']) ?>"
                        required
                    >

                </div>

            </div>

            <div class="row">

                <div class="form-group">

                    <label for="phone">
                        Phone
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($provider['phone'] ?? '') ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="service_type">
                        Service Type
                    </label>

                    <input
                        type="text"
                        id="service_type"
                        name="service_type"
                        value="<?= htmlspecialchars($provider['service_type'] ?? '') ?>"
                        placeholder="Example: Photography"
                    >

                </div>

            </div>

            <div class="form-group">

                <label for="address">
                    Address
                </label>

                <textarea
                    id="address"
                    name="address"
                    placeholder="Provider address"
                ><?= htmlspecialchars($provider['address'] ?? '') ?></textarea>

            </div>

            <div class="form-group">

                <label for="status">
                    Provider Status
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option
                        value="pending"
                        <?= $provider['status'] === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="active"
                        <?= $provider['status'] === 'active' ? 'selected' : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $provider['status'] === 'inactive' ? 'selected' : '' ?>
                    >
                        Inactive
                    </option>

                    <option
                        value="rejected"
                        <?= $provider['status'] === 'rejected' ? 'selected' : '' ?>
                    >
                        Rejected
                    </option>

                </select>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="btn save-btn"
                >
                    💾 Save Changes
                </button>

                <a
                    href="view.php?id=<?= $provider['id'] ?>"
                    class="btn cancel-btn"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>