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
        services.*,
        events.event_name
    FROM services
    LEFT JOIN events
        ON services.event_id = events.id
    ORDER BY services.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . htmlspecialchars($conn->error));
}

$event_sql = "
    SELECT id, event_name
    FROM events
    ORDER BY event_name ASC
";

$event_result = $conn->query($event_sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Services | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: "Segoe UI", Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #fffaf2,
            #fff1f5,
            #fff9df
        );

    color: #4d3439;

    min-height: 100vh;
}

.header {

    background:
        linear-gradient(
            135deg,
            #ffd4df,
            #fff0a6,
            #e8c46b
        );

    padding: 18px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    box-shadow:
        0 4px 18px
        rgba(0,0,0,0.10);

    position: sticky;

    top: 0;

    z-index: 100;
}

.logo {

    font-size: 27px;

    font-weight: bold;

    color: #71394a;
}

.admin-box {

    background: #fffaf0;

    padding: 10px 18px;

    border-radius: 30px;

    color: #71394a;

    font-weight: bold;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,0.08);
}

.container {

    width: 95%;

    max-width: 1400px;

    margin: auto;

    padding: 35px 0 60px;
}

.top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.top h1 {

    color: #71394a;

    font-size: 30px;

    margin-bottom: 7px;
}

.top p {

    color: #8d7077;

    font-size: 15px;
}

.add-btn {

    display: inline-block;

    background:
        linear-gradient(
            135deg,
            #e8bd48,
            #d9a928
        );

    color: white;

    text-decoration: none;

    padding: 13px 22px;

    border-radius: 25px;

    font-weight: bold;

    box-shadow:
        0 5px 12px
        rgba(193,145,28,0.25);

    transition: 0.3s;
}

.add-btn:hover {

    transform: translateY(-2px);

    background:
        linear-gradient(
            135deg,
            #d4a52f,
            #bd9022
        );
}

.message {

    padding: 15px 20px;

    border-radius: 14px;

    margin-bottom: 22px;

    font-weight: bold;
}

.success-message {

    background: #e5f7df;

    color: #34752d;

    border: 1px solid #b9dfad;
}

.error-message {

    background: #ffe2e5;

    color: #a33;

    border: 1px solid #f0b8c0;
}

.filter {

    background: rgba(255,255,255,0.95);

    padding: 20px;

    border-radius: 18px;

    margin-bottom: 25px;

    box-shadow:
        0 4px 18px
        rgba(0,0,0,0.07);

    display: flex;

    gap: 12px;

    flex-wrap: wrap;
}

.filter select,
.filter input {

    padding: 13px 15px;

    border: 1px solid #ead7bf;

    border-radius: 11px;

    outline: none;

    background: #fffdf8;

    color: #5d454b;

    font-size: 14px;
}

.filter select {

    min-width: 220px;
}

.filter input {

    width: 300px;
}

.filter select:focus,
.filter input:focus {

    border-color: #e0b73e;

    box-shadow:
        0 0 0 3px
        rgba(224,183,62,0.15);
}

.table-box {

    background: white;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 5px 25px
        rgba(0,0,0,0.08);
}

table {

    width: 100%;

    border-collapse: collapse;
}

thead {

    background:
        linear-gradient(
            90deg,
            #ffd5df,
            #ffeeb0,
            #f3d78d
        );
}

th {

    padding: 17px 15px;

    text-align: left;

    color: #5d3b43;

    font-size: 14px;

    white-space: nowrap;
}

td {

    padding: 15px;

    border-bottom:
        1px solid #f3e5da;

    vertical-align: middle;
}

tbody tr {

    transition: 0.2s;
}

tbody tr:hover {

    background: #fffaf5;
}

.service-image {

    width: 65px;

    height: 55px;

    object-fit: cover;

    border-radius: 10px;

    border: 2px solid #f2dca8;
}

.no-image {

    width: 65px;

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #fff4f6;

    border-radius: 10px;

    color: #bd8793;

    font-size: 11px;

    text-align: center;
}

.service-name {

    font-weight: bold;

    color: #71394a;

    font-size: 15px;
}

.description {

    font-size: 12px;

    color: #987e83;

    margin-top: 4px;

    max-width: 260px;

    line-height: 1.4;
}

