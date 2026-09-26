
<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit();
}

$provider_id = (int)$_SESSION["provider_id"];
$provider_name = $_SESSION["provider_name"] ?? "Provider";

$commission_rate = 20.00;

$earnings = [];

$sql = "
    SELECT
        pay.id AS payment_id,
        pay.booking_id,
        pay.amount AS payment_amount,
        pay.payment_method,
        pay.payment_status,
        pay.created_at AS payment_date,

        b.event_type_id,
        b.service_id,
        b.package_id,
        b.booking_date,
        b.booking_time,
        b.amount AS booking_amount,

        et.event_name,
        s.service_name,
        p.package_name

    FROM payments pay

    INNER JOIN bookings b
        ON pay.booking_id = b.id

    LEFT JOIN event_types et
        ON b.event_type_id = et.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN packages p
        ON b.package_id = p.id

    WHERE b.provider_id = ?
    AND pay.payment_status IN ('pending', 'paid')

    ORDER BY pay.id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $provider_id);

if (!$stmt->execute()) {
    die("Execute Error: " . htmlspecialchars($stmt->error));
}

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $payment_amount = (float)$row["payment_amount"];

    $commission_amount =
        $payment_amount * ($commission_rate / 100);

    $provider_amount =
        $payment_amount - $commission_amount;

    $status = strtolower(
        trim($row["payment_status"] ?? "pending")
    );

    $item_name = "Event Service";

    if (!empty($row["package_name"])) {
        $item_name = $row["package_name"];
    } elseif (!empty($row["service_name"])) {
        $item_name = $row["service_name"];
    }

    $row["item_name"] = $item_name;
    $row["commission_rate"] = $commission_rate;
    $row["commission_amount"] = $commission_amount;
    $row["provider_amount"] = $provider_amount;

    $earnings[] = $row;
}

$stmt->close();

$total_sales = 0;
$total_commission = 0;
$total_earnings = 0;
$paid_earnings = 0;
$pending_earnings = 0;

foreach ($earnings as $earning) {

    $payment_amount = (float)$earning["payment_amount"];
    $commission_amount = (float)$earning["commission_amount"];
    $provider_amount = (float)$earning["provider_amount"];

    $total_sales += $payment_amount;
    $total_commission += $commission_amount;
    $total_earnings += $provider_amount;

    $status = strtolower(
        trim($earning["payment_status"] ?? "pending")
    );

    if ($status === "paid") {
        $paid_earnings += $provider_amount;
    } else {
        $pending_earnings += $provider_amount;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Earnings | Provider</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f4;
    color: #4b3621;
}

.header {
    background: linear-gradient(
        135deg,
        #f8c8dc,
        #f4d58d
    );

    padding: 22px 35px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.08);
}

.header-left h1 {
    margin: 0;
    font-size: 27px;
    color: #5a3d34;
}

.header-left p {
    margin: 6px 0 0;
    color: #75594f;
    font-size: 14px;
}

.dashboard-btn {
    text-decoration: none;
    background: white;
    color: #805d12;
    padding: 11px 18px;
    border-radius: 10px;
    font-weight: bold;
}

.dashboard-btn:hover {
    background: #fff8df;
}

.container {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 20px;
}

.summary {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 22px;
    border-radius: 16px;

    box-shadow:
        0 5px 18px rgba(0,0,0,0.07);

    border-top: 4px solid #e6c15a;
}

.summary-icon {
    font-size: 28px;
    margin-bottom: 12px;
}

.summary-card h3 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #806f67;
    font-weight: normal;
}

.amount {
    font-size: 22px;
    font-weight: bold;
    color: #c39828;
}

.paid-card {
    border-top-color: #77b982;
}

.paid-card .amount {
    color: #25813b;
}

.pending-card {
    border-top-color: #e6a83c;
}

.pending-card .amount {
    color: #b87900;
}

