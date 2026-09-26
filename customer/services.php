<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$customer_name = "Customer";

$user_stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
    LIMIT 1
");

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

$event_id = 0;

if (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = (int) $_GET['event_id'];
}

$event_name = "Event";

if ($event_id > 0) {

    $event_stmt = $conn->prepare("
        SELECT event_name
        FROM event_types
        WHERE id = ?
        LIMIT 1
    ");

    if ($event_stmt) {

        $event_stmt->bind_param("i", $event_id);
        $event_stmt->execute();

        $event_result = $event_stmt->get_result();

        if ($event_result->num_rows > 0) {

            $event = $event_result->fetch_assoc();

            $event_name = $event['event_name'];
        }

        $event_stmt->close();
    }
}

$packages = [];

if ($event_id > 0) {

    $package_stmt = $conn->prepare("
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
        ORDER BY
            CASE
                WHEN package_name = 'Basic' THEN 1
                WHEN package_name = 'Silver' THEN 2
                WHEN package_name = 'Premium' THEN 3
                WHEN package_name = 'Luxury' THEN 4
                WHEN package_name = 'VIP' THEN 5
                ELSE 6
            END ASC
    ");

    if (!$package_stmt) {
        die("Package Query Error: " . htmlspecialchars($conn->error));
    }

    $package_stmt->bind_param("i", $event_id);
    $package_stmt->execute();

    $package_result = $package_stmt->get_result();

    while ($package = $package_result->fetch_assoc()) {
        $packages[] = $package;
    }

    $package_stmt->close();
}

$services = [];

if ($event_id > 0) {

    $service_stmt = $conn->prepare("
        SELECT DISTINCT
            s.id,
            s.service_name,
            s.description,
            s.category,
            s.image
        FROM services s
        WHERE s.event_id = ?
        AND s.status = 'active'
        ORDER BY s.service_name ASC
    ");

    if (!$service_stmt) {
        die("Service Query Error: " . htmlspecialchars($conn->error));
    }

    $service_stmt->bind_param("i", $event_id);
    $service_stmt->execute();

    $service_result = $service_stmt->get_result();

    while ($row = $service_result->fetch_assoc()) {
        $services[] = $row;
    }

    $service_stmt->close();

} else {

    $service_result = $conn->query("
        SELECT DISTINCT
            s.id,
            s.service_name,
            s.description,
            s.category,
            s.image
        FROM services s
        WHERE s.status = 'active'
        ORDER BY s.service_name ASC
    ");

    if (!$service_result) {
        die("Service Query Error: " . htmlspecialchars($conn->error));
    }

    while ($row = $service_result->fetch_assoc()) {
        $services[] = $row;
    }
}

function getServiceIcon($service_name)
{
    $name = strtolower(trim($service_name));

    if (strpos($name, 'catering') !== false) {
        return "🍽️";
    }

    if (strpos($name, 'chef') !== false) {
        return "👨‍🍳";
    }

    if (strpos($name, 'decoration') !== false) {
        return "🎀";
    }

    if (
        strpos($name, 'dishwasher') !== false ||
        strpos($name, 'cleaner') !== false
    ) {
        return "🧹";
    }

    if (strpos($name, 'dj') !== false) {
        return "🎧";
    }

    if (
        strpos($name, 'makeup') !== false ||
        strpos($name, 'beauty') !== false
    ) {
        return "💄";
    }

    if (strpos($name, 'pandit') !== false) {
        return "🙏";
    }

    if (
        strpos($name, 'photo') !== false ||
        strpos($name, 'video') !== false
    ) {
        return "📸";
    }

    if (strpos($name, 'transport') !== false) {
        return "🚗";
    }

    if (
        strpos($name, 'venue') !== false ||
        strpos($name, 'resort') !== false ||
        strpos($name, 'banquet') !== false ||
        strpos($name, 'party palace') !== false
    ) {
        return "🏛️";
    }

    if (strpos($name, 'waiter') !== false) {
        return "🤵";
    }

    return "🛍️";
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
<?= htmlspecialchars($event_name, ENT_QUOTES, 'UTF-8') ?>
Packages & Services - Event Planner
</title>

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

    padding: 16px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.08);
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: #6b4f00;
    white-space: nowrap;
}

.nav-links {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 18px;
    flex-wrap: wrap;
}

.nav-links span {
    color: #5c3b00;
    font-weight: bold;
}

.nav-links a {
    text-decoration: none;
    color: #5c3b00;
    font-weight: bold;
    transition: 0.2s;
}

.nav-links a:hover {
    color: #b8860b;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 35px auto;
}

.back {
    display: inline-block;
    margin-bottom: 22px;
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

.service-count {
    margin-top: 10px;
    color: #a06b00;
    font-weight: bold;
}

.section-title {
    text-align: center;
    margin-top: 40px;
    margin-bottom: 25px;
}

.section-title h2 {
    color: #7a4b00;
    font-size: 29px;
}

.section-title p {
    margin-top: 8px;
    color: #777;
    font-size: 14px;
}

.packages-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fit,
        minmax(250px, 1fr)
    );
    gap: 25px;
}

.package-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 2px solid #f3dfb3;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.08);

    text-align: center;
    transition: 0.3s;
}

.package-card:hover {
    transform: translateY(-5px);

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
    margin-top: 35px;

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(260px, 1fr)
        );

    gap: 25px;
}

