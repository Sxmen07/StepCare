<?php
// config/generate_hash.php
// ⚠️ DELETE THIS FILE AFTER YOU USE IT

$plainPassword = 'Admin@123';   // <-- change to whatever you want

$hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Plain password: " . $plainPassword . "<br>";
echo "Bcrypt hash: <br><textarea rows='3' cols='80'>" . $hash . "</textarea>";