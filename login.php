<?php

session_start();

unset($_SESSION['userLogin']);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/MySQL.php';

if ($controlIpAddress) {
  controlCorrectIpAddress();
}

$mysql = new MySQL();

$msgLoginError = '';

$inputLogin = isset($_POST['inputLogin']) ? trim($_POST['inputLogin']) : '';
$inputPassword = isset($_POST['inputPassword']) ? trim($_POST['inputPassword']) : '';


//--- кнопка Войти в систему ---
if (isset($_POST['buttonLogin'])) {
  $listIp = getIpAddressesClient();
  toLog('LOGIN: ' . $inputLogin . ' ' . implode(',', $listIp));

  $sql = "select `id` from `users` where `login` = '" . $inputLogin . "' and `pass` = '" . $inputPassword . "'";
  $zapUser = $mysql->querySelect($sql);
  if ($zapUser) {
    $_SESSION['userLogin'] = $inputLogin;
    header('Location: index.php');
    exit();
  } else {
    $msgLoginError = 'Неверный логин или пароль.';
    toLog('LOGIN ERROR: ' . $inputLogin . ' ' . $inputPassword);
  }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ГЕНЕРАТОР-ДОКУМЕНТОВ.РФ</title>
  <link rel="stylesheet" type="text/css" href="styles.css">
  <link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
  <script src="lib/jquery-3.5.1.min.js"></script>
  <script src="lib/jquery.maskedinput.min.js"></script>
</head>

<body>
<form id="formMain" name="formMain" method="post" enctype="multipart/form-data" style="height: 100%;">

<div class="loginMain">
  <div class="loginPanel">

    <div class="loginTitle">
      ГЕНЕРАТОР-ДОКУМЕНТОВ.РФ
    </div>

    <div class="loginText">
      Логин:
    </div>
    <div class="loginInput">
      <input type="text" name="inputLogin" class="inputLogin" placeholder="введите логин" value="<?=htmlspecialchars($inputLogin)?>" required onkeypress="noSubmitEnter(event);">
    </div>

    <div class="loginText">
      Пароль:
    </div>
    <div class="loginInput">
      <input type="password" name="inputPassword" class="inputLogin" placeholder="введите пароль" required onkeypress="noSubmitEnter(event);">
    </div>

    <div class="loginError">
      <span class="loginError"><?=$msgLoginError?></span>
    </div>

    <div class="loginButton">
      <button type="submit" class="buttonLogin" name="buttonLogin">
        Войти в систему
      </button>
    </div>

  </div>
</div>


<script>
  function noSubmitEnter(event) {
    if (event.keyCode == 13 || event.which == 13) {
      event.preventDefault();
    }
  }
</script>


</form>
</body>
</html>
