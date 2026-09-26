
<?php

session_start();

require_once "../includes/auth.php";
require_once "../../database.php";

$sql = "
    SELECT *
    FROM users
    WHERE LOWER(TRIM(role)) = 'customer'
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Customer query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customers | Event Planner Admin</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fff8fb;
            color: #333;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #b8860b;
            font-size: 30px;
            font-weight: 700;
        }

        .page-header p {
            color: #777;
            margin-top: 7px;
            font-size: 14px;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(184, 134, 11, 0.10);
            border: 1px solid #f3e4e8;
            overflow-x: auto;
        }

        .customer-count {
            display: inline-block;
            background: linear-gradient(135deg, #fff0f5, #fff8e1);
            color: #a87800;
            padding: 10px 18px;
            border-radius: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            border: 1px solid #f1d98a;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
            overflow: hidden;
            border: 1px solid #f0e2e6;
            border-radius: 12px;
        }

        th {
            background: linear-gradient(135deg, #f5d76e, #f8df91);
            color: #5c4500;
            padding: 15px;
            text-align: left;
            font-size: 14px;
            font-weight: 700;
            border-bottom: 1px solid #e7cc67;
        }

        th:first-child {
            border-top-left-radius: 11px;
        }

        th:last-child {
            border-top-right-radius: 11px;
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid #f1e8eb;
            font-size: 14px;
            color: #444;
            background: #ffffff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: #fff8fb;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #e8f8ed;
            color: #218838;
        }

        .inactive {
            background: #ffeaea;
            color: #dc3545;
        }

        .btn {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-right: 4px;
            transition: 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .view {
            background: #f5d76e;
            color: #5c4500;
        }

        .edit {
            background: #f8c8dc;
            color: #8a3156;
        }

        .delete {
            background: #dc3545;
            color: #ffffff;
        }

        .no-data {
            text-align: center;
            padding: 50px 20px;
            color: #888;
        }

        .no-data h3 {
            color: #b8860b;
            margin-bottom: 8px;
            font-size: 20px;
        }

        .no-data p {
            color: #999;
            font-size: 14px;
        }

        @media (max-width: 900px) {

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .card {
                padding: 18px;
            }

        }

    </style>

</head>

<body>

<?php
require_once "../includes/sidebar.php";
?>

<div class="main">

    <div class="page-header">

        <h1>Customers</h1>

        <p>
            Manage all registered Event Planner customers.
        </p>

    </div>

    <div class="card">

        <div class="customer-count">
            Total Customers:
            <?php echo $result->num_rows; ?>
        </div>

        <?php if ($result->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php while ($customer = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $customer['id']
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $customer['name'] ?? 'N/A'
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $customer['email'] ?? 'N/A'
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $customer['phone'] ?? 'N/A'
                            );
                            ?>
                        </td>

                        <td>

                            <?php

                            $status = $customer['status'] ?? 'active';

                            ?>

                            <span class="status <?php echo ($status === 'active') ? 'active' : 'inactive'; ?>">

                                <?php
                                echo ucfirst(
                                    htmlspecialchars($status)
                                );
                                ?>

                            </span>

                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $customer['created_at'] ?? 'N/A'
                            );
                            ?>
                        </td>

                        <td>

                            <a
                                href="view.php?id=<?php echo $customer['id']; ?>"
                                class="btn view"
                            >
                                View
                            </a>

                            <a
                                href="edit.php?id=<?php echo $customer['id']; ?>"
                                class="btn edit"
                            >
                                Edit
                            </a>

                            <a
                                href="delete.php?id=<?php echo $customer['id']; ?>"
                                class="btn delete"
                                onclick="return confirm('Are you sure you want to delete this customer?');"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="no-data">

                <h3>No Customers Found</h3>

                <p>
                    No customers have registered yet.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

