<?php

require_once "../includes/auth.php";
require_once "../../database.php";

$result = $conn->query("
    SELECT *
    FROM events
    ORDER BY id ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Events | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8ef;
    color: #5a4148;
}

.main {
    margin-left: 250px;
    padding: 30px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-family: Georgia, serif;
    color: #7d4b5c;
}

.add-btn {
    text-decoration: none;
    background: linear-gradient(135deg, #f5c84c, #d9a62e);
    color: white;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: bold;
}

.table-box {
    background: #fffdf8;
    border-radius: 18px;
    padding: 20px;
    border: 1px solid #efd48a;
    box-shadow: 0 8px 25px rgba(180,130,30,0.08);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #ffdce9;
    color: #704c56;
    padding: 14px;
    text-align: left;
}

td {
    padding: 14px;
    border-bottom: 1px solid #f3e5c4;
}

.status {
    background: #fff0b8;
    color: #8b6800;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.action {
    text-decoration: none;
    margin-right: 8px;
    font-weight: bold;
}

.edit {
    color: #b8860b;
}

.delete {
    color: #c6536c;
}

@media(max-width:800px) {

    .main {
        margin-left: 0;
    }

}

</style>

</head>

<body>

<div class="main">

    <div class="header">

        <div>

            <h1>🎉 Manage Events</h1>

            <p>Manage all Event Planner event types</p>

        </div>

        <a href="add.php" class="add-btn">
            + Add Event
        </a>

    </div>


    <div class="table-box">

        <table>

            <tr>

                <th>ID</th>

                <th>Event Name</th>

                <th>Description</th>

                <th>Status</th>

                <th>Action</th>

            </tr>


            <?php while ($event = $result->fetch_assoc()): ?>

            <tr>

                <td>
                    <?= $event["id"] ?>
                </td>

                <td>
                    <strong>
                        <?= htmlspecialchars($event["event_name"]) ?>
                    </strong>
                </td>

                <td>
                    <?= htmlspecialchars($event["description"] ?? "") ?>
                </td>

                <td>

                    <span class="status">
                        <?= htmlspecialchars($event["status"]) ?>
                    </span>

                </td>

                <td>

                    <a
                        class="action edit"
                        href="edit.php?id=<?= $event["id"] ?>"
                    >
                        Edit
                    </a>

                    <a
                        class="action delete"
                        href="delete.php?id=<?= $event["id"] ?>"
                        onclick="return confirm('Delete this event?');"
                    >
                        Delete
                    </a>

                </td>

            </tr>

            <?php endwhile; ?>

        </table>

    </div>

</div>

</body>

</html>