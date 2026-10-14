<?php
// test_hash.php
$hash_from_db = '$2y$10$SrYpZ3Roq.tCwjN342iL.eHjUBFNRjiX5X7TKX3VMzfnOaWfqEc16';
$password_you_are_typing = 'Admin@123'; // Change to the password you are typing

if (password_verify($password_you_are_typing, $hash_from_db)) {
    echo "✅ Success! The password matches the hash.";
} else {
    echo "❌ Failed. The hash does not match the password you typed.";
}