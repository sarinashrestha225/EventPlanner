<?php

session_start();

require_once "../database.php";

$offers = [];

$sql = "
    SELECT 
        o.id,
        o.provider_id,
        o.service_id,
        o.title,
        o.description,
        o.discount_type,
        o.discount_value,
        o.start_date,
        o.end_date,
        o.status,

        s.name AS service_name,

        p.name AS provider_name

    FROM offers o

    LEFT JOIN services s
        ON o.service_id = s.id

    LEFT JOIN providers p
        ON o.provider_id = p.id

    WHERE o.status = 'active'

    AND (
        o.start_date IS NULL
        OR o.start_date <= CURDATE()
    )

    AND (
        o.end_date IS NULL
        OR o.end_date >= CURDATE()
    )

    ORDER BY o.created_at DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $offers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Offers - Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f2;
    color: #444;
}

.header {
    background: linear-gradient(
        135deg,
        #d4af37,
        #f3d77a
    );

    padding: 20px 40px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
}

.logo {
    font-size: 25px;
    font-weight: bold;
    color: white;
}

.back-btn {
    background: white;
    color: #b8860b;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.back-btn:hover {
    background: #fff4cf;
}

.container {
    width: 90%;
    max-width: 1200px;

    margin: 40px auto;
}

.page-title {
    text-align: center;
    color: #b8860b;

    font-size: 32px;

    margin-bottom: 10px;
}

.subtitle {
    text-align: center;

    color: #777;

    margin-bottom: 35px;
}

.offers-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(280px, 1fr));

    gap: 25px;
}

.offer-card {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.08);

    transition: 0.3s;

    border: 1px solid #f1dfaa;
}

.offer-card:hover {

    transform: translateY(-5px);

    box-shadow:
        0 10px 30px rgba(0,0,0,0.13);
}

.offer-top {

    background: linear-gradient(
        135deg,
        #d4af37,
        #f5d77d
    );

    padding: 25px;

    text-align: center;

    color: white;
}

.offer-icon {

    font-size: 42px;

    margin-bottom: 8px;
}

.offer-title {

    font-size: 22px;

    font-weight: bold;

    margin: 0;
}

.offer-content {

    padding: 22px;
}

.description {

    color: #666;

    line-height: 1.6;

    min-height: 50px;
}

.info {

    margin-top: 15px;

    padding: 10px;

    background: #fff8e5;

    border-radius: 8px;

    font-size: 14px;
}

.info strong {

    color: #b8860b;
}

.discount {

    text-align: center;

    margin: 20px 0;

    padding: 12px;

    background: #ffe8ef;

    border-radius: 10px;

    color: #c2185b;

    font-size: 24px;

    font-weight: bold;
}

.service-btn {

    display: block;

    width: 100%;

    text-align: center;

    padding: 12px;

    background: #d4af37;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;

    transition: 0.3s;
}

.service-btn:hover {

    background: #b8860b;
}

.no-offers {

    background: white;

    padding: 50px;

    border-radius: 15px;

    text-align: center;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.07);
}

.no-offers h3 {

    color: #b8860b;
}

.no-offers p {

    color: #777;
}

@media(max-width:600px) {

    .header {
        padding: 15px 20px;
    }

    .container {
        width: 94%;
    }

    .page-title {
        font-size: 27px;
    }

}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        Event Planner
    </div>

    <a href="dashboard.php"
       class="back-btn">
        ← Dashboard
    </a>

</div>

<div class="container">

    <h1 class="page-title">
        🎉 Special Offers
    </h1>

    <p class="subtitle">
        Discover amazing offers from our verified service providers.
    </p>

    <?php if (count($offers) > 0): ?>

        <div class="offers-grid">

            <?php foreach ($offers as $offer): ?>

                <div class="offer-card">

                    <div class="offer-top">

                        <div class="offer-icon">
                            🎁
                        </div>

                        <h2 class="offer-title">

                            <?= htmlspecialchars(
                                $offer["title"]
                            ) ?>

                        </h2>

                    </div>

                    <div class="offer-content">

                        <?php if (!empty($offer["description"])): ?>

                            <p class="description">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $offer["description"]
                                    )
                                ) ?>

                            </p>

                        <?php endif; ?>

                        <div class="discount">

                            <?php if (
                                $offer["discount_type"]
                                === "percentage"
                            ): ?>

                                <?= htmlspecialchars(
                                    $offer["discount_value"]
                                ) ?>% OFF

                            <?php else: ?>

                                Rs.
                                <?= number_format(
                                    $offer["discount_value"],
                                    2
                                ) ?>

                                OFF

                            <?php endif; ?>

                        </div>

                        <?php if (
                            !empty($offer["service_name"])
                        ): ?>

                            <div class="info">

                                <strong>
                                    Service:
                                </strong>

                                <?= htmlspecialchars(
                                    $offer["service_name"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                        <?php if (
                            !empty($offer["provider_name"])
                        ): ?>

                            <div class="info">

                                <strong>
                                    Provider:
                                </strong>

                                <?= htmlspecialchars(
                                    $offer["provider_name"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                        <div class="info">

                            <strong>
                                Valid:
                            </strong>

                            <?php

                            if (
                                !empty($offer["start_date"])
                                &&
                                !empty($offer["end_date"])
                            ) {

                                echo date(
                                    "M d, Y",
                                    strtotime(
                                        $offer["start_date"]
                                    )
                                );

                                echo " - ";

                                echo date(
                                    "M d, Y",
                                    strtotime(
                                        $offer["end_date"]
                                    )
                                );

                            } elseif (
                                !empty($offer["start_date"])
                            ) {

                                echo "From ";

                                echo date(
                                    "M d, Y",
                                    strtotime(
                                        $offer["start_date"]
                                    )
                                );

                            } elseif (
                                !empty($offer["end_date"])
                            ) {

                                echo "Until ";

                                echo date(
                                    "M d, Y",
                                    strtotime(
                                        $offer["end_date"]
                                    )
                                );

                            } else {

                                echo "No expiry";

                            }

                            ?>

                        </div>

                        <?php if (
                            !empty($offer["service_id"])
                        ): ?>

                            <a
                                href="services.php?id=<?= (int)$offer["service_id"] ?>"
                                class="service-btn"
                            >
                                View Service
                            </a>

                        <?php else: ?>

                            <a
                                href="services.php"
                                class="service-btn"
                            >
                                View Services
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="no-offers">

            <h3>
                No Active Offers
            </h3>

            <p>
                There are currently no special offers available.
                Please check again later.
            </p>

        </div>

    <?php endif; ?>

</div>

</body>

</html>