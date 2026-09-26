<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$package_id = isset($_GET['package_id'])
    ? (int)$_GET['package_id']
    : 0;

if ($package_id <= 0) {
    die("Invalid package ID.");
}

$sql = "
    SELECT
        id,
        event_id,
        package_name,
        experience_level,
        description,
        price,
        image,
        min_budget,
        max_budget,
        min_price,
        max_price,
        status
    FROM packages
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Package Query Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $package_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Package not found.");
}

$package = $result->fetch_assoc();

$stmt->close();

$event_name = "Event";

$event_sql = "
    SELECT event_name
    FROM event_types
    WHERE id = ?
    LIMIT 1
";

$event_stmt = $conn->prepare($event_sql);

if ($event_stmt) {

    $event_stmt->bind_param(
        "i",
        $package['event_id']
    );

    $event_stmt->execute();

    $event_result = $event_stmt->get_result();

    if ($event_result->num_rows > 0) {

        $event = $event_result->fetch_assoc();

        $event_name = $event['event_name'];
    }

    $event_stmt->close();
}

$pricing_rows = [];

$pricing_sql = "
    SELECT
        id,
        min_guests,
        max_guests,
        original_price,
        combo_price
    FROM package_pricing
    WHERE package_id = ?
    ORDER BY min_guests ASC
";

$pricing_stmt = $conn->prepare($pricing_sql);

if (!$pricing_stmt) {
    die("Pricing Query Error: " . htmlspecialchars($conn->error));
}

$pricing_stmt->bind_param(
    "i",
    $package_id
);

$pricing_stmt->execute();

$pricing_result = $pricing_stmt->get_result();

while ($row = $pricing_result->fetch_assoc()) {
    $pricing_rows[] = $row;
}

$pricing_stmt->close();

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
    <?= htmlspecialchars(
        $package['package_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
    | Event Planner
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
    background: linear-gradient(
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
        rgba(0,0,0,0.1);
}

.logo {
    font-size: 24px;
    font-weight: bold;
    color: #7a4b00;
}

.nav-links {
    display: flex;
    gap: 18px;
}

.nav-links a {
    text-decoration: none;
    color: #5c3b00;
    font-weight: bold;
}

.nav-links a:hover {
    color: #b8860b;
}

.container {
    width: 92%;
    max-width: 1100px;
    margin: 35px auto;
}

.back {
    display: inline-block;
    margin-bottom: 25px;
    text-decoration: none;
    color: #8b6508;
    font-weight: bold;
}

.back:hover {
    color: #b8860b;
}

.package-box {
    background: white;
    border-radius: 18px;
    padding: 35px;
    border: 1px solid #f0dca8;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,0.10);
}

.package-title {
    text-align: center;
}

.package-title h1 {
    color: #7a4b00;
    font-size: 34px;
}

.level {
    display: inline-block;

    margin-top: 10px;

    padding: 7px 15px;

    background: #f8c8dc;

    border-radius: 20px;

    color: #6b3d52;

    font-weight: bold;
}

.description {
    margin-top: 20px;

    text-align: center;

    color: #666;

    line-height: 1.6;
}

.package-image {
    margin-top: 25px;

    height: 300px;

    border-radius: 15px;

    overflow: hidden;

    background: #fce4ec;

    display: flex;

    align-items: center;

    justify-content: center;
}

.package-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    font-size: 80px;
}

.budget {
    margin-top: 25px;

    padding: 20px;

    background: #fff8e1;

    border-radius: 12px;

    text-align: center;
}

.budget h3 {
    color: #7a4b00;

    margin-bottom: 8px;
}

.budget p {
    font-size: 20px;

    font-weight: bold;

    color: #a06b00;
}

.pricing {
    margin-top: 35px;
}

.pricing h2 {
    color: #7a4b00;

    margin-bottom: 18px;

    text-align: center;
}

.guest-selector {
    background: #fff8e1;

    border: 2px solid #f3dfb3;

    border-radius: 14px;

    padding: 22px;

    margin-bottom: 25px;

    text-align: center;
}

.guest-selector label {
    display: block;

    color: #7a4b00;

    font-weight: bold;

    font-size: 17px;

    margin-bottom: 12px;
}

.guest-select {
    width: 100%;

    max-width: 500px;

    padding: 13px 15px;

    border: 1px solid #d8bd76;

    border-radius: 8px;

    background: white;

    color: #5c3b00;

    font-size: 16px;

    font-weight: bold;

    outline: none;

    cursor: pointer;
}

.guest-select:focus {
    border-color: #d4af37;

    box-shadow:
        0 0 0 3px
        rgba(212,175,55,0.15);
}

.selected-summary {
    margin-top: 20px;

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(180px, 1fr)
        );

    gap: 15px;
}

