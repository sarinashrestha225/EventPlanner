<?php
session_start();
require_once "database.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["user_name"] ?? "";
$userRole = $_SESSION["user_role"] ?? "";

$events = [];
$packages = [];
$services = [];

$eventIcons = [
    "Wedding" => "💍",
    "Birthday" => "🎂",
    "Engagement" => "💑",
    "Anniversary" => "🎉",
    "Baby Shower" => "👶",
    "Corporate Event" => "🏢",
    "Party" => "🎊",
    "Graduation" => "🎓",
    "Religious Event" => "🙏",
    "Other Event" => "✨"
];

$eventResult = $conn->query("
    SELECT id, event_name, description, icon
    FROM event_types
    WHERE status = 'active'
    ORDER BY id ASC
");

if ($eventResult) {
    while ($row = $eventResult->fetch_assoc()) {
        $events[] = $row;
    }
}

$packageResult = $conn->query("
    SELECT
        p.id,
        p.event_id,
        p.package_name,
        p.experience_level,
        p.description,
        p.price,
        p.image,
        p.min_budget,
        p.max_budget,
        p.min_price,
        p.max_price,
        et.event_name
    FROM packages p
    LEFT JOIN event_types et
        ON et.id = p.event_id
    WHERE p.status = 'active'
    ORDER BY
        p.event_id ASC,
        CASE
            WHEN p.package_name = 'Basic' THEN 1
            WHEN p.package_name = 'Silver' THEN 2
            WHEN p.package_name = 'Premium' THEN 3
            WHEN p.package_name = 'Luxury' THEN 4
            WHEN p.package_name = 'VIP' THEN 5
            ELSE 6
        END ASC
");

if ($packageResult) {
    while ($row = $packageResult->fetch_assoc()) {
        $packages[] = $row;
    }
}

$serviceResult = $conn->query("
    SELECT
        s.id,
        s.service_name,
        s.description,
        s.category,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.availability,
        s.image,
        s.service_image,
        s.provider_id,
        p.name AS provider_name
    FROM services s
    LEFT JOIN providers p
        ON p.id = s.provider_id
    WHERE s.status = 'active'
    ORDER BY s.id DESC
    LIMIT 12
");

if ($serviceResult) {
    while ($row = $serviceResult->fetch_assoc()) {
        $services[] = $row;
    }
}

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function getEventIcon($eventName, $databaseIcon = "")
{
    global $eventIcons;

    if (!empty($databaseIcon)) {
        return $databaseIcon;
    }

    return $eventIcons[$eventName] ?? "🎉";
}

function getPackageIcon($packageName)
{
    switch (strtolower(trim($packageName))) {
        case "basic":
            return "🌸";
        case "silver":
            return "🥈";
        case "premium":
            return "💎";
        case "luxury":
            return "👑";
        case "vip":
            return "✨";
        default:
            return "📦";
    }
}

function getPackagePrice($package)
{
    $min = (float)($package["min_price"] ?? 0);
    $max = (float)($package["max_price"] ?? 0);
    $price = (float)($package["price"] ?? 0);

    if ($min > 0 && $max > 0 && $min != $max) {
        return "Rs. " . number_format($min, 0)
            . " - Rs. " . number_format($max, 0);
    }

    if ($min > 0) {
        return "From Rs. " . number_format($min, 0);
    }

    if ($price > 0) {
        return "Rs. " . number_format($price, 0);
    }

    return "View Price";
}

function getServiceIcon($name)
{
    $name = strtolower($name);

    if (strpos($name, "catering") !== false) return "🍽️";
    if (strpos($name, "makeup") !== false) return "💄";
    if (strpos($name, "photo") !== false) return "📸";
    if (strpos($name, "video") !== false) return "🎥";
    if (strpos($name, "dj") !== false) return "🎧";
    if (strpos($name, "decoration") !== false) return "🎀";
    if (strpos($name, "transport") !== false) return "🚗";
    if (strpos($name, "venue") !== false) return "🏛️";
    if (strpos($name, "chef") !== false) return "👨‍🍳";
    if (strpos($name, "pandit") !== false) return "🙏";
    if (strpos($name, "clean") !== false) return "🧹";

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
        return "Rs. " . number_format($min, 0)
            . " - Rs. " . number_format($max, 0);
    }

    if ($min > 0) {
        return "From Rs. " . number_format($min, 0);
    }

    if ($max > 0) {
        return "Up to Rs. " . number_format($max, 0);
    }

    return "Contact Provider";
}

function getImagePath($image)
{
    if (empty($image)) {
        return "";
    }

    return trim($image);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EventPlanner | Plan. Book. Celebrate.</title>

    <meta
        name="description"
        content="Find trusted event service providers, explore event packages and plan your perfect celebration with EventPlanner."
    >

    <style>

        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap');

        :root {
            --navy: #071725;
            --navy-light: #102b3c;
            --pink: #ed3e91;
            --pink-dark: #d82d7e;
            --pink-soft: #fff0f7;
            --gold: #e8b94e;
            --gold-dark: #b98920;
            --cream: #fffaf6;
            --white: #ffffff;
            --text: #202532;
            --muted: #747986;
            --border: #f0e2e9;
            --shadow: 0 8px 30px rgba(7, 23, 37, 0.07);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: 'DM Sans', Arial, sans-serif;
            line-height: 1.6;
        }

        a {
            transition: all 0.25s ease;
        }

        img {
            max-width: 100%;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        .navbar {
            min-height: 78px;
            padding: 13px 6%;
            background: var(--navy);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 22px rgba(0, 0, 0, 0.16);
        }

        .logo {
            color: white;
            text-decoration: none;
            font-size: 25px;
            font-weight: 700;
            letter-spacing: -0.7px;
            white-space: nowrap;
        }

        .logo-icon {
            color: var(--pink);
            margin-right: 5px;
        }

        .logo span {
            color: #f15b9e;
            font-family: 'Playfair Display', Georgia, serif;
            font-style: italic;
        }

        .logo small {
            display: block;
            color: #9eafbd;
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 2px;
            margin-left: 31px;
            margin-top: -5px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #e5eaf0;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 13px;
            border-radius: 7px;
        }

        .nav-links a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-links .active-link {
            color: #ff65ac;
            border-bottom: 2px solid var(--pink);
            border-radius: 0;
        }

        .nav-links .login-btn,
        .nav-links .register-btn {
            border-radius: 25px;
            padding: 10px 20px;
        }

        .login-btn {
            border: 1px solid #82909c;
            background: transparent;
            color: white !important;
        }

        .login-btn:hover {
            background: white !important;
            color: var(--navy) !important;
        }

        .register-btn {
            background: var(--pink);
            color: white !important;
            border: 1px solid var(--pink);
        }

        .register-btn:hover {
            background: var(--pink-dark);
            transform: translateY(-2px);
        }

        .hero {
            min-height: 570px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 80px 8%;
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(
                    90deg,
                    rgba(5, 18, 29, 0.96) 0%,
                    rgba(5, 18, 29, 0.84) 40%,
                    rgba(5, 18, 29, 0.27) 100%
                ),
                url("assets/event_planner_hero.png") center/cover no-repeat;
        }

        .hero-content {
            max-width: 650px;
            position: relative;
            z-index: 1;
        }

        .hero-tag {
            display: inline-block;
            color: #ffd37a;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .hero h1 {
            color: white;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(39px, 5vw, 68px);
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 23px;
        }

        .hero h1 span {
            display: block;
            color: #ff5da6;
        }

        .hero p {
            max-width: 560px;
            color: #e4e9ee;
            font-size: 16px;
            line-height: 1.9;
            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-buttons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 14px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .explore-btn {
            background: var(--pink);
            color: white;
            box-shadow: 0 8px 22px rgba(237, 62, 145, 0.3);
        }

        .explore-btn:hover {
            background: var(--pink-dark);
            transform: translateY(-3px);
        }

        .hero-secondary-btn {
            background: rgba(255, 255, 255, 0.07);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .hero-secondary-btn:hover {
            background: white;
            color: var(--navy);
        }

        .hero-features {
            position: absolute;
            right: 7%;
            top: 50%;
            transform: translateY(-50%);
            width: 225px;
            display: grid;
            gap: 15px;
        }

        .hero-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: rgba(7, 23, 37, 0.56);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            backdrop-filter: blur(6px);
        }

        .hero-feature-icon {
            min-width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(237, 62, 145, 0.2);
            color: #ff78b8;
            font-size: 20px;
        }

        .hero-feature strong {
            display: block;
            color: white;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .hero-feature small {
            display: block;
            color: #c2cbd4;
            font-size: 10px;
        }

        .section {
            padding: 78px 7%;
        }

        .section-title {
            max-width: 720px;
            margin: 0 auto 42px;
            text-align: center;
        }

        .section-label {
            display: block;
            color: var(--pink);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 9px;
        }

        .section-title h2 {
            color: var(--navy);
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(29px, 3vw, 40px);
            line-height: 1.25;
            margin-bottom: 12px;
        }

        .section-title p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .stats-strip {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 24px 7%;
        }

        .stats-grid {
            max-width: 1100px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            border-right: 1px solid var(--border);
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-number {
            color: var(--pink);
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 27px;
            font-weight: 700;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
        }

        .events-section {
            background: var(--cream);
        }

        .events {
            max-width: 1250px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(7, 23, 37, 0.13);
            border-color: #f2a9ca;
        }

        .event-icon {
            width: 78px;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            background: var(--pink-soft);
            border-radius: 50%;
            font-size: 38px;
        }

        .event-card h3 {
            color: var(--navy);
            font-size: 17px;
            margin-bottom: 10px;
        }

        .event-card p {
            min-height: 65px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .event-card a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 17px;
            color: var(--pink);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .event-card a:hover {
            color: var(--navy);
            gap: 11px;
        }

        .packages-section {
            background: var(--pink-soft);
        }

        .package-grid {
            max-width: 1250px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(255px, 1fr));
            gap: 25px;
        }

        .package-card {
            position: relative;
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: 0.3s ease;
        }

        .package-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 16px 35px rgba(7, 23, 37, 0.13);
        }

        .package-image,
        .package-no-image {
            width: 100%;
            height: 185px;
        }

        .package-image {
            display: block;
            object-fit: cover;
        }

        .package-no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff0f7, #fff4d5);
            font-size: 65px;
        }

        .package-content {
            padding: 24px;
        }

        .event-name {
            display: inline-block;
            background: #fff4d8;
            color: #97701b;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .package-name {
            color: var(--navy);
            font-size: 23px;
            line-height: 1.3;
            margin-bottom: 7px;
        }

        .package-level {
            color: var(--pink);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .package-description {
            min-height: 68px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .package-price {
            color: var(--pink);
            font-size: 19px;
            font-weight: 700;
            line-height: 1.5;
            margin: 17px 0 8px;
        }

        .package-budget {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 17px;
        }

        .view-package-btn {
            display: block;
            text-align: center;
            padding: 12px 15px;
            background: var(--navy);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .view-package-btn:hover {
            background: var(--pink);
        }

        .services-section {
            background: var(--cream);
        }

        .service-grid {
            max-width: 1250px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 25px;
        }

        .service-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 15px 35px rgba(7, 23, 37, 0.12);
        }

        .service-image,
        .no-image {
            width: 100%;
            height: 190px;
        }

        .service-image {
            display: block;
            object-fit: cover;
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff0f7, #fff4d5);
            font-size: 65px;
        }

        .service-content {
            padding: 23px;
        }

        .service-card h3 {
            color: var(--navy);
            font-size: 20px;
            line-height: 1.4;
            margin-bottom: 9px;
        }

        .provider {
            color: var(--pink);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .service-card p {
            min-height: 48px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .price {
            color: var(--pink);
            font-size: 19px;
            font-weight: 700;
        }

        .unit {
            color: var(--muted);
            font-size: 12px;
            margin-top: 5px;
        }

        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 18px;
        }

        .book-btn,
        .portfolio-btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .book-btn {
            background: var(--pink);
            color: white;
        }

        .book-btn:hover {
            background: var(--navy);
        }

        .portfolio-btn {
            background: #fff4d8;
            border: 1px solid #ead18b;
            color: #8b691a;
        }

        .portfolio-btn:hover {
            background: #f9e7b0;
        }

        .why-section {
            background: var(--pink-soft);
        }

        .why-grid {
            max-width: 1150px;
            margin: auto;
            display: grid;
            grid-template-columns: 1.1fr 2fr;
            gap: 45px;
            align-items: center;
        }

        .why-intro h2 {
            color: var(--navy);
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 38px;
            line-height: 1.25;
            margin-bottom: 15px;
        }

        .why-intro p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.9;
            margin-bottom: 23px;
        }

        .why-btn {
            display: inline-block;
            padding: 12px 22px;
            background: var(--pink);
            color: white;
            border-radius: 25px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .why-btn:hover {
            background: var(--navy);
        }

        .why-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .why-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
        }

        .why-icon {
            color: var(--pink);
            font-size: 30px;
            margin-bottom: 10px;
        }

        .why-card h3 {
            color: var(--navy);
            font-size: 16px;
            margin-bottom: 6px;
        }

        .why-card p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .portfolio-section {
            background: var(--cream);
            text-align: center;
        }

        .portfolio-preview {
            max-width: 1050px;
            min-height: 280px;
            margin: auto;
            padding: 60px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background:
                linear-gradient(
                    100deg,
                    rgba(7, 23, 37, 0.94),
                    rgba(7, 23, 37, 0.55)
                ),
                url("assets/event_planner_hero.png") center/cover;
            box-shadow: 0 10px 30px rgba(7, 23, 37, 0.12);
        }

        .portfolio-preview h2 {
            color: white;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
            margin-bottom: 14px;
        }

        .portfolio-preview p {
            max-width: 650px;
            color: #e9edf1;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 25px;
        }

        .view-portfolio {
            display: inline-block;
            padding: 13px 25px;
            border-radius: 25px;
            background: var(--pink);
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .view-portfolio:hover {
            background: white;
            color: var(--navy);
        }

        .steps-section {
            background: #f7f8fc;
        }

        .steps {
            max-width: 1100px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .step {
            position: relative;
            text-align: center;
            padding: 20px;
        }

        .step-number {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            background: var(--pink);
            color: white;
            border-radius: 50%;
            font-size: 25px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(237, 62, 145, 0.25);
        }

        .step h3 {
            color: var(--navy);
            font-size: 18px;
            margin-bottom: 8px;
        }

        .step p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .cta {
            padding: 78px 20px;
            text-align: center;
            background: linear-gradient(110deg, #f5b2d0, #f7d77d);
        }

        .cta h2 {
            color: var(--navy);
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(29px, 4vw, 42px);
            line-height: 1.25;
            margin-bottom: 13px;
        }

        .cta p {
            color: #4f4050;
            font-size: 15px;
            margin-bottom: 27px;
        }

        .cta a {
            display: inline-block;
            padding: 14px 28px;
            background: var(--navy);
            color: white;
            text-decoration: none;
            border-radius: 28px;
            font-size: 13px;
            font-weight: 700;
        }

        .cta a:hover {
            background: var(--pink);
            transform: translateY(-2px);
        }

        .empty {
            max-width: 800px;
            margin: auto;
            padding: 45px 20px;
            text-align: center;
            color: var(--muted);
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
        }

        footer {
            background: var(--navy);
            color: #c5d0da;
            padding: 48px 7% 24px;
            font-size: 13px;
        }

        .footer-grid {
            max-width: 1250px;
            margin: auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 35px;
            padding-bottom: 35px;
        }

        .footer-brand .logo {
            display: inline-block;
            margin-bottom: 15px;
        }

        .footer-brand p {
            max-width: 280px;
            color: #aab8c4;
            font-size: 12px;
            line-height: 1.8;
        }

        .footer-heading {
            color: white;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .footer-links a {
            color: #b9c5d0;
            text-decoration: none;
            font-size: 12px;
        }

        .footer-links a:hover {
            color: #ff6bad;
        }

        .contact-item {
            color: #b9c5d0;
            font-size: 12px;
            margin-bottom: 9px;
            overflow-wrap: anywhere;
        }

        .contact-item a {
            color: #b9c5d0;
            text-decoration: none;
        }

        .contact-item a:hover {
            color: #ff6bad;
        }

        .footer-bottom {
            max-width: 1250px;
            margin: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.13);
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            color: #8fa1b1;
            font-size: 11px;
        }

        .footer-bottom a {
            color: #b9c5d0;
            text-decoration: none;
        }

        @media (max-width: 1100px) {

            .hero-features {
                right: 4%;
                width: 195px;
            }

            .hero {
                padding-right: 29%;
            }

            .why-grid {
                grid-template-columns: 1fr;
            }

            .why-intro {
                text-align: center;
                max-width: 650px;
                margin: auto;
            }

            .why-intro p {
                margin-bottom: 20px;
            }

        }

        @media (max-width: 850px) {

            .navbar {
                flex-direction: column;
                padding: 15px 4%;
                gap: 12px;
            }

            .nav-links {
                gap: 3px;
            }

            .nav-links a {
                padding: 8px 9px;
                font-size: 12px;
            }

            .hero {
                min-height: 550px;
                padding: 70px 7%;
                background-position: center;
            }

            .hero-features {
                display: none;
            }

            .hero {
                padding-right: 7%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-item:nth-child(2) {
                border-right: none;
            }

            .steps {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .logo {
                font-size: 23px;
            }

            .logo small {
                font-size: 8px;
            }

            .nav-links {
                width: 100%;
            }

            .nav-links a {
                padding: 7px 8px;
                font-size: 11px;
            }

            .nav-links .login-btn,
            .nav-links .register-btn {
                padding: 8px 13px;
            }

            .hero {
                min-height: 510px;
                padding: 65px 6%;
            }

            .hero h1 {
                font-size: 39px;
            }

            .hero p {
                font-size: 14px;
                line-height: 1.8;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-buttons a {
                padding: 12px 20px;
            }

            .section {
                padding: 55px 5%;
            }

            .section-title h2 {
                font-size: 29px;
            }

            .section-title p {
                font-size: 13px;
            }

            .events,
            .package-grid,
            .service-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-item {
                border-right: none;
            }

            .stat-number {
                font-size: 24px;
            }

            .why-features {
                grid-template-columns: 1fr;
            }

            .why-intro h2 {
                font-size: 31px;
            }

            .portfolio-preview {
                padding: 40px 20px;
            }

            .portfolio-preview h2 {
                font-size: 27px;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }

        }

    </style>

</head>

<body>

<nav class="navbar">

    <a href="index.php" class="logo">
        <span class="logo-icon">▣</span>
        Event<span>Planner</span>
        <small>PLAN • BOOK • CELEBRATE</small>
    </a>

    <div class="nav-links">

        <a href="index.php" class="active-link">Home</a>

        <a href="#events">Events</a>

        <a href="#packages">Packages</a>

        <a href="#services">Services</a>

        <a href="portfolio.php">Gallery</a>

        <a href="#contact">Contact Us</a>

        <?php if ($isLoggedIn): ?>

            <?php if ($userRole === "provider"): ?>

                <a href="provider/dashboard.php">
                    Dashboard
                </a>

            <?php elseif ($userRole === "customer"): ?>

                <a href="customer/dashboard.php">
                    Dashboard
                </a>

            <?php endif; ?>

            <a href="logout.php" class="login-btn">
                Logout
            </a>

        <?php else: ?>

            <a href="login.php" class="login-btn">
                Login
            </a>

            <a href="register.php" class="register-btn">
                Register
            </a>

        <?php endif; ?>

    </div>

</nav>

<section class="hero">

    <div class="hero-content">

        <div class="hero-tag">
            ✦ Your Dream Event Starts Here
        </div>

        <h1>
            Plan Your
            <span>Perfect Event</span>
        </h1>

        <p>
            From beautiful weddings to unforgettable celebrations,
            discover trusted event service providers, explore complete
            packages and create moments that last a lifetime.
        </p>

        <div class="hero-buttons">

            <a href="#events" class="explore-btn">
                Explore Events
                <span>→</span>
            </a>

            <a href="#packages" class="hero-secondary-btn">
                View Packages
                <span>↗</span>
            </a>

        </div>

    </div>

    <div class="hero-features">

        <div class="hero-feature">

            <div class="hero-feature-icon">
                ♡
            </div>

            <div>
                <strong>Trusted Providers</strong>
                <small>Verified professionals</small>
            </div>

        </div>

        <div class="hero-feature">

            <div class="hero-feature-icon">
                ✓
            </div>

            <div>
                <strong>Easy Booking</strong>
                <small>Quick and simple</small>
            </div>

        </div>

        <div class="hero-feature">

            <div class="hero-feature-icon">
                ◈
            </div>

            <div>
                <strong>Multiple Services</strong>
                <small>Everything in one place</small>
            </div>

        </div>

        <div class="hero-feature">

            <div class="hero-feature-icon">
                ☎
            </div>

            <div>
                <strong>24/7 Support</strong>
                <small>We're here to help</small>
            </div>

        </div>

    </div>

</section>

<section class="stats-strip">

    <div class="stats-grid">

        <div class="stat-item">

            <div class="stat-number">
                <?= count($events) ?>+
            </div>

            <div class="stat-label">
                Event Categories
            </div>

        </div>

        <div class="stat-item">

            <div class="stat-number">
                <?= count($packages) ?>+
            </div>

            <div class="stat-label">
                Event Packages
            </div>

        </div>

        <div class="stat-item">

            <div class="stat-number">
                <?= count($services) ?>+
            </div>

            <div class="stat-label">
                Available Services
            </div>

        </div>

        <div class="stat-item">

            <div class="stat-number">
                100%
            </div>

            <div class="stat-label">
                Easy Planning
            </div>

        </div>

    </div>

</section>

<section class="section events-section" id="events">

    <div class="section-title">

        <span class="section-label">
            Find Your Celebration
        </span>

        <h2>
            Choose Your Event
        </h2>

        <p>
            Whatever the occasion, we have the services and packages
            to make your special day unforgettable.
        </p>

    </div>

    <?php if (empty($events)): ?>

        <div class="empty">
            No events are available right now.
        </div>

    <?php else: ?>

        <div class="events">

            <?php foreach ($events as $event): ?>

                <?php

                $eventId = (int)$event["id"];

                $eventName = $event["event_name"];

                $icon = getEventIcon(
                    $eventName,
                    $event["icon"] ?? ""
                );

                ?>

                <div class="event-card">

                    <div class="event-icon">
                        <?= e($icon) ?>
                    </div>

                    <h3>
                        <?= e($eventName) ?>
                    </h3>

                    <p>
                        <?= e(
                            $event["description"]
                            ?: "Plan your perfect event with our professional services."
                        ) ?>
                    </p>

                    <a href="customer/services.php?event_id=<?= $eventId ?>">
                        Explore Services →
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<section class="section packages-section" id="packages">

    <div class="section-title">

        <span class="section-label">
            Perfect Plans For Every Budget
        </span>

        <h2>
            Our Event Packages
        </h2>

        <p>
            Choose from Basic, Silver, Premium, Luxury and VIP packages.
            Find the perfect plan for your celebration.
        </p>

    </div>

    <?php if (empty($packages)): ?>

        <div class="empty">
            No active packages are available right now.
        </div>

    <?php else: ?>

        <div class="package-grid">

            <?php foreach ($packages as $package): ?>

                <?php

                $packageId = (int)$package["id"];

                $packageName = $package["package_name"];

                $packageIcon = getPackageIcon($packageName);

                $packageImage = getImagePath(
                    $package["image"] ?? ""
                );

                $minBudget = (float)($package["min_budget"] ?? 0);

                $maxBudget = (float)($package["max_budget"] ?? 0);

                ?>

                <div class="package-card">

                    <?php if ($packageImage !== ""): ?>

                        <img
                            src="<?= e($packageImage) ?>"
                            class="package-image"
                            alt="<?= e($packageName . " Package") ?>"
                            loading="lazy"
                        >

                    <?php else: ?>

                        <div class="package-no-image">
                            <?= e($packageIcon) ?>
                        </div>

                    <?php endif; ?>

                    <div class="package-content">

                        <?php if (!empty($package["event_name"])): ?>

                            <div class="event-name">
                                <?= e($package["event_name"]) ?>
                            </div>

                        <?php endif; ?>

                        <h3 class="package-name">

                            <?= e($packageIcon) ?>

                            <?= e($packageName) ?>

                        </h3>

                        <?php if (!empty($package["experience_level"])): ?>

                            <div class="package-level">
                                ✦ <?= e($package["experience_level"]) ?>
                            </div>

                        <?php endif; ?>

                        <p class="package-description">

                            <?= e(
                                $package["description"]
                                ?: "Complete event package with professional services."
                            ) ?>

                        </p>

                        <div class="package-price">

                            <?= e(getPackagePrice($package)) ?>

                        </div>

                        <?php if ($minBudget > 0 || $maxBudget > 0): ?>

                            <div class="package-budget">

                                Budget:

                                <?php if ($minBudget > 0 && $maxBudget > 0): ?>

                                    Rs. <?= number_format($minBudget, 0) ?>
                                    -
                                    Rs. <?= number_format($maxBudget, 0) ?>

                                <?php elseif ($minBudget > 0): ?>

                                    From Rs. <?= number_format($minBudget, 0) ?>

                                <?php else: ?>

                                    Up to Rs. <?= number_format($maxBudget, 0) ?>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                        <a
                            href="customer/view_package.php?package_id=<?= $packageId ?>"
                            class="view-package-btn"
                        >
                            View Package & Guest Prices →
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<section class="section services-section" id="services">

    <div class="section-title">

        <span class="section-label">
            Everything You Need
        </span>

        <h2>
            Popular Event Services
        </h2>

        <p>
            Explore individual services and prices without logging in.
            Login is required when you want to book.
        </p>

    </div>

    <?php if (empty($services)): ?>

        <div class="empty">
            No active services are available right now.
        </div>

    <?php else: ?>

        <div class="service-grid">

            <?php foreach ($services as $service): ?>

                <?php

                $serviceIcon = getServiceIcon(
                    $service["service_name"]
                );

                $serviceImage = "";

                if (!empty($service["service_image"])) {

                    $serviceImage = getImagePath(
                        $service["service_image"]
                    );

                } elseif (!empty($service["image"])) {

                    $serviceImage = getImagePath(
                        $service["image"]
                    );

                }

                ?>

                <div class="service-card">

                    <?php if ($serviceImage !== ""): ?>

                        <img
                            src="<?= e($serviceImage) ?>"
                            class="service-image"
                            alt="<?= e($service["service_name"]) ?>"
                            loading="lazy"
                        >

                    <?php else: ?>

                        <div class="no-image">
                            <?= e($serviceIcon) ?>
                        </div>

                    <?php endif; ?>

                    <div class="service-content">

                        <h3>

                            <?= e($serviceIcon) ?>

                            <?= e($service["service_name"]) ?>

                        </h3>

                        <?php if (!empty($service["provider_name"])): ?>

                            <div class="provider">

                                ✦ Provider:

                                <?= e($service["provider_name"]) ?>

                            </div>

                        <?php endif; ?>

                        <p>

                            <?= e(
                                $service["description"]
                                ?: "Professional event service for your special occasion."
                            ) ?>

                        </p>

                        <div class="price">

                            <?= e(getServicePrice($service)) ?>

                        </div>

                        <?php if (!empty($service["unit"])): ?>

                            <div class="unit">

                                Per <?= e($service["unit"]) ?>

                            </div>

                        <?php endif; ?>

                        <div class="card-actions">

                            <a
                                href="customer/book_service.php?service_id=<?= (int)$service["id"] ?>"
                                class="book-btn"
                            >
                                Book Now →
                            </a>

                            <?php if ((int)$service["provider_id"] > 0): ?>

                                <a
                                    href="portfolio.php?provider_id=<?= (int)$service["provider_id"] ?>"
                                    class="portfolio-btn"
                                >
                                    Portfolio
                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<section class="section why-section">

    <div class="why-grid">

        <div class="why-intro">

            <span class="section-label">
                The EventPlanner Difference
            </span>

            <h2>
                Why Choose Us?
            </h2>

            <p>
                We are committed to making your event planning experience
                simple, enjoyable and stress-free. Discover trusted providers,
                compare options and plan everything in one place.
            </p>

            <a href="#services" class="why-btn">
                Explore Services →
            </a>

        </div>

        <div class="why-features">

            <div class="why-card">

                <div class="why-icon">
                    ♡
                </div>

                <h3>
                    Professional Providers
                </h3>

                <p>
                    Discover event service providers and explore their work
                    before choosing your preferred service.
                </p>

            </div>

            <div class="why-card">

                <div class="why-icon">
                    ▣
                </div>

                <h3>
                    Easy Online Booking
                </h3>

                <p>
                    Browse services, choose your event and send a booking
                    request through a simple process.
                </p>

            </div>

            <div class="why-card">

                <div class="why-icon">
                    ◈
                </div>

                <h3>
                    Multiple Event Services
                </h3>

                <p>
                    Photography, catering, decoration, makeup, venues
                    and many more services in one place.
                </p>

            </div>

            <div class="why-card">

                <div class="why-icon">
                    ☎
                </div>

                <h3>
                    Friendly Support
                </h3>

                <p>
                    Get help when you need it and enjoy a smoother
                    event planning experience.
                </p>

            </div>

        </div>

    </div>

</section>

<section class="section portfolio-section">

    <div class="portfolio-preview">

        <span class="section-label">
            Inspiration For Your Big Day
        </span>

        <h2>
            Explore Beautiful Event Portfolios
        </h2>

        <p>
            See real photos and videos uploaded by event service providers.
            Explore their previous work and find inspiration for your own event.
        </p>

        <a href="portfolio.php" class="view-portfolio">
            View All Portfolios →
        </a>

    </div>

</section>

<section class="section steps-section">

    <div class="section-title">

        <span class="section-label">
            Simple & Stress-Free
        </span>

        <h2>
            How EventPlanner Works
        </h2>

        <p>
            Plan your special day in just a few simple steps.
        </p>

    </div>

    <div class="steps">

        <div class="step">

            <div class="step-number">
                1
            </div>

            <h3>
                Explore
            </h3>

            <p>
                Browse events, packages, services and prices.
            </p>

        </div>

        <div class="step">

            <div class="step-number">
                2
            </div>

            <h3>
                Compare
            </h3>

            <p>
                Compare packages, guest prices, providers and portfolios.
            </p>

        </div>

        <div class="step">

            <div class="step-number">
                3
            </div>

            <h3>
                Select
            </h3>

            <p>
                Choose your preferred event, package and services.
            </p>

        </div>

        <div class="step">

            <div class="step-number">
                4
            </div>

            <h3>
                Book
            </h3>

            <p>
                Login or register and send your booking request.
            </p>

        </div>

    </div>

</section>

<section class="cta">

    <h2>
        Ready to Plan Your Perfect Event?
    </h2>

    <p>
        Explore packages and services today. Your unforgettable celebration
        starts here.
    </p>

    <?php if ($isLoggedIn): ?>

        <?php if ($userRole === "provider"): ?>

            <a href="provider/dashboard.php">
                Go to Provider Dashboard →
            </a>

        <?php elseif ($userRole === "customer"): ?>

            <a href="customer/dashboard.php">
                Go to Customer Dashboard →
            </a>

        <?php else: ?>

            <a href="#packages">
                Explore Packages →
            </a>

        <?php endif; ?>

    <?php else: ?>

        <a href="register.php">
            Create Customer Account →
        </a>

    <?php endif; ?>

</section>

<footer id="contact">

    <div class="footer-grid">

        <div class="footer-brand">

            <a href="index.php" class="logo">

                <span class="logo-icon">▣</span>

                Event<span>Planner</span>

            </a>

            <p>
                We help you create unforgettable moments with trusted
                event providers, beautiful packages and professional services.
            </p>

        </div>

        <div>

            <div class="footer-heading">
                Quick Links
            </div>

            <div class="footer-links">

                <a href="index.php">Home</a>

                <a href="#events">Events</a>

                <a href="#packages">Packages</a>

                <a href="#services">Services</a>

                <a href="portfolio.php">Gallery</a>

            </div>

        </div>

        <div>

            <div class="footer-heading">
                Our Services
            </div>

            <div class="footer-links">

                <a href="#events">Wedding Planning</a>

                <a href="#events">Birthday Events</a>

                <a href="#events">Corporate Events</a>

                <a href="#events">Engagement</a>

                <a href="#events">Outdoor Events</a>

            </div>

        </div>

        <div>

            <div class="footer-heading">
                Contact Us
            </div>

            <div class="contact-item">
                ☎ Phone / WhatsApp:
                <a href="tel:+9779826614120">
                    +977 9826614120
                </a>
            </div>

            <div class="contact-item">
                ✉ Email:
                <a href="mailto:sarinashrestha225@gmail.com">
                    sarinashrestha225@gmail.com
                </a>
            </div>

            <div class="contact-item">
                ⌖ Satungal, Kathmandu, Nepal
            </div>

        </div>

    </div>

    <div class="footer-bottom">

        <div>
            © <?= date("Y") ?> EventPlanner. All Rights Reserved.
        </div>

        <div>

            <a href="index.php">Privacy Policy</a>

            &nbsp; | &nbsp;

            <a href="index.php">Terms & Conditions</a>

        </div>

    </div>

</footer>

</body>

</html>