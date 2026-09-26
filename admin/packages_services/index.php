
<?php

require_once "../includes/auth.php";
require_once "../../database.php";

$sql = "
    SELECT
        ps.id,
        p.package_name AS package_name,
        s.service_name AS service_name,
        s.price AS service_price
    FROM package_services ps
    INNER JOIN packages p
        ON ps.package_id = p.id
    INNER JOIN services s
        ON ps.service_id = s.id
    ORDER BY ps.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="admin-content">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px;">

        <div>
            <h1 class="page-title">Package Services</h1>

            <p style="color:#888; margin-top:5px;">
                Manage services assigned to packages
            </p>
        </div>

        <a href="add.php" class="btn btn-primary">
            + Add Service to Package
        </a>

    </div>

    <div class="card">

        <div style="overflow-x:auto;">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Package</th>
                        <th>Service</th>
                        <th>Service Price</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= (int)$row["id"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["package_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["service_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                Rs.
                                <?= number_format(
                                    (float)$row["service_price"],
                                    2
                                ) ?>
                            </td>

                            <td>

                                <a
                                    href="delete.php?id=<?= (int)$row["id"] ?>"
                                    class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to remove this service from the package?');"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="5"
                            style="text-align:center; padding:30px; color:#888;"
                        >
                            No services have been added to packages yet.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>

