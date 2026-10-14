<?php
session_start();
session_unset();
session_destroy();
header('Location: /Stepcare/loginNsignup/login/login.html');
exit;