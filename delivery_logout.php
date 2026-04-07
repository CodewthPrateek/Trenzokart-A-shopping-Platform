<?php
require 'config.php';
unset($_SESSION['delivery_boy_id'], $_SESSION['delivery_boy_name'], $_SESSION['delivery_boy_phone']);
header("Location: delivery_login.php");
exit();