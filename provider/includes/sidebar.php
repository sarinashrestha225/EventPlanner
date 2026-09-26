<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER["PHP_SELF"]);

?>

<style>

.provider-sidebar {
    position: fixed;
    top: 70px;
    left: 0;
    bottom: 0;
    width: 250px;
    background: #4b3621;
    padding: 20px 15px;
    overflow-y: auto;
    z-index: 900;
}

.sidebar-title {
    color: #d4af37;
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
    padding: 10px 12px;
    margin-bottom: 8px;
    letter-spacing: 1px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    color: #ffffff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 15px;
    transition: 0.2s;
}

.sidebar-menu a:hover {
    background: rgba(255,255,255,0.12);
    color: #d4af37;
}

.sidebar-menu a.active {
    background: #d4af37;
    color: #ffffff;
    font-weight: bold;
}

.menu-icon {
    width: 25px;
    text-align: center;
    font-size: 18px;
}

.menu-section {
    margin-top: 20px;
    margin-bottom: 8px;
    padding-left: 12px;
    color: #d4af37;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

@media(max-width: 800px) {

    .provider-sidebar {
        position: static;
        width: 100%;
        margin-top: 70px;
        padding: 10px;
    }

    .sidebar-menu {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 5px;
    }

    .sidebar-menu li {
        margin: 0;
    }

    .sidebar-menu a {
        font-size: 13px;
        padding: 10px;
    }

    .menu-section {
        grid-column: 1 / -1;
        margin-top: 12px;
    }

}

</style>

<aside class="provider-sidebar">

    <div class="sidebar-title">
        👨‍💼 Provider Panel
    </div>

    <ul class="sidebar-menu">

        <li class="menu-section">
            Main
        </li>

        <li>
            <a
                href="../dashboard.php"
                class="<?= $current_page === "dashboard.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">🏠</span>
                Dashboard
            </a>
        </li>

        <li>
            <a
                href="../profile.php"
                class="<?= in_array($current_page, ["profile.php", "edit_profile.php"], true) ? "active" : "" ?>"
            >
                <span class="menu-icon">👤</span>
                Profile
            </a>
        </li>

        <li class="menu-section">
            Services
        </li>

        <li>
            <a
                href="../services.php"
                class="<?= in_array($current_page, ["services.php", "add_service.php", "edit_service.php"], true) ? "active" : "" ?>"
            >
                <span class="menu-icon">🛠️</span>
                My Services
            </a>
        </li>

        <li class="menu-section">
            Bookings
        </li>

        <li>
            <a
                href="../bookings.php"
                class="<?= in_array($current_page, ["bookings.php", "view_booking.php", "update_booking.php"], true) ? "active" : "" ?>"
            >
                <span class="menu-icon">📅</span>
                Bookings
            </a>
        </li>

        <li>
            <a
                href="../availability.php"
                class="<?= $current_page === "availability.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">🗓️</span>
                Availability
            </a>
        </li>

        <li class="menu-section">
            Payments & Earnings
        </li>

        <li>
            <a
                href="../earnings.php"
                class="<?= $current_page === "earnings.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">💰</span>
                Earnings
            </a>
        </li>

        <li>
            <a
                href="../payments.php"
                class="<?= $current_page === "payments.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">💳</span>
                Payments
            </a>
        </li>

        <li class="menu-section">
            Communication
        </li>

        <li>
            <a
                href="../messages.php"
                class="<?= $current_page === "messages.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">💬</span>
                Messages
            </a>
        </li>

        <li>
            <a
                href="../notifications.php"
                class="<?= $current_page === "notifications.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">🔔</span>
                Notifications
            </a>
        </li>

        <li class="menu-section">
            Portfolio
        </li>

        <li>
            <a
                href="../portfolio.php"
                class="<?= in_array($current_page, ["portfolio.php", "add_portfolio.php", "edit_portfolio.php"], true) ? "active" : "" ?>"
            >
                <span class="menu-icon">🖼️</span>
                My Portfolio
            </a>
        </li>

        <li class="menu-section">
            Account
        </li>

        <li>
            <a
                href="../verification.php"
                class="<?= $current_page === "verification.php" ? "active" : "" ?>"
            >
                <span class="menu-icon">✅</span>
                Verification
            </a>
        </li>

        <li>
            <a href="../logout.php">
                <span class="menu-icon">🚪</span>
                Logout
            </a>
        </li>

    </ul>

</aside>