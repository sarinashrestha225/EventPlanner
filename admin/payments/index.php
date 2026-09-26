<?php

session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

$sql = "
    SELECT
        p.id,
        p.booking_id,
        p.customer_id,
        p.provider_id,
        p.amount,
        p.payment_method,
        p.bank_name,
        p.transaction_id,
        p.reference_number,
        p.payment_status,
        p.payment_note,
        p.payment_date,
        p.created_at,

        u.name AS customer_name,

        b.service_id,
        b.package_id,
        b.booking_date,

        s.service_name,
        pk.package_name

    FROM payments p

    LEFT JOIN users u
        ON p.customer_id = u.id

    LEFT JOIN bookings b
        ON p.booking_id = b.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN packages pk
        ON b.package_id = pk.id

    ORDER BY p.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Payment Verification - Admin</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f2;
    color: #555;
}

.header {
    background: linear-gradient(
        90deg,
        #d4af37,
        #f3d36a
    );

    padding: 20px 35px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    color: white;
}

.header h2 {
    margin: 0;
}

.back-btn {
    background: #8f6908;
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: bold;
}

.container {
    width: 94%;
    max-width: 1400px;
    margin: 35px auto;
}

.title {
    text-align: center;
    margin-bottom: 30px;
}

.title h1 {
    color: #b8860b;
    margin-bottom: 8px;
}

.title p {
    color: #777;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 15px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.08);

    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th {
    background: #fff1c7;
    color: #7a4b00;
    padding: 14px;
    text-align: left;
}

td {
    padding: 13px;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #fffdf5;
}

.amount {
    color: #b8860b;
    font-weight: bold;
}

.status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: capitalize;
}

.pending {
    background: #fff1c7;
    color: #a06b00;
}

.paid {
    background: #dff5df;
    color: #287a28;
}

.failed {
    background: #ffe1e1;
    color: #b00020;
}

.refunded {
    background: #e5e5e5;
    color: #555;
}

.view-btn {
    display: inline-block;
    background: #d4af37;
    color: white;
    text-decoration: none;
    padding: 8px 13px;
    border-radius: 7px;
    font-weight: bold;
}

.view-btn:hover {
    background: #b8860b;
}

.empty {
    text-align: center;
    padding: 50px;
    color: #888;
}

</style>

</head>

<body>

<div class="header">

    <h2>
        ✦ Event Planner Admin
    </h2>

    <a
        href="../dashboard.php"
        class="back-btn"
    >
        ← Dashboard
    </a>

</div>


<div class="container">

<div class="title">

    <h1>
        💳 Payment Verification
    </h1>

    <p>
        View and verify customer payments
    </p>

</div>


<div class="card">

<?php if ($result->num_rows > 0): ?>

<table>

<thead>

<tr>

    <th>ID</th>

    <th>Customer</th>

    <th>Booking</th>

    <th>Type</th>

    <th>Amount</th>

    <th>Method</th>

    <th>Transaction</th>

    <th>Status</th>

    <th>Date</th>

    <th>Action</th>

</tr>

</thead>

<tbody>

<?php while ($payment = $result->fetch_assoc()): ?>

<tr>

<td>
    #<?= (int)$payment["id"] ?>
</td>


<td>

    <?= htmlspecialchars(
        $payment["customer_name"]
        ?? "Customer"
    ) ?>

</td>


<td>

    #<?= (int)$payment["booking_id"] ?>

</td>


<td>

<?php if (!empty($payment["package_id"])): ?>

    📦
    <?= htmlspecialchars(
        $payment["package_name"]
        ?? "Package"
    ) ?>

<?php else: ?>

    🧩
    <?= htmlspecialchars(
        $payment["service_name"]
        ?? "Service"
    ) ?>

<?php endif; ?>

</td>


<td class="amount">

    Rs.
    <?= number_format(
        (float)$payment["amount"],
        2
    ) ?>

</td>


<td>

    <?= htmlspecialchars(
        ucwords(
            str_replace(
                "_",
                " ",
                $payment["payment_method"]
            )
        )
    ) ?>

</td>


<td>

<?php

$transaction =
    $payment["transaction_id"];

$reference =
    $payment["reference_number"];

if (!empty($transaction)) {

    echo htmlspecialchars($transaction);

}
elseif (!empty($reference)) {

    echo htmlspecialchars($reference);

}
else {

    echo "—";

}

?>

</td>


<td>

<span
    class="status
    <?= htmlspecialchars(
        strtolower(
            $payment["payment_status"]
        )
    ) ?>"
>

<?= htmlspecialchars(
    ucfirst(
        $payment["payment_status"]
    )
) ?>

</span>

</td>


<td>

<?= !empty($payment["created_at"])
    ? date(
        "d M Y",
        strtotime(
            $payment["created_at"]
        )
    )
    : "N/A"
?>

</td>


<td>

<a
    href="view.php?id=<?= (int)$payment["id"] ?>"
    class="view-btn"
>

    👁 View

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="empty">

    💳

    <h2>
        No Payments Found
    </h2>

    <p>
        Customer payments will appear here.
    </p>

</div>

<?php endif; ?>

</div>

</div>

</body>

</html>

<?php

$conn->close();

?>