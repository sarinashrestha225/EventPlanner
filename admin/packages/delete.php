<?php

session_start();

require_once __DIR__ . "/../../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {
    header("Location: index.php");
    exit();
}

$package_id = (int) $_GET["id"];

$package_stmt = $conn->prepare("
    SELECT
        id,
        image
    FROM packages
    WHERE id = ?
    LIMIT 1
");

$package_stmt->bind_param(
    "i",
    $package_id
);

$package_stmt->execute();

$package_result =
    $package_stmt->get_result();

if (
    $package_result->num_rows !== 1
) {
    header("Location: index.php");
    exit();
}

$package =
    $package_result->fetch_assoc();

$image =
    $package["image"] ?? "";

$conn->begin_transaction();

try {

    $delete_services =
        $conn->prepare("
            DELETE FROM package_services
            WHERE package_id = ?
        ");

    $delete_services->bind_param(
        "i",
        $package_id
    );

    if (
        !$delete_services->execute()
    ) {
        throw new Exception(
            "Failed to delete package services: " .
            $delete_services->error
        );
    }

    $delete_pricing =
        $conn->prepare("
            DELETE FROM package_pricing
            WHERE package_id = ?
        ");

    $delete_pricing->bind_param(
        "i",
        $package_id
    );

    if (
        !$delete_pricing->execute()
    ) {
        throw new Exception(
            "Failed to delete package pricing: " .
            $delete_pricing->error
        );
    }

    $delete_package =
        $conn->prepare("
            DELETE FROM packages
            WHERE id = ?
        ");

    $delete_package->bind_param(
        "i",
        $package_id
    );

    if (
        !$delete_package->execute()
    ) {
        throw new Exception(
            "Failed to delete package: " .
            $delete_package->error
        );
    }

    $conn->commit();

    if (!empty($image)) {

        $image_file =
            __DIR__ .
            "/../../" .
            $image;

        if (
            file_exists($image_file)
        ) {
            unlink($image_file);
        }
    }

    header(
        "Location: index.php?success=deleted"
    );

    exit();

} catch (Exception $e) {

    $conn->rollback();

    die(
        "Package could not be deleted: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        )
    );
}
?>