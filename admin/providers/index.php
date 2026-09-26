<?php

require_once "../includes/auth.php";
require_once "../../database.php";

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        password,
        service_type,
        address,
        status,
        citizenship_file,
        selfie_file,
        created_at
    FROM providers
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Provider Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff7fb;
            color: #333;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            color: #9d174d;
            font-size: 28px;
        }

        .page-header p {
            margin-top: 7px;
            color: #777;
        }

        .table-box {
            background: #ffffff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }

        th {
            background: #f9d7e8;
            color: #831843;
            padding: 14px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #f1e1e8;
            vertical-align: middle;
        }

        tr:hover {
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
            background: #dcfce7;
            color: #166534;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .inactive {
            background: #f3f4f6;
            color: #374151;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn {
            display: inline-block;
            padding: 7px 11px;
            margin: 2px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
        }

        .view {
            background: #fef3c7;
            color: #92400e;
        }

        .edit {
            background: #e0f2fe;
            color: #075985;
        }

        .services {
            background: #fce7f3;
            color: #9d174d;
        }

        .delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .file-link {
            color: #9d174d;
            text-decoration: none;
            font-weight: bold;
        }

        .no-file {
            color: #999;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #888;
        }
    </style>
</head>

<body>

<?php require_once "../includes/sidebar.php"; ?>

<div class="main-content">

    <div class="page-header">
        <h1>Provider Management</h1>
        <p>Manage all registered event service providers.</p>
    </div>

    <div class="table-box">

        <table>

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Service Type</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Citizenship</th>
                    <th>Selfie</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($provider = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo (int)$provider["id"]; ?>
                        </td>

                        <td>
                            <strong>
                                <?php echo htmlspecialchars($provider["name"]); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($provider["email"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($provider["phone"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($provider["service_type"] ?? ""); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($provider["address"] ?? ""); ?>
                        </td>

                        <td>

                            <?php
                            $status = $provider["status"] ?? "pending";

                            $status_class = "pending";

                            if ($status === "active") {
                                $status_class = "active";
                            } elseif ($status === "inactive") {
                                $status_class = "inactive";
                            } elseif ($status === "rejected") {
                                $status_class = "rejected";
                            }
                            ?>

                            <span class="status <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </span>

                        </td>

                        <td>

                            <?php if (!empty($provider["citizenship_file"])): ?>

                                <a
                                    href="../../<?php echo htmlspecialchars($provider["citizenship_file"]); ?>"
                                    target="_blank"
                                    class="file-link"
                                >
                                    View
                                </a>

                            <?php else: ?>

                                <span class="no-file">Not uploaded</span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (!empty($provider["selfie_file"])): ?>

                                <a
                                    href="../../<?php echo htmlspecialchars($provider["selfie_file"]); ?>"
                                    target="_blank"
                                    class="file-link"
                                >
                                    View
                                </a>

                            <?php else: ?>

                                <span class="no-file">Not uploaded</span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?php echo htmlspecialchars($provider["created_at"]); ?>
                        </td>

                        <td>

                            <a
                                href="view.php?id=<?php echo (int)$provider["id"]; ?>"
                                class="btn view"
                            >
                                👁 View
                            </a>

                            <a
                                href="edit.php?id=<?php echo (int)$provider["id"]; ?>"
                                class="btn edit"
                            >
                                ✏️ Edit
                            </a>

                            <a
                                href="services.php?provider_id=<?php echo (int)$provider["id"]; ?>"
                                class="btn services"
                            >
                                🛠 Services
                            </a>

                            <a
                                href="delete.php?id=<?php echo (int)$provider["id"]; ?>"
                                class="btn delete"
                                onclick="return confirm('Are you sure you want to delete this provider?');"
                            >
                                🗑 Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="11" class="empty">
                        No providers found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>