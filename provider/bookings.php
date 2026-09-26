<?php 

session_start(); 
 
require_once __DIR__ . "/includes/auth.php"; 
require_once __DIR__ . "/../database.php"; 
 
if (!isset($_SESSION["provider_id"])) { 
    header("Location: login.php"); 
    exit; 
} 
 
$provider_id = (int) $_SESSION["provider_id"]; 
 
$sql = " 
    SELECT 
        b.id, 
        b.customer_id, 
        b.event_type_id, 
        b.service_id, 
        b.package_id, 
        b.guests, 
        b.booking_date, 
        b.booking_time, 
        b.amount, 
        b.status, 
        b.payment_status, 
        b.customer_note, 
        b.created_at, 
 
        u.name AS customer_name, 
        u.phone AS customer_phone, 
 
        s.service_name, 
 
        p.package_name, 
 
        et.event_name 
 
    FROM bookings b 
 
    LEFT JOIN users u 
        ON u.id = b.customer_id 
 
    LEFT JOIN services s 
        ON s.id = b.service_id 
 
    LEFT JOIN packages p 
        ON p.id = b.package_id 
 
    LEFT JOIN event_types et 
        ON et.id = b.event_type_id 
 
    WHERE b.provider_id = ? 
 
    ORDER BY b.id DESC 
"; 
 
$stmt = $conn->prepare($sql); 
 
if (!$stmt) { 
    die("SQL Error: " . $conn->error); 
} 
 
$stmt->bind_param("i", $provider_id); 
$stmt->execute(); 
 
$result = $stmt->get_result(); 
 
function statusClass($status) 
{ 
    return strtolower(str_replace(" ", "-", $status)); 
} 
 
?> 

<!DOCTYPE html> 
<html lang="en"> 
<head> 

<meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
 
<title>My Bookings - Provider</title> 
 
<style> 
 
* { 
    box-sizing: border-box; 
} 
 
body { 
    margin: 0; 
    padding: 0; 
    font-family: Arial, sans-serif; 
    background: #fff8fb; 
    color: #333; 
} 
 
.container { 
    width: 95%; 
    max-width: 1250px; 
    margin: 30px auto; 
} 
 
.header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 25px; 
} 
 
.header h1 { 
    margin: 0; 
    color: #c2185b; 
} 
 
.back-btn { 
    text-decoration: none; 
    background: #c2185b; 
    color: white; 
    padding: 10px 18px; 
    border-radius: 8px; 
    font-weight: bold; 
} 
 
.back-btn:hover { 
    background: #ad1457; 
} 
 
.booking-card { 
    background: white; 
    border-radius: 14px; 
    padding: 22px; 
    margin-bottom: 20px; 
    box-shadow: 0 3px 12px rgba(0,0,0,0.08); 
    border-left: 5px solid #d4af37; 
} 
 
.booking-top { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    gap: 15px; 
    margin-bottom: 15px; 
} 
 
.booking-id { 
    font-size: 20px; 
    font-weight: bold; 
    color: #c2185b; 
} 
 
.status { 
    padding: 7px 14px; 
    border-radius: 20px; 
    font-size: 13px; 
    font-weight: bold; 
    text-transform: capitalize; 
} 
 
.status.pending { 
    background: #fff3cd; 
    color: #856404; 
} 
 
.status.confirmed { 
    background: #d4edda; 
    color: #155724; 
} 
 
.status.completed { 
    background: #d1ecf1; 
    color: #0c5460; 
} 
 
.status.cancelled { 
    background: #f8d7da; 
    color: #721c24; 
} 
 
.booking-info { 
    display: grid; 
    grid-template-columns: repeat(2, 1fr); 
    gap: 12px; 
    margin-top: 15px; 
} 
 
.info-box { 
    background: #fff8fb; 
    padding: 12px; 
    border-radius: 8px; 
} 
 
.info-box strong { 
    display: block; 
    color: #777; 
    font-size: 13px; 
    margin-bottom: 4px; 
} 
 
.info-box span { 
    font-size: 15px; 
    color: #333; 
} 
 
.amount { 
    color: #c2185b !important; 
    font-weight: bold; 
    font-size: 18px !important; 
} 
 
.note { 
    margin-top: 15px; 
    padding: 12px; 
    background: #fffaf0; 
    border-radius: 8px; 
    border-left: 4px solid #d4af37; 
} 
 
.actions { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 10px; 
    margin-top: 18px; 
} 
 
.view-btn, 
.message-btn, 
.call-btn { 
    display: inline-block; 
    text-decoration: none; 
    padding: 10px 17px; 
    border-radius: 8px; 
    font-weight: bold; 
    font-size: 14px; 
} 
 
.view-btn { 
    background: #c2185b; 
    color: white; 
} 
 
.view-btn:hover { 
    background: #ad1457; 
} 
 
.message-btn { 
    background: #d4af37; 
    color: white; 
} 
 
.message-btn:hover { 
    background: #b8962e; 
} 
 
.call-btn { 
    background: #28a745; 
    color: white; 
} 
 
.call-btn:hover { 
    background: #218838; 
} 
 
.no-bookings { 
    background: white; 
    padding: 40px; 
    text-align: center; 
    border-radius: 14px; 
    box-shadow: 0 3px 12px rgba(0,0,0,0.08); 
} 
 
.no-bookings h2 { 
    color: #c2185b; 
} 
 