.service-card {
    background: #fff;

    border-radius: 15px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.10);

    border:
        1px solid #f3dfb3;

    display: flex;

    flex-direction: column;

    transition: 0.3s;
}

.service-card:hover {
    transform: translateY(-5px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,0.15);
}

.service-image {
    height: 190px;

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
    font-size: 60px;
}

.service-content {
    padding: 20px;

    display: flex;

    flex-direction: column;

    flex: 1;
}

.service-content h3 {
    color: #7a4b00;

    margin-bottom: 10px;

    font-size: 21px;
}

.category {
    display: inline-block;

    width: fit-content;

    background: #fff3cd;

    color: #8b6508;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 12px;
}

.description {
    color: #666;

    font-size: 14px;

    line-height: 1.6;

    min-height: 48px;

    margin-bottom: 20px;
}

.book-btn {
    display: block;

    margin-top: auto;

    padding: 12px;

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #f6d365
        );

    color: white;

    text-align: center;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;

    transition: 0.3s;
}

.book-btn:hover {
    background: #b8860b;

    transform: scale(1.02);
}

.empty {
    margin-top: 40px;

    background: white;

    border:
        1px solid #f0dca8;

    padding: 50px;

    border-radius: 15px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,0.06);
}

.empty-icon {
    font-size: 60px;
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

    padding: 22px;

    text-align: center;

    background: #f8c8dc;

    color: #6b3d52;

    line-height: 1.7;
}

@media(max-width: 800px) {

    .navbar {
        flex-direction: column;
        padding: 18px;
    }

    .nav-links {
        justify-content: center;
    }

}

@media(max-width: 700px) {

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .page-title h1 {
        font-size: 27px;
    }

    .section-title h2 {
        font-size: 24px;
    }

    .packages-grid,
    .services-grid {
        grid-template-columns: 1fr;
    }

    .service-image {
        height: 210px;
    }

}