.event-badge {

    display: inline-block;

    background: #fff1c7;

    color: #8a6500;

    padding: 7px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.no-event {

    color: #999;

    font-size: 13px;
}

.price {

    color: #a77700;

    font-weight: bold;

    white-space: nowrap;
}

.badge {

    display: inline-block;

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.badge-active {

    background: #dff5df;

    color: #267326;
}

.badge-inactive {

    background: #ffe0e0;

    color: #a33;
}

.action-box {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.edit {

    background: #f6d36b;

    color: #513d00;

    padding: 8px 12px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 13px;

    font-weight: bold;
}

.delete {

    background: #f1a2b2;

    color: white;

    padding: 8px 12px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 13px;

    font-weight: bold;
}

.edit:hover,
.delete:hover {

    opacity: 0.8;
}

.empty {

    text-align: center;

    padding: 60px 20px;

    color: #999;

    font-size: 16px;
}

@media(max-width: 800px) {

    .header {

        flex-direction: column;

        gap: 12px;

        text-align: center;
    }

    .container {

        width: 92%;
    }

    .top {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .filter {

        flex-direction: column;
    }

    .filter select,
    .filter input {

        width: 100%;
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

    <div class="admin-box">

        👑 <?= htmlspecialchars($admin_name) ?>

    </div>

</div>

<div class="container">

    <?php if (isset($_GET["success"])): ?>

        <div class="message success-message">

            ✅ Service successfully added.

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["updated"])): ?>

        <div class="message success-message">

            ✏️ Service successfully updated.

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["deleted"])): ?>

        <div class="message success-message">

            🗑️ Service successfully deleted.

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="message error-message">

            ❌ Something went wrong.

        </div>

    <?php endif; ?>

    <div class="top">

        <div>

            <h1>
                🛎️ Manage Services
            </h1>

            <p>
                Add, edit and manage services for every event.
            </p>

        </div>

        <a
            href="add.php"
            class="add-btn"
        >

            ➕ Add Service

        </a>

    </div>

    <div class="filter">

        <select id="eventFilter">

            <option value="">
                🎉 All Events
            </option>

            <?php

            if ($event_result && $event_result->num_rows > 0):

                while ($event = $event_result->fetch_assoc()):

            ?>

                <option
                    value="<?= htmlspecialchars($event["event_name"]) ?>"
                >

                    <?= htmlspecialchars($event["event_name"]) ?>

                </option>

            <?php

                endwhile;

            endif;

            ?>

        </select>

        <input
            type="text"
            id="searchBox"
            placeholder="🔍 Search service..."
        >

    </div>

    <div class="table-box">

        <table id="serviceTable">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Image</th>

                    <th>Service</th>

                    <th>Event</th>

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

                            <?= (int)$row["id"] ?>

                        </td>

                        <td>

                            <?php

                            $image_name =
                                $row["image"] ?? "";

                            $image_path =
                                "../../uploads/services/"
                                . $image_name;

                            ?>

                            <?php if (
                                !empty($image_name)
                                &&
                                file_exists($image_path)
                            ): ?>

                                <img
                                    src="<?= htmlspecialchars($image_path) ?>"
                                    class="service-image"
                                    alt="Service"
                                >

                            <?php else: ?>

                                <div class="no-image">

                                    🖼️
                                    <br>
                                    No Image

                                </div>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="service-name">

                                <?= htmlspecialchars(
                                    $row["service_name"] ?? ""
                                ) ?>

                            </div>

                            <?php if (
                                !empty($row["description"])
                            ): ?>

                                <div class="description">

                                    <?= htmlspecialchars(
                                        $row["description"]
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (
                                !empty($row["event_name"])
                            ): ?>

                                <span class="event-badge">

                                    🎉
                                    <?= htmlspecialchars(
                                        $row["event_name"]
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="no-event">

                                    No Event Assigned

                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="price">

                            Rs.
                            <?= number_format(
                                (float)($row["price"] ?? 0),
                                2
                            ) ?>

                        </td>

                        <td>

                            <?php if (
                                ($row["status"] ?? "")
                                === "active"
                            ): ?>

                                <span class="badge badge-active">

                                    🟢 Active

                                </span>

                            <?php else: ?>

                                <span class="badge badge-inactive">

                                    🔴 Inactive

                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="action-box">

                                <a
                                    href="edit.php?id=<?= (int)$row["id"] ?>"
                                    class="edit"
                                >

                                    ✏️ Edit

                                </a>

                                <a
                                    href="delete.php?id=<?= (int)$row["id"] ?>"
                                    class="delete"
                                    onclick="return confirm('Are you sure you want to delete this service?');"
                                >

                                    🗑️ Delete

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        class="empty"
                    >

                        🛎️

                        <br><br>

                        No services added yet.

                        <br><br>

                        Click
                        <b>➕ Add Service</b>
                        to add your first service.

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

const eventFilter =
    document.getElementById("eventFilter");

const table =
    document.getElementById("serviceTable");

function filterServices() {

    const search =
        searchBox.value
        .toLowerCase()
        .trim();

    const selectedEvent =
        eventFilter.value
        .toLowerCase()
        .trim();

    const rows =
        table.querySelectorAll("tbody tr");

    rows.forEach(function(row) {

        const cells = row.children;

        if (cells.length < 7) {

            return;

        }

        const serviceText =
            row.innerText
            .toLowerCase();

        const eventText =
            cells[3]
            .innerText
            .toLowerCase()
            .trim();

        const searchMatch =
            serviceText.includes(search);

        const eventMatch =
            selectedEvent === ""
            ||
            eventText.includes(selectedEvent);

        if (
            searchMatch &&
            eventMatch
        ) {

            row.style.display = "";

        } else {

            row.style.display = "none";

        }

    });

}

searchBox.addEventListener(
    "keyup",
    filterServices
);

eventFilter.addEventListener(
    "change",
    filterServices
);

setTimeout(function() {

    const messages =
        document.querySelectorAll(".message");

    messages.forEach(function(message) {

        message.style.transition =
            "opacity 0.5s";

        message.style.opacity = "0";

        setTimeout(function() {

            message.remove();

        }, 500);

    });

}, 4000);

</script>

</body>

</html>