<?php

require_once 'classes/UserInfo.php';

//--- кнопка Выйти из системы ---
if (isset($_POST['buttonLogout'])) {
  header('Location: login.php');
  exit();
}

$userInfo = new UserInfo(isset($_SESSION['userLogin']) ? $_SESSION['userLogin'] : '');

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
<form id="formMain" name="formMain" method="post" enctype="multipart/form-data" style="height: 100%;" autocomplete="off">

<div class="generalContent">

<!-- шапка сайта -->
<div class="headerSite">
  <div class="logoUser">
    Пользователь: <span class="spanUserInfo"><?=$userInfo->userLogin?></span>,
    имя: <span class="spanUserInfo"><?=$userInfo->userName?></span>,
    роль: <span class="spanUserInfo"><?=$userInfo->userRoleName;?></span>
  </div>

  <div class="logoTitle">
    <a href="index.php" class="logoTitle">ГЕНЕРАТОР-ДОКУМЕНТОВ.РФ</a>
  </div>

  <div class="logoExit">
    <button type="submit" class="buttonAction" name="buttonLogout">
      Выйти из системы
    </button>
  </div>
</div>

<!-- главное меню -->
<div class="panelMainMenu">
  <div class="itemMenuMain"><a class="itemMenuMain" href="document.php">Реестр документов</a></div>
  <?php if ($userInfo->userRoleCode == 'admin') { ?>
    <div class="itemMenuMain"><a class="itemMenuMain" href="register.php">Справочники</a></div>
    <div class="itemMenuMain"><a class="itemMenuMain" href="doccons.php">Конструктор документов</a></div>
    <div class="itemMenuMain"><a class="itemMenuMain" href="users.php">Пользователи</a></div>
    <div class="itemMenuMain"><a class="itemMenuMain" href="ipaddrs.php">IP-адреса</a></div>
  <?php } ?>
</div>


<!-- содержимое страницы -->
<div class="mainContent">
