<?php

session_start();

require_once "../database.php";

if (!isset($_GET['event_type_id']) || !is_numeric($_GET['event_type_id'])) {
    die("Invalid event type.");
}

$event_type_id = (int) $_GET['event_type_id'];

$event_sql = "
    SELECT id, event_name
    FROM event_types
    WHERE id = ?
    LIMIT 1
";

$event_stmt = $conn->prepare($event_sql);

if (!$event_stmt) {
    die("Event Query Error: " . $conn->error);
}

$event_stmt->bind_param("i", $event_type_id);
$event_stmt->execute();

$event_result = $event_stmt->get_result();

if ($event_result->num_rows === 0) {
    die("Event type not found.");
}

$event = $event_result->fetch_assoc();

$event_name = $event['event_name'];

$event_stmt->close();

$sql = "
    SELECT
        s.id,
        s.service_name,
        s.description,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.image,
        s.status

    FROM event_services AS es

    INNER JOIN services AS s
        ON es.service_id = s.id

    WHERE es.event_type_id = ?
      AND s.status = 'active'

    ORDER BY s.service_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Service Query Error: " . $conn->error);
}

$stmt->bind_param("i", $event_type_id);

if (!$stmt->execute()) {
    die("Service Execution Error: " . $stmt->error);
}

$result = $stmt->get_result();

$customer_name = "Customer";

if (isset($_SESSION['user_name']) && $_SESSION['user_name'] != "") {
    $customer_name = $_SESSION['user_name'];
} elseif (isset($_SESSION['name']) && $_SESSION['name'] != "") {
    $customer_name = $_SESSION['name'];
}

$event_icon = "🎉";

switch (strtolower($event_name)) {

    case "wedding":
        $event_icon = "💍";
        break;

    case "birthday":
        $event_icon = "🎂";
        break;

    case "engagement":
        $event_icon = "💎";
        break;

    case "anniversary":
        $event_icon = "❤️";
        break;

    case "baby shower":
        $event_icon = "👶";
        break;

    case "corporate event":
        $event_icon = "🏢";
        break;

    case "party":
        $event_icon = "🎊";
        break;

    case "graduation":
        $event_icon = "🎓";
        break;

    case "religious event":
        $event_icon = "🛕";
        break;

    default:
        $event_icon = "🎉";
        break;
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
        <?= htmlspecialchars($event_name) ?> Services | Event Planner
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fffaf0;
            color: #4b3a2f;
        }

        header {

            background:
                linear-gradient(
                    135deg,
                    #f8c8dc,
                    #f7d774
                );

            padding: 18px 45px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.10);
        }

        .logo {

            font-size: 25px;

            font-weight: bold;

            color: #6b4f2a;
        }

        .welcome {

            font-size: 15px;

            font-weight: bold;

            color: #5c4632;
        }

        nav {

            background: #ffffff;

            padding: 13px 45px;

            display: flex;

            gap: 24px;

            flex-wrap: wrap;

            border-bottom:
                1px solid #ead9b8;
        }

        nav a {

            text-decoration: none;

            color: #6b4f2a;

            font-size: 14px;

            font-weight: bold;
        }

        nav a:hover {

            color: #c49a28;
        }

        .container {

            width: 92%;

            max-width: 1200px;

            margin: 35px auto;
        }

        .back-btn {

            display: inline-block;

            padding: 10px 18px;

            background: #f8c8dc;

            color: #5c3d4d;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;

            margin-bottom: 25px;
        }

        .back-btn:hover {

            background: #f1aecb;
        }

        .page-title {

            text-align: center;

            margin-bottom: 35px;
        }

        .page-title .icon {

            font-size: 55px;

            margin-bottom: 8px;
        }

        .page-title h1 {

            font-size: 32px;

            color: #8b6914;

            margin-bottom: 10px;
        }

        .page-title p {

            color: #777;

            font-size: 15px;
        }

        .service-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(280px, 1fr)
                );

            gap: 25px;
        }

        .service-card {

            background: #ffffff;

            border-radius: 15px;

            overflow: hidden;

            border:
                1px solid #ead9b8;

            box-shadow:
                0 5px 18px rgba(0, 0, 0, 0.08);

            transition: 0.3s;
        }

        .service-card:hover {

            transform: translateY(-5px);

            box-shadow:
                0 10px 25px rgba(0, 0, 0, 0.14);
        }

        .service-image {

            width: 100%;

            height: 190px;

            object-fit: cover;

            display: block;
        }

        .no-image {

            width: 100%;

            height: 190px;

            background: #f8e6ee;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 55px;
        }

        .service-content {

            padding: 20px;
        }

        .service-content h2 {

            color: #8b6914;

            font-size: 21px;

            margin-bottom: 10px;
        }

        .description {

            color: #666;

            font-size: 14px;

            line-height: 1.6;

            min-height: 65px;
        }

        .price-box {

            margin-top: 15px;

            padding: 12px;

            background: #fff8dc;

            border-left:
                4px solid #d4af37;

            border-radius: 7px;
        }

        .price-label {

            font-size: 12px;

            color: #777;

            margin-bottom: 5px;
        }

        .price {

            color: #9a7415;

            font-size: 18px;

            font-weight: bold;
        }

        .unit {

            font-size: 12px;

            color: #777;

            margin-top: 4px;
        }

        .button-area {

            display: flex;

            gap: 10px;

            margin-top: 18px;
        }

        .view-btn,
        .book-btn {

            flex: 1;

            text-align: center;

            padding: 11px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;
        }

        .view-btn {

            background: #f8c8dc;

            color: #5c3d4d;
        }

        .view-btn:hover {

            background: #f1aecb;
        }

        .book-btn {

            background: #d4af37;

            color: #ffffff;
        }

        .book-btn:hover {

            background: #b89220;
        }

        .empty-box {

            background: #ffffff;

            border:
                1px solid #ead9b8;

            border-radius: 15px;

            padding: 60px 20px;

            text-align: center;

            box-shadow:
                0 5px 18px rgba(0, 0, 0, 0.06);
        }

        .empty-icon {

            font-size: 60px;

            margin-bottom: 15px;
        }

        .empty-box h2 {

            color: #8b6914;

            margin-bottom: 10px;
        }

        .empty-box p {

            color: #777;

            margin-bottom: 20px;
        }

        footer {

            margin-top: 60px;

            background: #f8c8dc;

            padding: 20px;

            text-align: center;

            color: #5c3d4d;

            font-size: 14px;
        }

        @media (max-width: 700px) {

            header {

                padding: 15px 20px;

                flex-direction: column;

                gap: 8px;
            }

            nav {

                padding: 12px 20px;

                gap: 15px;
            }

            .container {

                width: 94%;
            }

            .page-title h1 {

                font-size: 26px;
            }

        }

    </style>

