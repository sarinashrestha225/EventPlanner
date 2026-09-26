<?php

require_once "../includes/auth.php";
require_once "../../database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM providers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$provider = $result->fetch_assoc();

$stmt->close();

$statusClass = "pending";

if ($provider['status'] === "active") {
    $statusClass = "active";
} elseif ($provider['status'] === "inactive") {
    $statusClass = "inactive";
} elseif ($provider['status'] === "rejected") {
    $statusClass = "rejected";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Provider | Event Planner</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff8f2;
            color: #4b3b2f;
        }

        .container {
            width: 92%;
            max-width: 1100px;
            margin: 30px auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0;
            color: #b8860b;
        }

        .back-btn {
            text-decoration: none;
            background: #f8d7da;
            color: #7a3b42;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .back-btn:hover {
            background: #f3c2c7;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-top: 5px solid #d4af37;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-icon {
            width: 90px;
            height: 90px;
            margin: auto;
            border-radius: 50%;
            background: #fff0f5;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 42px;
        }

        .profile-header h2 {
            margin: 12px 0 5px;
            color: #6b4f3a;
        }

        .profile-header p {
            margin: 0;
            color: #888;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .detail-box {
            background: #fffaf5;
            padding: 18px;
            border-radius: 10px;
            border: 1px solid #f0dfc8;
        }

        .detail-box strong {
            display: block;
            color: #a07800;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .detail-box span {
            font-size: 16px;
            color: #4b3b2f;
            word-break: break-word;
        }

        .status {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status.active {
            background: #d4edda;
            color: #155724;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .documents {
            margin-top: 30px;
        }

        .documents h3 {
            color: #b8860b;
            border-bottom: 2px solid #f1dfbd;
            padding-bottom: 10px;
        }

        .document-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .document-card {
            background: #fffaf5;
            border: 1px solid #f0dfc8;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }

        .document-card h4 {
            margin-top: 0;
            color: #6b4f3a;
        }

        .document-card img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .no-file {
            color: #999;
            padding: 30px;
        }

        .actions {
            margin-top: 30px;
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            text-decoration: none;
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: bold;
        }

        .edit-btn {
            background: #d4af37;
            color: white;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
        }

        @media (max-width: 700px) {

            .details,
            .document-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <h1>👨‍💼 Provider Details</h1>

        <a href="index.php" class="back-btn">
            ← Back to Providers
        </a>

    </div>

    <div class="card">

        <div class="profile-header">

            <div class="profile-icon">
                👤
            </div>

            <h2>
                <?= htmlspecialchars($provider['name']) ?>
            </h2>

            <p>
                <?= htmlspecialchars($provider['email']) ?>
            </p>

        </div>

        <div class="details">

            <div class="detail-box">

                <strong>Provider ID</strong>

                <span>
                    #<?= htmlspecialchars($provider['id']) ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Status</strong>

                <span class="status <?= $statusClass ?>">
                    <?= htmlspecialchars($provider['status']) ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Full Name</strong>

                <span>
                    <?= htmlspecialchars($provider['name']) ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Email</strong>

                <span>
                    <?= htmlspecialchars($provider['email']) ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Phone</strong>

                <span>
                    <?= !empty($provider['phone'])
                        ? htmlspecialchars($provider['phone'])
                        : 'Not provided'
                    ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Service Type</strong>

                <span>
                    <?= !empty($provider['service_type'])
                        ? htmlspecialchars($provider['service_type'])
                        : 'Not provided'
                    ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Address</strong>

                <span>
                    <?= !empty($provider['address'])
                        ? htmlspecialchars($provider['address'])
                        : 'Not provided'
                    ?>
                </span>

            </div>

            <div class="detail-box">

                <strong>Registered Date</strong>

                <span>
                    <?= htmlspecialchars($provider['created_at']) ?>
                </span>

            </div>

        </div>

        <div class="documents">

            <h3>📄 Verification Documents</h3>

            <div class="document-grid">

                <div class="document-card">

                    <h4>🪪 Citizenship</h4>

                    <?php if (!empty($provider['citizenship_file'])): ?>

                        <img
                            src="../../<?= htmlspecialchars($provider['citizenship_file']) ?>"
                            alt="Citizenship"
                        >

                    <?php else: ?>

                        <div class="no-file">
                            No citizenship document uploaded.
                        </div>

                    <?php endif; ?>

                </div>

                <div class="document-card">

                    <h4>🤳 Selfie</h4>

                    <?php if (!empty($provider['selfie_file'])): ?>

                        <img
                            src="../../<?= htmlspecialchars($provider['selfie_file']) ?>"
                            alt="Provider Selfie"
                        >

                    <?php else: ?>

                        <div class="no-file">
                            No selfie uploaded.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="actions">

            <a
                href="edit.php?id=<?= $provider['id'] ?>"
                class="btn edit-btn"
            >
                ✏️ Edit Provider
            </a>

            <a
                href="delete.php?id=<?= $provider['id'] ?>"
                class="btn delete-btn"
                onclick="return confirm('Are you sure you want to delete this provider?');"
            >
                🗑️ Delete Provider
            </a>

        </div>

    </div>

</div>

</body>

</html>