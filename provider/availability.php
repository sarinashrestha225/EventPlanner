<?php
session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (!isset($_SESSION["provider_id"])) {
    header("Location: login.php");
    exit;
}

$provider_id = (int) $_SESSION["provider_id"];

$month = isset($_GET["month"]) ? (int) $_GET["month"] : (int) date("m");
$year = isset($_GET["year"]) ? (int) $_GET["year"] : (int) date("Y");

if ($month < 1 || $month > 12) {
    $month = (int) date("m");
}

if ($year < 2020 || $year > 2100) {
    $year = (int) date("Y");
}

$first_day = strtotime("$year-$month-01");
$days_in_month = (int) date("t", $first_day);
$start_weekday = (int) date("w", $first_day);

$previous_month = $month - 1;
$previous_year = $year;

if ($previous_month < 1) {
    $previous_month = 12;
    $previous_year--;
}

$next_month = $month + 1;
$next_year = $year;

if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$availability = [];

$stmt = $conn->prepare("
    SELECT
        id,
        available_date,
        start_time,
        end_time,
        status
    FROM provider_availability
    WHERE provider_id = ?
    AND available_date BETWEEN ? AND ?
    ORDER BY available_date ASC
");

$start_date = date("Y-m-01", $first_day);
$end_date = date("Y-m-t", $first_day);

$stmt->bind_param(
    "iss",
    $provider_id,
    $start_date,
    $end_date
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $availability[$row["available_date"]] = $row;
}

$stmt->close();

$bookings = [];

$booking_stmt = $conn->prepare("
    SELECT
        id,
        booking_date,
        booking_time,
        status
    FROM bookings
    WHERE provider_id = ?
    AND booking_date BETWEEN ? AND ?
    ORDER BY booking_date ASC, booking_time ASC
");

$booking_stmt->bind_param(
    "iss",
    $provider_id,
    $start_date,
    $end_date
);

$booking_stmt->execute();

$booking_result = $booking_stmt->get_result();

while ($row = $booking_result->fetch_assoc()) {
    $bookings[$row["booking_date"]][] = $row;
}

$booking_stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $available_date = $_POST["available_date"] ?? "";
    $status = $_POST["status"] ?? "available";
    $start_time = $_POST["start_time"] ?? "";
    $end_time = $_POST["end_time"] ?? "";

    if (!$available_date) {
        header("Location: availability.php?month=$month&year=$year&error=date");
        exit;
    }

    if (!in_array($status, ["available", "unavailable"], true)) {
        $status = "available";
    }

    if ($status === "unavailable") {
        $start_time = null;
        $end_time = null;
    } else {
        $start_time = $start_time !== "" ? $start_time : null;
        $end_time = $end_time !== "" ? $end_time : null;
    }

    $check = $conn->prepare("
        SELECT id
        FROM provider_availability
        WHERE provider_id = ?
        AND available_date = ?
        LIMIT 1
    ");

    $check->bind_param(
        "is",
        $provider_id,
        $available_date
    );

    $check->execute();

    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {

        $existing = $check_result->fetch_assoc();
        $availability_id = (int) $existing["id"];

        $check->close();

        $update = $conn->prepare("
            UPDATE provider_availability
            SET
                start_time = ?,
                end_time = ?,
                status = ?
            WHERE id = ?
            AND provider_id = ?
        ");

        $update->bind_param(
            "sssii",
            $start_time,
            $end_time,
            $status,
            $availability_id,
            $provider_id
        );

        $update->execute();
        $update->close();

    } else {

        $check->close();

        $insert = $conn->prepare("
            INSERT INTO provider_availability
            (
                provider_id,
                available_date,
                start_time,
                end_time,
                status
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $insert->bind_param(
            "issss",
            $provider_id,
            $available_date,
            $start_time,
            $end_time,
            $status
        );

        $insert->execute();
        $insert->close();
    }

    header("Location: availability.php?month=$month&year=$year&success=1");
    exit;
}

if (isset($_GET["delete"])) {

    $delete_id = (int) $_GET["delete"];

    if ($delete_id > 0) {

        $delete = $conn->prepare("
            DELETE FROM provider_availability
            WHERE id = ?
            AND provider_id = ?
        ");

        $delete->bind_param(
            "ii",
            $delete_id,
            $provider_id
        );

        $delete->execute();
        $delete->close();
    }

    header("Location: availability.php?month=$month&year=$year&deleted=1");
    exit;
}

$month_name = date("F Y", $first_day);

$today = date("Y-m-d");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Availability</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffaf5;
            color: #4b3a2f;
        }

        .container {
            width: 95%;
            max-width: 1250px;
            margin: 30px auto;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .top-header h1 {
            margin: 0;
            color: #8b6508;
        }

        .back-btn {
            text-decoration: none;
            background: #d4af37;
            color: white;
            padding: 11px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #e8f8e8;
            color: #267326;
        }

        .error {
            background: #ffe5e5;
            color: #a30000;
        }

        .calendar-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .calendar-header h2 {
            margin: 0;
            color: #8b6508;
        }

        .month-buttons {
            display: flex;
            gap: 8px;
        }

        .month-buttons a {
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 7px;
            background: #f4d77d;
            color: #5b4300;
            font-weight: bold;
        }

        .weekdays,
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }

        .weekday {
            padding: 12px;
            text-align: center;
            font-weight: bold;
            background: #fff1d6;
            border: 1px solid #f0dfbd;
        }

        .day {
            min-height: 115px;
            border: 1px solid #eee0d0;
            padding: 8px;
            position: relative;
            background: #fffdf9;
        }

        .empty {
            background: #fafafa;
        }

        .day-number {
            font-weight: bold;
            font-size: 15px;
            margin-bottom: 7px;
        }

        .today {
            border: 3px solid #d4af37;
        }

        .availability-status {
            display: block;
            padding: 5px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
        }

        .available {
            background: #dcf5dc;
            color: #267326;
        }

        .unavailable {
            background: #ffdede;
            color: #a30000;
        }

        .booked {
            background: #e8defc;
            color: #5b3b91;
            display: block;
            padding: 4px;
            border-radius: 5px;
            font-size: 11px;
            margin-top: 4px;
        }

        .time {
            font-size: 11px;
            color: #765f4e;
            text-align: center;
            margin-bottom: 4px;
        }

        .form-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .form-box h2 {
            margin-top: 0;
            color: #8b6508;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #dccdbd;
            border-radius: 7px;
            font-size: 15px;
        }

        .save-btn {
            background: #d4af37;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
        }

        .availability-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        .availability-list h2 {
            color: #8b6508;
        }

        .availability-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #eee0d0;
        }

        .availability-row:last-child {
            border-bottom: none;
        }

        .date-info strong {
            display: block;
            margin-bottom: 5px;
        }

        .delete-btn {
            text-decoration: none;
            background: #dc5c5c;
            color: white;
            padding: 8px 13px;
            border-radius: 6px;
            font-size: 13px;
        }

        .legend {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 18px;
            font-size: 13px;
        }

        .legend span {
            padding: 7px 10px;
            border-radius: 6px;
        }

        @media (max-width: 750px) {
            .calendar-header,
            .top-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .day {
                min-height: 90px;
                padding: 5px;
            }

            .weekday {
                font-size: 12px;
                padding: 8px 3px;
            }

            .booked {
                font-size: 9px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="top-header">
        <h1>Provider Availability</h1>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="message">
            Availability saved successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["deleted"])): ?>
        <div class="message">
            Availability deleted successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <div class="message error">
            Please select a date.
        </div>
    <?php endif; ?>

    <div class="calendar-box">

        <div class="calendar-header">

            <div class="month-buttons">
                <a href="availability.php?month=<?php echo $previous_month; ?>&year=<?php echo $previous_year; ?>">
                    Previous
                </a>

                <a href="availability.php?month=<?php echo date("m"); ?>&year=<?php echo date("Y"); ?>">
                    Today
                </a>

                <a href="availability.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">
                    Next
                </a>
            </div>

            <h2><?php echo $month_name; ?></h2>

        </div>

        <div class="weekdays">
            <div class="weekday">Sun</div>
            <div class="weekday">Mon</div>
            <div class="weekday">Tue</div>
            <div class="weekday">Wed</div>
            <div class="weekday">Thu</div>
            <div class="weekday">Fri</div>
            <div class="weekday">Sat</div>
        </div>

        <div class="calendar">

            <?php for ($i = 0; $i < $start_weekday; $i++): ?>
                <div class="day empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $days_in_month; $day++): ?>

                <?php
                $date = sprintf(
                    "%04d-%02d-%02d",
                    $year,
                    $month,
                    $day
                );

                $is_today = ($date === $today);

                $day_availability = $availability[$date] ?? null;
                $day_bookings = $bookings[$date] ?? [];
                ?>

                <div class="day <?php echo $is_today ? "today" : ""; ?>">

                    <div class="day-number">
                        <?php echo $day; ?>
                    </div>

                    <?php if ($day_availability): ?>

                        <?php if ($day_availability["status"] === "available"): ?>

                            <span class="availability-status available">
                                Available
                            </span>

                            <?php if ($day_availability["start_time"] && $day_availability["end_time"]): ?>

                                <div class="time">
                                    <?php echo date("g:i A", strtotime($day_availability["start_time"])); ?>
                                    -
                                    <?php echo date("g:i A", strtotime($day_availability["end_time"])); ?>
                                </div>

                            <?php endif; ?>

                        <?php else: ?>

                            <span class="availability-status unavailable">
                                Unavailable
                            </span>

                        <?php endif; ?>

                    <?php endif; ?>

                    <?php foreach ($day_bookings as $booking): ?>

                        <span class="booked">
                            Booking #<?php echo (int) $booking["id"]; ?>

                            <?php if (!empty($booking["booking_time"])): ?>
                                -
                                <?php echo date("g:i A", strtotime($booking["booking_time"])); ?>
                            <?php endif; ?>
                        </span>

                    <?php endforeach; ?>

                </div>

            <?php endfor; ?>

        </div>

        <div class="legend">
            <span class="available">Available</span>
            <span class="unavailable">Unavailable</span>
            <span class="booked">Booked</span>
        </div>

    </div>

    <div class="form-box">

        <h2>Set Availability</h2>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Select Date</label>
                    <input
                        type="date"
                        name="available_date"
                        min="<?php echo date("Y-m-d"); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Status</label>

                    <select name="status" id="status" onchange="toggleTimeFields()">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>

                <div class="form-group" id="startTimeGroup">
                    <label>Start Time</label>
                    <input type="time" name="start_time">
                </div>

                <div class="form-group" id="endTimeGroup">
                    <label>End Time</label>
                    <input type="time" name="end_time">
                </div>

                <div class="form-group full">
                    <button type="submit" class="save-btn">
                        Save Availability
                    </button>
                </div>

            </div>

        </form>

    </div>

    <div class="availability-list">

        <h2>This Month's Availability</h2>

        <?php if (empty($availability)): ?>

            <p>No availability has been added for this month.</p>

        <?php else: ?>

            <?php foreach ($availability as $item): ?>

                <div class="availability-row">

                    <div class="date-info">

                        <strong>
                            <?php echo date("l, F j, Y", strtotime($item["available_date"])); ?>
                        </strong>

                        <?php if ($item["status"] === "available"): ?>

                            <span class="availability-status available">
                                Available
                            </span>

                            <?php if ($item["start_time"] && $item["end_time"]): ?>

                                <div class="time">
                                    <?php echo date("g:i A", strtotime($item["start_time"])); ?>
                                    -
                                    <?php echo date("g:i A", strtotime($item["end_time"])); ?>
                                </div>

                            <?php else: ?>

                                <div class="time">
                                    All Day
                                </div>

                            <?php endif; ?>

                        <?php else: ?>

                            <span class="availability-status unavailable">
                                Unavailable
                            </span>

                        <?php endif; ?>

                    </div>

                    <a
                        href="availability.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&delete=<?php echo (int) $item["id"]; ?>"
                        class="delete-btn"
                        onclick="return confirm('Are you sure you want to delete this availability?')"
                    >
                        Delete
                    </a>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

<script>
function toggleTimeFields() {

    const status = document.getElementById("status").value;

    const startGroup = document.getElementById("startTimeGroup");
    const endGroup = document.getElementById("endTimeGroup");

    const startInput = document.querySelector('input[name="start_time"]');
    const endInput = document.querySelector('input[name="end_time"]');

    if (status === "unavailable") {

        startGroup.style.display = "none";
        endGroup.style.display = "none";

        startInput.value = "";
        endInput.value = "";

    } else {

        startGroup.style.display = "flex";
        endGroup.style.display = "flex";
    }
}

toggleTimeFields();
</script>

</body>
</html>