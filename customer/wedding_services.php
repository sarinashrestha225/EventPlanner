<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$customer_name = "Customer";

$user_sql = "
    SELECT name
    FROM users
    WHERE id = ?
    LIMIT 1
";

$user_stmt = $conn->prepare($user_sql);

if ($user_stmt) {

    $user_stmt->bind_param("i", $user_id);

    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows > 0) {

        $user = $user_result->fetch_assoc();

        $customer_name = $user['name'];
    }

    $user_stmt->close();
}

$event_sql = "
    SELECT id, event_name
    FROM event_types
    WHERE event_name = 'Wedding'
    LIMIT 1
";

$event_result = $conn->query($event_sql);

if (!$event_result) {

    die("Event Query Error: " . $conn->error);
}

if ($event_result->num_rows == 0) {

    die("Wedding event not found.");
}

$event = $event_result->fetch_assoc();

$wedding_id = (int) $event['id'];

$package_sql = "
    SELECT
        id,
        package_name,
        experience_level,
        description,
        price,
        image,
        min_budget,
        max_budget,
        min_price,
        max_price
    FROM packages
    WHERE event_id = ?
      AND status = 'active'
    ORDER BY id ASC
";

$package_stmt = $conn->prepare($package_sql);

if (!$package_stmt) {

    die("Package Query Error: " . $conn->error);
}

$package_stmt->bind_param(
    "i",
    $wedding_id
);

$package_stmt->execute();

$packages = $package_stmt->get_result();

$service_sql = "
    SELECT
        s.id,
        s.service_name,
        s.description,
        s.price,
        s.min_price,
        s.max_price,
        s.unit,
        s.image,
        s.service_image
    FROM event_services es
    INNER JOIN services s
        ON es.service_id = s.id
    WHERE es.event_type_id = ?
      AND s.status = 'active'
    ORDER BY s.service_name ASC
";

$service_stmt = $conn->prepare($service_sql);

if (!$service_stmt) {

    die("Service Query Error: " . $conn->error);
}

$service_stmt->bind_param(
    "i",
    $wedding_id
);

$service_stmt->execute();

$services = $service_stmt->get_result();

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
    Wedding Services | Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: Arial, sans-serif;

    background: #fffaf2;

    color: #333;
}

.navbar {

    background:
        linear-gradient(
            90deg,
            #f8c8dc,
            #f6d365
        );

    padding: 15px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.1);
}

.logo {

    font-size: 24px;

    font-weight: bold;

    color: #7a4b00;

    white-space: nowrap;
}

.nav-links {

    display: flex;

    gap: 16px;

    align-items: center;

    flex-wrap: wrap;

    justify-content: center;
}

.nav-links a {

    text-decoration: none;

    color: #5c3b00;

    font-weight: 600;
}

.nav-links a:hover {

    color: #b8860b;
}

.nav-user {

    color: #5c3b00;

    font-weight: bold;
}

.container {

    width: 92%;

    max-width: 1200px;

    margin: 35px auto;
}

.back {

    display: inline-block;

    margin-bottom: 20px;

    text-decoration: none;

    color: #8b6508;

    font-weight: bold;
}

.page-title {

    text-align: center;

    margin-bottom: 35px;
}

.page-title h1 {

    font-size: 34px;

    color: #7a4b00;
}

.page-title p {

    margin-top: 10px;

    color: #777;

    line-height: 1.5;
}

.section-title {

    text-align: center;

    margin: 35px 0 25px;
}

.section-title h2 {

    color: #7a4b00;

    font-size: 28px;
}

.section-title p {

    margin-top: 8px;

    color: #777;
}

.packages-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(250px, 1fr)
        );

    gap: 25px;
}

.package-card {

    background: white;

    border-radius: 16px;

    padding: 25px;

    border:
        2px solid #f3dfb3;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.08);

    text-align: center;

    transition: 0.3s;
}

.package-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,0.14);
}

.package-image {

    width: 100%;

    height: 160px;

    background: #fce4ec;

    border-radius: 12px;

    overflow: hidden;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 18px;
}

.package-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}

.package-icon {

    font-size: 55px;
}

.package-card h3 {

    color: #7a4b00;

    font-size: 23px;

    margin-bottom: 8px;
}

.experience {

    display: inline-block;

    padding: 5px 12px;

    border-radius: 20px;

    background: #fff1c7;

    color: #8b6508;

    font-size: 12px;

    font-weight: bold;

    text-transform: uppercase;

    margin-bottom: 12px;
}

.package-description {

    color: #666;

    font-size: 14px;

    line-height: 1.6;

    min-height: 50px;
}

.package-price {

    margin-top: 18px;

    color: #a06b00;

    font-size: 18px;

    font-weight: bold;
}

.package-budget {

    margin-top: 8px;

    font-size: 12px;

    color: #888;
}

