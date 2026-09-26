<?php

session_start();
require_once "../database.php";

$is_admin = false;

if (
    isset($_SESSION["admin_logged_in"]) &&
    $_SESSION["admin_logged_in"] === true
) {
    $is_admin = true;
}

if (
    isset($_SESSION["user_role"]) &&
    $_SESSION["user_role"] === "admin"
) {
    $is_admin = true;
}

if (!$is_admin) {
    header("Location: login.php");
    exit();
}

$admin_username = $_SESSION["admin_username"]
    ?? $_SESSION["admin_name"]
    ?? "Admin";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "add") {

        $provider_id = (int)($_POST["provider_id"] ?? 0);
        $service_id = !empty($_POST["service_id"]) ? (int)$_POST["service_id"] : null;
        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $discount_type = $_POST["discount_type"] ?? "percentage";
        $discount_value = (float)($_POST["discount_value"] ?? 0);
        $start_date = !empty($_POST["start_date"]) ? $_POST["start_date"] : null;
        $end_date = !empty($_POST["end_date"]) ? $_POST["end_date"] : null;
        $status = $_POST["status"] ?? "active";

        if ($provider_id <= 0 || $title === "" || $discount_value <= 0) {
            $error = "Please fill all required fields.";
        } elseif (!in_array($discount_type, ["percentage", "fixed"], true)) {
            $error = "Invalid discount type.";
        } elseif (!in_array($status, ["active", "inactive", "expired"], true)) {
            $error = "Invalid status.";
        } elseif ($discount_type === "percentage" && $discount_value > 100) {
            $error = "Percentage discount cannot exceed 100%.";
        } elseif (
            $start_date !== null &&
            $end_date !== null &&
            $end_date < $start_date
        ) {
            $error = "End date cannot be before start date.";
        } else {

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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "iisssdsss",
                    $provider_id,
                    $service_id,
                    $title,
                    $description,
                    $discount_type,
                    $discount_value,
                    $start_date,
                    $end_date,
                    $status
                );

                if ($stmt->execute()) {
                    $message = "Offer added successfully.";
                } else {
                    $error = "Failed to add offer: " . $stmt->error;
                }

                $stmt->close();

            } else {
                $error = "Failed to prepare offer query.";
            }
        }
    }

    if ($action === "delete") {

        $offer_id = (int)($_POST["offer_id"] ?? 0);

        if ($offer_id > 0) {

            $stmt = $conn->prepare("
                DELETE FROM offers
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param("i", $offer_id);

                if ($stmt->execute()) {
                    $message = "Offer deleted successfully.";
                } else {
                    $error = "Failed to delete offer.";
                }

                $stmt->close();

            } else {
                $error = "Failed to prepare delete query.";
            }
        }
    }

    if ($action === "status") {

        $offer_id = (int)($_POST["offer_id"] ?? 0);
        $status = $_POST["status"] ?? "";

        if (
            $offer_id > 0 &&
            in_array($status, ["active", "inactive", "expired"], true)
        ) {

            $stmt = $conn->prepare("
                UPDATE offers
                SET status = ?
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param("si", $status, $offer_id);

                if ($stmt->execute()) {
                    $message = "Offer status updated.";
                } else {
                    $error = "Failed to update status.";
                }

                $stmt->close();

            } else {
                $error = "Failed to prepare status query.";
            }
        }
    }
}

$providers = [];

