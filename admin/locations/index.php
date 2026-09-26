<?php

require_once "../includes/auth.php";
require_once "../../database.php";

$search = trim($_GET['search'] ?? '');
$city = trim($_GET['city'] ?? '');

$where = [];

if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where[] = "(city LIKE '%$safe_search%' OR area LIKE '%$safe_search%')";
}

if ($city !== '') {
    $safe_city = $conn->real_escape_string($city);
    $where[] = "city = '$safe_city'";
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

$sql = "SELECT id, city, area, status, created_at
        FROM locations
        $where_sql
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

$cities_result = $conn->query("SELECT DISTINCT city FROM locations ORDER BY city ASC");

include "../includes/header.php";
include "../includes/sidebar.php";

?>

<div class="admin-content">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px;">

        <div>
            <h1 class="page-title">Locations</h1>
            <p style="color:#888; margin-top:5px;">
                Manage cities and areas for Event Planner
            </p>
        </div>

        <a href="add.php" class="btn btn-primary">
            Add Location
        </a>

    </div>

    <div class="card" style="margin-bottom:20px;">

        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="Search city or area..."
                style="
                    flex:1;
                    min-width:220px;
                    padding:12px 15px;
                    border:1px solid #ddd;
                    border-radius:8px;
                    font-size:14px;
                "
            >

            <select
                name="city"
                style="
                    padding:12px 15px;
                    border:1px solid #ddd;
                    border-radius:8px;
                    min-width:180px;
                    font-size:14px;
                "
            >

                <option value="">All Cities</option>

                <?php if ($cities_result && $cities_result->num_rows > 0): ?>

                    <?php while ($city_row = $cities_result->fetch_assoc()): ?>

                        <option
                            value="<?= htmlspecialchars($city_row['city'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $city === $city_row['city'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($city_row['city'], ENT_QUOTES, 'UTF-8') ?>
                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>

            <?php if ($search !== '' || $city !== ''): ?>

                <a
                    href="index.php"
                    class="btn btn-warning"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>

    <div class="card">

        <div style="margin-bottom:15px; color:#777; font-size:14px;">
            <?php if ($search !== '' || $city !== ''): ?>
                Search results: <?= $result->num_rows ?>
            <?php else: ?>
                All Locations: <?= $result->num_rows ?>
            <?php endif; ?>
        </div>

        <div style="overflow-x:auto;">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>City</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= (int)$row['id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['area'], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>

                                <?php if ($row['status'] === 'active'): ?>

                                    <span style="
                                        display:inline-block;
                                        background:#dff9e8;
                                        color:#008f4c;
                                        padding:6px 12px;
                                        border-radius:20px;
                                        font-size:13px;
                                        font-weight:600;
                                    ">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span style="
                                        display:inline-block;
                                        background:#ffe5e5;
                                        color:#c62828;
                                        padding:6px 12px;
                                        border-radius:20px;
                                        font-size:13px;
                                        font-weight:600;
                                    ">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div style="display:flex; gap:8px; flex-wrap:wrap;">

                                    <a
                                        href="edit.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?= (int)$row['id'] ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this location?');"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="5" style="text-align:center; padding:30px; color:#888;">
                            No locations found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>