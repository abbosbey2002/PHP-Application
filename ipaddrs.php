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

$visiblePanelListAddr = 'hidden';
$visiblePanelEditAddr = 'hidden';

if (isset($_GET['ipaddr'])) {
  $visiblePanelEditAddr = 'show';
} else {
  $visiblePanelListAddr = 'show';
}

$addressIpId = isset($_GET['ipaddr']) ? $_GET['ipaddr'] : 0;

$addressIpAddress = isset($_POST['addressIpAddress']) ? trim($_POST['addressIpAddress']) : '';
$addressIpComment = isset($_POST['addressIpComment']) ? trim($_POST['addressIpComment']) : '';


//--- кнопка Сохранить ip-адрес ---
if (isset($_POST['buttonSaveAddr'])) {
  //--- проверка на уникальность ---
  $sql = "select `id` from `ipaddrs` where `ipaddress` = '" . addslashes($addressIpAddress) . "' and `id` <> " . $addressIpId;
  $zapAddrs = $mysql->querySelect($sql);
  if ($zapAddrs) {
    $messageError = "IP-адрес  \"" . $addressIpAddress ."\" уже существует в системе.";
  }

  if (!filter_var($addressIpAddress, FILTER_VALIDATE_IP)) {
    $messageError = "Недопустимый IP-адрес.";
  }

  if ($messageError == '') {
    if ($addressIpId > 0) {
      $sql = "update `ipaddrs` set `ipaddress` = '" . addslashes($addressIpAddress) . "', `comment` = '" . addslashes($addressIpComment) . "' where `id` = " . $addressIpId;
      $mysql->queryUpdate($sql);
    } else {
      $sql = "insert into `ipaddrs` (`ipaddress`, `comment`) values ('" . $addressIpAddress . "', '" . addslashes($addressIpComment) . "')";
      $addressIpId = $mysql->queryInsert($sql);
      //if ($addressIpId > 0) {
        //header('Location: ?user=' . $addressIpId);
        //exit();
      //}
    }
    header('Location: ?');
    exit();
  }
}


//--- цикл по переменным $_POST ---
foreach ($_POST as $key => $value) {

  //--- удаление ip-адреса ---
  if (strpos($key, 'deleteAddr_') !== false) {
    $idForDel = substr($key, 11);
    $sql = "delete from `ipaddrs` where `id` = " . $idForDel;
    $mysql->queryDelete($sql);
  }

}


require_once 'header.php';

?>


<?php if ($visiblePanelListAddr == 'show') { ?>
<!-- ------------------------------ панель списка ip-адресов ------------------------------- -->

<div class="pageTitle">Список разрешенных IP-адресов</div>

<div class="panelAction">
  <a class="buttonAction" href="?ipaddr=0">Создать новый IP-адрес</a>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<!-- панель для табличной части -->
<div id="dataContainer"></div>

<script>
  $.post('ipaddrsPage.php', {'page' : 1}, function(data) { $('#dataContainer').html(data); });
</script>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditAddr == 'show') { ?>
<!-- ------------------------------ панель редактирования ip-адреса ------------------------ -->

<?php
  if ($addressIpId > 0 && !isset($_POST['addressIpAddress'])) {
    $sql = "select * from `ipaddrs` where `id` = " . $addressIpId;
    $zapAddrs = $mysql->querySelect($sql);
    if ($zapAddrs) {
      $addressIpAddress = $zapAddrs[0]['ipaddress'];
      $addressIpComment = $zapAddrs[0]['comment'];
    }
  }
?>

<div class="pageTitle">Список разрешенных IP-адресов</div>

<div class="parTitle">
  <?php
    if ($addressIpId > 0) {
      echo 'IP-адрес "' . $addressIpAddress . '"';
    } else {
      echo 'Новый IP-адрес';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?">Назад</a>
  <button type="submit" name="buttonSaveAddr" class="buttonAction" onclick="controlRequiredAddress()">Сохранить</button>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<div class="panelEditor">
  <div class="panelEditorRow">
    <div class="panelEditorLabel">IP-адрес:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue fontBold" id="addressIpAddress" name="addressIpAddress" value="<?=htmlspecialchars($addressIpAddress)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Комментарий:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue" id="addressIpComment" name="addressIpComment" value="<?=htmlspecialchars($addressIpComment)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

</div>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<script>
  function controlRequiredAddress() {
    $('#addressIpAddress').css('border-color', '');
    var addressIpAddress = $('#addressIpAddress').val().trim();
    if (addressIpAddress == '') {
      $('#addressIpAddress').css('border-color', 'red');
      alert('Не заполнено поле "IP-адрес".');
      event.preventDefault();
      return false;
    }

    $('#addressIpComment').css('border-color', '');
    var addressIpComment = $('#addressIpComment').val().trim();
    if (addressIpComment == '') {
      $('#addressIpComment').css('border-color', 'red');
      alert('Не заполнено поле "Комментарий".');
      event.preventDefault();
      return false;
    }
  }
</script>


<?php

require_once 'footer.php';

?>
