
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["admin_id"])) {

    if (isset($_COOKIE["admin_id"])) {

        require_once __DIR__ . "/../../database.php";

        $admin_id = (int) $_COOKIE["admin_id"];

        if ($admin_id > 0) {

            $stmt = $conn->prepare("
                SELECT id, name
                FROM admins
                WHERE id = ?
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param("i", $admin_id);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {

                    $admin = $result->fetch_assoc();

                    $_SESSION["admin_id"] = (int) $admin["id"];
                    $_SESSION["admin_name"] = $admin["name"];
                    $_SESSION["user_id"] = (int) $admin["id"];
                    $_SESSION["user_role"] = "admin";
                }

                $stmt->close();
            }
        }
    }
}

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["user_id"])) {
    $_SESSION["user_id"] = $_SESSION["admin_id"];
}

if (!isset($_SESSION["user_role"])) {
    $_SESSION["user_role"] = "admin";
}

?>

