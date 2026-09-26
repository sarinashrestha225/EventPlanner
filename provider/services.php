
<?php
session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];

$stmt = $conn->prepare("
    SELECT
        id,
        service_name,
        service_image,
        category,
        description,
        price,
        min_price,
        max_price,
        unit,
        status,
        availability,
        created_at
    FROM services
    WHERE provider_id = ?
    ORDER BY id DESC
");

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Services - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f0;
    color: #4b3621;
}

.container {
    max-width: 1100px;
    margin: 40px auto;
    padding: 20px;
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

h1 {
    margin: 0;
}

.top p {
    color: #777;
}

.add-btn {
    background: #d4af37;
    color: white;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: bold;
}

.add-btn:hover {
    background: #b8860b;
}

.card {
    background: white;
    border-radius: 15px;
    padding: 22px;
    margin-bottom: 18px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.service-content {
    display: flex;
    gap: 20px;
}

.service-image {
    width: 180px;
    height: 140px;
    object-fit: cover;
    border-radius: 10px;
    background: #eee;
}

.service-info {
    flex: 1;
}

.service-name {
    font-size: 22px;
    font-weight: bold;
    color: #b8860b;
}

.category {
    display: inline-block;
    margin-top: 8px;
    background: #fff1c9;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 13px;
}

.description {
    margin: 12px 0;
    color: #666;
    line-height: 1.6;
}

.price {
    font-size: 20px;
    font-weight: bold;
    margin-top: 10px;
}

.price small {
    font-size: 14px;
    color: #777;
    font-weight: normal;
}

.status {
    display: inline-block;
    margin-top: 10px;
    padding: 6px 12px;
    border-radius: 20px;
    background: #d4edda;
    color: #155724;
}

.status.inactive {
    background: #f8d7da;
    color: #721c24;
}

.availability {
    display: inline-block;
    margin-top: 10px;
    margin-left: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    background: #d4edda;
    color: #155724;
    font-weight: bold;
}

.availability.unavailable {
    background: #f8d7da;
    color: #721c24;
}

.actions {
    margin-top: 18px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.availability-btn {
    color: white;
    padding: 9px 15px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
}

.make-unavailable {
    background: #e67e22;
}

.make-unavailable:hover {
    background: #ca6f1e;
}

.make-available {
    background: #28a745;
}

.make-available:hover {
    background: #218838;
}

.edit {
    background: #3498db;
    color: white;
    padding: 9px 15px;
    border-radius: 7px;
    text-decoration: none;
}

.edit:hover {
    background: #217dbb;
}

.delete {
    background: #dc3545;
    color: white;
    padding: 9px 15px;
    border-radius: 7px;
    text-decoration: none;
}

.delete:hover {
    background: #bb2d3b;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #777;
}

.empty h2 {
    color: #4b3621;
}

.back {
    display: inline-block;
    margin-top: 15px;
    color: #6b4f2a;
    text-decoration: none;
    font-weight: bold;
}

.back:hover {
    color: #b8860b;
}

@media (max-width: 700px) {

    .top {
        flex-direction: column;
        align-items: flex-start;
    }

    .service-content {
        flex-direction: column;
    }

    .service-image {
        width: 100%;
        height: 200px;
    }

    .actions {
        flex-direction: column;
    }

    .availability,
    .status {
        margin-left: 0;
    }

    .availability-btn,
    .edit,
    .delete {
        text-align: center;
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="top">

        <div>
            <h1>🛠️ My Services</h1>
            <p>Manage the services you provide.</p>
        </div>

        <a href="add_service.php" class="add-btn">
            ➕ Add Service
        </a>

    </div>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($service = $result->fetch_assoc()): ?>

            <div class="card">

                <div class="service-content">

                    <?php if (!empty($service["service_image"])): ?>

                        <img
                            src="uploads/services/<?= htmlspecialchars(
                                $service["service_image"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            class="service-image"
                            alt="Service Image"
                        >

                    <?php else: ?>

                        <div
                            class="service-image"
                            style="display:flex;align-items:center;justify-content:center;"
                        >
                            🛠️ No Image
                        </div>

                    <?php endif; ?>

                    <div class="service-info">

                        <div class="service-name">
                            <?= htmlspecialchars(
                                $service["service_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </div>

                        <?php if (!empty($service["category"])): ?>

                            <span class="category">
                                <?= htmlspecialchars(
                                    $service["category"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </span>

                        <?php endif; ?>

                        <div class="description">

                            <?= nl2br(
                                htmlspecialchars(
                                    $service["description"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            ) ?>

                        </div>

                        <div class="price">

                            Rs.
                            <?= number_format(
                                (float) $service["price"],
                                2
                            ) ?>

                            <?php if (!empty($service["unit"])): ?>

                                <small>
                                    / <?= htmlspecialchars(
                                        $service["unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </small>

                            <?php endif; ?>

                        </div>

                        <div class="status <?= $service["status"] === "inactive" ? "inactive" : "" ?>">

                            <?= ucfirst(
                                htmlspecialchars(
                                    $service["status"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            ) ?>

                        </div>

                        <div class="availability <?= $service["availability"] === "unavailable" ? "unavailable" : "" ?>">

                            <?= $service["availability"] === "available"
                                ? "🟢 Available"
                                : "🔴 Unavailable"
                            ?>

                        </div>

                        <div class="actions">

                            <a
                                href="toggle_service.php?id=<?= (int) $service["id"] ?>"
                                class="availability-btn <?= $service["availability"] === "available" ? "make-unavailable" : "make-available" ?>"
                                onclick="return confirm('Change service availability?');"
                            >
                                <?= $service["availability"] === "available"
                                    ? "Make Unavailable"
                                    : "Make Available"
                                ?>
                            </a>

                            <a
                                href="edit_service.php?id=<?= (int) $service["id"] ?>"
                                class="edit"
                            >
                                ✏️ Edit
                            </a>

                            <a
                                href="delete_service.php?id=<?= (int) $service["id"] ?>"
                                class="delete"
                                onclick="return confirm('Are you sure you want to delete this service?');"
                            >
                                🗑️ Delete
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="card empty">

            <h2>📭 No Services Yet</h2>

            <p>
                You have not added any service yet.
            </p>

            <a href="add_service.php" class="add-btn">
                ➕ Add Your First Service
            </a>

        </div>

    <?php endif; ?>

    <a href="dashboard.php" class="back">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>

