<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$sql = "
    SELECT
        id,
        booking_id,
        payment_id,
        customer_id,
        provider_id,
        service_id,
        total_amount,
        commission_rate,
        commission_amount,
        provider_amount,
        status,
        paid_at,
        created_at
    FROM commissions
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

}

$total_commission = 0;
$pending_commission = 0;
$paid_commission = 0;
$provider_payable = 0;
$total_records = 0;

$summary_sql = "
    SELECT

        COUNT(*) AS total_records,

        COALESCE(
            SUM(commission_amount),
            0
        ) AS total_commission,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'pending'
                    THEN commission_amount
                    ELSE 0
                END
            ),
            0
        ) AS pending_commission,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'paid'
                    THEN commission_amount
                    ELSE 0
                END
            ),
            0
        ) AS paid_commission,

        COALESCE(
            SUM(provider_amount),
            0
        ) AS provider_payable

    FROM commissions
";

$summary_result =
    $conn->query($summary_sql);

if ($summary_result) {

    $summary =
        $summary_result->fetch_assoc();

    $total_records =
        (int)$summary["total_records"];

    $total_commission =
        (float)$summary["total_commission"];

    $pending_commission =
        (float)$summary["pending_commission"];

    $paid_commission =
        (float)$summary["paid_commission"];

    $provider_payable =
        (float)$summary["provider_payable"];

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

    <title>
        Commissions | Event Planner Admin
    </title>

    <style>

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }

        body {

            background: #fffaf0;

            color: #5a4630;

        }

        .main-content {

            margin-left: 250px;

            padding: 30px;

            min-height: 100vh;

        }

        .page-header {

            background:
                linear-gradient(
                    135deg,
                    #fff2a8,
                    #ffd75e,
                    #f8c1d4
                );

            padding: 25px 30px;

            border-radius: 20px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 20px
                rgba(218, 165, 32, 0.15);

        }

        .page-header h1 {

            color: #704800;

            font-size: 30px;

            margin-bottom: 7px;

        }

        .page-header p {

            color: #765d40;

            font-size: 14px;

        }

        .summary {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 25px;

        }

        .summary-card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 16px;

            padding: 20px;

            box-shadow:
                0 5px 20px
                rgba(218, 165, 32, 0.08);

        }

        .summary-title {

            color: #927750;

            font-size: 12px;

            font-weight: bold;

            margin-bottom: 8px;

        }

        .summary-value {

            color: #704800;

            font-size: 20px;

            font-weight: bold;

        }

        .card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 5px 20px
                rgba(218, 165, 32, 0.10);

        }

        .table-container {

            width: 100%;

            overflow-x: auto;

        }

        table {

            width: 100%;

            min-width: 1250px;

            border-collapse: collapse;

        }

        thead {

            background: #f6d568;

        }

        th {

            padding: 14px;

            text-align: left;

            color: #674300;

            font-size: 13px;

            white-space: nowrap;

        }

        td {

            padding: 13px;

            border-bottom:
                1px solid #f1dfb0;

            font-size: 13px;

            white-space: nowrap;

        }

        tbody tr:nth-child(even) {

            background: #fff3f6;

        }

        tbody tr:hover {

            background: #fff0b3;

        }

        .rate {

            background: #fff0a6;

            color: #705000;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

        }

        .status {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

        }

        .pending {

            background: #ffe5a0;

            color: #8a6200;

        }

        .paid {

            background: #dff3c4;

            color: #4e7528;

        }

        .company-amount {

            color: #8a6200;

            font-weight: bold;

        }

        .provider-amount {

            color: #4e7528;

            font-weight: bold;

        }

        .empty {

            text-align: center;

            padding: 50px;

            color: #8c7858;

        }

        @media (max-width: 1100px) {

            .summary {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }

        @media (max-width: 700px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

            .summary {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>

<body>

<?php

include "../includes/sidebar.php";

?>

<div class="main-content">

    <div class="page-header">

        <h1>
            💰 Commissions
        </h1>

        <p>
            Manage company commissions and provider payments
        </p>

    </div>

    <div class="summary">

        <div class="summary-card">

            <div class="summary-title">

                Total Records

            </div>

            <div class="summary-value">

                <?= $total_records ?>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-title">

                Total Commission

            </div>

            <div class="summary-value">

                Rs.
                <?= number_format(
                    $total_commission,
                    2
                ) ?>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-title">

                Paid Commission

            </div>

            <div class="summary-value">

                Rs.
                <?= number_format(
                    $paid_commission,
                    2
                ) ?>

            </div>

        </div>

        <div class="summary-card">

            <div class="summary-title">

                Provider Payable

            </div>

            <div class="summary-value">

                Rs.
                <?= number_format(
                    $provider_payable,
                    2
                ) ?>

            </div>

        </div>

    </div>

    <div class="card">

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Commission ID
                        </th>

                        <th>
                            Booking ID
                        </th>

                        <th>
                            Payment ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Provider
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Total Amount
                        </th>

                        <th>
                            Commission
                        </th>

                        <th>
                            Company Amount
                        </th>

                        <th>
                            Provider Amount
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

                <?php if ($result->num_rows > 0): ?>

                    <?php while (
                        $commission =
                        $result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                #<?= (int)$commission["id"] ?>

                            </td>

                            <td>

                                #<?= (int)$commission["booking_id"] ?>

                            </td>

                            <td>

                                <?php

                                if (
                                    $commission["payment_id"]
                                    !== null
                                ) {

                                    echo "#"
                                        . (int)
                                        $commission["payment_id"];

                                } else {

                                    echo "N/A";

                                }

                                ?>

                            </td>

                            <td>

                                #<?= (int)$commission["customer_id"] ?>

                            </td>

                            <td>

                                <?php

                                if (
                                    $commission["provider_id"]
                                    !== null
                                ) {

                                    echo "#"
                                        . (int)
                                        $commission["provider_id"];

                                } else {

                                    echo "N/A";

                                }

                                ?>

                            </td>

                            <td>

                                <?php

                                if (
                                    $commission["service_id"]
                                    !== null
                                ) {

                                    echo "#"
                                        . (int)
                                        $commission["service_id"];

                                } else {

                                    echo "N/A";

                                }

                                ?>

                            </td>

                            <td>

                                Rs.
                                <?= number_format(
                                    (float)
                                    $commission[
                                        "total_amount"
                                    ],
                                    2
                                ) ?>

                            </td>

                            <td>

                                <span class="rate">

                                    <?= number_format(
                                        (float)
                                        $commission[
                                            "commission_rate"
                                        ],
                                        2
                                    ) ?>%

                                </span>

                            </td>

                            <td>

                                <span
                                    class="company-amount"
                                >

                                    Rs.
                                    <?= number_format(
                                        (float)
                                        $commission[
                                            "commission_amount"
                                        ],
                                        2
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <span
                                    class="provider-amount"
                                >

                                    Rs.
                                    <?= number_format(
                                        (float)
                                        $commission[
                                            "provider_amount"
                                        ],
                                        2
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <span
                                    class="status
                                    <?= htmlspecialchars(
                                        $commission["status"]
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $commission["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $commission["created_at"]
                                ) ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="12"
                            class="empty"
                        >

                            💰

                            <br><br>

                            No commission records found.

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