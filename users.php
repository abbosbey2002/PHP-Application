<?php

session_start();

require_once 'functions.php';
require_once 'classes/MySQL.php';

if ($controlIpAddress) {
  controlCorrectIpAddress();
}

controlLogin();

$mysql = new MySQL();

$messageError = '';

$visiblePanelListUser = 'hidden';
$visiblePanelEditUser = 'hidden';

if (isset($_GET['user'])) {
  $visiblePanelEditUser = 'show';
} else {
  $visiblePanelListUser = 'show';
}

$userId = isset($_GET['user']) ? $_GET['user'] : 0;

$userLogin = isset($_POST['userLogin']) ? trim($_POST['userLogin']) : '';
$userPass = isset($_POST['userPass']) ? trim($_POST['userPass']) : '';
$userName = isset($_POST['userName']) ? trim($_POST['userName']) : '';
$userRole = isset($_POST['userRole']) ? trim($_POST['userRole']) : '';
$userSelCity = '0'; //$userSelCity = isset($_POST['userSelCity']) ? trim($_POST['userSelCity']) : '';
$userIsBoss = isset($_POST['userIsBoss']) ? trim($_POST['userIsBoss']) : '';
$userCessionId = isset($_POST['userCessionId']) ? trim($_POST['userCessionId']) : 0;


//--- кнопка Сохранить пользователя ---
if (isset($_POST['buttonSaveUser'])) {
  //--- проверка на уникальность ---
  $sql = "select `id` from `users` where `login` = '" . addslashes($userLogin) . "' and `id` <> " . $userId;
  $zapUsers = $mysql->querySelect($sql);
  if ($zapUsers) {
    $messageError = "Пользователь с логином \"" . $userLogin ."\" уже существует в системе.";
  }

  if ($messageError == '') {
    if ($userId > 0) {
      $sql = "update `users` set `login` = '" . addslashes($userLogin) . "', `pass` = '" . addslashes($userPass) . "', `name` = '" . addslashes($userName) . "', `role` = '" . addslashes($userRole) . "', `selCity` = " . $userSelCity . ", `isBoss` = " . $userIsBoss . ", `cessionId` = " . $userCessionId . " where `id` = " . $userId;
      $mysql->queryUpdate($sql);
    } else {
      $sql = "insert into `users` (`login`, `pass`, `name`, `role`, `selCity`, `isBoss`, `cessionId`) values ('" . $userLogin . "', '" . addslashes($userPass) . "', '" . addslashes($userName) . "', '" . addslashes($userRole) . "', " . $userSelCity . ", " . $userIsBoss . ", " . $userCessionId . ")";
      $userId = $mysql->queryInsert($sql);
      //if ($userId > 0) {
        //header('Location: ?user=' . $userId);
        //exit();
      //}
    }
    header('Location: ?');
    exit();
  }
}


//--- цикл по переменным $_POST ---
foreach ($_POST as $key => $value) {

  //--- удаление пользователя ---
  if (strpos($key, 'deleteUser_') !== false) {
    $idForDel = substr($key, 11);
    // проверка на возможность удаления
    $sql = "select d.`id`, u.`login` from `documents` d inner join `users` u on u.`login` = d.`userCreate` where u.`id` = " . $idForDel;
    $zapExsts = $mysql->querySelect($sql);
    if ($zapExsts) {
      $messageError = 'Удаление пользователя "' . $zapExsts[0]['login'] . '" невозможно, так как имеются созданные им документы.';
    } else {
      $sql = "delete from `users` where `id` = " . $idForDel;
      $mysql->queryDelete($sql);
    }
  }

}


require_once 'header.php';

?>


