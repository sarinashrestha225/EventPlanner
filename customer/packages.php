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

$wedding_sql = "
    SELECT id, event_name
    FROM event_types
    WHERE event_name = 'Wedding'
    LIMIT 1
";

$wedding_result = $conn->query($wedding_sql);

if (!$wedding_result) {
    die("Wedding Query Error: " . $conn->error);
}

if ($wedding_result->num_rows === 0) {
    die("Wedding event type not found.");
}

$wedding = $wedding_result->fetch_assoc();

$wedding_id = (int) $wedding['id'];

$package_sql = "
    SELECT DISTINCT
        id,
        event_id,
        package_name,
        experience_level,
        description,
        price,
        image,
        status,
        min_budget,
        max_budget,
        min_price,
        max_price
    FROM packages
    WHERE event_id = ?
      AND status = 'active'
    ORDER BY
        CASE experience_level
            WHEN 'standard' THEN 1
            WHEN 'good' THEN 2
            WHEN 'premium' THEN 3
            WHEN 'luxury' THEN 4
            WHEN 'vip' THEN 5
            ELSE 6
        END,
        id ASC
";

$package_stmt = $conn->prepare($package_sql);

if (!$package_stmt) {
    die("Package Query Error: " . $conn->error);
}

$package_stmt->bind_param("i", $wedding_id);

$package_stmt->execute();

$packages = $package_stmt->get_result();

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
    Wedding Packages | Event Planner
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

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

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.10);

}

.logo {

    font-size: 24px;

    font-weight: bold;

    color: #7a4b00;

}

.nav-links {

    display: flex;

    gap: 18px;

    align-items: center;

    flex-wrap: wrap;

}

.nav-links a {

    text-decoration: none;

    color: #5c3b00;

    font-weight: 600;

}

.nav-links a:hover {

    color: #b8860b;

}

.container {

    width: 94%;

    max-width: 1400px;

    margin: 35px auto;

}

.back {

    display: inline-block;

    margin-bottom: 20px;

    text-decoration: none;

    color: #8b6508;

    font-weight: bold;

}

.back:hover {

    color: #b8860b;

}

.page-title {

    text-align: center;

    margin-bottom: 10px;

}

.page-title h1 {

    font-size: 34px;

    color: #7a4b00;

}

.page-title p {

    margin-top: 8px;

    color: #777;

    font-size: 15px;

}

.packages-grid {

    width: 100%;

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 20px;

    margin-top: 30px;

    align-items: stretch;

}

.package-card {

    background: #ffffff;

    border:
        1px solid
        #f0dca8;

    border-radius: 15px;

    padding: 25px 18px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.08);

    transition:
        all 0.3s ease;

    display: flex;

    flex-direction: column;

    min-width: 0;

}

.package-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,0.12);

}

.package-icon {

    font-size: 42px;

    margin-bottom: 12px;

}

.package-card h3 {

    color: #7a4b00;

    font-size: 21px;

    margin-bottom: 8px;

}

.package-level {

    display: inline-block;

    background: #fff3cd;

    color: #8b6508;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 12px;

}

.package-description {

    color: #666;

    font-size: 14px;

    line-height: 1.5;

    min-height: 45px;

    margin-bottom: 12px;

}

.package-price {

    color: #a06b00;

    font-size: 16px;

    font-weight: bold;

    margin-bottom: 8px;

}

.package-budget {

    color: #555;

    font-size: 13px;

    margin-top: 8px;

    margin-bottom: 15px;

    line-height: 1.5;

}

.view-package-btn {

    display: block;

    width: 100%;

    margin-top: auto;

    padding: 11px 10px;

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f6d365
        );

    color: #ffffff;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;

    font-size: 14px;

    transition: 0.3s;

}

.view-package-btn:hover {

    background: #b8860b;

    transform: scale(1.02);

}

.view-services-btn {

    display: block;

    width: 100%;

    margin-top: 10px;

    padding: 10px;

    background: #f8c8dc;

    color: #6b3d52;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;

    font-size: 14px;

    transition: 0.3s;

}

.view-services-btn:hover {

    background: #f3aac8;

}

.empty {

    margin-top: 40px;

    background: white;

    border:
        1px solid
        #f0dca8;

    padding: 50px;

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

@media (max-width: 1200px) {

    .packages-grid {

        grid-template-columns:
            repeat(3, 1fr);

    }

}

@media (max-width: 800px) {

    .packages-grid {

        grid-template-columns:
            repeat(2, 1fr);

        gap: 15px;

    }

}

@media (max-width: 500px) {

    .navbar {

        flex-direction: column;

        gap: 12px;

    }

    .nav-links {

        justify-content: center;

    }

    .packages-grid {

        grid-template-columns: 1fr;

    }

    .package-card {

        padding:
            22px 18px;

    }

    .page-title h1 {

        font-size: 27px;

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

        <span>

            👋 Hello,
            <?= htmlspecialchars(
                $customer_name,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

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

            💍 Wedding Packages

        </h1>

        <p>

            Choose the perfect package
            for your wedding.

        </p>

    </div>

    <?php if ($packages->num_rows > 0): ?>

        <div class="packages-grid">

            <?php while ($package = $packages->fetch_assoc()): ?>

                <div class="package-card">

                    <div class="package-icon">

                        👑

                    </div>

                    <h3>

                        <?= htmlspecialchars(
                            $package['package_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h3>

                    <div class="package-level">

                        <?= htmlspecialchars(
                            ucfirst(
                                $package['experience_level']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

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

                            echo "Wedding event package.";

                        }

                        ?>

                    </p>

                    <div class="package-price">

                        <?php

                        if (
                            $package['min_price'] > 0 &&
                            $package['max_price'] > 0
                        ) {

                            echo "Rs. " .
                                number_format(
                                    $package['min_price']
                                ) .
                                " - Rs. " .
                                number_format(
                                    $package['max_price']
                                );

                        } elseif (
                            $package['price'] > 0
                        ) {

                            echo "Rs. " .
                                number_format(
                                    $package['price']
                                );

                        } else {

                            echo "Price on request";

                        }

                        ?>

                    </div>

                    <div class="package-budget">

                        <strong>
                            Budget:
                        </strong>

                        <br>

                        Rs.
                        <?= number_format(
                            $package['min_budget']
                        ) ?>

                        -

                        Rs.
                        <?= number_format(
                            $package['max_budget']
                        ) ?>

                    </div>

                    <a
                        href="view_package.php?package_id=<?= (int)$package['id'] ?>"
                        class="view-package-btn"
                    >

                        👀 View Package

                    </a>

                    <a
                        href="services.php?event_id=<?= (int)$package['event_id'] ?>"
                        class="view-services-btn"
                    >

                        🛎️ View Services

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

</div>

<footer>

    ©  Event Planner

    <br>

    Plan • Book • Celebrate 🎉

</footer>

</body>

</html>

<?php

$package_stmt->close();

?>
