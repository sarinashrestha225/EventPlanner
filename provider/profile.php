<?php

session_start();

require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['provider_id'])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION['provider_id'];

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        service_type,
        address,
        city,
        area,
        bio,
        profile_photo,
        status,
        citizenship_file,
        selfie_file,
        created_at
    FROM providers
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $provider_id);
$stmt->execute();

$result = $stmt->get_result();
$provider = $result->fetch_assoc();

$stmt->close();

if (!$provider) {
    die("Provider not found.");
}

function showValue($value)
{
    if ($value === null || $value === '') {
        return "Not provided";
    }

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$status = strtolower($provider['status'] ?? 'pending');

if ($status === 'active') {
    $statusClass = 'active';
    $statusText = 'Active';
} elseif ($status === 'blocked') {
    $statusClass = 'blocked';
    $statusText = 'Blocked';
} else {
    $statusClass = 'pending';
    $statusText = 'Pending';
}

$citizenship = !empty($provider['citizenship_file']);
$selfie = !empty($provider['selfie_file']);

$verified = $citizenship && $selfie;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Profile - Provider</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #fff8f0;
    color: #4b3621;
}

.container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 20px;
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.back {
    text-decoration: none;
    color: #6b4f2a;
    font-weight: bold;
}

h1 {
    margin-bottom: 5px;
}

.subtitle {
    color: #777;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 10px;
}

.avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #d4af37;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 35px;
    font-weight: bold;
    overflow: hidden;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.provider-name {
    font-size: 26px;
    font-weight: bold;
}

.provider-type {
    color: #777;
    margin-top: 5px;
}

.status {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 20px;
    font-weight: bold;
    margin-top: 8px;
}

.active {
    background: #d4edda;
    color: #155724;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.blocked {
    background: #f8d7da;
    color: #721c24;
}

h2 {
    color: #b8860b;
    border-bottom: 1px solid #eee;
    padding-bottom: 12px;
    margin-top: 0;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.info {
    padding: 10px;
}

.label {
    display: block;
    color: #888;
    font-size: 14px;
    margin-bottom: 6px;
}

.value {
    font-size: 16px;
    font-weight: bold;
    word-break: break-word;
}

.location-box {
    background: #fff8f2;
    border: 1px solid #f0d9df;
    border-radius: 12px;
    padding: 18px;
}

.location-title {
    color: #b8860b;
    font-size: 17px;
    font-weight: bold;
    margin-bottom: 12px;
}

.verification {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 10px;
}

.verified {
    background: #d4edda;
    color: #155724;
}

.not-verified {
    background: #fff3cd;
    color: #856404;
}

.button {
    display: inline-block;
    margin-top: 15px;
    padding: 12px 20px;
    background: #d4af37;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
}

.button:hover {
    opacity: 0.9;
}

.call-button {
    background: #c88a9b;
    margin-left: 8px;
}

@media(max-width: 700px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .profile-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .container {
        margin: 15px auto;
        padding: 12px;
    }

    .call-button {
        margin-left: 0;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="top">

        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>

    <h1>
        👤 My Profile
    </h1>

    <div class="subtitle">
        View and manage your provider information.
    </div>


    <div class="card">

        <div class="profile-header">

            <div class="avatar">

                <?php if (!empty($provider['profile_photo'])): ?>

                    <img
                        src="uploads/profile/<?= htmlspecialchars(
                            $provider['profile_photo'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="Profile Photo"
                    >

                <?php else: ?>

                    <?= strtoupper(
                        substr(
                            $provider['name'] ?? 'P',
                            0,
                            1
                        )
                    ) ?>

                <?php endif; ?>

            </div>


            <div>

                <div class="provider-name">

                    <?= showValue($provider['name']) ?>

                </div>

                <div class="provider-type">

                    <?= showValue($provider['service_type']) ?>
                    Provider

                </div>

                <span class="status <?= $statusClass ?>">

                    <?= $statusText ?>

                </span>

            </div>

        </div>

    </div>


    <div class="card">

        <h2>
            👤 Basic Information
        </h2>

        <div class="grid">

            <div class="info">

                <span class="label">
                    Provider ID
                </span>

                <span class="value">
                    <?= (int) $provider['id'] ?>
                </span>

            </div>


            <div class="info">

                <span class="label">
                    Full Name
                </span>

                <span class="value">
                    <?= showValue($provider['name']) ?>
                </span>

            </div>


            <div class="info">

                <span class="label">
                    Email
                </span>

                <span class="value">
                    <?= showValue($provider['email']) ?>
                </span>

            </div>


            <div class="info">

                <span class="label">
                    Phone
                </span>

                <span class="value">

                    <?= showValue($provider['phone']) ?>

                    <?php if (!empty($provider['phone'])): ?>

                        <a
                            href="tel:<?= htmlspecialchars(
                                $provider['phone'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            class="button call-button"
                        >
                            📞 Call
                        </a>

                    <?php endif; ?>

                </span>

            </div>


            <div class="info">

                <span class="label">
                    Service Type
                </span>

                <span class="value">
                    <?= showValue($provider['service_type']) ?>
                </span>

            </div>


            <div class="info">

                <span class="label">
                    Address
                </span>

                <span class="value">
                    <?= showValue($provider['address']) ?>
                </span>

            </div>


            <div class="info">

                <span class="label">
                    Provider Since
                </span>

                <span class="value">

                    <?php if (!empty($provider['created_at'])): ?>

                        <?= date(
                            'd M Y',
                            strtotime($provider['created_at'])
                        ) ?>

                    <?php else: ?>

                        Not provided

                    <?php endif; ?>

                </span>

            </div>

        </div>


        <a
            href="edit_profile.php"
            class="button"
        >
            ✏️ Edit Profile
        </a>

    </div>


    <div class="card">

        <h2>
            City & Area
        </h2>

        <div class="location-box">

            <div class="location-title">
                Your Service Location
            </div>

            <div class="grid">

                <div class="info">

                    <span class="label">
                        City
                    </span>

                    <span class="value">
                        <?= showValue($provider['city']) ?>
                    </span>

                </div>


                <div class="info">

                    <span class="label">
                        Area
                    </span>

                    <span class="value">
                        <?= showValue($provider['area']) ?>
                    </span>

                </div>

            </div>

        </div>


        <a
            href="edit_profile.php"
            class="button"
        >
            ✏️ Edit Location
        </a>

    </div>


    <div class="card">

        <h2>
            🔐 Verification
        </h2>


        <?php if ($verified): ?>

            <div class="verification verified">
                ✅ Verification documents submitted.
            </div>

        <?php else: ?>

            <div class="verification not-verified">
                ⚠️ Verification documents not submitted.
            </div>

        <?php endif; ?>


        <div class="grid">

            <div class="info">

                <span class="label">
                    Citizenship
                </span>

                <span class="value">

                    <?= $citizenship
                        ? '✅ Submitted'
                        : '❌ Not submitted'
                    ?>

                </span>

            </div>


            <div class="info">

                <span class="label">
                    Selfie
                </span>

                <span class="value">

                    <?= $selfie
                        ? '✅ Submitted'
                        : '❌ Not submitted'
                    ?>

                </span>

            </div>

        </div>


        <a
            href="verification.php"
            class="button"
        >
            🔐 Manage Verification
        </a>

    </div>

</div>

</body>

</html>