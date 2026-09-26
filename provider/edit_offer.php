<?php
session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$offer_id = (int) ($_GET["id"] ?? $_POST["offer_id"] ?? 0);

if ($offer_id <= 0) {
    header("Location: offers.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM offers
    WHERE id = ? AND provider_id = ?
");
$stmt->bind_param("ii", $offer_id, $provider_id);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();
$stmt->close();

if (!$offer) {
    header("Location: offers.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $service_id = (int) ($_POST["service_id"] ?? 0);
    $discount_type = $_POST["discount_type"] ?? "percentage";
    $discount_value = (float) ($_POST["discount_value"] ?? 0);
    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";

    if ($title === "" || $start_date === "" || $end_date === "") {
        $error = "Please fill all required fields.";
    } elseif (!in_array($discount_type, ["percentage", "fixed"], true)) {
        $error = "Invalid discount type.";
    } elseif ($discount_value <= 0) {
        $error = "Discount value must be greater than 0.";
    } elseif ($discount_type === "percentage" && $discount_value > 100) {
        $error = "Percentage discount cannot be more than 100%.";
    } elseif ($end_date < $start_date) {
        $error = "End date cannot be before start date.";
    } else {

        if ($service_id > 0) {

            $stmt = $conn->prepare("
                SELECT id
                FROM services
                WHERE id = ? AND provider_id = ?
            ");

            $stmt->bind_param("ii", $service_id, $provider_id);
            $stmt->execute();

            $service_result = $stmt->get_result();
            $service_exists = $service_result->fetch_assoc();

            $stmt->close();

            if (!$service_exists) {
                $error = "Invalid service selected.";
            }

        } else {
            $service_id = null;
        }

        if ($error === "") {

            $stmt = $conn->prepare("
                UPDATE offers
                SET
                    service_id = ?,
                    title = ?,
                    description = ?,
                    discount_type = ?,
                    discount_value = ?,
                    start_date = ?,
                    end_date = ?
                WHERE id = ? AND provider_id = ?
            ");

            $stmt->bind_param(
                "isssdssii",
                $service_id,
                $title,
                $description,
                $discount_type,
                $discount_value,
                $start_date,
                $end_date,
                $offer_id,
                $provider_id
            );

            if ($stmt->execute()) {
                $message = "Offer updated successfully.";

                $offer["service_id"] = $service_id;
                $offer["title"] = $title;
                $offer["description"] = $description;
                $offer["discount_type"] = $discount_type;
                $offer["discount_value"] = $discount_value;
                $offer["start_date"] = $start_date;
                $offer["end_date"] = $end_date;
            } else {
                $error = "Unable to update offer.";
            }

            $stmt->close();
        }
    }
}

$services = [];

$stmt = $conn->prepare("
    SELECT id, service_name
    FROM services
    WHERE provider_id = ?
    ORDER BY service_name ASC
");

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$service_result = $stmt->get_result();

while ($row = $service_result->fetch_assoc()) {
    $services[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Offer | Provider</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf0;
            color: #4b3a2a;
        }

        .page {
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            color: #9b6b00;
        }

        .header p {
            color: #777;
            margin-top: 7px;
        }

        .card {
            max-width: 850px;
            background: #fff;
            padding: 28px;
            border-radius: 15px;
            border: 1px solid #f1dfb0;
            box-shadow: 0 4px 15px rgba(180, 140, 50, 0.12);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5cf96;
            border-radius: 9px;
            background: #fffdf7;
            font-size: 14px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-save {
            background: #d4af37;
            color: white;
        }

        .btn-back {
            background: #f3e4b7;
            color: #665000;
        }

        .message {
            background: #e6f6e6;
            color: #247024;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #fde4e4;
            color: #9b1c1c;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 750px) {
            .page {
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

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="page">

    <div class="header">
        <div>
            <h1>Edit Offer</h1>
            <p>Update your offer details.</p>
        </div>
    </div>

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

    <div class="card">

        <form method="POST">

            <input
                type="hidden"
                name="offer_id"
                value="<?= (int) $offer["id"] ?>"
            >

            <div class="form-grid">

                <div>
                    <label>Offer Title *</label>

                    <input
                        type="text"
                        name="title"
                        value="<?= htmlspecialchars($offer["title"]) ?>"
                        required
                    >
                </div>

                <div>
                    <label>Service</label>

                    <select name="service_id">

                        <option value="0">
                            All My Services
                        </option>

                        <?php foreach ($services as $service): ?>

                            <option
                                value="<?= (int) $service["id"] ?>"
                                <?= (int) $offer["service_id"] === (int) $service["id"] ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($service["service_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="full">

                    <label>Description</label>

                    <textarea name="description"><?= htmlspecialchars($offer["description"] ?? "") ?></textarea>

                </div>

                <div>

                    <label>Discount Type *</label>

                    <select name="discount_type" required>

                        <option
                            value="percentage"
                            <?= $offer["discount_type"] === "percentage" ? "selected" : "" ?>
                        >
                            Percentage (%)
                        </option>

                        <option
                            value="fixed"
                            <?= $offer["discount_type"] === "fixed" ? "selected" : "" ?>
                        >
                            Fixed Amount (Rs.)
                        </option>

                    </select>

                </div>

                <div>

                    <label>Discount Value *</label>

                    <input
                        type="number"
                        name="discount_value"
                        min="1"
                        step="0.01"
                        value="<?= htmlspecialchars($offer["discount_value"]) ?>"
                        required
                    >

                </div>

                <div>

                    <label>Start Date *</label>

                    <input
                        type="date"
                        name="start_date"
                        value="<?= htmlspecialchars($offer["start_date"]) ?>"
                        required
                    >

                </div>

                <div>

                    <label>End Date *</label>

                    <input
                        type="date"
                        name="end_date"
                        value="<?= htmlspecialchars($offer["end_date"]) ?>"
                        required
                    >

                </div>

                <div class="full">

                    <div class="buttons">

                        <button
                            type="submit"
                            class="btn btn-save"
                        >
                            Save Changes
                        </button>

                        <a
                            href="offers.php"
                            class="btn btn-back"
                        >
                            Back to Offers
                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>

</div>

</body>
</html>