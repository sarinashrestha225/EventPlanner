<?php

session_start();

require_once "database.php";

$services = [];

$sql = "
    SELECT
        s.id,
        s.service_name,
        s.description,
        s.category,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.image,
        s.service_image,
        s.provider_id,
        p.name AS provider_name
    FROM services s
    LEFT JOIN providers p
        ON p.id = s.provider_id
    WHERE s.status = 'active'
    ORDER BY s.service_name ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

function getServiceIcon($name)
{
    $name = strtolower($name);

    if (strpos($name, "catering") !== false) return "🍽️";
    if (strpos($name, "chef") !== false) return "👨‍🍳";
    if (strpos($name, "makeup") !== false) return "💄";
    if (strpos($name, "beauty") !== false) return "💄";
    if (strpos($name, "photo") !== false) return "📸";
    if (strpos($name, "video") !== false) return "🎥";
    if (strpos($name, "dj") !== false) return "🎧";
    if (strpos($name, "music") !== false) return "🎵";
    if (strpos($name, "decoration") !== false) return "🎀";
    if (strpos($name, "flower") !== false) return "💐";
    if (strpos($name, "transport") !== false) return "🚗";
    if (strpos($name, "venue") !== false) return "🏛️";
    if (strpos($name, "pandit") !== false) return "🙏";
    if (strpos($name, "priest") !== false) return "🙏";
    if (strpos($name, "clean") !== false) return "🧹";
    if (strpos($name, "security") !== false) return "🛡️";
    if (strpos($name, "lighting") !== false) return "💡";
    if (strpos($name, "cake") !== false) return "🎂";
    if (strpos($name, "transport") !== false) return "🚗";

    return "🎉";
}

function getServicePrice($service)
{
    $price = (float)($service["price"] ?? 0);
    $min = (float)($service["min_price"] ?? 0);
    $max = (float)($service["max_price"] ?? 0);

    if ($price > 0) {
        return "Rs. " . number_format($price, 0);
    }

    if ($min > 0 && $max > 0) {
        return "Rs. " . number_format($min, 0) .
               " - Rs. " . number_format($max, 0);
    }

    if ($min > 0) {
        return "From Rs. " . number_format($min, 0);
    }

    if ($max > 0) {
        return "Up to Rs. " . number_format($max, 0);
    }

    return "Contact Provider";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Services | Event Planner</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fffaf2;
            color: #4b3621;
        }

        .navbar {
            background: linear-gradient(
                90deg,
                #f8c8dc,
                #fff1dc,
                #f6d365
            );

            padding: 18px 45px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
            color: #6b4f00;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .nav-links a {
            text-decoration: none;
            color: #5c3b00;
            font-weight: bold;
        }

        .nav-links a:hover {
            color: #b8860b;
        }

        .login-btn {
            background: #b8860b;
            color: white !important;
            padding: 10px 18px;
            border-radius: 8px;
        }

        .hero {
            text-align: center;
            padding: 60px 20px 40px;
        }

        .hero h1 {
            font-size: 42px;
            color: #7a5200;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 17px;
            color: #705f4a;
            max-width: 700px;
            margin: auto;
            line-height: 1.6;
        }

        .container {
            width: 92%;
            max-width: 1250px;
            margin: 0 auto 60px;
        }

        .service-count {
            text-align: center;
            margin-bottom: 30px;
            color: #8b6508;
            font-weight: bold;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .service-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;

            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);

            transition: 0.3s;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .service-image {
            width: 100%;
            height: 210px;
            object-fit: cover;
            display: block;
        }

        .no-image {
            height: 210px;

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 65px;

            background: linear-gradient(
                135deg,
                #fff0f6,
                #fff4d6
            );
        }

        .service-content {
            padding: 22px;
        }

        .service-content h2 {
            font-size: 21px;
            color: #6b4f00;
            margin-bottom: 10px;
        }

        .category {
            display: inline-block;
            background: #f8d7e6;
            color: #7a3150;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .provider {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .description {
            color: #66594b;
            line-height: 1.5;
            min-height: 45px;
            margin-bottom: 15px;
        }

        .price {
            font-size: 19px;
            font-weight: bold;
            color: #b8860b;
            margin-bottom: 17px;
        }

        .book-btn {
            display: inline-block;
            width: 100%;
            text-align: center;

            background: #b8860b;
            color: white;

            text-decoration: none;

            padding: 11px 15px;

            border-radius: 8px;

            font-weight: bold;
        }

        .book-btn:hover {
            background: #956f08;
        }

        .empty {
            background: white;
            padding: 50px;
            text-align: center;
            border-radius: 15px;
            color: #777;
        }

        footer {
            background: #4b3621;
            color: white;
            text-align: center;
            padding: 22px;
            margin-top: 40px;
        }

        @media (max-width: 900px) {
            .service-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .navbar {
                padding: 15px 20px;
            }

            .nav-links {
                gap: 12px;
            }
        }

        @media (max-width: 600px) {
            .service-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .hero h1 {
                font-size: 32px;
            }
        }

    </style>

</head>

<body>

<header class="navbar">

    <div class="logo">
        🎉 Event Planner
    </div>

    <nav class="nav-links">

        <a href="index.php">Home</a>

        <a href="services.php">Services</a>

        <a href="portfolio.php">Portfolio</a>

        <a href="login.php" class="login-btn">Login</a>

        <a href="register.php">Register</a>

    </nav>

</header>

<section class="hero">

    <h1>Our Services</h1>

    <p>
        Explore professional event services for weddings,
        birthdays, engagements, parties, corporate events
        and other special occasions.
    </p>

</section>

<div class="container">

    <div class="service-count">

        <?= count($services) ?> Services Available

    </div>

    <?php if (empty($services)): ?>

        <div class="empty">
            No services are available right now.
        </div>

    <?php else: ?>

        <div class="service-grid">

            <?php foreach ($services as $service): ?>

                <?php

                $icon = getServiceIcon($service["service_name"]);

                $image = "";

                if (!empty($service["service_image"])) {
                    $image = $service["service_image"];
                } elseif (!empty($service["image"])) {
                    $image = $service["image"];
                }

                ?>

                <div class="service-card">

                    <?php if ($image !== ""): ?>

                        <img
                            src="<?= htmlspecialchars(
                                $image,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            class="service-image"
                            alt="<?= htmlspecialchars(
                                $service["service_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                    <?php else: ?>

                        <div class="no-image">
                            <?= $icon ?>
                        </div>

                    <?php endif; ?>

                    <div class="service-content">

                        <h2>
                            <?= $icon ?>
                            <?= htmlspecialchars(
                                $service["service_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </h2>

                        <?php if (!empty($service["category"])): ?>

                            <div class="category">
                                <?= htmlspecialchars(
                                    $service["category"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($service["provider_name"])): ?>

                            <div class="provider">
                                Provider:
                                <?= htmlspecialchars(
                                    $service["provider_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </div>

                        <?php endif; ?>

                        <p class="description">

                            <?= htmlspecialchars(
                                $service["description"] ?: "Professional event service.",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </p>

                        <div class="price">

                            <?= htmlspecialchars(
                                getServicePrice($service),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                            <?php if (!empty($service["unit"])): ?>

                                <span style="font-size:13px;color:#777;">
                                    / <?= htmlspecialchars(
                                        $service["unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <a href="login.php" class="book-btn">
                            Login to Book
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<footer>

    

</footer>

</body>

</html>