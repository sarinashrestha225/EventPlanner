<?php

session_start();

require_once "../database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["user_id"];

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $booking_id = !empty($_POST["booking_id"])
        ? (int) $_POST["booking_id"]
        : null;

    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($subject === "") {

        $error = "Please enter complaint subject.";

    } elseif ($message === "") {

        $error = "Please enter your complaint message.";

    } else {

        $sql = "INSERT INTO complaints
                (customer_id, booking_id, subject, message)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param(
                "iiss",
                $customer_id,
                $booking_id,
                $subject,
                $message
            );

            if ($stmt->execute()) {

                $success = "Your complaint has been submitted successfully.";

            } else {

                $error = "Failed to submit complaint: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$bookings = [];

$sql = "SELECT id, status, booking_date
        FROM bookings
        WHERE customer_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    $stmt->close();
}

$complaints = [];

$sql = "SELECT 
            c.*,
            b.status AS booking_status
        FROM complaints c
        LEFT JOIN bookings b
            ON c.booking_id = b.id
        WHERE c.customer_id = ?
        ORDER BY c.id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Complaints | Event Planner</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8f0;
    color: #4a3b32;
}

.header {
    background: linear-gradient(
        135deg,
        #f8d7da,
        #f6c453
    );

    padding: 20px 40px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow: 0 3px 10px rgba(0,0,0,0.12);
}

.header h1 {
    color: #6b421d;
}

.back-btn {
    text-decoration: none;
    background: #fff;
    color: #8a5a00;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: bold;
}

.container {
    width: 90%;
    max-width: 1100px;
    margin: 35px auto;
}

.card {
    background: #fff;

    padding: 25px;

    border-radius: 15px;

    margin-bottom: 30px;

    box-shadow: 0 5px 18px rgba(0,0,0,0.08);
}

.card h2 {
    margin-bottom: 20px;
    color: #7a4e00;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

.form-group input,
.form-group select,
.form-group textarea {

    width: 100%;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    background: #fffdf9;
}

.form-group textarea {
    min-height: 130px;
    resize: vertical;
}

.submit-btn {

    background: #d4a017;

    color: white;

    border: none;

    padding: 12px 25px;

    border-radius: 8px;

    cursor: pointer;

    font-weight: bold;

    font-size: 15px;
}

.submit-btn:hover {
    background: #b8860b;
}

.success {
    background: #d4edda;
    color: #155724;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.error {
    background: #f8d7da;
    color: #721c24;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.complaint {

    border: 1px solid #eee;

    border-left: 5px solid #d4a017;

    padding: 20px;

    margin-bottom: 18px;

    border-radius: 10px;

    background: #fffdf9;
}

.complaint h3 {
    color: #6b421d;
    margin-bottom: 8px;
}

.complaint p {
    margin: 7px 0;
    line-height: 1.5;
}

.date {
    color: #777;
    font-size: 13px;
}

.status {

    display: inline-block;

    padding: 5px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.in_progress {
    background: #cce5ff;
    color: #004085;
}

.resolved {
    background: #d4edda;
    color: #155724;
}

.rejected {
    background: #f8d7da;
    color: #721c24;
}

.reply {

    margin-top: 15px;

    padding: 15px;

    background: #fdf0f5;

    border-radius: 10px;
}

.reply strong {
    color: #8a5a00;
}

@media (max-width: 600px) {

    .header {
        padding: 15px 20px;
    }

    .header h1 {
        font-size: 22px;
    }

    .container {
        width: 94%;
    }
}

</style>

</head>

<body>

<div class="header">

    <h1>📢 Complaints</h1>

    <a href="dashboard.php" class="back-btn">
        ← Dashboard
    </a>

</div>

<div class="container">

<div class="card">

    <h2>Submit a Complaint</h2>

    <?php if ($success): ?>

        <div class="success">
            <?php echo htmlspecialchars($success); ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <form method="POST"
          action="complaint.php">

        <div class="form-group">

            <label>Select Booking (Optional)</label>

            <select name="booking_id">

                <option value="">
                    -- Select Booking --
                </option>

                <?php foreach ($bookings as $booking): ?>

                    <option value="<?php echo $booking["id"]; ?>">

                        Booking #<?php echo $booking["id"]; ?>

                        -

                        <?php echo htmlspecialchars($booking["status"]); ?>

                        <?php if (!empty($booking["booking_date"])): ?>

                            -

                            <?php echo htmlspecialchars($booking["booking_date"]); ?>

                        <?php endif; ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="form-group">

            <label>Complaint Subject</label>

            <input
                type="text"
                name="subject"
                placeholder="Enter complaint subject"
                maxlength="255"
                required
            >

        </div>

        <div class="form-group">

            <label>Complaint Message</label>

            <textarea
                name="message"
                placeholder="Describe your complaint..."
                required
            ></textarea>

        </div>

        <button
            type="submit"
            class="submit-btn">

            Submit Complaint

        </button>

    </form>

</div>

<div class="card">

    <h2>My Complaints</h2>

    <?php if (empty($complaints)): ?>

        <p>
            You have not submitted any complaints yet.
        </p>

    <?php else: ?>

        <?php foreach ($complaints as $complaint): ?>

            <div class="complaint">

                <h3>
                    <?php
                    echo htmlspecialchars(
                        $complaint["subject"]
                    );
                    ?>
                </h3>

                <p>

                    <strong>Complaint:</strong><br>

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $complaint["message"]
                        )
                    );
                    ?>

                </p>

                <?php if (!empty($complaint["booking_id"])): ?>

                    <p>
                        <strong>Booking:</strong>
                        #<?php
                        echo (int) $complaint["booking_id"];
                        ?>
                    </p>

                <?php endif; ?>

                <p>

                    <strong>Status:</strong>

                    <span class="status
                        <?php
                        echo htmlspecialchars(
                            $complaint["status"]
                        );
                        ?>">

                        <?php
                        echo ucfirst(
                            str_replace(
                                "_",
                                " ",
                                $complaint["status"]
                            )
                        );
                        ?>

                    </span>

                </p>

                <?php if (!empty($complaint["admin_reply"])): ?>

                    <div class="reply">

                        <strong>
                            Admin Reply:
                        </strong>

                        <p>

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $complaint["admin_reply"]
                                )
                            );
                            ?>

                        </p>

                    </div>

                <?php endif; ?>

                <p class="date">

                    Submitted:

                    <?php
                    echo htmlspecialchars(
                        $complaint["created_at"]
                    );
                    ?>

                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</div>

</body>

</html>