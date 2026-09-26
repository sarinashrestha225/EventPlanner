<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION["admin_logged_in"]) ||
    empty($_SESSION["admin_logged_in"]) ||
    !isset($_SESSION["admin_id"])
) {
    header("Location: ../login.php");
    exit();
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

    <title>Event Planner - Admin Panel</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff8f5;
            color: #4d3a40;
        }

        a {
            text-decoration: none;
        }

        .admin-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 25px;
            color: #6b4050;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 3px 14px rgba(170, 120, 80, 0.10);
            margin-bottom: 20px;
            border: 1px solid #f3dfd7;
        }

        button,
        .btn {
            border: none;
            padding: 10px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
        }

        .btn-primary {
            background: #e7b84b;
            color: #5b3d18;
        }

        .btn-primary:hover {
            background: #d9a936;
        }

        .btn-success {
            background: #d98b9b;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #c97889;
        }

        .btn-danger {
            background: #d86b72;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #c95b63;
        }

        .btn-warning {
            background: #f3d58a;
            color: #5b461c;
        }

        .btn-warning:hover {
            background: #eac76d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        table th,
        table td {
            padding: 13px;
            border-bottom: 1px solid #f0e1dc;
            text-align: left;
        }

        table th {
            background: #ffe7ee;
            color: #704653;
        }

        table tr:hover td {
            background: #fff9f6;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #e6cfc8;
            border-radius: 7px;
            margin-top: 6px;
            margin-bottom: 15px;
            background: #fffdfc;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #e7b84b;
        }

        label {
            font-weight: 600;
            color: #674650;
        }

        @media (max-width: 768px) {

            .admin-content {
                margin-left: 0;
                padding: 15px;
            }

        }

    </style>

</head>

<body></body>