.package-btn {

    display: block;

    margin-top: 18px;

    padding: 12px;

    border-radius: 8px;

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f6d365
        );

    color: white;

    text-decoration: none;

    font-weight: bold;
}

.package-btn:hover {

    background: #b8860b;
}

.divider {

    border: none;

    border-top:
        2px solid #f3dfb3;

    margin: 50px 0;
}

.services-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(260px, 1fr)
        );

    gap: 25px;
}

.service-card {

    background: white;

    border-radius: 15px;

    overflow: hidden;

    border:
        1px solid #f3dfb3;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.10);

    transition: 0.3s;
}

.service-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,0.15);
}

.service-image {

    height: 180px;

    background: #fce4ec;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;
}

.service-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}

.no-image {

    font-size: 55px;
}

.service-content {

    padding: 20px;
}

.service-content h3 {

    color: #7a4b00;

    margin-bottom: 10px;

    font-size: 21px;
}

.description {

    color: #666;

    font-size: 14px;

    line-height: 1.6;

    min-height: 45px;
}

.price {

    margin-top: 15px;

    color: #a06b00;

    font-size: 17px;

    font-weight: bold;
}

.unit {

    color: #888;

    font-size: 13px;

    margin-top: 5px;
}

.book-btn {

    display: block;

    margin-top: 18px;

    text-align: center;

    padding: 11px;

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f6d365
        );

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;
}

.book-btn:hover {

    background: #b8860b;
}

.empty {

    background: white;

    border:
        1px solid #f0dca8;

    padding: 45px;

    border-radius: 15px;

    text-align: center;
}

.empty-icon {

    font-size: 55px;
}

.empty h2 {

    margin-top: 15px;

    color: #7a4b00;
}

.empty p {

    margin-top: 8px;

    color: #777;
}

footer {

    margin-top: 60px;

    padding: 20px;

    text-align: center;

    background: #f8c8dc;

    color: #6b3d52;
}

@media(max-width: 700px) {

    .navbar {

        flex-direction: column;

        text-align: center;
    }

    .page-title h1 {

        font-size: 27px;
    }

    .section-title h2 {

        font-size: 24px;
    }

}

</style>

</head>

<body>