@media (max-width: 700px) { 
 
    .booking-info { 
        grid-template-columns: 1fr; 
    } 
 
    .header { 
        flex-direction: column; 
        align-items: flex-start; 
    } 
 
    .booking-top { 
        flex-direction: column; 
        align-items: flex-start; 
    } 
 
    .actions { 
        flex-direction: column; 
    } 
 
    .view-btn, 
    .message-btn, 
    .call-btn { 
        text-align: center; 
        width: 100%; 
    } 
} 
 
</style> 
</head> 
 
<body> 
 
<div class="container"> 
 
    <div class="header"> 
        <h1>📋 My Bookings</h1> 
 
        <a href="dashboard.php" class="back-btn"> 
            ← Dashboard 
        </a> 
    </div> 
 
    <?php if ($result->num_rows === 0): ?> 
 
        <div class="no-bookings"> 
            <h2>No Bookings Yet</h2> 
            <p>You don't have any customer bookings at the moment.</p> 
        </div> 
 
    <?php else: ?> 
 
        <?php while ($booking = $result->fetch_assoc()): ?> 
 
            <?php 
                $booking_id = (int) $booking["id"]; 
 
                $customer_name = $booking["customer_name"] ?? "Customer"; 
                $customer_phone = $booking["customer_phone"] ?? ""; 
 
                $event_name = $booking["event_name"] ?? "Event"; 
 
                $service_name = $booking["service_name"] ?? ""; 
 
                $package_name = $booking["package_name"] ?? ""; 
 
                $status = $booking["status"] ?? "pending"; 
 
                $payment_status = $booking["payment_status"] ?? "unpaid"; 
 
                $amount = (float) ($booking["amount"] ?? 0); 
 
                $guests = $booking["guests"]; 
 
                $note = $booking["customer_note"] ?? ""; 
 
                $date = !empty($booking["booking_date"]) 
                    ? date("d M Y", strtotime($booking["booking_date"])) 
                    : "Not specified"; 
 
                $time = !empty($booking["booking_time"]) 
                    ? date("h:i A", strtotime($booking["booking_time"])) 
                    : "Not specified"; 
            ?> 
 
            <div class="booking-card"> 
 
                <div class="booking-top"> 
 
                    <div class="booking-id"> 
                        Booking #<?= $booking_id ?> 
                    </div> 
 
                    <div class="status <?= htmlspecialchars(statusClass($status)) ?>"> 
                        <?= htmlspecialchars(ucfirst($status)) ?> 
                    </div> 
 
                </div> 
 
                <div class="booking-info"> 
 
                    <div class="info-box"> 
                        <strong>Customer</strong> 
                        <span> 
                            <?= htmlspecialchars($customer_name) ?> 
                        </span> 
                    </div> 
 
                    <div class="info-box"> 
                        <strong>Event</strong> 
                        <span> 
                            <?= htmlspecialchars($event_name) ?> 
                        </span> 
                    </div> 
 
                    <?php if (!empty($package_name)): ?> 
 
                        <div class="info-box"> 
                            <strong>Package</strong> 
                            <span> 
                                <?= htmlspecialchars($package_name) ?> 
                            </span> 
                        </div> 
 
                    <?php endif; ?> 
 
                    <?php if (!empty($service_name)): ?> 
 
                        <div class="info-box"> 
                            <strong>Service</strong> 
                            <span> 
                                <?= htmlspecialchars($service_name) ?> 
                            </span> 
                        </div> 
 
                    <?php endif; ?> 
 
                    <div class="info-box"> 
                        <strong>Booking Date</strong> 
                        <span> 
                            <?= htmlspecialchars($date) ?> 
                        </span> 
                    </div> 
 
                    <div class="info-box"> 
                        <strong>Booking Time</strong> 
                        <span> 
                            <?= htmlspecialchars($time) ?> 
                        </span> 
                    </div> 
 
                    <?php if ($guests !== null && $guests !== ""): ?> 
 
                        <div class="info-box"> 
                            <strong>Guests</strong> 
                            <span> 
                                <?= (int) $guests ?> 
                            </span> 
                        </div> 
 
                    <?php endif; ?> 
 
                    <div class="info-box"> 
                        <strong>Amount</strong> 
                        <span class="amount"> 
                            Rs. <?= number_format($amount, 2) ?> 
                        </span> 
                    </div> 
 
                    <div class="info-box"> 
                        <strong>Payment</strong> 
                        <span> 
                            <?= htmlspecialchars(ucfirst($payment_status)) ?> 
                        </span> 
                    </div> 
 
                </div> 
 
                <?php if (!empty($note)): ?> 
 
                    <div class="note"> 
                        <strong>Customer Note:</strong><br> 
                        <?= nl2br(htmlspecialchars($note)) ?> 
                    </div> 
 
                <?php endif; ?> 
 
                <div class="actions"> 
 
                    <a 
                        href="view_booking.php?id=<?= $booking_id ?>" 
                        class="view-btn" 
                    > 
                        👁 View Details 
                    </a> 
 
                    <a 
                        href="messages.php?booking_id=<?= $booking_id ?>" 
                        class="message-btn" 
                    > 
                        💬 Message Customer 
                    </a> 
 
                    <?php if (!empty($customer_phone)): ?> 
 
                        <a 
                            href="tel:<?= htmlspecialchars($customer_phone) ?>" 
                            class="call-btn" 
                        > 
                            📞 Call Customer 
                        </a> 
 
                    <?php endif; ?> 
 
                </div> 
 
            </div> 
 
        <?php endwhile; ?> 
 
    <?php endif; ?> 
 
</div> 
 
</body> 
</html> 
 
<?php 
$stmt->close(); 
$conn->close(); 
?>