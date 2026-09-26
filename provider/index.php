<?php 
 
session_start(); 
 
if (isset($_SESSION['provider_id'])) { 
 
    header("Location: dashboard.php"); 
    exit; 
 
} 
 
?> 
 
<!DOCTYPE html> 
 
<html lang="en"> 
 
<head> 
 
<meta charset="UTF-8"> 
 
<meta 
    name="viewport" 
    content="width=device-width, initial-scale=1.0" 
> 
 
<title> 
    Provider - Event Planner 
</title> 
 
 
<style> 
 
* { 
    box-sizing: border-box; 
} 
 
 
body { 
 
    margin: 0; 
 
    min-height: 100vh; 
 
    font-family: 
        Arial, 
        Helvetica, 
        sans-serif; 
 
    background: #fff8f0; 
 
    color: #4b3621; 
 
    display: flex; 
 
    justify-content: center; 
 
    align-items: center; 
} 
 
 
.container { 
 
    width: 900px; 
 
    max-width: 92%; 
 
    display: grid; 
 
    grid-template-columns: 1fr 1fr; 
 
    background: #ffffff; 
 
    border-radius: 20px; 
 
    overflow: hidden; 
 
    box-shadow: 
        0 10px 35px 
        rgba(0,0,0,0.10); 
} 
 
 
.left { 
 
    background: #4b3621; 
 
    color: white; 
 
    padding: 55px 40px; 
 
    display: flex; 
 
    flex-direction: column; 
 
    justify-content: center; 
 
    text-align: center; 
} 
 
 
.logo { 
 
    font-size: 70px; 
 
    margin-bottom: 15px; 
} 
 
 
.left h1 { 
 
    margin: 0 0 15px; 
 
    color: #d4af37; 
 
    font-size: 32px; 
} 
 
 
.left p { 
 
    color: #f5eee5; 
 
    line-height: 1.7; 
 
    margin: 0; 
} 
 
 
.right { 
 
    padding: 50px 40px; 
 
    display: flex; 
 
    flex-direction: column; 
 
    justify-content: center; 
} 
 
 
.right h2 { 
 
    margin-top: 0; 
 
    color: #4b3621; 
 
    font-size: 28px; 
} 
 
 
.subtitle { 
 
    color: #777; 
 
    line-height: 1.6; 
 
    margin-bottom: 30px; 
} 
 
 
.btn { 
 
    display: block; 
 
    width: 100%; 
 
    padding: 14px; 
 
    border-radius: 9px; 
 
    text-align: center; 
 
    text-decoration: none; 
 
    font-weight: bold; 
 
    margin-bottom: 15px; 
 
    transition: 0.2s; 
} 
 
 
.login-btn { 
 
    background: #d4af37; 
 
    color: white; 
} 
 
 
.login-btn:hover { 
 
    background: #b8860b; 
} 
 
 
.register-btn { 
 
    background: #f5eee5; 
 
    color: #4b3621; 
 
    border: 1px solid #ddd; 
} 
 
 
.register-btn:hover { 
 
    background: #eee2d2; 
} 
 
 
.customer-link { 
 
    margin-top: 15px; 
 
    text-align: center; 
 
    color: #777; 
 
    font-size: 14px; 
} 
 
 
.customer-link a { 
 
    color: #b8860b; 
 
    text-decoration: none; 
 
    font-weight: bold; 
} 
 
 
@media(max-width: 700px) { 
 
    body { 
 
        padding: 20px; 
    } 
 
 
    .container { 
 
        grid-template-columns: 1fr; 
    } 
 
 
    .left { 
 
        padding: 35px 25px; 
    } 
 
 
    .logo { 
 
        font-size: 55px; 
    } 
 
 
    .left h1 { 
 
        font-size: 27px; 
    } 
 
 
    .right { 
 
        padding: 35px 25px; 
    } 
 
} 
 
</style> 
 
</head> 
 
 
<body> 
 
 
<div class="container"> 
 
 
    <div class="left"> 
 
        <div class="logo"> 
            👨‍💼 
        </div> 
 
 
        <h1> 
            Event Planner 
        </h1> 
 
 
        <p> 
 
            Become a service provider and 
            connect with customers looking 
            for event services. 
 
        </p> 
 
    </div> 
 
 
    <div class="right"> 
 
 
        <h2> 
            Provider Portal 
        </h2> 
 
 
        <div class="subtitle"> 
 
            Manage your services, bookings, 
            payments, portfolio and customers 
            from one place. 
 
        </div> 
 
 
        <a 
            href="login.php" 
            class="btn login-btn" 
        > 
 
            🔐 Provider Login 
 
        </a> 
 
 
        <a 
            href="register.php" 
            class="btn register-btn" 
        > 
 
            📝 Become a Provider 
 
        </a> 
 
 
        <div class="customer-link"> 
 
            Looking for event services? 
 
            <a href="../index.php"> 
                Go to Event Planner 
            </a> 
 
        </div> 
 
 
    </div> 
 
 
</div> 
 
 
</body> 
 
</html>