<?php
// Change 'Admin@123' to whatever password you want to use
$password = 'Admin@123'; 
echo password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
?>