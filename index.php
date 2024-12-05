<?php

session_start();

require_once 'functions.php';

if ($controlIpAddress) {
  controlCorrectIpAddress();
}

controlLogin();

header('Location: document.php');
exit();

?>
