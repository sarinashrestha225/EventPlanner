<?php

require_once "../../database.php";

require_once "../includes/auth.php";

$sql = "
    SELECT
        id,
        user_id,
        user_role,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {

    die(
        "Database Error: " .
        htmlspecialchars($conn->error)
    );

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
        Notifications | Event Planner Admin
    </title>

    <style>

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            font-family: Arial, sans-serif;

        }

        body {

            background: #fffaf0;

            color: #5a4630;

        }

        .main-content {

            margin-left: 250px;

            padding: 30px;

            min-height: 100vh;

        }

        .page-header {

            background:
                linear-gradient(
                    135deg,
                    #fff2a8,
                    #ffd75e,
                    #f8c1d4
                );

            padding: 25px 30px;

            border-radius: 20px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.15
                );

        }

        .page-header h1 {

            color: #704800;

            font-size: 30px;

            margin-bottom: 7px;

        }

        .page-header p {

            color: #765d40;

            font-size: 14px;

        }

        .success {

            background: #e5f4d2;

            border: 1px solid #bddb94;

            color: #4e7528;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

        }

        .error {

            background: #ffe0e8;

            border: 1px solid #e5a5b7;

            color: #9a3655;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

        }

        .card {

            background: #fffdf7;

            border: 1px solid #f0d88a;

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 5px 20px
                rgba(
                    218,
                    165,
                    32,
                    0.10
                );

        }

        .table-container {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;

        }

        thead {

            background: #f6d568;

        }

        th {

            padding: 14px;

            text-align: left;

            color: #674300;

            font-size: 13px;

            white-space: nowrap;

        }

        td {

            padding: 13px;

            border-bottom:
                1px solid #f1dfb0;

            font-size: 13px;

            vertical-align: middle;

        }

        tbody tr:nth-child(even) {

            background: #fff3f6;

        }

        tbody tr:hover {

            background: #fff0b3;

        }

        .id {

            font-weight: bold;

            color: #8a6200;

        }

        .role {

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

        }

        .customer {

            background: #f8c1d4;

            color: #8a3f5d;

        }

        .provider {

            background: #fff0a6;

            color: #705000;

        }

        .admin {

            background: #e7d8ff;

            color: #7040a0;

        }

        .title {

            color: #704800;

            font-weight: bold;

        }

        .message {

            max-width: 300px;

            color: #765d40;

            line-height: 1.5;

        }

        .type {

            padding: 6px 10px;

            border-radius: 20px;

            background: #fff0a6;

            color: #705000;

            font-size: 11px;

            font-weight: bold;

        }

        .read {

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

        }

        .read-yes {

            background: #dff3c4;

            color: #4e7528;

        }

        .read-no {

            background: #ffd2df;

            color: #a23d5d;

        }

        .delete {

            display: inline-block;

            text-decoration: none;

            padding: 7px 11px;

            border-radius: 7px;

            background: #f4caca;

            color: #9b2929;

            font-size: 11px;

            font-weight: bold;

        }

        .delete:hover {

            opacity: 0.85;

        }

        .empty {

            text-align: center;

            padding: 50px;

            color: #8c7858;

        }

        @media (max-width: 900px) {

            .main-content {

                margin-left: 0;

                padding: 15px;

            }

        }

    </style>

</head>

<body>

<?php

include "../includes/sidebar.php";

?>

<div class="main-content">

    <div class="page-header">

        <h1>
            🔔 Notifications
        </h1>

        <p>
            Manage customer, provider and admin notifications
        </p>

    </div>

    <?php if (isset($_GET["deleted"])): ?>

        <div class="success">

            ✅ Notification deleted successfully.

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="error">

            ⚠️

            <?php

            if (
                $_GET["error"] === "invalid_id"
            ) {

                echo "Invalid notification ID.";

            }

            elseif (
                $_GET["error"] === "not_found"
            ) {

                echo "Notification not found.";

            }

            else {

                echo "Something went wrong.";

            }

            ?>

        </div>

    <?php endif; ?>

    <div class="card">

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Title
                        </th>

                        <th>
                            Message
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Read
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while (
                        $notification =
                        $result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                <span class="id">

                                    #
                                    <?= (int)$notification["id"] ?>

                                </span>

                            </td>

                            <td>

                                <?php

                                if (
                                    $notification["user_id"]
                                    !== null
                                ) {

                                    echo "#" .
                                        (int)$notification["user_id"];

                                } else {

                                    echo "All Users";

                                }

                                ?>

                            </td>

                            <td>

                                <span
                                    class="role
                                    <?= htmlspecialchars(
                                        $notification["user_role"]
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $notification["user_role"]
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <span class="title">

                                    <?= htmlspecialchars(
                                        $notification["title"]
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <div class="message">

                                    <?php

                                    $message =
                                        $notification["message"];

                                    if (
                                        mb_strlen($message) > 80
                                    ) {

                                        echo htmlspecialchars(
                                            mb_substr(
                                                $message,
                                                0,
                                                80
                                            ) . "..."
                                        );

                                    } else {

                                        echo htmlspecialchars(
                                            $message
                                        );

                                    }

                                    ?>

                                </div>

                            </td>

                            <td>

                                <span class="type">

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $notification["type"]
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?php

                                $is_read =
                                    (int)$notification["is_read"];

                                ?>

                                <?php if ($is_read === 1): ?>

                                    <span
                                        class="read read-yes"
                                    >

                                        ✓ Read

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="read read-no"
                                    >

                                        ● Unread

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $notification["created_at"]
                                ) ?>

                            </td>

                            <td>

                                <a
                                    href="delete.php?id=<?= (int)$notification["id"] ?>"
                                    class="delete"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this notification?'
                                        );
                                    "
                                >

                                    🗑 Delete

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

                            🔔 No notifications found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>