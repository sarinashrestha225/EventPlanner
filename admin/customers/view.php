<?php

session_start();

require_once "../includes/auth.php";
require_once "../../database.php";


// Check customer ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];


// Get customer
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Customer not found.");
}

$customer = $result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Details | Event Planner</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff8f2;
            color: #333;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            color: #b8860b;
            margin-bottom: 5px;
        }

        .header p {
            color: #777;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 30px;
            max-width: 900px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
            margin-bottom: 25px;
        }

        .avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #f5d76e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            color: #5c4500;
        }

        .profile h2 {
            color: #8a3156;
            margin-bottom: 5px;
        }

        .profile p {
            color: #777;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-box {
            background: #fff8fb;
            padding: 18px;
            border-radius: 12px;
        }

        .detail-box label {
            display: block;
            font-size: 13px;
            color: #888;
            margin-bottom: 7px;
        }

        .detail-box strong {
            font-size: 16px;
            color: #333;
        }

        .status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
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

        .actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .back {
            background: #f5d76e;
            color: #5c4500;
        }

        .edit {
            background: #f8c8dc;
            color: #8a3156;
        }

        @media(max-width: 800px) {

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .details {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<?php

require_once "../includes/sidebar.php";

?>


<div class="main">


    <div class="header">

        <h1>Customer Details</h1>

        <p>
            View complete information about this customer.
        </p>

    </div>


    <div class="card">


        <div class="profile">

            <div class="avatar">

                <?php

                $name = $customer['name'] ?? 'C';

                echo strtoupper(substr($name, 0, 1));

                ?>

            </div>


            <div>

                <h2>

                    <?php

                    echo htmlspecialchars(
                        $customer['name'] ?? 'N/A'
                    );

                    ?>

                </h2>

                <p>Customer ID: #<?php echo $customer['id']; ?></p>

            </div>

        </div>


        <div class="details">


            <div class="detail-box">

                <label>Full Name</label>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $customer['name'] ?? 'N/A'
                    );

                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <label>Email</label>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $customer['email'] ?? 'N/A'
                    );

                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <label>Phone</label>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $customer['phone'] ?? 'N/A'
                    );

                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <label>Status</label>

                <?php

                $status = $customer['status'] ?? 'active';

                ?>

                <span class="status <?php echo ($status === 'active') ? 'active' : 'inactive'; ?>">

                    <?php echo ucfirst(htmlspecialchars($status)); ?>

                </span>

            </div>


            <div class="detail-box">

                <label>Customer ID</label>

                <strong>

                    #<?php echo $customer['id']; ?>

                </strong>

            </div>


            <div class="detail-box">

                <label>Registered Date</label>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $customer['created_at'] ?? 'N/A'
                    );

                    ?>

                </strong>

            </div>


        </div>


        <div class="actions">

            <a
                href="index.php"
                class="btn back"
            >
                ← Back to Customers
            </a>


            <a
                href="edit.php?id=<?php echo $customer['id']; ?>"
                class="btn edit"
            >
                Edit Customer
            </a>

        </div>


    </div>


</div>


</body>

</html>