<?php if ($visiblePanelListUser == 'show') { ?>
<!-- ------------------------------ панель списка пользователей ------------------------------- -->

<div class="pageTitle">Пользователи</div>

<div class="panelAction">
  <a class="buttonAction" href="?user=0">Создать нового пользователя</a>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<!-- панель для табличной части -->
<div id="dataContainer"></div>

<script>
  $.post('userPage.php', {'page' : 1}, function(data) { $('#dataContainer').html(data); });
</script>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditUser == 'show') { ?>
<!-- ------------------------------ панель редактирования пользователя ------------------------ -->

<?php
  if ($userId > 0 && !isset($_POST['userLogin'])) {
    $sql = "select * from `users` where `id` = " . $userId;
    $zapUsers = $mysql->querySelect($sql);
    if ($zapUsers) {
      $userLogin = $zapUsers[0]['login'];
      $userPass = $zapUsers[0]['pass'];
      $userName = $zapUsers[0]['name'];
      $userRole = $zapUsers[0]['role'];
      $userSelCity = $zapUsers[0]['selCity'];
      $userIsBoss = $zapUsers[0]['isBoss'];
      $userCessionId = $zapUsers[0]['cessionId'];
    }
  }
?>

<div class="pageTitle">Пользователи</div>

<div class="parTitle">
  <?php
    if ($userId > 0) {
      echo 'Пользователь "' . $userLogin . '" "' . $userName . '"';
    } else {
      echo 'Новый пользователь';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?">Назад</a>
  <button type="submit" name="buttonSaveUser" class="buttonAction" onclick="controlRequiredUser()">Сохранить</button>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<div class="panelEditor">
  <div class="panelEditorRow">
    <div class="panelEditorLabel">Логин:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue fontBold" id="userLogin" name="userLogin" value="<?=htmlspecialchars($userLogin)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Пароль:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue" id="userPass" name="userPass" value="<?=htmlspecialchars($userPass)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Имя пользователя:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue" id="userName" name="userName" value="<?=htmlspecialchars($userName)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Роль пользователя:</div>
    <div class="panelEditorValue">
      <select id="userRole" name="userRole" class="inputValue">
        <option value="" <?=($userRole == '' ? 'selected' : '')?>></option>
        <option value="admin" <?=($userRole == 'admin' ? 'selected' : '')?>>Администратор</option>
        <option value="user" <?=($userRole == 'user' ? 'selected' : '')?>>Менеджер</option>
      </select>
    </div>
  </div>

  <!--
  <div class="panelEditorRow">
    <div class="panelEditorLabel">Выбор города:</div>
    <div class="panelEditorValue">
      <select id="userSelCity" name="userSelCity" class="inputValue">
        <option value="0" <?=($userSelCity == '0' ? 'selected' : '')?>>Нет</option>
        <option value="1" <?=($userSelCity == '1' ? 'selected' : '')?>>Да</option>
      </select>
    </div>
  </div>
  -->

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Генеральный директор:</div>
    <div class="panelEditorValue">
      <select id="userIsBoss" name="userIsBoss" class="inputValue">
        <option value="0" <?=($userIsBoss == '0' ? 'selected' : '')?>>Нет</option>
        <option value="1" <?=($userIsBoss == '1' ? 'selected' : '')?>>Да</option>
      </select>
    </div>
  </div>

  <?php
    $sprCessionId = 0;
    $sql = "select `id` from `registers` where `name` = 'Цессионарий'";
    $zapCess = $mysql->querySelect($sql);
    if ($zapCess) {
      $sprCessionId = $zapCess[0]['id'];
    }
    $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $sprCessionId . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) order by d.`fieldValue`";
    $zapCessions = $mysql->querySelect($sql);
  ?>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Цессионарий:</div>
    <div class="panelEditorValue">
      <select id="userCessionId" name="userCessionId" class="inputValue">
        <option value="0" <?=($userCessionId == '0' ? 'selected' : '')?>></option>
        <?php
          if ($zapCessions) {
            foreach ($zapCessions as $zapCession) {
              echo '<option value="' . $zapCession['zapId'] . '" ' . ($zapCession['zapId'] == $userCessionId ? 'selected' : '') . '>' . $zapCession['fieldValue'] . '</option>';
            }
          }
        ?>
      </select>
    </div>
  </div>

</div>


<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<script>
  function controlRequiredUser() {
    $('#userLogin').css('border-color', '');
    var userLogin = $('#userLogin').val().trim();
    if (userLogin == '') {
      $('#userLogin').css('border-color', 'red');
      alert('Не заполнено поле "Логин".');
      event.preventDefault();
      return false;
    }

    $('#userPass').css('border-color', '');
    var userPass = $('#userPass').val().trim();
    if (userPass == '') {
      $('#userPass').css('border-color', 'red');
      alert('Не заполнено поле "Пароль".');
      event.preventDefault();
      return false;
    }

    $('#userName').css('border-color', '');
    var userName = $('#userName').val().trim();
    if (userName == '') {
      $('#userName').css('border-color', 'red');
      alert('Не заполнено поле "Имя пользователя".');
      event.preventDefault();
      return false;
    }

    $('#userRole').css('border-color', '');
    var userRole = $('#userRole').val().trim();
    if (userRole == '') {
      $('#userRole').css('border-color', 'red');
      alert('Не заполнено поле "Роль пользователя".');
      event.preventDefault();
      return false;
    }
  }
</script>


<?php

require_once 'footer.php';

?>
