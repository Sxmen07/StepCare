<?php
session_start();
session_unset();
session_destroy();
header('Location: /stepcare/loginNsignup/login/login.html');
exit;