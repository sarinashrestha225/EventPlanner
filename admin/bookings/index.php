<?php

session_start();

require_once "../includes/auth.php";
require_once "../../database.php";

if (isset($_GET['accept'])) {

    $booking_id = intval($_GET['accept']);

    if ($booking_id > 0) {

        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'Accepted'
            WHERE id = ?
        ");

        $stmt->bind_param("i", $booking_id);

        if ($stmt->execute()) {

            $stmt->close();

            header("Location: index.php?success=accepted");
            exit;

        } else {

            $stmt->close();

            header("Location: index.php?error=accept_failed");
            exit;
        }
    }
}

if (isset($_GET['reject'])) {

    $booking_id = intval($_GET['reject']);

    if ($booking_id > 0) {

        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'Rejected'
            WHERE id = ?
        ");

        $stmt->bind_param("i", $booking_id);

        if ($stmt->execute()) {

            $stmt->close();

            header("Location: index.php?success=rejected");
            exit;

        } else {

            $stmt->close();

            header("Location: index.php?error=reject_failed");
            exit;
        }
    }
}

if (isset($_GET['delete'])) {

    $booking_id = intval($_GET['delete']);

    if ($booking_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM bookings
            WHERE id = ?
        ");

        $stmt->bind_param("i", $booking_id);

        if ($stmt->execute()) {

            $stmt->close();

            header("Location: index.php?success=deleted");
            exit;

        } else {

            $stmt->close();

            header("Location: index.php?error=delete_failed");
            exit;
        }
    }
}

$result = $conn->query("
    SELECT *
    FROM bookings
    ORDER BY id DESC
");

$total_bookings = 0;
$pending_bookings = 0;
$accepted_bookings = 0;
$completed_bookings = 0;

$count_result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Accepted') AS accepted,
        SUM(status = 'Completed') AS completed
    FROM bookings
");

if ($count_result) {

    $count = $count_result->fetch_assoc();

    $total_bookings = intval($count['total'] ?? 0);
    $pending_bookings = intval($count['pending'] ?? 0);
    $accepted_bookings = intval($count['accepted'] ?? 0);
    $completed_bookings = intval($count['completed'] ?? 0);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Bookings | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: "Segoe UI", Arial, sans-serif;

    background: #fff8ef;

    color: #5a4148;

    padding: 30px;
}

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    background: #fffdf8;

    padding: 22px 25px;

    border-radius: 18px;

    border: 1px solid #efd48a;

    box-shadow: 0 5px 20px rgba(190,140,40,0.08);

    margin-bottom: 25px;
}

.header h1 {

    font-family: Georgia, serif;

    color: #7d4b5c;

    font-size: 28px;
}

.header p {

    color: #9b7b83;

    margin-top: 5px;

    font-size: 14px;
}

.back-btn {

    text-decoration: none;

    background: #fff1d6;

    color: #8b6500;

    padding: 11px 18px;

    border-radius: 10px;

    border: 1px solid #e8c66a;

    font-weight: 600;
}

.back-btn:hover {

    background: #ffe8a8;
}

.alert {

    padding: 14px 18px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-weight: 600;
}

.success {

    background: #e7f8ed;

    color: #23733d;

    border: 1px solid #a9dfb9;
}

.error {

    background: #ffe7eb;

    color: #a8324a;

    border: 1px solid #efabb8;
}

.stats {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(190px, 1fr));

    gap: 18px;

    margin-bottom: 25px;
}

.stat {

    background: #fffdf8;

    border: 1px solid #f0d994;

    border-radius: 17px;

    padding: 20px;

    box-shadow: 0 6px 20px rgba(180,130,30,0.08);
}

.stat-icon {

    font-size: 28px;

    margin-bottom: 8px;
}

.stat-title {

    color: #8b6c73;

    font-size: 14px;
}

.stat-number {

    font-size: 27px;

    font-weight: bold;

    color: #b8860b;

    margin-top: 5px;
}

.table-box {

    background: #fffdf8;

    border: 1px solid #f0d994;

    border-radius: 18px;

    padding: 20px;

    box-shadow: 0 6px 20px rgba(180,130,30,0.08);

    overflow-x: auto;
}

.table-title {

    font-family: Georgia, serif;

    color: #7d4b5c;

    font-size: 22px;

    margin-bottom: 18px;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}

th {

    background: #ffe8a8;

    color: #684754;

    padding: 13px;

    text-align: left;

    font-size: 14px;
}

td {

    padding: 13px;

    border-bottom: 1px solid #f1e2bf;

    font-size: 14px;

    vertical-align: middle;
}

tr:hover {

    background: #fff8f3;
}

