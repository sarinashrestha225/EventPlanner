<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];
$package_id = (int) ($_GET["id"] ?? 0);

if ($package_id <= 0) {
    header("Location: packages.php");
    exit;
}

$message = "";
$error = "";

$stmt = $conn->prepare("
    SELECT
        id,
        package_name,
        event_id,
        status
    FROM packages
    WHERE id = ? AND provider_id = ?
");

if (!$stmt) {
    die("Package query error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $package_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    header("Location: packages.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "add";

    if ($action === "add") {

        $min_guests = (int) ($_POST["min_guests"] ?? 0);
        $max_guests = (int) ($_POST["max_guests"] ?? 0);
        $original_price = (float) ($_POST["original_price"] ?? 0);
        $combo_price = (float) ($_POST["combo_price"] ?? 0);

        if ($min_guests <= 0) {

            $error = "Minimum guests must be greater than 0.";

        } elseif ($max_guests <= 0) {

            $error = "Maximum guests must be greater than 0.";

        } elseif ($min_guests > $max_guests) {

            $error = "Minimum guests cannot be greater than maximum guests.";

        } elseif ($original_price < 0 || $combo_price < 0) {

            $error = "Price cannot be negative.";

        } elseif ($combo_price > $original_price) {

            $error = "Combo price should not be greater than original price.";

        } else {

            $stmt = $conn->prepare("
                SELECT id
                FROM package_pricing
                WHERE package_id = ?
                AND min_guests <= ?
                AND max_guests >= ?
            ");

            if (!$stmt) {

                $error = "Pricing check error: " . $conn->error;

            } else {

                $stmt->bind_param(
                    "iii",
                    $package_id,
                    $max_guests,
                    $min_guests
                );

                $stmt->execute();

                $result = $stmt->get_result();
                $overlap = $result->fetch_assoc();

                $stmt->close();

                if ($overlap) {

                    $error = "This guest range overlaps with an existing pricing range.";

                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO package_pricing
                        (
                            package_id,
                            min_guests,
                            max_guests,
                            original_price,
                            combo_price
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {

                        $error = "Pricing insert query error: " . $conn->error;

                    } else {

                        $stmt->bind_param(
                            "iiidd",
                            $package_id,
                            $min_guests,
                            $max_guests,
                            $original_price,
                            $combo_price
                        );

                        if ($stmt->execute()) {

                            $message = "Package pricing added successfully.";

                        } else {

                            $error = "Unable to add pricing: " . $stmt->error;
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }

    if ($action === "edit") {

        $pricing_id = (int) ($_POST["pricing_id"] ?? 0);
        $min_guests = (int) ($_POST["min_guests"] ?? 0);
        $max_guests = (int) ($_POST["max_guests"] ?? 0);
        $original_price = (float) ($_POST["original_price"] ?? 0);
        $combo_price = (float) ($_POST["combo_price"] ?? 0);

        if ($pricing_id <= 0) {

            $error = "Invalid pricing ID.";

        } elseif ($min_guests <= 0 || $max_guests <= 0) {

            $error = "Guest numbers must be greater than 0.";

        } elseif ($min_guests > $max_guests) {

            $error = "Minimum guests cannot be greater than maximum guests.";

        } elseif ($original_price < 0 || $combo_price < 0) {

            $error = "Price cannot be negative.";

        } elseif ($combo_price > $original_price) {

            $error = "Combo price should not be greater than original price.";

        } else {

            $stmt = $conn->prepare("
                SELECT pp.id
                FROM package_pricing pp
                INNER JOIN packages p
                    ON pp.package_id = p.id
                WHERE pp.id = ?
                AND pp.package_id = ?
                AND p.provider_id = ?
            ");

            if (!$stmt) {

                $error = "Pricing ownership query error: " . $conn->error;

            } else {

                $stmt->bind_param(
                    "iii",
                    $pricing_id,
                    $package_id,
                    $provider_id
                );

                $stmt->execute();

                $result = $stmt->get_result();
                $pricing_exists = $result->fetch_assoc();

                $stmt->close();

                if (!$pricing_exists) {

                    $error = "Pricing record not found.";

                } else {

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM package_pricing
                        WHERE package_id = ?
                        AND id != ?
                        AND min_guests <= ?
                        AND max_guests >= ?
                    ");

                    if (!$stmt) {

                        $error = "Pricing overlap query error: " . $conn->error;

                    } else {

                        $stmt->bind_param(
                            "iiii",
                            $package_id,
                            $pricing_id,
                            $max_guests,
                            $min_guests
                        );

                        $stmt->execute();

                        $result = $stmt->get_result();
                        $overlap = $result->fetch_assoc();

                        $stmt->close();

                        if ($overlap) {

                            $error = "This guest range overlaps with another pricing range.";

                        } else {

                            $stmt = $conn->prepare("
                                UPDATE package_pricing
                                SET
                                    min_guests = ?,
                                    max_guests = ?,
                                    original_price = ?,
                                    combo_price = ?
                                WHERE id = ?
                                AND package_id = ?
                            ");

                            if (!$stmt) {

                                $error = "Pricing update query error: " . $conn->error;

                            } else {

                                $stmt->bind_param(
                                    "iiddii",
                                    $min_guests,
                                    $max_guests,
                                    $original_price,
                                    $combo_price,
                                    $pricing_id,
                                    $package_id
                                );

                                if ($stmt->execute()) {

                                    $message = "Package pricing updated successfully.";

                                } else {

                                    $error = "Unable to update pricing: " . $stmt->error;
                                }

                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    }

    if ($action === "delete") {

        $pricing_id = (int) ($_POST["pricing_id"] ?? 0);

        if ($pricing_id <= 0) {

            $error = "Invalid pricing ID.";

        } else {

            $stmt = $conn->prepare("
                DELETE pp
                FROM package_pricing pp
                INNER JOIN packages p
                    ON pp.package_id = p.id
                WHERE pp.id = ?
                AND pp.package_id = ?
                AND p.provider_id = ?
            ");

            if (!$stmt) {

                $error = "Pricing delete query error: " . $conn->error;

            } else {

                $stmt->bind_param(
                    "iii",
                    $pricing_id,
                    $package_id,
                    $provider_id
                );

                if ($stmt->execute()) {

                    if ($stmt->affected_rows > 0) {

                        $message = "Package pricing deleted successfully.";

                    } else {

                        $error = "Pricing record not found.";
                    }

                } else {

                    $error = "Unable to delete pricing: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}

$pricing_list = [];

$stmt = $conn->prepare("
    SELECT
        id,
        min_guests,
        max_guests,
        original_price,
        combo_price,
        created_at
    FROM package_pricing
    WHERE package_id = ?
    ORDER BY min_guests ASC
");

if (!$stmt) {
    die("Pricing list query error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $package_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $pricing_list[] = $row;
}

$stmt->close();

$current_page = basename($_SERVER["PHP_SELF"]);

include __DIR__ . "/includes/header.php";
include __DIR__ . "/includes/sidebar.php";

?>

<div class="provider-content">

    <div class="page-header">

        <div>

            <h1>Package Pricing</h1>

            <p>
                Manage guest-based pricing for
                <strong>
                    <?= htmlspecialchars($package["package_name"]) ?>
                </strong>
            </p>

        </div>

        <a href="packages.php" class="back-button">
            Back to Packages
        </a>

    </div>

    <?php if ($message !== ""): ?>

        <div class="alert success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="pricing-form-card">

        <h2>Add Pricing Range</h2>

        <p class="form-description">
            Set different prices according to the number of guests.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="add"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label>Minimum Guests</label>

                    <input
                        type="number"
                        name="min_guests"
                        min="1"
                        placeholder="Example: 50"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Maximum Guests</label>

                    <input
                        type="number"
                        name="max_guests"
                        min="1"
                        placeholder="Example: 100"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Original Price</label>

                    <input
                        type="number"
                        name="original_price"
                        min="0"
                        step="0.01"
                        placeholder="Example: 500000"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Combo Price</label>

                    <input
                        type="number"
                        name="combo_price"
                        min="0"
                        step="0.01"
                        placeholder="Example: 400000"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                class="btn-primary"
            >
                Add Pricing
            </button>

        </form>

    </div>

    <div class="pricing-section">

        <div class="section-title">

            <div>

                <h2>Pricing Ranges</h2>

                <p>
                    Guest-wise pricing for this package
                </p>

            </div>

            <span>
                <?= count($pricing_list) ?> Ranges
            </span>

        </div>

        <?php if (empty($pricing_list)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    ₹
                </div>

                <h3>
                    No pricing added yet
                </h3>

                <p>
                    Add a guest range and price using the form above.
                </p>

            </div>

        <?php else: ?>

            <div class="pricing-grid">

                <?php foreach ($pricing_list as $pricing): ?>

                    <div class="pricing-card">

                        <div class="guest-range">

                            <span>
                                Guest Range
                            </span>

                            <strong>
                                <?= (int) $pricing["min_guests"] ?>
                                -
                                <?= (int) $pricing["max_guests"] ?>
                                Guests
                            </strong>

                        </div>

                        <div class="price-row">

                            <div>

                                <span>
                                    Original Price
                                </span>

                                <strong class="original-price">

                                    Rs.
                                    <?= number_format(
                                        (float) $pricing["original_price"],
                                        2
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                        <div class="price-row combo-row">

                            <div>

                                <span>
                                    Combo Price
                                </span>

                                <strong class="combo-price">

                                    Rs.
                                    <?= number_format(
                                        (float) $pricing["combo_price"],
                                        2
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                        <?php
                        $original = (float) $pricing["original_price"];
                        $combo = (float) $pricing["combo_price"];

                        $discount = 0;

                        if ($original > 0 && $combo < $original) {
                            $discount = (($original - $combo) / $original) * 100;
                        }
                        ?>

                        <?php if ($discount > 0): ?>

                            <div class="discount-badge">

                                <?= number_format($discount, 0) ?>% OFF

                            </div>

                        <?php endif; ?>

                        <div class="pricing-actions">

                            <button
                                type="button"
                                class="btn-edit"
                                onclick="openEditModal(
                                    <?= (int) $pricing["id"] ?>,
                                    <?= (int) $pricing["min_guests"] ?>,
                                    <?= (int) $pricing["max_guests"] ?>,
                                    <?= htmlspecialchars(json_encode((float) $pricing["original_price"])) ?>,
                                    <?= htmlspecialchars(json_encode((float) $pricing["combo_price"])) ?>
                                )"
                            >
                                Edit
                            </button>

                            <form
                                method="POST"
                                onsubmit="return confirm('Delete this pricing range?')"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="pricing_id"
                                    value="<?= (int) $pricing["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn-delete"
                                >
                                    Delete
                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<div
    class="modal-overlay"
    id="editModal"
>

    <div class="edit-modal">

        <div class="modal-header">

            <h2>Edit Pricing</h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeEditModal()"
            >
                ×
            </button>

        </div>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="pricing_id"
                id="edit_pricing_id"
            >

            <div class="form-group">

                <label>Minimum Guests</label>

                <input
                    type="number"
                    name="min_guests"
                    id="edit_min_guests"
                    min="1"
                    required
                >

            </div>

            <div class="form-group">

                <label>Maximum Guests</label>

                <input
                    type="number"
                    name="max_guests"
                    id="edit_max_guests"
                    min="1"
                    required
                >

            </div>

            <div class="form-group">

                <label>Original Price</label>

                <input
                    type="number"
                    name="original_price"
                    id="edit_original_price"
                    min="0"
                    step="0.01"
                    required
                >

            </div>

            <div class="form-group">

                <label>Combo Price</label>

                <input
                    type="number"
                    name="combo_price"
                    id="edit_combo_price"
                    min="0"
                    step="0.01"
                    required
                >

            </div>

            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeEditModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-primary"
                >
                    Update Pricing
                </button>

            </div>

        </form>

    </div>

</div>

<script>

function openEditModal(
    id,
    minGuests,
    maxGuests,
    originalPrice,
    comboPrice
) {

    document.getElementById("edit_pricing_id").value = id;
    document.getElementById("edit_min_guests").value = minGuests;
    document.getElementById("edit_max_guests").value = maxGuests;
    document.getElementById("edit_original_price").value = originalPrice;
    document.getElementById("edit_combo_price").value = comboPrice;

    document.getElementById("editModal").classList.add("show");
}

function closeEditModal() {

    document.getElementById("editModal").classList.remove("show");
}

document.getElementById("editModal").addEventListener(
    "click",
    function(event) {

        if (event.target === this) {
            closeEditModal();
        }

    }
);

</script>

<style>

.provider-content {
    padding: 30px;
    background: #fffaf0;
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
    color: #6b4b16;
}

.page-header p {
    margin-top: 7px;
    color: #777;
}

.back-button {
    text-decoration: none;
    background: #f3d6df;
    color: #6b3f4b;
    padding: 11px 18px;
    border-radius: 9px;
    font-weight: 700;
}

.back-button:hover {
    background: #edc0ce;
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
}

.alert.success {
    background: #fff1b8;
    color: #725200;
    border: 1px solid #e7c75f;
}

.alert.error {
    background: #ffe3e3;
    color: #9d1c1c;
    border: 1px solid #e5aaaa;
}

.pricing-form-card {
    background: #fff;
    border: 1px solid #ead9a5;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(176, 137, 50, 0.08);
}

.pricing-form-card h2 {
    color: #6b4b16;
    margin-top: 0;
    margin-bottom: 5px;
}

.form-description {
    color: #777;
    margin-top: 0;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-bottom: 17px;
}

.form-group label {
    font-weight: 600;
    color: #5f4a28;
}

.form-group input {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #dbc98e;
    border-radius: 9px;
    background: #fffdf7;
    font-size: 14px;
}

.form-group input:focus {
    outline: none;
    border-color: #d4af37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.12);
}

.btn-primary {
    border: none;
    background: #d4af37;
    color: #fff;
    padding: 12px 22px;
    border-radius: 9px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary:hover {
    background: #b99420;
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
}

.section-title h2 {
    color: #6b4b16;
    margin: 0;
}

.section-title p {
    color: #777;
    margin: 5px 0 0;
}

.section-title span {
    background: #fff1b8;
    color: #725200;
    padding: 7px 12px;
    border-radius: 20px;
    font-weight: 600;
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.pricing-card {
    background: #fff;
    border: 1px solid #ead9a5;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(176, 137, 50, 0.08);
}

.guest-range {
    background: #fffaf0;
    padding: 14px;
    border-radius: 11px;
    margin-bottom: 15px;
}

.guest-range span {
    display: block;
    color: #777;
    font-size: 13px;
    margin-bottom: 5px;
}

.guest-range strong {
    color: #6b4b16;
    font-size: 18px;
}

.price-row {
    padding: 12px 0;
    border-bottom: 1px solid #f0e7ca;
}

.price-row div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.price-row span {
    color: #777;
    font-size: 14px;
}

.original-price {
    color: #777;
    text-decoration: line-through;
}

.combo-row {
    border-bottom: none;
}

.combo-price {
    color: #a97900;
    font-size: 19px;
}

.discount-badge {
    display: inline-block;
    background: #e1f5e4;
    color: #237b35;
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 800;
    margin-top: 5px;
}

.pricing-actions {
    display: flex;
    gap: 9px;
    margin-top: 18px;
}

.pricing-actions form {
    flex: 1;
}

.pricing-actions button {
    width: 100%;
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
}

.pricing-actions .btn-edit {
    background: #fff1b8;
    color: #725200;
}

.pricing-actions .btn-edit:hover {
    background: #f5df82;
}

.pricing-actions .btn-delete {
    background: #ffe0e0;
    color: #a32626;
}

.pricing-actions .btn-delete:hover {
    background: #ffcaca;
}

.empty-state {
    background: #fff;
    border: 1px solid #ead9a5;
    border-radius: 16px;
    padding: 45px;
    text-align: center;
}

.empty-icon {
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    background: #fff1b8;
    color: #a97900;
    border-radius: 50%;
    font-size: 25px;
    font-weight: 800;
}

.empty-state h3 {
    color: #6b4b16;
}

.empty-state p {
    color: #777;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 9999;
}

.modal-overlay.show {
    display: flex;
}

.edit-modal {
    width: 100%;
    max-width: 500px;
    background: #fff;
    border-radius: 16px;
    padding: 25px;
    box-sizing: border-box;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h2 {
    color: #6b4b16;
    margin: 0;
}

.modal-close {
    border: none;
    background: #ffe0e0;
    color: #a32626;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    font-size: 22px;
    cursor: pointer;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 10px;
}

.btn-cancel {
    border: none;
    background: #eee5cf;
    color: #5f4a28;
    padding: 12px 20px;
    border-radius: 9px;
    font-weight: 700;
    cursor: pointer;
}

.btn-cancel:hover {
    background: #e2d5b7;
}

@media (max-width: 1000px) {

    .pricing-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .provider-content {
        padding: 18px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .pricing-grid {
        grid-template-columns: 1fr;
    }

    .section-title {
        align-items: flex-start;
        flex-direction: column;
    }

    .pricing-actions {
        flex-direction: column;
    }

}

</style>