</head>

<body>

<header>

    <div class="logo">
        🎉 Event Planner
    </div>

    <div class="welcome">

        👋 Hello,
        <?= htmlspecialchars($customer_name) ?>

    </div>

</header>

<nav>

    <a href="dashboard.php">
        🏠 Dashboard
    </a>

    <a href="profile.php">
        👤 Profile
    </a>

    <a href="my_bookings.php">
        📋 My Bookings
    </a>

    <a href="notifications.php">
        🔔 Notifications
    </a>

    <a href="messages.php">
        💬 Messages
    </a>

    <a href="change_password.php">
        🔐 Password
    </a>

    <a href="logout.php">
        🚪 Logout
    </a>

</nav>

<div class="container">

    <a
        href="dashboard.php"
        class="back-btn"
    >
        ← Back to Events
    </a>

    <div class="page-title">

        <div class="icon">
            <?= $event_icon ?>
        </div>

        <h1>

            <?= htmlspecialchars($event_name) ?>
            Services

        </h1>

        <p>

            Choose the services you need
            for your <?= htmlspecialchars($event_name) ?>.

        </p>

    </div>

    <?php if ($result->num_rows > 0): ?>

        <div class="service-grid">

            <?php while ($service = $result->fetch_assoc()): ?>

                <div class="service-card">

                    <?php

                    $image_path =
                        "../uploads/services/"
                        . $service['image'];

                    ?>

                    <?php if (
                        !empty($service['image'])
                        &&
                        file_exists($image_path)
                    ): ?>

                        <img
                            src="<?= htmlspecialchars($image_path) ?>"
                            alt="<?= htmlspecialchars($service['service_name']) ?>"
                            class="service-image"
                        >

                    <?php else: ?>

                        <div class="no-image">
                            🛍️
                        </div>

                    <?php endif; ?>

                    <div class="service-content">

                        <h2>

                            <?= htmlspecialchars(
                                $service['service_name']
                            ) ?>

                        </h2>

                        <p class="description">

                            <?= htmlspecialchars(
                                $service['description']
                                ?: 'Professional service for your event.'
                            ) ?>

                        </p>

                        <div class="price-box">

                            <div class="price-label">

                                Estimated Price

                            </div>

                            <?php if (
                                $service['min_price'] > 0
                                &&
                                $service['max_price'] > 0
                            ): ?>

                                <div class="price">

                                    Rs.
                                    <?= number_format(
                                        $service['min_price'],
                                        0
                                    ) ?>

                                    -

                                    Rs.
                                    <?= number_format(
                                        $service['max_price'],
                                        0
                                    ) ?>

                                </div>

                            <?php elseif (
                                $service['price'] > 0
                            ): ?>

                                <div class="price">

                                    Rs.
                                    <?= number_format(
                                        $service['price'],
                                        0
                                    ) ?>

                                </div>

                            <?php else: ?>

                                <div class="price">

                                    Contact Provider

                                </div>

                            <?php endif; ?>

                            <div class="unit">

                                <?= htmlspecialchars(
                                    $service['unit']
                                ) ?>

                            </div>

                        </div>

                        <div class="button-area">

                            <a
                                href="service_details.php?id=<?= (int)$service['id'] ?>"
                                class="view-btn"
                            >
                                View Details
                            </a>

                            <a
                                href="book_service.php?service_id=<?= (int)$service['id'] ?>&event_type_id=<?= $event_type_id ?>"
                                class="book-btn"
                            >
                                Book Now
                            </a>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-box">

            <div class="empty-icon">
                📭
            </div>

            <h2>

                No Services Available

            </h2>

            <p>

                There are currently no services
                available for this event.

            </p>

            <a
                href="dashboard.php"
                class="back-btn"
            >
                ← Choose Another Event
            </a>

        </div>

    <?php endif; ?>

</div>

<footer>

    © <?= date("Y") ?> Event Planner

    <br>

    Plan • Book • Celebrate 🎉

</footer>

<?php

$stmt->close();

$conn->close();

?>

</body>

</html>