<div class="navbar">

    <div class="logo">

        🎉 Event Planner

    </div>

    <div class="nav-links">

        <span class="nav-user">

            👋 Hello,
            <?php
            echo htmlspecialchars(
                $customer_name,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </span>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="profile.php">
            Profile
        </a>

        <a href="my_bookings.php">
            My Bookings
        </a>

        <a href="notifications.php">
            🔔
        </a>

        <a href="messages.php">
            💬
        </a>

        <a href="logout.php">
            Logout
        </a>

    </div>

</div>

<div class="container">

    <a
        href="dashboard.php"
        class="back"
    >

        ← Back to Events

    </a>

    <div class="page-title">

        <h1>
            💍 Wedding Services
        </h1>

        <p>

            Choose a complete wedding package
            or book individual services.

        </p>

    </div>

    <div class="section-title">

        <h2>
            👑 Wedding Packages
        </h2>

        <p>

            Select a package according to
            your budget and guest count.

        </p>

    </div>

    <?php if ($packages->num_rows > 0): ?>

        <div class="packages-grid">

            <?php while ($package = $packages->fetch_assoc()): ?>

                <div class="package-card">

                    <div class="package-image">

                        <?php

                        if (
                            !empty(
                                $package['image']
                            )
                        ) {

                            $package_image =
                                "../uploads/packages/" .
                                $package['image'];

                            if (
                                file_exists(
                                    $package_image
                                )
                            ) {

                        ?>

                                <img
                                    src="<?php
                                        echo htmlspecialchars(
                                            $package_image,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                    alt="<?php
                                        echo htmlspecialchars(
                                            $package['package_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                        <?php

                            } else {

                        ?>

                                <div class="package-icon">
                                    👑
                                </div>

                        <?php

                            }

                        } else {

                        ?>

                            <div class="package-icon">
                                👑
                            </div>

                        <?php

                        }

                        ?>

                    </div>

                    <h3>

                        <?php

                        echo htmlspecialchars(
                            $package['package_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>

                    </h3>

                    <?php if (
                        !empty(
                            $package['experience_level']
                        )
                    ): ?>

                        <div class="experience">

                            <?php

                            echo htmlspecialchars(
                                $package['experience_level'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </div>

                    <?php endif; ?>

                    <p class="package-description">

                        <?php

                        if (
                            !empty(
                                $package['description']
                            )
                        ) {

                            echo htmlspecialchars(
                                $package['description'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                        } else {

                            echo
                            "Complete wedding package for your special day.";

                        }

                        ?>

                    </p>

                    <div class="package-price">

                        <?php

                        $package_min_price =
                            (float)$package['min_price'];

                        $package_max_price =
                            (float)$package['max_price'];

                        if (
                            $package_min_price > 0 &&
                            $package_max_price > 0
                        ) {

                            echo
                            "Rs. " .
                            number_format(
                                $package_min_price
                            ) .
                            " - Rs. " .
                            number_format(
                                $package_max_price
                            );

                        } elseif (
                            $package['price'] > 0
                        ) {

                            echo
                            "Rs. " .
                            number_format(
                                $package['price']
                            );

                        } else {

                            echo
                            "Price on request";

                        }

                        ?>

                    </div>

                    <?php

                    $min_budget =
                        (float)$package['min_budget'];

                    $max_budget =
                        (float)$package['max_budget'];

                    ?>

                    <?php if (
                        $min_budget > 0 ||
                        $max_budget > 0
                    ): ?>

                        <div class="package-budget">

                            Budget:

                            <?php

                            if (
                                $min_budget > 0 &&
                                $max_budget > 0
                            ) {

                                echo
                                "Rs. " .
                                number_format(
                                    $min_budget
                                ) .
                                " - Rs. " .
                                number_format(
                                    $max_budget
                                );

                            } elseif (
                                $min_budget > 0
                            ) {

                                echo
                                "From Rs. " .
                                number_format(
                                    $min_budget
                                );

                            } else {

                                echo
                                "Up to Rs. " .
                                number_format(
                                    $max_budget
                                );
                            }

                            ?>

                        </div>

                    <?php endif; ?>

                    <a
                        href="view_package.php?package_id=<?php
                            echo (int)$package['id'];
                        ?>"
                        class="package-btn"
                    >

                        👀 View Package

                    </a>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                👑
            </div>

            <h2>
                No Wedding Packages Available
            </h2>

            <p>

                Wedding packages will appear here
                when they are added.

            </p>

        </div>

    <?php endif; ?>

    <hr class="divider">

    <div class="section-title">

        <h2>
            💐 Individual Wedding Services
        </h2>

        <p>

            Or choose individual services
            according to your needs.

        </p>

    </div>

    <?php if ($services->num_rows > 0): ?>

        <div class="services-grid">

            <?php while ($service = $services->fetch_assoc()): ?>

                <div class="service-card">

                    <div class="service-image">

                        <?php

                        $service_image_name = null;

                        if (
                            !empty(
                                $service['image']
                            )
                        ) {

                            $service_image_name =
                                $service['image'];

                        } elseif (
                            !empty(
                                $service['service_image']
                            )
                        ) {

                            $service_image_name =
                                $service['service_image'];
                        }

                        if (
                            !empty(
                                $service_image_name
                            )
                        ) {

                            $service_image_path =
                                "../provider/uploads/services/" .
                                $service_image_name;

                            if (
                                file_exists(
                                    $service_image_path
                                )
                            ) {

                        ?>

                                <img
                                    src="<?php
                                        echo htmlspecialchars(
                                            $service_image_path,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                    alt="<?php
                                        echo htmlspecialchars(
                                            $service['service_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                        <?php

                            } else {

                        ?>

                                <div class="no-image">
                                    💐
                                </div>

                        <?php

                            }

                        } else {

                        ?>

                            <div class="no-image">
                                💐
                            </div>

                        <?php

                        }

                        ?>

                    </div>

                    <div class="service-content">

                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $service['service_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </h3>

                        <p class="description">

                            <?php

                            if (
                                !empty(
                                    $service['description']
                                )
                            ) {

                                echo htmlspecialchars(
                                    $service['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                            } else {

                                echo
                                "Professional service for your wedding.";

                            }

                            ?>

                        </p>

                        <div class="price">

                            <?php

                            $min_price =
                                (float)$service['min_price'];

                            $max_price =
                                (float)$service['max_price'];

                            $price =
                                (float)$service['price'];

                            if (
                                $min_price > 0 &&
                                $max_price > 0
                            ) {

                                echo
                                "Rs. " .
                                number_format(
                                    $min_price
                                ) .
                                " - Rs. " .
                                number_format(
                                    $max_price
                                );

                            } elseif (
                                $price > 0
                            ) {

                                echo
                                "Rs. " .
                                number_format(
                                    $price
                                );

                            } else {

                                echo
                                "Price on request";

                            }

                            ?>

                        </div>

                        <?php if (
                            !empty(
                                $service['unit']
                            )
                        ): ?>

                            <div class="unit">

                                📌

                                <?php

                                echo htmlspecialchars(
                                    $service['unit'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </div>

                        <?php endif; ?>

                        <a
                            href="book_service.php?service_id=<?php
                                echo (int)$service['id'];
                            ?>"
                            class="book-btn"
                        >

                            🛎️ Book This Service

                        </a>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                💐
            </div>

            <h2>
                No Wedding Services Available
            </h2>

            <p>

                Wedding services will appear here
                when they are added.

            </p>

        </div>

    <?php endif; ?>

</div>

<footer>

    ©  Event Planner

    <br>

    Plan • Book • Celebrate 🎉

</footer>

</body>

</html>
