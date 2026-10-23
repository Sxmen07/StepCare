<?php
session_start();
session_unset();
session_destroy();
header('Location: /StepCare/loginNsignup/login/login.html');
exit;
?>