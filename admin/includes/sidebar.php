
<div class="admin-sidebar">

    <div class="sidebar-logo">
        <div class="logo-icon">✦</div>
        <h2>Event Planner</h2>
        <p>Admin Panel</p>
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="../dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>
        </li>

        <li>
            <a href="../events/index.php">
                <span>🎉</span>
                Events
            </a>
        </li>

        <li>
            <a href="../services/index.php">
                <span>🛠</span>
                Services
            </a>
        </li>

        <li>
            <a href="../packages/index.php">
                <span>📦</span>
                Packages
            </a>
        </li>

        <li>
            <a href="../packages_services/index.php">
                <span>🔗</span>
                Package Services
            </a>
        </li>

        <li>
            <a href="../venues/index.php">
                <span>🏛</span>
                Venues
            </a>
        </li>

        <li>
            <a href="../locations/index.php">
                <span>📍</span>
                Locations
            </a>
        </li>

        <li>
            <a href="../providers/index.php">
                <span>👤</span>
                Providers
            </a>
        </li>

        <li>
            <a href="../customers/index.php">
                <span>👥</span>
                Customers
            </a>
        </li>

        <li>
            <a href="../bookings/index.php">
                <span>📅</span>
                Bookings
            </a>
        </li>

        <li>
            <a href="../payments/index.php">
                <span>💰</span>
                Payments
            </a>
        </li>

        <li>
            <a href="../commissions/index.php">
                <span>💵</span>
                Commissions
            </a>
        </li>

        <li>
            <a href="../coupons/index.php">
                <span>🎟</span>
                Coupons
            </a>
        </li>

        <li>
            <a href="../reviews/index.php">
                <span>⭐</span>
                Reviews
            </a>
        </li>

        <li>
            <a href="../notifications/index.php">
                <span>🔔</span>
                Notifications
            </a>
        </li>

        <li>
            <a href="../reports/index.php">
                <span>📊</span>
                Reports
            </a>
        </li>

        <li class="logout-menu">
            <a href="../logout.php">
                <span>🚪</span>
                Logout
            </a>
        </li>

    </ul>

</div>

<style>

.admin-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: linear-gradient(
        180deg,
        #ffd7e4 0%,
        #ffe8c8 48%,
        #fff5dc 100%
    );
    color: #674650;
    z-index: 1000;
    overflow-y: auto;
    border-right: 2px solid #e7c65e;
    box-shadow: 4px 0 20px rgba(160, 110, 20, 0.08);
}

.sidebar-logo {
    padding: 23px 18px;
    text-align: center;
    border-bottom: 1px solid #efd783;
}

.logo-icon {
    font-size: 24px;
    color: #b8860b;
    margin-bottom: 3px;
}

.sidebar-logo h2 {
    font-family: Georgia, serif;
    font-size: 22px;
    margin: 0 0 5px 0;
    color: #9a7010;
}

.sidebar-logo p {
    margin: 0;
    font-size: 13px;
    color: #967b82;
}

.sidebar-menu {
    list-style: none;
    padding: 15px 10px;
    margin: 0;
}

.sidebar-menu li {
    margin: 3px 0;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 13px;
    color: #674650;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.2s ease;
    font-size: 14px;
    font-weight: 500;
}

.sidebar-menu li a span {
    width: 23px;
    text-align: center;
    font-size: 17px;
}

.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.78);
    color: #9b7108;
    transform: translateX(3px);
}

.sidebar-menu li a:active {
    background: #ffe9a5;
}

.logout-menu {
    margin-top: 18px !important;
    padding-top: 10px;
    border-top: 1px solid #efd783;
}

.logout-menu a {
    color: #b13e58 !important;
    background: rgba(255, 255, 255, 0.55);
}

.logout-menu a:hover {
    background: #f8c8dc !important;
    color: #8a3156 !important;
}

.admin-sidebar::-webkit-scrollbar {
    width: 6px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: #e3c56a;
    border-radius: 10px;
}

@media (max-width: 768px) {

    .admin-sidebar {
        position: relative;
        width: 100%;
        height: auto;
        border-right: none;
        border-bottom: 2px solid #e7c65e;
    }

    .sidebar-menu {
        padding: 10px;
    }

}

</style>