.price-box {
    background: white;

    border-radius: 10px;

    padding: 15px;

    border: 1px solid #ead9ae;
}

.price-box span {
    display: block;

    color: #777;

    font-size: 13px;

    margin-bottom: 7px;
}

.price-box strong {
    color: #a06b00;

    font-size: 21px;
}

.price-box.original-box strong {
    color: #999;

    text-decoration: line-through;

    font-size: 17px;
}

.price-table {
    width: 100%;

    border-collapse: collapse;
}

.price-table th,
.price-table td {
    padding: 14px;

    border: 1px solid #ead9ae;

    text-align: center;
}

.price-table th {
    background: #f8c8dc;

    color: #6b3d52;
}

.price-table td {
    background: white;
}

.original {
    text-decoration: line-through;

    color: #999;
}

.combo {
    font-weight: bold;

    color: #a06b00;
}

.price-table tr.active-row td {
    background: #fff3cd;

    border-top:
        2px solid #d4af37;

    border-bottom:
        2px solid #d4af37;
}

.book-btn {
    display: block;

    width: 300px;

    margin: 30px auto 0;

    padding: 14px;

    text-align: center;

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

    font-size: 16px;

    transition: 0.3s;
}

.book-btn:hover {
    background: #b8860b;

    transform: translateY(-2px);
}

.book-btn.disabled {
    background: #bbb;

    cursor: not-allowed;

    pointer-events: none;
}

.no-pricing {
    margin-top: 30px;

    padding: 25px;

    background: #fff8e1;

    border-radius: 12px;

    text-align: center;

    color: #777;
}

footer {
    margin-top: 60px;

    padding: 20px;

    text-align: center;

    background: #f8c8dc;

    color: #6b3d52;
}

