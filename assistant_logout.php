<?php
require 'config.php';
unset($_SESSION['assistant_id'], $_SESSION['assistant_name'], $_SESSION['assistant_sid']);
header("Location: assistant_login.php");
exit();