.main-card {
    background: white;
    padding: 25px;
    border-radius: 16px;

    box-shadow:
        0 5px 18px rgba(0,0,0,0.07);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-header h2 {
    margin: 0;
    color: #65463c;
    font-size: 21px;
}

.card-header span {
    color: #8a7770;
    font-size: 13px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

th {
    background: #fff4da;
    color: #624b3f;
    padding: 14px 12px;
    text-align: left;
    border-bottom: 2px solid #ead7a5;
    font-size: 14px;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid #f0e8e3;
    vertical-align: middle;
    font-size: 14px;
}

tr:hover td {
    background: #fffaf7;
}

.id {
    color: #8a6b1d;
    font-weight: bold;
}

.item-name {
    font-weight: bold;
    color: #65463c;
}

.event-name {
    color: #806f67;
    font-size: 13px;
}

.money {
    font-weight: bold;
    color: #6c563f;
}

.commission {
    color: #b64b4b;
    font-weight: bold;
}

.commission-amount {
    color: #8d7770;
    font-size: 12px;
    margin-top: 4px;
}

.provider-money {
    color: #21833b;
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

.status-paid {
    background: #dff3e2;
    color: #237638;
}

.status-pending {
    background: #fff0c9;
    color: #946c00;
}

.date {
    color: #75645d;
    white-space: nowrap;
}

.empty {
    text-align: center;
    padding: 65px 20px;
}

.empty-icon {
    font-size: 55px;
    margin-bottom: 15px;
}

.empty h2 {
    margin: 0 0 8px;
    color: #65463c;
}

.empty p {
    margin: 0;
    color: #8a7770;
}

.footer-note {
    margin-top: 18px;
    padding: 15px;

    background: #fff8e8;

    border-left: 4px solid #e4bd4f;

    border-radius: 8px;

    color: #705d40;

    font-size: 13px;
}

@media (max-width: 1100px) {

    .summary {
        grid-template-columns: repeat(3, 1fr);
    }

}

@media (max-width: 750px) {

    .header {
        padding: 18px 20px;
    }

    .header-left h1 {
        font-size: 22px;
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .container {
        padding: 0 15px;
        margin: 20px auto;
    }

    .main-card {
        padding: 18px;
    }

}

@media (max-width: 500px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .dashboard-btn {
        width: 100%;
        text-align: center;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="header-left">

        <h1>💰 Provider Earnings</h1>

        <p>
            Welcome,
            <?= htmlspecialchars(
                $provider_name,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>

    </div>

    <a
        href="dashboard.php"
        class="dashboard-btn"
    >
        ← Dashboard
    </a>

</header>

<div class="container">

    <div class="summary">

        <div class="summary-card">

            <div class="summary-icon">
                💵
            </div>

            <h3>
                Total Sales
            </h3>

            <div class="amount">
                Rs.
                <?= number_format(
                    $total_sales,
                    2
                ) ?>
            </div>

        </div>

        <div class="summary-card">

            <div class="summary-icon">
                🏢
            </div>

            <h3>
                Company Commission
            </h3>

            <div class="amount">
                Rs.
                <?= number_format(
                    $total_commission,
                    2
                ) ?>
            </div>

        </div>

        <div class="summary-card">

            <div class="summary-icon">
                💰
            </div>

            <h3>
                Your Total Earnings
            </h3>

            <div class="amount">
                Rs.
                <?= number_format(
                    $total_earnings,
                    2
                ) ?>
            </div>

        </div>

        <div class="summary-card paid-card">

            <div class="summary-icon">
                ✅
            </div>

            <h3>
                Paid to You
            </h3>

            <div class="amount">
                Rs.
                <?= number_format(
                    $paid_earnings,
                    2
                ) ?>
            </div>

        </div>

        <div class="summary-card pending-card">

            <div class="summary-icon">
                ⏳
            </div>

            <h3>
                Pending Earnings
            </h3>

            <div class="amount">
                Rs.
                <?= number_format(
                    $pending_earnings,
                    2
                ) ?>
            </div>

        </div>

    </div>

    <div class="main-card">

        <div class="card-header">

            <h2>
                📊 Earnings History
            </h2>

            <span>
                <?= count($earnings) ?>
                record<?= count($earnings) == 1 ? "" : "s" ?>
            </span>

        </div>

        <?php if (empty($earnings)): ?>

            <div class="empty">

                <div class="empty-icon">
                    💰
                </div>

                <h2>
                    No Earnings Yet
                </h2>

                <p>
                    Earnings will appear here after customer payments are processed.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Payment
                            </th>

                            <th>
                                Booking
                            </th>

                            <th>
                                Package / Service
                            </th>

                            <th>
                                Event
                            </th>

                            <th>
                                Payment Amount
                            </th>

                            <th>
                                Commission
                            </th>

                            <th>
                                Your Earnings
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($earnings as $earning): ?>

                        <?php

                        $status = strtolower(
                            trim(
                                $earning["payment_status"] ?? "pending"
                            )
                        );

                        $status_class = preg_replace(
                            "/[^a-z0-9_-]/",
                            "",
                            $status
                        );

                        ?>

                        <tr>

                            <td class="id">
                                #<?= (int)$earning["payment_id"] ?>
                            </td>

                            <td class="id">
                                #<?= (int)$earning["booking_id"] ?>
                            </td>

                            <td>

                                <div class="item-name">

                                    <?= htmlspecialchars(
                                        $earning["item_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>

                            <td>

                                <div class="event-name">

                                    <?= htmlspecialchars(
                                        $earning["event_name"] ?? "Event",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </td>

                            <td class="money">

                                Rs.
                                <?= number_format(
                                    (float)$earning["payment_amount"],
                                    2
                                ) ?>

                            </td>

                            <td class="commission">

                                <?= number_format(
                                    (float)$earning["commission_rate"],
                                    2
                                ) ?>%

                                <div class="commission-amount">

                                    Rs.
                                    <?= number_format(
                                        (float)$earning["commission_amount"],
                                        2
                                    ) ?>

                                </div>

                            </td>

                            <td class="provider-money">

                                Rs.
                                <?= number_format(
                                    (float)$earning["provider_amount"],
                                    2
                                ) ?>

                            </td>

                            <td>

                                <span
                                    class="status status-<?= htmlspecialchars(
                                        $status_class,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $status ?: "Pending"
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>

                            <td class="date">

                                <?= htmlspecialchars(
                                    $earning["payment_date"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div class="footer-note">

                💡 Company commission is calculated at
                <?= number_format($commission_rate, 0) ?>%
                of each customer payment.
                Your earnings are the remaining amount.

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>

