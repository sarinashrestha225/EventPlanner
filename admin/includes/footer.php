<footer class="admin-footer">

    <div class="footer-content">

        <p>
            © <?= date("Y") ?> Event Planner. All rights reserved.
        </p>

    </div>

</footer>

<style>

    .admin-footer {
        margin-left: 250px;
        padding: 20px 30px;
        background: #fffdf7;
        border-top: 1px solid #f0d88a;
        text-align: center;
    }

    .footer-content p {
        margin: 0;
        color: #8c7858;
        font-size: 13px;
    }

    @media (max-width: 900px) {

        .admin-footer {
            margin-left: 0;
            padding: 18px 15px;
        }

    }

</style>