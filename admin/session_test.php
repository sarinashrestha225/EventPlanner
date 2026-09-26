<?php

session_start();

echo "<h2>Admin Session Test</h2>";

echo "<pre>";
print_r($_SESSION);
echo "</pre>";