@media(max-width:700px) {

    .navbar {
        flex-direction: column;

        gap: 12px;
    }

    .nav-links {
        flex-wrap: wrap;

        justify-content: center;
    }

    .package-box {
        padding: 20px;
    }

    .package-title h1 {
        font-size: 27px;
    }

    .package-image {
        height: 220px;
    }

    .price-table {
        font-size: 13px;
    }

    .price-table th,
    .price-table td {
        padding: 10px 6px;
    }

    .selected-summary {
        grid-template-columns: 1fr;
    }

    .book-btn {
        width: 100%;
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

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="my_bookings.php">
            My Bookings
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

    <div class="package-box">

        <div class="package-title">

            <h1>

                👑

                <?= htmlspecialchars(
                    $package['package_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h1>

            <?php if (!empty($package['experience_level'])): ?>

                <div class="level">

                    <?= htmlspecialchars(
                        ucfirst(
                            $package['experience_level']
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>

        </div>

        <p class="description">

            <?= !empty($package['description'])
                ? nl2br(
                    htmlspecialchars(
                        $package['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                )
                : "Complete event package for your special occasion."
            ?>

        </p>

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
                    alt="Package Image"
                >

            <?php else: ?>

                <div class="no-image">
                    👑
                </div>

            <?php endif; ?>

        </div>

        <div class="budget">

            <h3>
                💰 Recommended Budget
            </h3>

            <p>

                <?php

                $min_budget =
                    (float)($package['min_budget'] ?? 0);

                $max_budget =
                    (float)($package['max_budget'] ?? 0);

                if (
                    $min_budget > 0 &&
                    $max_budget > 0
                ) {

                    echo
                        "Rs. " .
                        number_format($min_budget) .
                        " - Rs. " .
                        number_format($max_budget);

                } elseif ($min_budget > 0) {

                    echo
                        "From Rs. " .
                        number_format($min_budget);

                } elseif ($max_budget > 0) {

                    echo
                        "Up to Rs. " .
                        number_format($max_budget);

                } else {

                    echo "Price on request";
                }

                ?>

            </p>

        </div>

        <?php if (count($pricing_rows) > 0): ?>

            <div class="pricing">

                <h2>
                    👥 Select Number of Guests
                </h2>

                <div class="guest-selector">

                    <label for="guestPricing">

                        How many guests are you planning for?

                    </label>

                    <select
                        id="guestPricing"
                        class="guest-select"
                    >

                        <option value="">
                            -- Select Guest Range --
                        </option>

                        <?php foreach (
                            $pricing_rows as $index => $row
                        ): ?>

                            <option
                                value="<?= (int)$row['id'] ?>"
                                data-min-guests="<?= (int)$row['min_guests'] ?>"
                                data-max-guests="<?= (int)$row['max_guests'] ?>"
                                data-original-price="<?= (float)$row['original_price'] ?>"
                                data-combo-price="<?= (float)$row['combo_price'] ?>"
                            >

                                <?= number_format(
                                    $row['min_guests']
                                ) ?>

                                -

                                <?= number_format(
                                    $row['max_guests']
                                ) ?>

                                Guests

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="selected-summary">

                        <div class="price-box">

                            <span>
                                👥 Selected Guests
                            </span>

                            <strong id="selectedGuests">
                                Not selected
                            </strong>

                        </div>

                        <div class="price-box">

                            <span>
                                💰 Combo Price
                            </span>

                            <strong id="selectedComboPrice">
                                Select guests
                            </strong>

                        </div>

                    </div>

                    <div class="selected-summary">

                        <div class="price-box original-box">

                            <span>
                                Original Price
                            </span>

                            <strong id="selectedOriginalPrice">
                                Select guests
                            </strong>

                        </div>

                        <div class="price-box">

                            <span>
                                🎉 Package
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $package['package_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>

                <h2>
                    👥 Guest-wise Package Pricing
                </h2>

                <table class="price-table">

                    <thead>

                        <tr>

                            <th>
                                Guests
                            </th>

                            <th>
                                Original Price
                            </th>

                            <th>
                                Combo Price
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach (
                            $pricing_rows as $row
                        ): ?>

                            <tr
                                data-pricing-row="<?= (int)$row['id'] ?>"
                            >

                                <td>

                                    <?= number_format(
                                        $row['min_guests']
                                    ) ?>

                                    -

                                    <?= number_format(
                                        $row['max_guests']
                                    ) ?>

                                </td>

                                <td class="original">

                                    Rs.

                                    <?= number_format(
                                        $row['original_price']
                                    ) ?>

                                </td>

                                <td class="combo">

                                    Rs.

                                    <?= number_format(
                                        $row['combo_price']
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

                <a
                    id="bookPackageButton"
                    href="#"
                    class="book-btn disabled"
                >
                    📅 Select Guests to Continue
                </a>

            </div>

        <?php else: ?>

            <div class="no-pricing">

                <h3>
                    👥 Guest Pricing Not Available
                </h3>

                <p>
                    Guest-wise pricing has not been added for this package yet.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

<footer>

    ©  Event Planner

    <br>

    Plan • Book • Celebrate 🎉

</footer>

<?php if (count($pricing_rows) > 0): ?>

<script>

const guestPricing =
    document.getElementById("guestPricing");

const selectedGuests =
    document.getElementById("selectedGuests");

const selectedComboPrice =
    document.getElementById("selectedComboPrice");

const selectedOriginalPrice =
    document.getElementById("selectedOriginalPrice");

const bookPackageButton =
    document.getElementById("bookPackageButton");

const packageId =
    <?= (int)$package['id'] ?>;

guestPricing.addEventListener(
    "change",
    function () {

        const selectedOption =
            guestPricing.options[
                guestPricing.selectedIndex
            ];

        document.querySelectorAll(
            "[data-pricing-row]"
        ).forEach(function (row) {

            row.classList.remove(
                "active-row"
            );

        });

        if (!selectedOption.value) {

            selectedGuests.textContent =
                "Not selected";

            selectedComboPrice.textContent =
                "Select guests";

            selectedOriginalPrice.textContent =
                "Select guests";

            bookPackageButton.href =
                "#";

            bookPackageButton.classList.add(
                "disabled"
            );

            bookPackageButton.textContent =
                "📅 Select Guests to Continue";

            return;
        }

        const pricingId =
            selectedOption.value;

        const minGuests =
            selectedOption.dataset.minGuests;

        const maxGuests =
            selectedOption.dataset.maxGuests;

        const originalPrice =
            parseFloat(
                selectedOption.dataset.originalPrice
            );

        const comboPrice =
            parseFloat(
                selectedOption.dataset.comboPrice
            );

        selectedGuests.textContent =
            Number(minGuests).toLocaleString() +
            " - " +
            Number(maxGuests).toLocaleString() +
            " Guests";

        selectedOriginalPrice.textContent =
            "Rs. " +
            originalPrice.toLocaleString();

        selectedComboPrice.textContent =
            "Rs. " +
            comboPrice.toLocaleString();

        const activeRow =
            document.querySelector(
                '[data-pricing-row="' +
                pricingId +
                '"]'
            );

        if (activeRow) {

            activeRow.classList.add(
                "active-row"
            );
        }

        bookPackageButton.href =
            "book_package.php?package_id=" +
            packageId +
            "&pricing_id=" +
            pricingId;

        bookPackageButton.classList.remove(
            "disabled"
        );

        bookPackageButton.textContent =
            "📅 Book This Package";

    }
);

</script>

<?php endif; ?>

</body>

</html>
