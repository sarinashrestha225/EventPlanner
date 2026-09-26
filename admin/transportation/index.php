<?php
session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$result = $conn->query("
    SELECT *
    FROM transportation
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transportation</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fffaf3;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #f8c8dc, #f6d365);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #5a3d00;
        }

        .back {
            text-decoration: none;
            background: #fff;
            color: #8a6500;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .container {
            padding: 30px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .top h2 {
            color: #6b4f00;
        }

        .add-btn {
            background: #d4af37;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .table-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8c8dc;
            color: #5a3d00;
            padding: 14px;
            text-align: left;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #fff8fb;
        }

        .image {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 13px;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 13px;
        }

        .edit {
            color: #856404;
            text-decoration: none;
            font-weight: bold;
            margin-right: 10px;
        }

        .delete {
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #777;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>🚗 Transportation Management</h1>

    <a href="../dashboard.php" class="back">
        ← Dashboard
    </a>
</div>

<div class="container">

    <div class="top">
        <h2>Transportation List</h2>

        <a href="add.php" class="add-btn">
            + Add Transportation
        </a>
    </div>

    <div class="table-box">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Vehicle Name</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>
                        <td>
                            <?= $row["id"] ?>
                        </td>

                        <td>
                            <?php if (!empty($row["image"])): ?>
                                <img
                                    src="../../uploads/transportation/<?= htmlspecialchars($row["image"]) ?>"
                                    class="image"
                                >
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["vehicle_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["vehicle_type"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row["capacity"]) ?>
                        </td>

                        <td>
                            Rs. <?= number_format($row["price"], 2) ?>
                        </td>

                        <td>
                            <?php if ($row["status"] === "active"): ?>
                                <span class="status-active">
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="status-inactive">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a
                                href="edit.php?id=<?= $row["id"] ?>"
                                class="edit"
                            >
                                Edit
                            </a>

                            <a
                                href="delete.php?id=<?= $row["id"] ?>"
                                class="delete"
                                onclick="return confirm('Are you sure you want to delete this transportation?')"
                            >
                                Delete
                            </a>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="empty">
                        No transportation added yet.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>