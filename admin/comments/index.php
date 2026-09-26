<?php

require_once "../includes/auth.php";
require_once "../../database.php";



$sql = "
    SELECT
        id,
        user_id,
        user_name,
        comment,
        service_id,
        service_name,
        review_id,
        status,
        created_at
    FROM comments
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Query Error: " . $conn->error);
}




$total_comments = $result->num_rows;

$approved_count = 0;
$pending_count = 0;
$hidden_count = 0;


$count_sql = "
    SELECT
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Hidden') AS hidden
    FROM comments
";

$count_result = $conn->query($count_sql);

if ($count_result) {

    $count_row = $count_result->fetch_assoc();

    $approved_count = (int)($count_row['approved'] ?? 0);

    $pending_count = (int)($count_row['pending'] ?? 0);

    $hidden_count = (int)($count_row['hidden'] ?? 0);
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
    Comments | Event Planner Admin
</title>


<style>


* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}




body {

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #fffaf3,
            #fff1f5,
            #fff9df
        );

    color: #5b4148;

    min-height: 100vh;
}



.main {

    margin-left: 250px;

    padding: 30px;
}


.page-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 25px;
}


.page-header h1 {

    color: #754858;

    font-family:
        Georgia,
        serif;

    font-size: 30px;
}


.page-header p {

    margin-top: 5px;

    color: #967b82;

    font-size: 14px;
}



.back-btn {

    text-decoration: none;

    background:
        linear-gradient(
            90deg,
            #f4d26a,
            #ffe9a5
        );

    color: #674700;

    padding: 11px 18px;

    border-radius: 10px;

    font-weight: bold;

    border:
        1px solid #d8b442;
}


.back-btn:hover {

    opacity: .85;
}




.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 22px;
}


.stat-card {

    background:
        rgba(255,255,255,.92);

    border:
        1px solid #eed58a;

    border-radius: 16px;

    padding: 18px;

    box-shadow:
        0 5px 18px
        rgba(160,110,20,.07);
}


.stat-title {

    color: #8d7379;

    font-size: 13px;
}


.stat-number {

    margin-top: 5px;

    font-size: 25px;

    font-weight: bold;

    color: #aa7b00;
}




.card {

    background:
        rgba(255,255,255,.95);

    border:
        1px solid #eed58a;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 5px 20px
        rgba(160,110,20,.08);

    overflow-x: auto;
}



table {

    width: 100%;

    border-collapse:
        collapse;

    min-width: 1100px;
}


thead {

    background:
        linear-gradient(
            90deg,
            #d4af37,
            #e6c65c
        );

    color: white;
}


th {

    padding: 14px 12px;

    text-align: left;

    font-size: 13px;

    white-space:
        nowrap;
}


td {

    padding: 14px 12px;

    border-bottom:
        1px solid #f0e7d0;

    font-size: 13px;

    vertical-align:
        middle;
}


tbody tr:hover {

    background:
        #fff7f9;
}


.user-name {

    font-weight: bold;

    color: #754858;
}




.comment-text {

    max-width: 300px;

    line-height: 1.5;

    color: #5b4148;
}




.service {

    color: #9a7010;

    font-weight: bold;
}




.review-id {

    color: #777;

    font-weight: bold;
}




.status {

    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}


.status-approved {

    background:
        #d4edda;

    color:
        #155724;
}


.status-pending {

    background:
        #fff3cd;

    color:
        #856404;
}


.status-hidden {

    background:
        #e2e3e5;

    color:
        #383d41;
}




.actions {

    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}


.btn {

    text-decoration: none;

    display: inline-block;

    padding: 7px 10px;

    border-radius: 7px;

    font-size: 11px;

    font-weight: bold;

    white-space:
        nowrap;
}