$provider_result = $conn->query("
    SELECT id, name
    FROM providers
    ORDER BY name ASC
");

if ($provider_result) {
    while ($row = $provider_result->fetch_assoc()) {
        $providers[] = $row;
    }
}

$services = [];

$service_result = $conn->query("
    SELECT id, service_name
    FROM services
    WHERE status = 'active'
    ORDER BY service_name ASC
");

if ($service_result) {
    while ($row = $service_result->fetch_assoc()) {
        $services[] = $row;
    }
}

$offers = [];

$offer_result = $conn->query("
    SELECT
        o.id,
        o.provider_id,
        o.service_id,
        o.title,
        o.description,
        o.discount_type,
        o.discount_value,
        o.start_date,
        o.end_date,
        o.status,
        o.created_at,
        p.name AS provider_name,
        s.service_name
    FROM offers o
    LEFT JOIN providers p
        ON o.provider_id = p.id
    LEFT JOIN services s
        ON o.service_id = s.id
    ORDER BY o.id DESC
");

if ($offer_result) {
    while ($row = $offer_result->fetch_assoc()) {
        $offers[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Offers - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8fb;
    color: #4b3040;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: #fff0f6;
    padding: 25px 15px;
    overflow-y: auto;
    border-right: 1px solid #f2d5e2;
}

.logo {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    color: #b8860b;
    margin-bottom: 25px;
}

.sidebar a {
    display: block;
    text-decoration: none;
    color: #5a3b4b;
    padding: 12px 14px;
    margin-bottom: 5px;
    border-radius: 10px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #f8d7e5;
}

.main {
    margin-left: 240px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.admin-info {
    font-weight: bold;
}

.logout {
    color: #a33;
    text-decoration: none;
    margin-left: 15px;
}

h1 {
    color: #8b5e3c;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.full {
    grid-column: 1 / -1;
}

label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}

input,
select,
textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #e1c5d2;
    border-radius: 8px;
}

textarea {
    min-height: 90px;
    resize: vertical;
}

button {
    border: none;
    border-radius: 8px;
    padding: 10px 16px;
    cursor: pointer;
}

.add-btn {
    background: #d4af37;
    color: white;
    font-weight: bold;
}

.delete-btn {
    background: #dc3545;
    color: white;
}

.status-btn {
    background: #f1d9a5;
    color: #6b4d00;
}

.message {
    background: #dff5e5;
    color: #216b36;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #ffe1e1;
    color: #a22;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: top;
}

th {
    background: #fff0f6;
}

.badge {
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.active {
    background: #dff5e5;
    color: #21743a;
}

.inactive {
    background: #eee;
    color: #555;
}

.expired {
    background: #ffe1e1;
    color: #a22;
}

.actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

@media (max-width: 900px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        border-right: none;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

</style>

</head>

<body>

<div class="sidebar">

    <div class="logo">
        ✦ Event Planner
    </div>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="customers/index.php">👥 Customers</a>
    <a href="providers/index.php">👨‍💼 Providers</a>
    <a href="events/index.php">🎉 Events</a>
    <a href="services/index.php">🛎️ Services</a>
    <a href="transportation/index.php">🚗 Transportation</a>
    <a href="locations/index.php">📍 Locations</a>
    <a href="venues/index.php">🏨 Venues</a>
    <a href="bookings/index.php">📅 Bookings</a>
    <a href="payments/index.php">💳 Payments</a>
    <a href="packages/index.php">📦 Packages</a>
    <a href="offers.php" class="active">🎁 Offers</a>
    <a href="coupons/index.php">🎟️ Coupons</a>
    <a href="reviews/index.php">⭐ Reviews</a>
    <a href="comments/index.php">💬 Comments</a>
    <a href="commissions/index.php">💰 Commissions</a>
    <a href="notifications/index.php">🔔 Notifications</a>
    <a href="reports/index.php">📊 Reports</a>
    <a href="logout.php">🚪 Logout</a>

</div>

<div class="main">

    <div class="topbar">

        <div>
            <h1>🎁 Offers</h1>
        </div>

        <div class="admin-info">

            👑 <?= htmlspecialchars($admin_username) ?>

            <a href="logout.php" class="logout">
                Logout
            </a>

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

        <h2>➕ Add New Offer</h2>

        <form method="POST">

            <input type="hidden" name="action" value="add">

            <div class="form-grid">

                <div>

                    <label>Provider *</label>

                    <select name="provider_id" required>

                        <option value="">
                            Select Provider
                        </option>

                        <?php foreach ($providers as $provider): ?>

                            <option value="<?= (int)$provider['id'] ?>">
                                <?= htmlspecialchars($provider['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div>

                    <label>Service</label>

                    <select name="service_id">

                        <option value="">
                            All Services
                        </option>

                        <?php foreach ($services as $service): ?>

                            <option value="<?= (int)$service['id'] ?>">
                                <?= htmlspecialchars($service['service_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="full">

                    <label>Offer Title *</label>

                    <input
                        type="text"
                        name="title"
                        placeholder="Example: Wedding Special Discount"
                        required
                    >

                </div>

                <div class="full">

                    <label>Description</label>

                    <textarea
                        name="description"
                        placeholder="Describe this offer..."
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
                        min="0.01"
                        step="0.01"
                        required
                    >

                </div>

                <div>

                    <label>Start Date</label>

                    <input
                        type="date"
                        name="start_date"
                    >

                </div>

                <div>

                    <label>End Date</label>

                    <input
                        type="date"
                        name="end_date"
                    >

                </div>

                <div>

                    <label>Status</label>

                    <select name="status">

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                        <option value="expired">
                            Expired
                        </option>

                    </select>

                </div>

                <div style="display:flex;align-items:end;">

                    <button
                        type="submit"
                        class="add-btn"
                    >
                        🎁 Add Offer
                    </button>

                </div>

            </div>

        </form>

    </div>

    <div class="card">

        <h2>📋 All Offers</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Offer</th>
                        <th>Provider</th>
                        <th>Service</th>
                        <th>Discount</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($offers) > 0): ?>

                    <?php foreach ($offers as $offer): ?>

                        <tr>

                            <td>
                                <?= (int)$offer['id'] ?>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($offer['title']) ?>
                                </strong>

                                <?php if (!empty($offer['description'])): ?>

                                    <br>

                                    <small>
                                        <?= nl2br(htmlspecialchars($offer['description'])) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $offer['provider_name'] ?? 'Unknown'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $offer['service_name'] ?? 'All Services'
                                ) ?>
                            </td>

                            <td>

                                <?php if (
                                    $offer['discount_type'] === 'percentage'
                                ): ?>

                                    <?= number_format(
                                        (float)$offer['discount_value'],
                                        2
                                    ) ?>%

                                <?php else: ?>

                                    Rs.
                                    <?= number_format(
                                        (float)$offer['discount_value'],
                                        2
                                    ) ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= !empty($offer['start_date'])
                                    ? htmlspecialchars($offer['start_date'])
                                    : 'Any date'
                                ?>

                                <br>

                                to

                                <br>

                                <?= !empty($offer['end_date'])
                                    ? htmlspecialchars($offer['end_date'])
                                    : 'Any date'
                                ?>

                            </td>

                            <td>

                                <span class="badge <?= htmlspecialchars(
                                    $offer['status']
                                ) ?>">

                                    <?= ucfirst(
                                        htmlspecialchars($offer['status'])
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <div class="actions">

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="status"
                                        >

                                        <input
                                            type="hidden"
                                            name="offer_id"
                                            value="<?= (int)$offer['id'] ?>"
                                        >

                                        <?php if (
                                            $offer['status'] === 'active'
                                        ): ?>

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="inactive"
                                            >

                                            <button
                                                type="submit"
                                                class="status-btn"
                                            >
                                                Deactivate
                                            </button>

                                        <?php else: ?>

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="active"
                                            >

                                            <button
                                                type="submit"
                                                class="status-btn"
                                            >
                                                Activate
                                            </button>

                                        <?php endif; ?>

                                    </form>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Delete this offer?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="offer_id"
                                            value="<?= (int)$offer['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-btn"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            style="text-align:center;"
                        >
                            No offers found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>