.status {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.status-pending {

    background: #fff0c7;

    color: #9a7000;
}

.status-accepted {

    background: #dff5e5;

    color: #24763d;
}

.status-rejected {

    background: #ffe1e6;

    color: #a52e45;
}

.status-completed {

    background: #e5e1ff;

    color: #51429a;
}

.actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.btn {

    display: inline-block;

    text-decoration: none;

    padding: 7px 11px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 600;

    border: none;

    cursor: pointer;
}

.accept {

    background: #dff5e5;

    color: #23733d;
}

.accept:hover {

    background: #bde9c9;
}

.reject {

    background: #ffe1e6;

    color: #a8324a;
}

.reject:hover {

    background: #ffcbd4;
}

.delete {

    background: #f5dcdc;

    color: #9b3030;
}

.delete:hover {

    background: #ecc2c2;
}

.empty {

    text-align: center;

    padding: 50px 20px;

    color: #9b7b83;

    font-size: 16px;
}

@media (max-width: 700px) {

    body {
        padding: 15px;
    }

    .header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

}

</style>

</head>

<body>

<div class="header">

    <div>

        <h1>📅 Booking Management</h1>

        <p>
            Manage customer bookings and approve service requests.
        </p>

    </div>

    <a
        href="../dashboard.php"
        class="back-btn"
    >
        ← Dashboard
    </a>

</div>

<?php if (isset($_GET['success'])): ?>

    <div class="alert success">

        <?php

        if ($_GET['success'] === 'accepted') {

            echo "✅ Booking accepted successfully.";

        } elseif ($_GET['success'] === 'rejected') {

            echo "❌ Booking rejected successfully.";

        } elseif ($_GET['success'] === 'deleted') {

            echo "🗑️ Booking deleted successfully.";

        }

        ?>

    </div>

<?php endif; ?>

<?php if (isset($_GET['error'])): ?>

    <div class="alert error">

        ❌ Something went wrong. Please try again.

    </div>

<?php endif; ?>

<div class="stats">

    <div class="stat">

        <div class="stat-icon">
            📅
        </div>

        <div class="stat-title">
            Total Bookings
        </div>

        <div class="stat-number">
            <?= $total_bookings ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-icon">
            ⏳
        </div>

        <div class="stat-title">
            Pending
        </div>

        <div class="stat-number">
            <?= $pending_bookings ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-icon">
            ✅
        </div>

        <div class="stat-title">
            Accepted
        </div>

        <div class="stat-number">
            <?= $accepted_bookings ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-icon">
            🎉
        </div>

        <div class="stat-title">
            Completed
        </div>

        <div class="stat-number">
            <?= $completed_bookings ?>
        </div>

    </div>

</div>

<div class="table-box">

    <div class="table-title">
        All Bookings
    </div>

    <?php if ($result && $result->num_rows > 0): ?>

        <table>

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Customer</th>

                    <th>Provider</th>

                    <th>Service</th>

                    <th>Amount</th>

                    <th>Status</th>

                    <th>Booking Date</th>

                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php

                $status = $row['status'] ?? 'Pending';

                $status_class = 'status-pending';

                if ($status === 'Accepted') {

                    $status_class = 'status-accepted';

                } elseif ($status === 'Rejected') {

                    $status_class = 'status-rejected';

                } elseif ($status === 'Completed') {

                    $status_class = 'status-completed';

                }

                ?>

                <tr>

                    <td>

                        #<?= intval($row['id'] ?? 0) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $row['customer_name']
                            ?? $row['user_name']
                            ?? $row['customer_id']
                            ?? '-'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $row['provider_name']
                            ?? $row['provider_id']
                            ?? '-'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $row['service_name']
                            ?? $row['service_id']
                            ?? '-'
                        ) ?>

                    </td>

                    <td>

                        Rs.

                        <?= number_format(
                            floatval(
                                $row['amount']
                                ?? $row['total_amount']
                                ?? $row['price']
                                ?? 0
                            )
                        ) ?>

                    </td>

                    <td>

                        <span class="status <?= $status_class ?>">

                            <?= htmlspecialchars($status) ?>

                        </span>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $row['booking_date']
                            ?? $row['event_date']
                            ?? $row['created_at']
                            ?? '-'
                        ) ?>

                    </td>

                    <td>

                        <div class="actions">

                            <?php if ($status === 'Pending'): ?>

                                <a
                                    href="index.php?accept=<?= intval($row['id']) ?>"
                                    class="btn accept"
                                    onclick="return confirm('Are you sure you want to ACCEPT this booking?');"
                                >
                                    ✅ Accept
                                </a>

                                <a
                                    href="index.php?reject=<?= intval($row['id']) ?>"
                                    class="btn reject"
                                    onclick="return confirm('Are you sure you want to REJECT this booking?');"
                                >
                                    ❌ Reject
                                </a>

                            <?php elseif ($status === 'Accepted'): ?>

                                <span class="btn accept">
                                    ✅ Accepted
                                </span>

                            <?php elseif ($status === 'Completed'): ?>

                                <span class="btn accept">
                                    🎉 Completed
                                </span>

                            <?php endif; ?>

                            <a
                                href="index.php?delete=<?= intval($row['id']) ?>"
                                class="btn delete"
                                onclick="return confirm('Are you sure you want to DELETE this booking?');"
                            >
                                🗑️ Delete
                            </a>

                        </div>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    <?php else: ?>

        <div class="empty">

            📭 No bookings found.

            <br><br>

            Customer ले booking गरेपछि यहाँ देखिन्छ।

        </div>

    <?php endif; ?>

</div>

</body>

</html>