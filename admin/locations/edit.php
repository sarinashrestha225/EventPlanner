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

if (
    !isset($_GET["id"]) ||
    !ctype_digit((string)$_GET["id"])
) {
    header("Location: index.php?error=invalid_id");
    exit();
}

$id = (int)$_GET["id"];

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $city = trim($_POST["city"] ?? "");
    $area = trim($_POST["area"] ?? "");
    $status = $_POST["status"] ?? "active";

    if ($city === "" || $area === "") {

        $error = "City and area are required.";

    } elseif (!in_array($status, ["active", "inactive"], true)) {

        $error = "Invalid status.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE locations
             SET city = ?, area = ?, status = ?
             WHERE id = ?"
        );

        if (!$stmt) {

            $error = "Database Error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "sssi",
                $city,
                $area,
                $status,
                $id
            );

            if ($stmt->execute()) {

                $stmt->close();

                header("Location: index.php?success=updated");
                exit();

            } else {

                $error = "Failed to update location: " . $stmt->error;

                $stmt->close();
            }
        }
    }
}


$stmt = $conn->prepare(
    "SELECT id, city, area, status
     FROM locations
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: index.php?error=not_found");
    exit();
}

$location = $result->fetch_assoc();

$stmt->close();


include "../includes/header.php";
include "../includes/sidebar.php";

?>

<div class="admin-content">

    <h1 class="page-title">
        Edit Location
    </h1>


    <div class="card" style="max-width:700px;">

        <?php if ($error !== ""): ?>

            <div style="
                background:#ffe5e5;
                color:#c62828;
                padding:12px;
                border-radius:8px;
                margin-bottom:20px;
            ">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
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
                    value="<?= htmlspecialchars($location['city'], ENT_QUOTES, 'UTF-8') ?>"
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
                    value="<?= htmlspecialchars($location['area'], ENT_QUOTES, 'UTF-8') ?>"
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

                    <option
                        value="active"
                        <?= $location['status'] === 'active' ? 'selected' : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $location['status'] === 'inactive' ? 'selected' : '' ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Update Location
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