@media(max-width: 500px) {

    .nav-links {
        gap: 12px;
        font-size: 14px;
    }

    .logo {
        font-size: 22px;
    }

    .empty {
        padding: 35px 20px;
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
            🏠 Dashboard
        </a>

        <a href="profile.php">
            👤 Profile
        </a>

        <a href="my_bookings.php">
            📋 My Bookings
        </a>

        <a href="notifications.php">
            🔔
        </a>

        <a href="messages.php">
            💬
        </a>

        <a href="change_password.php">
            🔐
        </a>

        <a href="logout.php">
            🚪 Logout
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
            🎉
            <?= htmlspecialchars(
                $event_name,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
            Packages & Services
        </h1>

        <p>
            Choose a complete package or book individual services for your event.
        </p>

    </div>

    <?php if (count($packages) > 0): ?>

        <div class="section-title">

            <h2>
                👑
                <?= htmlspecialchars(
                    $event_name,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                Packages
            </h2>

            <p>
                Choose the package according to your budget and requirements.
            </p>

        </div>

        <div class="packages-grid">

            <?php foreach ($packages as $package): ?>

                <div class="package-card">

                    <div class="package-image">

                        <?php

                        $package_image = "";

                        if (!empty($package['image'])) {

                            $package_image =
                                "../uploads/packages/" .
                                basename($package['image']);
                        }

                        if (
                            !empty($package_image) &&
                            file_exists($package_image)
                        ):

                        ?>

                            <img
                                src="<?= htmlspecialchars(
                                    $package_image,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $package['package_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        <?php else: ?>

                            <div class="package-icon">
                                👑
                            </div>

                        <?php endif; ?>

                    </div>

                    <h3>

                        <?= htmlspecialchars(
                            $package['package_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h3>

                    <?php if (!empty($package['experience_level'])): ?>

                        <div class="experience">

                            <?= htmlspecialchars(
                                $package['experience_level'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    <?php endif; ?>

                    <p class="package-description">

                        <?php if (!empty($package['description'])): ?>

                            <?= nl2br(
                                htmlspecialchars(
                                    $package['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>

                        <?php else: ?>

                            Complete
                            <?= htmlspecialchars(
                                strtolower($event_name),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            package for your special event.

                        <?php endif; ?>

                    </p>

                    <div class="package-price">

                        <?php

                        $package_min_price =
                            (float)($package['min_price'] ?? 0);

                        $package_max_price =
                            (float)($package['max_price'] ?? 0);

                        $package_price =
                            (float)($package['price'] ?? 0);

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

                        } elseif ($package_price > 0) {

                            echo
                                "Rs. " .
                                number_format(
                                    $package_price
                                );

                        } else {

                            echo "Price on request";

                        }

                        ?>

                    </div>

                    <?php

                    $min_budget =
                        (float)($package['min_budget'] ?? 0);

                    $max_budget =
                        (float)($package['max_budget'] ?? 0);

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

                            } elseif ($min_budget > 0) {

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
                        href="view_package.php?package_id=<?= (int)$package['id'] ?>"
                        class="package-btn"
                    >
                        👀 View Package
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                👑
            </div>

            <h2>
                No
                <?= htmlspecialchars(
                    $event_name,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                Packages Available
            </h2>

            <p>
                Packages will appear here when they are available.
            </p>

        </div>

    <?php endif; ?>

    <hr class="divider">

    <div class="section-title">

        <h2>
            💐
            Individual
            <?= htmlspecialchars(
                $event_name,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
            Services
        </h2>

        <p>
            Or choose individual services according to your needs.
        </p>

        <div class="service-count">
            <?= count($services) ?> Services Available
        </div>

    </div>

    <?php if (count($services) > 0): ?>

        <div class="services-grid">

            <?php foreach ($services as $service): ?>

                <div class="service-card">

                    <div class="service-image">

                        <?php if (!empty($service['image'])): ?>

                            <img
                                src="../uploads/services/<?= htmlspecialchars(
                                    basename($service['image']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $service['service_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                <?= getServiceIcon(
                                    $service['service_name']
                                ) ?>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="service-content">

                        <h3>

                            <?= htmlspecialchars(
                                $service['service_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h3>

                        <?php if (!empty($service['category'])): ?>

                            <div class="category">

                                <?= htmlspecialchars(
                                    $service['category'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>

                        <?php endif; ?>

                        <p class="description">

                            <?php if (!empty($service['description'])): ?>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $service['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?>

                            <?php else: ?>

                                Professional service for your
                                <?= htmlspecialchars(
                                    strtolower($event_name),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>.

                            <?php endif; ?>

                        </p>

                        <a
                            href="service_details.php?id=<?= (int)$service['id'] ?>&event_id=<?= $event_id ?>"
                            class="book-btn"
                        >
                            🛍️ View & Book
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                🛍️
            </div>

            <h2>
                No
                <?= htmlspecialchars(
                    $event_name,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                Services Available
            </h2>

            <p>
                There are currently no services available for this event.
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
