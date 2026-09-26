<?php
session_start();

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["user_name"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Event Planner</title>

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

        /* NAVBAR */

        .navbar {
            background: white;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .logo {
            font-size: 26px;
            font-weight: bold;
            color: #c49a00;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-links a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: #b8860b;
        }

        .login-btn {
            background: #d4af37;
            color: white !important;
            padding: 10px 20px;
            border-radius: 8px;
        }

        .register-btn {
            background: #f8d7da;
            color: #8a4b55 !important;
            padding: 10px 20px;
            border-radius: 8px;
        }

        .dashboard-btn {
            background: #d4af37;
            color: white !important;
            padding: 10px 20px;
            border-radius: 8px;
        }

        .logout-btn {
            color: #b00020 !important;
        }

        /* HERO */

        .hero {
            min-height: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(
                135deg,
                #fff8f2,
                #fff1e6,
                #fff8dc
            );
        }

        .hero-content {
            max-width: 800px;
        }

        .hero h1 {
            font-size: 50px;
            color: #b8860b;
            margin-bottom: 20px;
        }

        .hero h2 {
            font-size: 28px;
            color: #555;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 18px;
            line-height: 1.7;
            color: #666;
            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .primary-btn {
            background: #d4af37;
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
        }

        .secondary-btn {
            background: #f8d7da;
            color: #8a4b55;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
        }

        /* EVENT TYPES */

        .section {
            padding: 60px 7%;
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h2 {
            font-size: 32px;
            color: #b8860b;
        }

        .section-title p {
            color: #777;
            margin-top: 10px;
        }

        .events {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            transition: 0.3s;
        }

        .event-card:hover {
            transform: translateY(-5px);
        }

        .event-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .event-card h3 {
            color: #555;
            margin-bottom: 8px;
        }

        .event-card p {
            color: #888;
            font-size: 14px;
        }

        /* FOOTER */

        footer {
            background: #fff;
            border-top: 1px solid #eee;
            text-align: center;
            padding: 25px;
            color: #777;
        }

        /* MOBILE */

        @media(max-width: 700px) {

            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .hero h1 {
                font-size: 38px;
            }

            .hero h2 {
                font-size: 22px;
            }

            .hero-buttons {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>


<!-- NAVBAR -->

<nav class="navbar">

    <div class="logo">
        Event Planner
    </div>

    <div class="nav-links">

        <a href="index.php">
            Home
        </a>

        <a href="events.php">
            Events
        </a>

        <?php if ($isLoggedIn): ?>

            <span style="color:#b8860b;">
                Hi, <?= htmlspecialchars($userName) ?>
            </span>

            <a class="dashboard-btn" href="dashboard.php">
                Dashboard
            </a>

            <a class="logout-btn" href="logout.php">
                Logout
            </a>

        <?php else: ?>

            <a class="login-btn" href="login.php">
                Login
            </a>

            <a class="register-btn" href="register.php">
                Register
            </a>

        <?php endif; ?>

    </div>

</nav>


<!-- HERO -->

<section class="hero">

    <div class="hero-content">

        <?php if ($isLoggedIn): ?>

            <h1>Welcome, <?= htmlspecialchars($userName) ?>! 🎉</h1>

        <?php else: ?>

            <h1>Plan Your Perfect Event ✨</h1>

        <?php endif; ?>

        <h2>Everything You Need in One Place</h2>

        <p>
            Find trusted event service providers, explore packages,
            compare services and make your event memorable with
            Event Planner.
        </p>

        <div class="hero-buttons">

            <a href="events.php" class="primary-btn">
                Explore Events
            </a>

            <?php if (!$isLoggedIn): ?>

                <a href="register.php" class="secondary-btn">
                    Create Account
                </a>

            <?php else: ?>

                <a href="dashboard.php" class="secondary-btn">
                    Go to Dashboard
                </a>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- EVENT TYPES -->

<section class="section">

    <div class="section-title">

        <h2>Popular Events</h2>

        <p>
            Choose the type of event you want to organize
        </p>

    </div>


    <div class="events">


        <div class="event-card">

            <div class="event-icon">
                💍
            </div>

            <h3>Wedding</h3>

            <p>
                Plan your dream wedding
            </p>

        </div>


        <div class="event-card">

            <div class="event-icon">
                🎂
            </div>

            <h3>Birthday</h3>

            <p>
                Make birthdays special
            </p>

        </div>


        <div class="event-card">

            <div class="event-icon">
                💑
            </div>

            <h3>Engagement</h3>

            <p>
                Celebrate your engagement
            </p>

        </div>


        <div class="event-card">

            <div class="event-icon">
                👶
            </div>

            <h3>Baby Shower</h3>

            <p>
                Celebrate the new beginning
            </p>

        </div>


        <div class="event-card">

            <div class="event-icon">
                🏢
            </div>

            <h3>Corporate Event</h3>

            <p>
                Organize professional events
            </p>

        </div>


        <div class="event-card">

            <div class="event-icon">
                🎓
            </div>

            <h3>Graduation</h3>

            <p>
                Celebrate your achievement
            </p>

        </div>


    </div>

</section>


<!-- FOOTER -->

<footer>

    © <?= date("Y") ?> Event Planner.
    All Rights Reserved.

</footer>


</body>

</html>