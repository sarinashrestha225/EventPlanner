
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$provider_name = $_SESSION['provider_name'] ?? 'Provider';

$current_page = basename($_SERVER['PHP_SELF']);

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
    <?= htmlspecialchars($page_title ?? 'Provider Panel', ENT_QUOTES, 'UTF-8') ?>
    - Event Planner
</title>


<style>

/* =====================================================
   RESET
===================================================== */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #fff8f0;

    color: #4b3621;
}


/* =====================================================
   TOP HEADER
===================================================== */

.top-header {

    position: fixed;

    top: 0;

    left: 0;

    right: 0;

    height: 70px;

    background: #ffffff;

    border-bottom:
        1px solid #eee;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 25px;

    z-index: 1000;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);
}


/* =====================================================
   LOGO
===================================================== */

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 21px;

    font-weight: bold;

    color: #4b3621;

    text-decoration: none;
}


.logo-icon {

    font-size: 28px;
}


/* =====================================================
   PROVIDER AREA
===================================================== */

.provider-area {

    display: flex;

    align-items: center;

    gap: 15px;
}


.provider-name {

    font-weight: bold;

    color: #4b3621;
}


.logout-btn {

    background: #f8d7da;

    color: #721c24;

    padding: 9px 14px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 14px;

    font-weight: bold;
}


.logout-btn:hover {

    background: #f1bfc4;
}


/* =====================================================
   PAGE CONTENT
===================================================== */

.main-content {

    margin-left: 250px;

    padding: 95px 30px 30px;

    min-height: 100vh;
}


/* =====================================================
   COMMON CARD
===================================================== */

.card {

    background: #ffffff;

    border-radius: 14px;

    padding: 22px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.06);
}


/* =====================================================
   PAGE TITLE
===================================================== */

.page-title {

    margin: 0 0 8px;

    color: #4b3621;
}


.page-subtitle {

    margin: 0 0 25px;

    color: #777;
}


/* =====================================================
   BUTTON
===================================================== */

.btn {

    display: inline-block;

    padding: 10px 16px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: bold;

    border: none;

    cursor: pointer;
}


.btn-primary {

    background: #d4af37;

    color: #ffffff;
}


.btn-primary:hover {

    background: #b8860b;
}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width: 800px) {

    .top-header {

        padding: 0 15px;
    }


    .provider-name {

        display: none;
    }


    .main-content {

        margin-left: 0;

        padding:
            90px 15px 25px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     TOP HEADER
===================================================== -->

<header class="top-header">


    <!-- LOGO -->

    <a
        href="../dashboard.php"
        class="logo"
    >

        <span class="logo-icon">
            🎉
        </span>

        <span>
            Event Planner
        </span>

    </a>


    <!-- PROVIDER -->

    <div class="provider-area">

        <span class="provider-name">

            👤

            <?= htmlspecialchars(
                $provider_name,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>


        <a
            href="../logout.php"
            class="logout-btn"
        >

            🚪 Logout

        </a>

    </div>


</header>


<!-- =====================================================
     MAIN CONTENT START
===================================================== -->

<main class="main-content">

