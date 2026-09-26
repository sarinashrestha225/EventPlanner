<?php

session_start();

require_once "../../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $city = trim($_POST["city"] ?? "");
    $area = trim($_POST["area"] ?? "");
    $status = $_POST["status"] ?? "active";

    if ($city === "" || $area === "") {

        $message = "City and area are required.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $message = "Invalid status.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO locations (city, area, status) VALUES (?, ?, ?)"
        );

        if (!$stmt) {

            $message = "Database Error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "sss",
                $city,
                $area,
                $status
            );

            if ($stmt->execute()) {

                $stmt->close();

                header("Location: index.php?success=added");
                exit();

            } else {

                $message = "Failed to add location: " . $stmt->error;

                $stmt->close();
            }
        }
    }
}

include "../includes/header.php";
include "../includes/sidebar.php";

?>

<div class="admin-content">

    <h1 class="page-title">
        Add Location
    </h1>

    <div class="card" style="max-width:700px;">

        <?php if ($message !== ""): ?>

            <div style="
                background:#ffe5e5;
                color:#c62828;
                padding:12px;
                border-radius:8px;
                margin-bottom:20px;
            ">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div style="margin-bottom:18px;">

                <label>
                    <strong>City</strong>
                </label>

                <input
                    type="text"
                    name="city"
                    placeholder="Enter city"
                    required
                    style="width:100%; padding:12px; margin-top:7px;"
                >

            </div>


            <div style="margin-bottom:18px;">

                <label>
                    <strong>Area</strong>
                </label>

                <input
                    type="text"
                    name="area"
                    placeholder="Enter area"
                    required
                    style="width:100%; padding:12px; margin-top:7px;"
                >

            </div>


            <div style="margin-bottom:20px;">

                <label>
                    <strong>Status</strong>
                </label>

                <select
                    name="status"
                    style="width:100%; padding:12px; margin-top:7px;"
                >

                    <option value="active">
                        Active
                    </option>

                    <option value="inactive">
                        Inactive
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Location
            </button>

            <a
                href="index.php"
                class="btn"
                style="margin-left:8px;"
            >
                Cancel
            </a>

        </form>

    </div>

</div>

<?php include "../includes/footer.php"; ?>