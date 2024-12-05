<?php

require_once 'config.php';
require_once 'classes/MySQL.php';


//--- запись в лог ---
function toLog($msg) {
  file_put_contents(__DIR__ . '/events.log', date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL, FILE_APPEND);
}

//--- вывод сообщения об ошибке на странице ---
function echoError($msg) {
  if ($msg) {
      echo '<span style="color:#f00; font-size:16px; font-style:italic;">' . $msg . '</span><br>';
  }
}


//--- проверка залогиненного пользователя ---
function controlLogin() {
  if (isset($_POST['buttonLogout'])) {
    unset($_SESSION['userLogin']);
    foreach ($_SESSION as $key => $value) {
      unset($_SESSION[$key]);
    }
  }

  if (!isset($_SESSION['userLogin']) || $_SESSION['userLogin'] == '') {
    header('Location: login.php');
    exit();
  }
}


//--- наименование роли пользователя ---
function getUserRoleName($userRoleCode) {
  $userRoleName = '';
  switch ($userRoleCode) {
    case 'admin':
      $userRoleName = 'Администратор';
      break;
    case 'user':
      $userRoleName = 'Менеджер';
      break;
  }
  return $userRoleName;
}


//--- типы полей справочника ---
function getTypesFields($isDoc = false, $key = null) {
  $types = [];
  $types['text'] = 'Текст';
  $types['textarea'] = 'Многострочный текст';
  $types['number'] = 'Целое число';
  $types['decimal'] = 'Десятичное число';
  $types['date'] = 'Дата';
  $types['time'] = 'Время';
  $types['bool'] = 'Да/нет';
  $types['fio'] = "ФИО";
  $types['numrus'] = 'Только цифры и русские буквы';

  if ($isDoc) {
    $mysql = new MySQL();
    $sql = "select `id`, `name`, `typeSprav` from `registers` order by `name`";
    $zapSprs = $mysql->querySelect($sql);
    if ($zapSprs) {
      foreach ($zapSprs as $zapSpr) {
        if ($zapSpr['typeSprav'] == 0) {
          $types['list_' . $zapSpr['id']] = 'Перечень "' . $zapSpr['name'] . '"';
        }
        if ($zapSpr['typeSprav'] == 1) {
          $types['sprav_' . $zapSpr['id']] = 'Справочник "' . $zapSpr['name'] . '"';
        }
      }
    }
  }

  if ($key == null) {
    return $types;
  } else {
    return $types[$key];
  }
}


//--- проверка типа поля ---
function verificationTypeField($fieldType, $fieldValue, $fieldName) {
  $res = '';

  if ($fieldType == 'number' && $fieldValue != '') {
    if (!preg_match("/^\d+$/", $fieldValue)) {
      $res .= ($res == '' ? '' : '<br>') . "Значение \"" . htmlspecialchars($fieldValue) . "\" в поле \"" . htmlspecialchars($fieldName) . "\" не является целым числом.";
    }
  }

  if ($fieldType == 'decimal' && $fieldValue != '') {
    if (!is_numeric($fieldValue)) {
      $res .= ($res == '' ? '' : '<br>') . "Значение \"" . htmlspecialchars($fieldValue) . "\" в поле \"" . htmlspecialchars($fieldName) . "\" не является десятичным числом.";
    }
  }

  if ($fieldType == 'date' && $fieldValue != '') {
    // проверяется элементом управления 'date'
  }

  if ($fieldType == 'time' && $fieldValue != '') {
    $timeTest = $fieldValue;
    $isTime = true;
    if (!preg_match("/^[0-9]{2}:[0-9]{2}$/", $timeTest)) {
      $isTime = false;
    } else {
      if (substr($timeTest, 0, 2) > 24 || substr($timeTest, 3, 2) > 59) {
        $isTime = false;
      }
    }
    if (!$isTime) {
      $res .= ($res == '' ? '' : '<br>') . "Значение \"" . htmlspecialchars($fieldValue) . "\" в поле \"" . htmlspecialchars($fieldName) . "\" не является временем.";
    }
  }

  if ($fieldType == 'bool' && $fieldValue != '') {
    if (mb_strtolower($fieldValue) != 'да' && mb_strtolower($fieldValue) != 'нет') {
      $res .= ($res == '' ? '' : '<br>') . "Значение \"" . htmlspecialchars($fieldValue) . "\" в поле \"" . htmlspecialchars($fieldName) . "\" не является допустимым значением.";
    }
  }

  if ($fieldType == 'fio' && $fieldValue != '') {
    if (mb_strpos($fieldValue, ' ') === false) {
      $res .= ($res == '' ? '' : '<br>') . "Значение \"" . htmlspecialchars($fieldValue) . "\" в поле \"" . htmlspecialchars($fieldName) . "\" не является допустимым значением для ФИО.";
    }
  }

  if ($fieldType == 'numrus' && $fieldValue != '') {
    //if (!preg_match("/^[\d а-яА-Я]+$/", $fieldValue)) {
    if (!preg_match("/^[0123456789 абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ]+$/", $fieldValue)) {
        $res .= ($res == '' ? '' : '<br>') . "Значение \"" . htmlspecialchars($fieldValue) . "\" в поле \"" . htmlspecialchars($fieldName) . "\" содержит недопустимые символы.";
    }
  }

  return $res;
}


//--- ip-адреса клиента ---
function getIpAddressesClient() {
  $listIp = [];
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$listIp[] = $_SERVER['HTTP_CLIENT_IP'];
  }
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$listIp[] = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
  if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
		$listIp[] = $_SERVER['HTTP_X_REAL_IP'];
  }
  if (!empty($_SERVER['REMOTE_ADDR'])) {
		$listIp[] = $_SERVER['REMOTE_ADDR'];
  }

  return $listIp;
}


//--- проверка разрешенного ip-адреса ---
function controlCorrectIpAddress() {
  $isCorrect = false;

  $listIp = getIpAddressesClient();

  $mysql = new MySQL();
  $sql = "select `ipaddress` from `ipaddrs`";
  $zapIps = $mysql->querySelect($sql);
  if ($zapIps) {
    foreach ($zapIps as $zapIp) {
      if (in_array($zapIp['ipaddress'], $listIp)) {
        $isCorrect = true;
        break;
      }
    }
  }

  if (!$isCorrect) {
    //header('Location: noaccess.php');
    //exit();
  }
}


?>
