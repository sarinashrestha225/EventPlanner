<?php
session_start();

require_once "../../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";

$sql = "
    SELECT
        venues.*,
        locations.city,
        locations.area
    FROM venues
    INNER JOIN locations
        ON venues.location_id = locations.id
    ORDER BY venues.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Venues | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #fff8f2;
    color: #4d3038;
}

/* HEADER */

.header {
    background: linear-gradient(
        135deg,
        #ffd5df,
        #fff0a8,
        #f1d18a
    );

    padding: 18px 30px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow: 0 3px 15px rgba(0,0,0,.08);
}

.logo {
    font-size: 27px;
    font-weight: bold;
    color: #71394a;
}

.admin {
    background: #fffaf0;
    padding: 10px 18px;
    border-radius: 25px;
    font-weight: bold;
}

/* MAIN */

.container {
    padding: 35px;
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.top h1 {
    color: #71394a;
}

.top p {
    margin-top: 6px;
    color: #8a6a70;
}

.add-btn {
    background: #dcae3e;
    color: white;
    text-decoration: none;
    padding: 12px 22px;
    border-radius: 25px;
    font-weight: bold;
}

.add-btn:hover {
    background: #bd9024;
}

/* FILTER */

.filter {
    background: white;
    padding: 20px;
    border-radius: 18px;
    margin-bottom: 25px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
}

.filter input,
.filter select {
    padding: 12px;
    border: 1px solid #ead6bb;
    border-radius: 10px;
    outline: none;
    margin-right: 10px;
}

.filter input {
    width: 280px;
}

/* TABLE */

.table-box {
    background: white;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 3px 20px rgba(0,0,0,.07);
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: linear-gradient(
        90deg,
        #ffd5df,
        #ffeeb0
    );
}

th {
    padding: 16px;
    text-align: left;
}

td {
    padding: 15px;
    border-bottom: 1px solid #f3e4d7;
}

tr:hover {
    background: #fffaf5;
}

/* PRICE */

.price {
    color: #a77700;
    font-weight: bold;
}

/* STATUS */

.badge {
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

.active {
    background: #dff5df;
    color: #267326;
}

.inactive {
    background: #ffe0e0;
    color: #a33;
}

/* ACTION */

.edit {
    background: #f6d36b;
    color: #513d00;
    padding: 7px 12px;
    border-radius: 8px;
    text-decoration: none;
    margin-right: 5px;
}

.delete {
    background: #f3a6b5;
    color: white;
    padding: 7px 12px;
    border-radius: 8px;
    text-decoration: none;
}

.edit:hover,
.delete:hover {
    opacity: .8;
}

/* IMAGE */

.venue-image {
    width: 70px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
}

.no-image {
    color: #999;
    font-size: 13px;
}

/* EMPTY */

.empty {
    text-align: center;
    padding: 40px;
    color: #999;
}

/* MOBILE */

@media(max-width: 800px) {

    .header {
        flex-direction: column;
        gap: 10px;
    }

    .container {
        padding: 20px;
    }

    .top {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .filter input {
        width: 100%;
        margin-top: 10px;
    }

    .table-box {
        overflow-x: auto;
    }

    table {
        min-width: 1100px;
    }
}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        ✦ Event Planner
    </div>

    <div class="admin">
        👑 <?= htmlspecialchars($admin_name) ?>
    </div>

</div>


<div class="container">

    <div class="top">

        <div>

            <h1>🏨 Manage Venues</h1>

            <p>
                Add and manage Party Palace, Resort, Hotel and other venues.
            </p>

        </div>

        <a href="add.php" class="add-btn">
            ➕ Add Venue
        </a>

    </div>


    <div class="filter">

        <select id="cityFilter">

            <option value="">
                🏙️ All Districts
            </option>

            <option value="Kathmandu">
                Kathmandu
            </option>

            <option value="Bhaktapur">
                Bhaktapur
            </option>

            <option value="Lalitpur">
                Lalitpur
            </option>

        </select>


        <select id="typeFilter">

            <option value="">
                🏨 All Venue Types
            </option>

            <option value="Party Palace">
                Party Palace
            </option>

            <option value="Resort">
                Resort
            </option>

            <option value="Hotel">
                Hotel
            </option>

            <option value="Banquet">
                Banquet
            </option>

            <option value="Outdoor Venue">
                Outdoor Venue
            </option>

            <option value="Other">
                Other
            </option>

        </select>


        <input
            type="text"
            id="searchBox"
            placeholder="🔍 Search venue..."
        >

    </div>


    <div class="table-box">

        <table id="venueTable">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Image</th>

                    <th>Venue</th>

                    <th>Type</th>

                    <th>Location</th>

                    <th>Capacity</th>

                    <th>Price</th>

                    <th>Status</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= $row["id"] ?>
                        </td>


                        <td>

                            <?php if (!empty($row["image"])): ?>

                                <img
                                    src="../../uploads/venues/<?= htmlspecialchars($row["image"]) ?>"
                                    class="venue-image"
                                >

                            <?php else: ?>

                                <span class="no-image">
                                    🖼️ No Image
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>
                            <strong>
                                <?= htmlspecialchars($row["venue_name"]) ?>
                            </strong>
                        </td>


                        <td>
                            <?= htmlspecialchars($row["venue_type"]) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars($row["city"]) ?>
                            →
                            <?= htmlspecialchars($row["area"]) ?>
                        </td>


                        <td>
                            <?= number_format($row["capacity"]) ?>
                            people
                        </td>


                        <td class="price">

                            Rs.
                            <?= number_format(
                                $row["price"],
                                2
                            ) ?>

                        </td>


                        <td>

                            <span class="badge
                                <?= $row["status"] == "active"
                                    ? "active"
                                    : "inactive"
                                ?>"
                            >

                                <?= ucfirst($row["status"]) ?>

                            </span>

                        </td>


                        <td>

                            <a
                                href="edit.php?id=<?= $row["id"] ?>"
                                class="edit"
                            >
                                ✏️ Edit
                            </a>

                            <a
                                href="delete.php?id=<?= $row["id"] ?>"
                                class="delete"
                                onclick="return confirm('Delete this venue?');"
                            >
                                🗑️ Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="9"
                        class="empty"
                    >

                        🏨 No venues added yet.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<script>

const searchBox =
    document.getElementById("searchBox");

const cityFilter =
    document.getElementById("cityFilter");

const typeFilter =
    document.getElementById("typeFilter");


function filterVenues() {

    const search =
        searchBox.value.toLowerCase();

    const city =
        cityFilter.value.toLowerCase();

    const type =
        typeFilter.value.toLowerCase();

    const rows =
        document.querySelectorAll(
            "#venueTable tbody tr"
        );


    rows.forEach(row => {

        const text =
            row.innerText.toLowerCase();

        const location =
            row.children[4]?.innerText.toLowerCase();

        const venueType =
            row.children[3]?.innerText.toLowerCase();


        const searchMatch =
            text.includes(search);

        const cityMatch =
            city === ""
            ||
            location.includes(city);

        const typeMatch =
            type === ""
            ||
            venueType === type;


        row.style.display =
            searchMatch &&
            cityMatch &&
            typeMatch
            ? ""
            : "none";

    });

}


searchBox.addEventListener(
    "keyup",
    filterVenues
);

cityFilter.addEventListener(
    "change",
    filterVenues
);

typeFilter.addEventListener(
    "change",
    filterVenues
);

</script>

</body>
</html>