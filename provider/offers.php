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
                INSERT INTO offers
                (
                    provider_id,
                    service_id,
                    title,
                    description,
                    discount_type,
                    discount_value,
                    start_date,
                    end_date,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->bind_param(
                "iisssdss",
                $provider_id,
                $service_id,
                $title,
                $description,
                $discount_type,
                $discount_value,
                $start_date,
                $end_date
            );

            if ($stmt->execute()) {
                $message = "Offer added successfully.";
            } else {
                $error = "Unable to add offer.";
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

$offers = [];

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.title,
        o.description,
        o.discount_type,
        o.discount_value,
        o.start_date,
        o.end_date,
        o.status,
        o.created_at,
        s.service_name
    FROM offers o
    LEFT JOIN services s
        ON o.service_id = s.id
    WHERE o.provider_id = ?
    ORDER BY o.id DESC
");

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$offer_result = $stmt->get_result();

while ($row = $offer_result->fetch_assoc()) {
    $offers[] = $row;
}

$stmt->close();

$current_page = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Offers | Provider</title>

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
            margin: 6px 0 0;
            color: #777;
        }

        .card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(180, 140, 50, 0.12);
            border: 1px solid #f1dfb0;
            margin-bottom: 25px;
        }

        .card h2 {
            margin-top: 0;
            color: #9b6b00;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
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
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            border: none;
            padding: 11px 17px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-add {
            background: #d4af37;
            color: #fff;
        }

        .btn-edit {
            background: #f6d889;
            color: #5c4700;
        }

        .btn-delete {
            background: #f4b5b5;
            color: #7a1717;
        }

        .btn-toggle {
            background: #f5e4a8;
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

        .offers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .offer {
            border: 1px solid #ecd89e;
            border-radius: 14px;
            padding: 20px;
            background: #fffdf7;
        }

        .offer h3 {
            margin-top: 0;
            color: #8d6500;
        }

        .offer p {
            line-height: 1.5;
        }

        .discount {
            font-size: 24px;
            font-weight: bold;
            color: #c79500;
            margin: 12px 0;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .active {
            background: #dff3df;
            color: #267126;
        }

        .inactive {
            background: #eeeeee;
            color: #666;
        }

        .offer-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #777;
        }

        @media (max-width: 1000px) {
            .offers-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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

            .offers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="page">

    <div class="header">
        <div>
            <h1>Offers</h1>
            <p>Create special offers and discounts for your customers.</p>
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

        <h2>Create New Offer</h2>

        <form method="POST">

            <div class="form-grid">

                <div>
                    <label>Offer Title *</label>

                    <input
                        type="text"
                        name="title"
                        placeholder="Example: Wedding Makeup Special Offer"
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

                            <option value="<?= (int) $service["id"] ?>">
                                <?= htmlspecialchars($service["service_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="full">

                    <label>Description</label>

                    <textarea
                        name="description"
                        placeholder="Describe your offer..."
                    ></textarea>

                </div>

                <div>

                    <label>Discount Type *</label>

                    <select name="discount_type" required>

                        <option value="percentage">
                            Percentage (%)
                        </option>

                        <option value="fixed">
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
                        placeholder="Example: 20"
                        required
                    >

                </div>

                <div>

                    <label>Start Date *</label>

                    <input
                        type="date"
                        name="start_date"
                        required
                    >

                </div>

                <div>

                    <label>End Date *</label>

                    <input
                        type="date"
                        name="end_date"
                        required
                    >

                </div>

                <div class="full">

                    <button
                        type="submit"
                        class="btn btn-add"
                    >
                        + Create Offer
                    </button>

                </div>

            </div>

        </form>

    </div>

    <div class="card">

        <h2>My Offers</h2>

        <?php if (count($offers) === 0): ?>

            <div class="empty">
                No offers created yet.
            </div>

        <?php else: ?>

            <div class="offers-grid">

                <?php foreach ($offers as $offer): ?>

                    <div class="offer">

                        <span class="status <?= $offer["status"] === "active" ? "active" : "inactive" ?>">
                            <?= ucfirst($offer["status"]) ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($offer["title"]) ?>
                        </h3>

                        <?php if (!empty($offer["service_name"])): ?>

                            <p>
                                <strong>Service:</strong>
                                <?= htmlspecialchars($offer["service_name"]) ?>
                            </p>

                        <?php else: ?>

                            <p>
                                <strong>Service:</strong>
                                All My Services
                            </p>

                        <?php endif; ?>

                        <div class="discount">

                            <?php if ($offer["discount_type"] === "percentage"): ?>

                                <?= number_format((float) $offer["discount_value"], 0) ?>% OFF

                            <?php else: ?>

                                Rs. <?= number_format((float) $offer["discount_value"], 2) ?> OFF

                            <?php endif; ?>

                        </div>

                        <?php if (!empty($offer["description"])): ?>

                            <p>
                                <?= nl2br(htmlspecialchars($offer["description"])) ?>
                            </p>

                        <?php endif; ?>

                        <p>
                            <strong>Valid:</strong><br>

                            <?= date("d M Y", strtotime($offer["start_date"])) ?>

                            -

                            <?= date("d M Y", strtotime($offer["end_date"])) ?>
                        </p>

                        <div class="offer-actions">

                            <a
                                href="edit_offer.php?id=<?= (int) $offer["id"] ?>"
                                class="btn btn-edit"
                            >
                                Edit
                            </a>

                            <a
                                href="?toggle=<?= (int) $offer["id"] ?>"
                                class="btn btn-toggle"
                            >
                                <?= $offer["status"] === "active" ? "Deactivate" : "Activate" ?>
                            </a>

                            <a
                                href="delete_offer.php?id=<?= (int) $offer["id"] ?>"
                                class="btn btn-delete"
                                onclick="return confirm('Delete this offer?')"
                            >
                                Delete
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>