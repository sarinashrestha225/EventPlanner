
<?php

require_once "../includes/auth.php";
require_once "../../database.php";

$provider_id = isset($_GET["provider_id"]) ? (int)$_GET["provider_id"] : 0;

if ($provider_id <= 0) {
    header("Location: index.php");
    exit;
}

$provider_stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        service_type,
        address,
        city,
        area,
        status
    FROM providers
    WHERE id = ?
    LIMIT 1
");

$provider_stmt->bind_param("i", $provider_id);
$provider_stmt->execute();

$provider_result = $provider_stmt->get_result();
$provider = $provider_result->fetch_assoc();

if (!$provider) {
    header("Location: index.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $service_ids = isset($_POST["service_ids"])
        ? $_POST["service_ids"]
        : [];

    if (!is_array($service_ids)) {
        $service_ids = [];
    }

    $service_ids = array_map("intval", $service_ids);
    $service_ids = array_unique($service_ids);

    $conn->begin_transaction();

    try {

        $delete = $conn->prepare("
            DELETE FROM provider_services
            WHERE provider_id = ?
        ");

        $delete->bind_param("i", $provider_id);
        $delete->execute();

        if (!empty($service_ids)) {

            $insert = $conn->prepare("
                INSERT INTO provider_services
                (
                    provider_id,
                    service_id,
                    price,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    0.00,
                    'active'
                )
            ");

            foreach ($service_ids as $service_id) {

                if ($service_id <= 0) {
                    continue;
                }

                $check = $conn->prepare("
                    SELECT id
                    FROM services
                    WHERE id = ?
                    AND status = 'active'
                    LIMIT 1
                ");

                $check->bind_param("i", $service_id);
                $check->execute();

                $check_result = $check->get_result();

                if ($check_result->num_rows > 0) {

                    $insert->bind_param(
                        "ii",
                        $provider_id,
                        $service_id
                    );

                    $insert->execute();
                }

                $check->close();
            }

            $insert->close();
        }

        $conn->commit();

        header(
            "Location: services.php?provider_id=" .
            $provider_id .
            "&saved=1"
        );

        exit;

    } catch (Exception $e) {

        $conn->rollback();

        $message = "Something went wrong while saving services.";
    }
}

if (isset($_GET["saved"]) && $_GET["saved"] == "1") {
    $message = "Provider services saved successfully.";
}

$assigned_services = [];

$assigned = $conn->prepare("
    SELECT service_id
    FROM provider_services
    WHERE provider_id = ?
    AND status = 'active'
");

$assigned->bind_param("i", $provider_id);
$assigned->execute();

$assigned_result = $assigned->get_result();

while ($row = $assigned_result->fetch_assoc()) {
    $assigned_services[] = (int)$row["service_id"];
}

$assigned->close();

$services = $conn->query("
    SELECT
        id,
        service_name,
        category,
        description,
        min_price,
        max_price,
        unit
    FROM services
    WHERE status = 'active'
    ORDER BY service_name ASC
");

if (!$services) {
    die("Services Query Error: " . $conn->error);
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

    <title>Provider Services</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff7fb;
            color: #333;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            color: #9d174d;
        }

        .subtitle {
            color: #777;
            margin-top: 7px;
        }

        .back {
            text-decoration: none;
            background: #fce7f3;
            color: #9d174d;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: bold;
        }

        .provider-box {
            background: white;
            padding: 22px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .provider-box h2 {
            margin: 0 0 12px;
            color: #9d174d;
        }

        .provider-info {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 25px;
            color: #666;
        }

        .services-box {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .service-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .service-top h2 {
            margin: 0;
            color: #b8860b;
        }

        .select-all {
            background: #fff4cc;
            padding: 10px 14px;
            border-radius: 8px;
            font-weight: bold;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fill,
                minmax(280px, 1fr)
            );
            gap: 15px;
        }

        .service-card {
            border: 1px solid #f1d8e3;
            border-radius: 12px;
            padding: 16px;
            background: #fff;
        }

        .service-card:hover {
            border-color: #d88aa9;
        }

        .service-label {
            display: flex;
            gap: 12px;
            cursor: pointer;
        }

        .service-checkbox {
            width: 18px;
            height: 18px;
            margin-top: 3px;
        }

        .service-name {
            font-weight: bold;
            color: #9d174d;
            margin-bottom: 7px;
        }

        .category {
            display: inline-block;
            background: #fff0f6;
            color: #9d174d;
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 11px;
            margin-bottom: 8px;
        }

        .description {
            color: #777;
            font-size: 13px;
            line-height: 1.5;
        }

        .price {
            margin-top: 9px;
            font-size: 12px;
            color: #555;
        }

        .bottom {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .save {
            border: none;
            background: #d88aa9;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .save:hover {
            background: #bd6e90;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #777;
        }

    </style>

</head>

<body>

<?php require_once "../includes/sidebar.php"; ?>

<div class="main-content">

    <div class="top">

        <div>

            <h1>Provider Services</h1>

            <div class="subtitle">
                Select the services this provider offers.
            </div>

        </div>

        <a href="index.php" class="back">
            ← Back to Providers
        </a>

    </div>

    <?php if ($message !== ""): ?>

        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <div class="provider-box">

        <h2>
            <?php echo htmlspecialchars($provider["name"]); ?>
        </h2>

        <div class="provider-info">

            <span>
                <strong>Email:</strong>
                <?php echo htmlspecialchars($provider["email"]); ?>
            </span>

            <span>
                <strong>Phone:</strong>
                <?php echo htmlspecialchars($provider["phone"] ?? "-"); ?>
            </span>

            <span>
                <strong>Service Type:</strong>
                <?php echo htmlspecialchars($provider["service_type"] ?? "-"); ?>
            </span>

            <span>
                <strong>Location:</strong>

                <?php

                $location = trim(
                    ($provider["area"] ?? "") .
                    (
                        !empty($provider["area"]) &&
                        !empty($provider["city"])
                        ? ", "
                        : ""
                    ) .
                    ($provider["city"] ?? "")
                );

                if ($location === "") {
                    $location = $provider["address"] ?? "-";
                }

                echo htmlspecialchars($location);

                ?>

            </span>

        </div>

    </div>

    <div class="services-box">

        <div class="service-top">

            <h2>Select Services</h2>

            <label class="select-all">

                <input
                    type="checkbox"
                    id="selectAll"
                >

                Select All

            </label>

        </div>

        <form method="POST">

            <?php if ($services->num_rows > 0): ?>

                <div class="service-grid">

                    <?php while ($service = $services->fetch_assoc()): ?>

                        <?php

                        $service_id = (int)$service["id"];

                        $checked = in_array(
                            $service_id,
                            $assigned_services,
                            true
                        );

                        ?>

                        <div class="service-card">

                            <label class="service-label">

                                <input
                                    type="checkbox"
                                    name="service_ids[]"
                                    value="<?php echo $service_id; ?>"
                                    class="service-checkbox"
                                    <?php echo $checked ? "checked" : ""; ?>
                                >

                                <div>

                                    <div class="service-name">
                                        <?php
                                        echo htmlspecialchars(
                                            $service["service_name"]
                                        );
                                        ?>
                                    </div>

                                    <?php if (!empty($service["category"])): ?>

                                        <span class="category">
                                            <?php
                                            echo htmlspecialchars(
                                                $service["category"]
                                            );
                                            ?>
                                        </span>

                                    <?php endif; ?>

                                    <?php if (!empty($service["description"])): ?>

                                        <div class="description">
                                            <?php
                                            echo htmlspecialchars(
                                                $service["description"]
                                            );
                                            ?>
                                        </div>

                                    <?php endif; ?>

                                    <div class="price">

                                        Rs.
                                        <?php
                                        echo number_format(
                                            (float)$service["min_price"]
                                        );
                                        ?>

                                        -

                                        Rs.
                                        <?php
                                        echo number_format(
                                            (float)$service["max_price"]
                                        );
                                        ?>

                                        /

                                        <?php
                                        echo htmlspecialchars(
                                            $service["unit"]
                                        );
                                        ?>

                                    </div>

                                </div>

                            </label>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="empty">
                    No active services found.
                </div>

            <?php endif; ?>

            <div class="bottom">

                <a href="index.php" class="back">
                    ← Back
                </a>

                <button type="submit" class="save">
                    Save Services
                </button>

            </div>

        </form>

    </div>

</div>

<script>

const selectAll = document.getElementById("selectAll");

const checkboxes = document.querySelectorAll(
    ".service-checkbox"
);

selectAll.addEventListener("change", function() {

    checkboxes.forEach(function(checkbox) {

        checkbox.checked = selectAll.checked;

    });

});

checkboxes.forEach(function(checkbox) {

    checkbox.addEventListener("change", function() {

        const total = checkboxes.length;

        const checked = document.querySelectorAll(
            ".service-checkbox:checked"
        ).length;

        selectAll.checked =
            total > 0 &&
            total === checked;

    });

});

</script>

</body>
</html>