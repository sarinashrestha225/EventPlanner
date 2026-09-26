<?php
session_start();
require_once __DIR__ . "/../database.php";

$sql = "
    SELECT id, event_name, description, icon
    FROM event_types
    WHERE status = 'active'
    ORDER BY event_name ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Error loading events: " . mysqli_error($conn));
}

$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    $events[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Event Planner</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff7fa;
            color: #333;
        }

        header {
            background: linear-gradient(135deg, #f8a9c4, #ffd86b);
            padding: 18px 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 26px;
            font-weight: bold;
            color: #fff;
        }

        nav {
            display: flex;
            gap: 22px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: #fff;
            font-weight: bold;
        }

        .container {
            max-width: 1200px;
            margin: 45px auto;
            padding: 0 20px;
        }

        .title {
            text-align: center;
            margin-bottom: 35px;
        }

        .title h1 {
            color: #d94f83;
            font-size: 34px;
            margin-bottom: 10px;
        }

        .title p {
            color: #777;
            font-size: 16px;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
        }

        .event-card {
            background: #fff;
            border-radius: 18px;
            padding: 28px 22px;
            text-align: center;
            box-shadow: 0 5px 18px rgba(0,0,0,0.08);
            border: 1px solid #f6d5df;
            transition: 0.3s;
        }

        .event-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 10px 25px rgba(217,79,131,0.18);
        }

        .event-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff0f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
        }

        .event-card h3 {
            color: #d94f83;
            font-size: 22px;
            margin-bottom: 12px;
        }

        .event-card p {
            color: #777;
            min-height: 50px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            background: linear-gradient(135deg, #f8a9c4, #e88aad);
            color: #fff;
            padding: 11px 22px;
            border-radius: 25px;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn:hover {
            background: linear-gradient(135deg, #e88aad, #d94f83);
        }

        .empty {
            text-align: center;
            padding: 50px;
            background: #fff;
            border-radius: 15px;
            color: #777;
        }

        @media (max-width: 600px) {
            header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 12px;
            }

            nav {
                gap: 12px;
            }

            .title h1 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">✦ Event Planner</div>

    <nav>
        <a href="index.php">Home</a>

        <?php if (isset($_SESSION["customer_id"])): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container">

    <div class="title">
        <h1>Choose Your Event</h1>
        <p>Select an event to explore available packages and services.</p>
    </div>

    <?php if (count($events) > 0): ?>

        <div class="events-grid">

            <?php foreach ($events as $event): ?>

                <div class="event-card">

                    <div class="event-icon">
                        <?= !empty($event["icon"]) ? htmlspecialchars($event["icon"]) : "🎉" ?>
                    </div>

                    <h3>
                        <?= htmlspecialchars($event["event_name"]) ?>
                    </h3>

                    <p>
                        <?= !empty($event["description"])
                            ? htmlspecialchars($event["description"])
                            : "Explore packages and services for your special event." ?>
                    </p>

                    <a class="btn" href="packages.php?event_id=<?= (int)$event["id"] ?>">
                        View Packages →
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">
            <h2>No events available</h2>
            <p>Please check again later.</p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>