.view {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.approve {

    background:
        #d4edda;

    color:
        #155724;
}


.hide {

    background:
        #e2e3e5;

    color:
        #383d41;
}


.delete {

    background:
        #f8d7da;

    color:
        #721c24;
}


.btn:hover {

    opacity: .75;
}




.empty {

    text-align: center;

    padding: 60px 20px;

    color: #777;
}


.empty-icon {

    font-size: 45px;

    margin-bottom: 10px;
}


.empty h3 {

    color: #754858;

    margin-bottom: 5px;
}



@media(max-width:900px) {

    .main {

        margin-left: 210px;

        padding: 20px;
    }


    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media(max-width:600px) {

    .main {

        margin-left: 0;

        padding: 15px;
    }


    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }


    .stats {

        grid-template-columns:
            1fr;
    }

}

</style>

</head>


<body>


<?php

require_once "../includes/sidebar.php";

?>


<div class="main">


    

    <div class="page-header">

        <div>

            <h1>
                💬 Comments
            </h1>

            <p>
                Manage customer comments and feedback.
            </p>

        </div>


        <a
            href="../dashboard.php"
            class="back-btn"
        >
            ← Dashboard
        </a>

    </div>


   

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">
                💬 Total Comments
            </div>

            <div class="stat-number">

                <?php
                echo $total_comments;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                ⏳ Pending
            </div>

            <div class="stat-number">

                <?php
                echo $pending_count;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                ✅ Approved
            </div>

            <div class="stat-number">

                <?php
                echo $approved_count;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                🚫 Hidden
            </div>

            <div class="stat-number">

                <?php
                echo $hidden_count;
                ?>

            </div>

        </div>

    </div>


    <div class="card">


        <?php if ($total_comments > 0): ?>


        <table>


            <thead>

                <tr>

                    <th>
                        👤 User Name
                    </th>

                    <th>
                        📝 Comment
                    </th>

                    <th>
                        📅 Date
                    </th>

                    <th>
                        📌 Related Service
                    </th>

                    <th>
                        ⭐ Related Review
                    </th>

                    <th>
                        📊 Status
                    </th>

                    <th>
                        ⚙ Actions
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php

            while (
                $row =
                $result->fetch_assoc()
            ):

            ?>


                <?php

                $status =
                    $row['status']
                    ?? 'Pending';


                if (
                    $status === 'Approved'
                ) {

                    $status_class =
                        'status-approved';

                } elseif (
                    $status === 'Hidden'
                ) {

                    $status_class =
                        'status-hidden';

                } else {

                    $status_class =
                        'status-pending';
                }

                ?>


                <tr>


                    <!-- USER -->

                    <td>

                        <div class="user-name">

                            👤

                            <?php

                            echo htmlspecialchars(
                                $row['user_name']
                                ?? 'Unknown'
                            );

                            ?>

                        </div>

                    </td>


                    <!-- COMMENT -->

                    <td>

                        <div class="comment-text">

                            <?php

                            echo htmlspecialchars(
                                $row['comment']
                                ?? ''
                            );

                            ?>

                        </div>

                    </td>


                    <!-- DATE -->

                    <td>

                        <?php

                        if (
                            !empty(
                                $row['created_at']
                            )
                        ) {

                            echo date(
                                "d M Y",
                                strtotime(
                                    $row['created_at']
                                )
                            );

                        } else {

                            echo "-";
                        }

                        ?>

                    </td>


                    <!-- SERVICE -->

                    <td>

                        <?php

                        if (
                            !empty(
                                $row['service_name']
                            )
                        ):

                        ?>

                            <span class="service">

                                📌

                                <?php

                                echo htmlspecialchars(
                                    $row['service_name']
                                );

                                ?>

                            </span>

                        <?php

                        elseif (
                            !empty(
                                $row['service_id']
                            )
                        ):

                        ?>

                            Service
                            #<?php
                            echo (int)
                                $row['service_id'];
                            ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>


                    <!-- REVIEW -->

                    <td>

                        <?php

                        if (
                            !empty(
                                $row['review_id']
                            )
                        ):

                        ?>

                            <span class="review-id">

                                ⭐ Review #

                                <?php

                                echo (int)
                                    $row['review_id'];

                                ?>

                            </span>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>


                    <!-- STATUS -->

                    <td>

                        <span
                            class="status
                            <?php
                            echo $status_class;
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $status
                            );

                            ?>

                        </span>

                    </td>


                    <!-- ACTIONS -->

                    <td>

                        <div class="actions">


                            <!-- VIEW -->

                            <a
                                href="view.php?id=<?php echo (int)$row['id']; ?>"
                                class="btn view"
                            >

                                🔍 View

                            </a>


                            <!-- APPROVE -->

                            <?php

                            if (
                                $status !==
                                'Approved'
                            ):

                            ?>

                                <a
                                    href="approve.php?id=<?php echo (int)$row['id']; ?>"
                                    class="btn approve"
                                    onclick="
                                        return confirm(
                                            'Approve this comment?'
                                        );
                                    "
                                >

                                    ✅ Approve

                                </a>

                            <?php endif; ?>


                            <!-- HIDE -->

                            <?php

                            if (
                                $status !==
                                'Hidden'
                            ):

                            ?>

                                <a
                                    href="hide.php?id=<?php echo (int)$row['id']; ?>"
                                    class="btn hide"
                                    onclick="
                                        return confirm(
                                            'Hide this comment from public?'
                                        );
                                    "
                                >

                                    🚫 Hide

                                </a>

                            <?php endif; ?>


                            <!-- DELETE -->

                            <a
                                href="delete.php?id=<?php echo (int)$row['id']; ?>"
                                class="btn delete"
                                onclick="
                                    return confirm(
                                        'Are you sure you want to delete this comment?'
                                    );
                                "
                            >

                                🗑 Delete

                            </a>


                        </div>

                    </td>


                </tr>


            <?php endwhile; ?>


            </tbody>

        </table>


        <?php else: ?>


        <div class="empty">

            <div class="empty-icon">
                📭
            </div>

            <h3>
                No Comments Found
            </h3>

            <p>
                Customer comments will appear here.
            </p>

        </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>


<?php

$conn->close();

?>