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

$visiblePanelListRegister = 'hidden';
$visiblePanelEditRegister = 'hidden';
$visiblePanelMakeRegister = 'hidden';
$visiblePanelEditField = 'hidden';
$visiblePanelEditZapis = 'hidden';

if (isset($_GET['zap'])) {
  $visiblePanelEditZapis = 'show';
} elseif (isset($_GET['field'])) {
  $visiblePanelEditField = 'show';
} elseif (isset($_GET['designer'])) {
  $visiblePanelMakeRegister = 'show';
} elseif (isset($_GET['register'])) {
  $visiblePanelEditRegister = 'show';
} else {
  $visiblePanelListRegister = 'show';
}

$registerId = isset($_GET['designer']) ? $_GET['designer'] : 0;
$registerId = isset($_GET['register']) ? $_GET['register'] : $registerId;
$fieldId = isset($_GET['field']) ? $_GET['field'] : 0;
$zapisId = isset($_GET['zap']) ? $_GET['zap'] : 0;

$registerName = isset($_POST['registerName']) ? $_POST['registerName'] : '';
$registerComment = isset($_POST['registerComment']) ? $_POST['registerComment'] : '';
$typeSprav = isset($_POST['typeSprav']) ? $_POST['typeSprav'] : 1;

$fieldName = isset($_POST['fieldName']) ? $_POST['fieldName'] : '';
$fieldComment = isset($_POST['fieldComment']) ? $_POST['fieldComment'] : '';
$fieldType = isset($_POST['fieldType']) ? $_POST['fieldType'] : '';
$fieldRequired = isset($_POST['fieldRequired']) ? $_POST['fieldRequired'] : 0;
$fieldUnique = isset($_POST['fieldUnique']) ? $_POST['fieldUnique'] : 0;
$fieldPriority = isset($_POST['fieldPriority']) ? $_POST['fieldPriority'] : 0;


//--- кнопка Сохранить справочник ---
if (isset($_POST['buttonSaveDesigner'])) {
  $sql = "select `id` from `registers` where `name` = '" . addslashes($registerName) . "' and `id` <> " . $registerId;
  $zapRegs = $mysql->querySelect($sql);
  if ($zapRegs) {
    $messageError .= ($messageError == '' ? '' : '<br>') . 'Справочник с наименованием "' . $registerName . '" уже существует.';
  } else {
    if ($registerId > 0) {
      $sql = "update `registers` set `name` = '" . addslashes($registerName) . "', `comment` = '" . addslashes($registerComment) . "', `typeSprav` = " . $typeSprav . " where `id` = " . $registerId;
      $mysql->queryUpdate($sql);
    } else {
      $sql = "insert into `registers` (`name`, `comment`, `typeSprav`) values ('" . addslashes($registerName) . "', '" . addslashes($registerComment) . "', " . $typeSprav . ")";
      $registerId = $mysql->queryInsert($sql);
      if ($registerId > 0) {
        header('Location: ?designer=' . $registerId);
        exit();
      }
    }
  }
}


//--- кнопка Сохранить поле ---
if (isset($_POST['buttonSaveField'])) {
  if ($fieldId > 0) {
    $sql = "update `registers_cons` set `fieldName` = '" . addslashes($fieldName) . "', `comment` = '" . addslashes($fieldComment) . "', `fieldType` = '" . $fieldType . "', `isRequired` = " . $fieldRequired . ", `isUnique` = " . $fieldUnique . ", `priority` = " . $fieldPriority . " where `id` = " . $fieldId;
    $mysql->queryUpdate($sql);
  } else {
    $sql = "insert into `registers_cons` (`registerId`, `fieldName`, `comment`, `fieldType`, `isRequired`, `isUnique`, `priority`) values (" . $registerId . ", '" . addslashes($fieldName) . "', '" . addslashes($fieldComment) . "', '" . $fieldType . "', " . $fieldRequired . ", " . $fieldUnique . ", " . $fieldPriority . ")";
    $fieldId = $mysql->queryInsert($sql);
    //if ($fieldId > 0) {
      //header('Location: ?designer=' . $registerId . '&field=' . $fieldId);
      //exit();
    //}
  }
  header('Location: ?designer=' . $registerId);
  exit();
}


//--- кнопка Сохранить запись справочника ---
if (isset($_POST['buttonSaveZapis'])) {
  if ($zapisId == 0) {
    $sql = "select max(`zapId`) as newZapId from `registers_data`";
    $zapNewIds = $mysql->querySelect($sql);
    $newZapId = 1;
    if ($zapNewIds) {
      $newZapId = $zapNewIds[0]['newZapId'] + 1;
    }
  }
  $sqlComs = [];
  $isCorrect = true;
  foreach ($_POST as $key => $value) {
    if (strpos($key, 'fieldRegister_') !== false) {
      $idField = substr($key, 14);
      $sql = "select `fieldName`, `fieldType`, `isUnique` from `registers_cons` where `id` = " . $idField;
      $zapCons = $mysql->querySelect($sql);
      if ($zapCons) {
        $fieldName = $zapCons[0]['fieldName'];
        // проверка на уникальность
        $isUnique = $zapCons[0]['isUnique'];
        if ($isUnique == 1) {
          $sql = "select `id` from `registers_data` where `fieldId` = " . $idField . " and `fieldValue` = '" . addslashes(trim($value)) . "' and `zapId` <> " . $zapisId;
          $zapUniVals = $mysql->querySelect($sql);
          if ($zapUniVals) {
            $isCorrect = false;
            $messageError .= ($messageError == '' ? '' : '<br>') . "В справочнике уже существует запись со значением \"" . htmlspecialchars(trim($value)) . "\" в поле \"" . htmlspecialchars($fieldName) . "\".";
            break;
          }
        }

        // проверка типа поля
        $fieldType = $zapCons[0]['fieldType'];
        $verify = verificationTypeField($fieldType, trim($value), $fieldName);
        if ($verify != '') {
          $messageError .= ($messageError == '' ? '' : '<br>') . $verify;
        }
        if ($messageError != '') {
          $isCorrect = false;
        }
      }

      // запись в базу данных
      if ($zapisId > 0) {
        $sql = "select `id` from `registers_data` where `zapId` = " . $zapisId . " and `fieldId` = " . $idField;
        $zapExts = $mysql->querySelect($sql);
        if ($zapExts) {
          $sqlComs[] = "update `registers_data` set `fieldValue` = '" . addslashes(trim($value)) . "' where `zapId` = " . $zapisId . " and `fieldId` = " . $idField;
        } else {
          $sqlComs[] = "insert into `registers_data` (`registerId`, `zapId`, `fieldId`, `fieldValue`) values (" . $registerId . ", " . $zapisId . ", " . $idField . ", '" . addslashes(trim($value)) . "')";
        }
      } else {
        $sqlComs[] = "insert into `registers_data` (`registerId`, `zapId`, `fieldId`, `fieldValue`) values (" . $registerId . ", " . $newZapId . ", " . $idField . ", '" . addslashes(trim($value)) . "')";
      }
    }
  }
  if ($isCorrect) {
    foreach ($sqlComs as $sql) {
      if (substr($sql, 0, 6) == 'update') {
        $mysql->queryUpdate($sql);
      } else {
        $mysql->queryInsert($sql);
      }
    }
    //if ($zapisId == 0) {
      //$zapisId = $newZapId;
      //header('Location: ?register=' . $registerId . '&zap=' . $zapisId);
      //exit();
    //}
  }
  if ($isCorrect) {
    header('Location: ?register=' . $registerId);
    exit();
  }
}


$fieldValues = [];

//--- цикл по переменным $_POST ---
foreach ($_POST as $key => $value) {

  //--- поля справочника ---
  if (strpos($key, 'fieldRegister_') !== false) {
    $idField = substr($key, 14);
    $fieldValues[$idField] = $value;
  }


  //--- удаление справочника ---
  if (strpos($key, 'deleteRegister_') !== false) {
    $idForDel = substr($key, 15);
    // проверка на возможность удаления
    $sql = "select `id` from `registers_cons` where `registerId` = " . $idForDel;
    $zapExsts = $mysql->querySelect($sql);
    if ($zapExsts) {
      $messageError = 'Удаление справочника невозможно, так как его значения используются в созданных документах.';
    } else {
      $sql = "delete from `registers` where `id` = " . $idForDel;
      $mysql->queryDelete($sql);
    }
  }


  //--- удаление поля справочника ---
  if (strpos($key, 'deleteField_') !== false) {
    $idForDel = substr($key, 12);
    // проверка на возможность удаления
    $sql = "select `id` from `registers_data` where `registerId` = " . $registerId . " and `fieldId` = " . $idForDel;
    $zapExsts = $mysql->querySelect($sql);
    if ($zapExsts) {
      $messageError = 'Удаление поля невозможно, так как оно используется в созданных документах.';
    } else {
      $sql = "delete from `registers_cons` where `registerId` = " . $registerId . " and `id` = " . $idForDel;
      $mysql->queryDelete($sql);
    }
  }


  //--- удаление записи справочника ---
  if (strpos($key, 'deleteZapis_') !== false) {
    $idForDel = substr($key, 12);
    // проверка на возможность удаления
    $sql = "select d.`id` from `documents_data` d inner join `templates_cons` c on c.`id` = d.`fieldId` where c.`fieldType` = 'sprav_" . $registerId . "' and d.`fieldValue` = '" . $idForDel . "'";
    $zapExsts = $mysql->querySelect($sql);
    if ($zapExsts) {
      $messageError = 'Удаление записи невозможно, так как она используется в созданных документах.';
    } else {
      $sql = "delete from `registers_data` where `registerId` = " . $registerId . " and `zapId` = " . $idForDel;
      $mysql->queryDelete($sql);
    }
  }

}


require_once 'header.php';

?>

<?php if ($visiblePanelListRegister == 'show') { ?>
<!-- ------------------------------ панель списка справочников -------------------------------- -->

<div class="pageTitle">Справочники</div>

<div class="panelAction">
  <a class="buttonAction" href="?designer=0">Создать новый справочник</a>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<!-- панель для табличной части -->
<div id="dataContainer"></div>

<script>
  $.post('registerPage.php', {'page' : 1}, function(data) { $('#dataContainer').html(data); });
</script>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditRegister == 'show') { ?>
<!-- ------------------------------ панель редактирования справочника ------------------------- -->

<?php
  $sql = "select `name` from `registers` where `id` = " . $registerId;
  $zapRegs = $mysql->querySelect($sql);
  if ($zapRegs) {
    $registerName = $zapRegs[0]['name'];
  }
?>

<div class="pageTitle">Справочники</div>

<div class="parTitle">
  Справочник "<?=$registerName?>"
</div>

<div class="panelAction">
  <a class="buttonAction" href="?">Назад</a>
  <a class="buttonAction" href="?register=<?=$registerId?>&zap=0">Создать новую запись</a>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<!-- панель для табличной части -->
<div id="dataContainer"></div>

<script>
  $.post('registerEdit.php', {'page' : 1, 'whereid' : <?=$registerId?>}, function(data) { $('#dataContainer').html(data); });
</script>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelMakeRegister == 'show') { ?>
<!-- ------------------------------ панель конструктора справочника --------------------------- -->

<?php
  if ($registerId > 0 && !isset($_POST['registerName'])) {
    $sql = "select * from `registers` where `id` = " . $registerId;
    $zapRegs = $mysql->querySelect($sql);
    if ($zapRegs) {
      $registerName = $zapRegs[0]['name'];
      $registerComment = $zapRegs[0]['comment'];
      $typeSprav = $zapRegs[0]['typeSprav'];
    }
  }
?>

<div class="pageTitle">Справочники</div>

<div class="parTitle">
  <?php
    if ($registerId > 0) {
      echo 'Конструктор справочника "' . $registerName . '"';
    } else {
      echo 'Конструктор нового справочника';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?">Назад</a>
  <button type="submit" name="buttonSaveDesigner" class="buttonAction" onclick="controlRequiredDesigner()">Сохранить</button>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<div class="panelEditor">
  <div class="panelEditorRow">
    <div class="panelEditorLabel">Наименование:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue fontBold" id="registerName" name="registerName" value="<?=htmlspecialchars($registerName)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Описание:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue" id="registerComment" name="registerComment" value="<?=htmlspecialchars($registerComment)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Тип справочника:</div>
    <div class="panelEditorValue">
      <select id="typeSprav" name="typeSprav" class="inputValue">
        <option value="0" <?=($typeSprav == 0 ? 'selected' : '')?>>Перечень</option>
        <option value="1" <?=($typeSprav == 1 ? 'selected' : '')?>>Справочник</option>
      </select>
    </div>
  </div>

</div>

<?php if ($registerId > 0) { ?>

<div class="parTitle">
  Поля справочника
</div>

<div class="panelAction">
  <a class="buttonAction" href="?designer=<?=$registerId?>&field=<?=$fieldId?>">Создать новое поле</a>
</div>

<!-- табличная часть -->
<div id="dataContainer"></div>

<script>
  $.post('registerCons.php', {'page' : 1, 'whereid' : <?=$registerId?>}, function(data) { $('#dataContainer').html(data); });
</script>

<?php } ?>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditField == 'show') { ?>
<!-- ------------------------------ панель редактирования поля -------------------------------- -->

<?php
  if ($fieldId > 0 && !isset($_POST['fieldName'])) {
    $sql = "select * from `registers_cons` where `id` = " . $fieldId;
    $zapFields = $mysql->querySelect($sql);
    if ($zapFields) {
      $fieldName = $zapFields[0]['fieldName'];
      $fieldComment = $zapFields[0]['comment'];
      $fieldType = $zapFields[0]['fieldType'];
      $fieldRequired = $zapFields[0]['isRequired'];
      $fieldUnique = $zapFields[0]['isUnique'];
      $fieldPriority = $zapFields[0]['priority'];
    }
  }

  $registerName = '';
  $sql = "select `name` from `registers` where `id` = " . $registerId;
  $zapRegs = $mysql->querySelect($sql);
  if ($zapRegs) {
    $registerName = $zapRegs[0]['name'];
  }
?>

<div class="pageTitle">Справочники</div>

<div class="parTitle">
  <?php
    echo 'Конструктор справочника "' . $registerName . '" - ';
    if ($fieldId > 0) {
      echo 'поле "' . $fieldName . '"';
    } else {
      echo 'новое поле';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?designer=<?=$registerId?>">Назад</a>
  <button type="submit" name="buttonSaveField" class="buttonAction" onclick="controlRequiredFieldCons()">Сохранить</button>
</div>

<div class="panelEditor">
  <div class="panelEditorRow">
    <div class="panelEditorLabel">Наименование поля:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue fontBold" id="fieldName" name="fieldName" value="<?=htmlspecialchars($fieldName)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Описание поля:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue" id="fieldComment" name="fieldComment" value="<?=htmlspecialchars($fieldComment)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Тип поля:</div>
    <div class="panelEditorValue">
      <select class="inputValue" id="fieldType" name="fieldType">
        <option value=""></option>';
        <?php
          $types = getTypesFields(true);
          foreach ($types as $key => $value) {
            echo '<option value="' . $key . '" ' . ($fieldType == $key ? 'selected' : '') . '>' . $value . '</option>';
          }
        ?>
      </select>
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Обязательное поле:</div>
    <div class="panelEditorValue">
      <select class="inputValue" id="fieldRequired" name="fieldRequired">
        <option value="0" <?=($fieldRequired == 0 ? 'selected' : '')?>>Нет</option>';
        <option value="1" <?=($fieldRequired == 1 ? 'selected' : '')?>>Да</option>';
      </select>
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Уникальное значение:</div>
    <div class="panelEditorValue">
      <select class="inputValue" id="fieldUnique" name="fieldUnique">
        <option value="0" <?=($fieldUnique == 0 ? 'selected' : '')?>>Нет</option>';
        <option value="1" <?=($fieldUnique == 1 ? 'selected' : '')?>>Да</option>';
      </select>
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Приоритет:</div>
    <div class="panelEditorValue">
      <input type="number" class="inputValue" id="fieldPriority" name="fieldPriority" value="<?=htmlspecialchars($fieldPriority)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

</div>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditZapis == 'show') { ?>
<!-- ------------------------------ панель редактирования записи справочника ------------------ -->

<?php
  if ($zapisId > 0 && !isset($_POST['fieldRegisterId'])) {
    $fieldValues = [];
    $sql = "select `fieldId`, `fieldValue` from `registers_data` where `zapId` = " . $zapisId;
    $zapZapiss = $mysql->querySelect($sql);
    if ($zapZapiss) {
      foreach ($zapZapiss as $zapZapis) {
        $fieldValues[$zapZapis['fieldId']] = $zapZapis['fieldValue'];
      }
    }
  }

  $registerName = '';
  $sql = "select `name` from `registers` where `id` = " . $registerId;
  $zapRegs = $mysql->querySelect($sql);
  if ($zapRegs) {
    $registerName = $zapRegs[0]['name'];
  }

  $fieldRegisters = [];
  $fieldRequireds = [];
  $fieldTypes = [];
  $sql = "select `id`, `fieldName`, `fieldType`, `isRequired` from `registers_cons` where `registerId` = " . $registerId . ' order by `priority` desc, `fieldName`';
  $zapFields = $mysql->querySelect($sql);
  if ($zapFields) {
    foreach ($zapFields as $zapField) {
      $fieldRegisters[$zapField['id']] = $zapField['fieldName'];
      $fieldRequireds[$zapField['id']] = $zapField['isRequired'];
      $fieldTypes[$zapField['id']] = $zapField['fieldType'];
    }
  }
?>

<div class="pageTitle">Справочники</div>

<div class="parTitle">
  <?php
    echo 'Справочник "' . $registerName . '" - ';
    if ($zapisId > 0) {
      echo 'редактирование записи';
    } else {
      echo 'новая запись';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?register=<?=$registerId?>">Назад</a>
  <button type="submit" name="buttonSaveZapis" class="buttonAction" onclick="controlRequiredZapis()">Сохранить</button>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<div class="panelEditor">
  <input type="hidden" name="fieldRegisterId" value="">
  <?php
    foreach ($fieldRegisters as $key => $value) {
      echo '<div class="panelEditorRow">';
      echo '<div class="panelEditorLabel">' . $value . ':</div>';
      echo '<div class="panelEditorValue">';
      $fieldValue = isset($fieldValues[$key]) ? $fieldValues[$key] : '';
      $classRequired = '';
      if ($fieldRequireds[$key] == 1) {
        $classRequired = 'classRequired';
      }

      if (substr($fieldTypes[$key], 0, 6) == 'sprav_') {
        $idSprav = substr($fieldTypes[$key], 6);
        $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) order by d.`fieldValue`";
        $zapSpravs = $mysql->querySelect($sql);
      ?>

        <select class="inputValue <?=$classRequired?>" name="fieldRegister_<?=$key?>" namefield="<?=$value?>">
          <option value="" <?=($fieldValue == '' ? 'selected' : '')?>></option>
          <?php
            if ($zapSpravs) {
              foreach ($zapSpravs as $zapSprav) {
                echo '<option value="' . $zapSprav['zapId'] . '" ' . ($fieldValue == $zapSprav['zapId'] ? 'selected' : '') . '>' . $zapSprav['fieldValue'] . '</option>';
              }
            }
          ?>
        </select>

      <?php } elseif ($fieldTypes[$key] == 'date') {
        echo '<input type="date" class="inputValue ' . $classRequired . '" name="fieldRegister_' . $key . '" value="' . htmlspecialchars($fieldValue) . '" onkeypress="noSubmitEnter(event);" namefield="' . $value . '">';
      } elseif ($fieldTypes[$key] == 'bool') {
        echo '<select class="inputValue ' . $classRequired . '" name="fieldRegister_' . $key . '" namefield="' . $value . '">';
        echo '<option value="" ' . ($fieldValue == '' ? 'selected' : '') . '></option>';
        echo '<option value="Нет" ' . ($fieldValue == 'Нет' ? 'selected' : '') . '>Нет</option>';
        echo '<option value="Да" ' . ($fieldValue == 'Да' ? 'selected' : '') . '>Да</option>';
        echo '</select>';
      } elseif ($fieldTypes[$key] == 'textarea') {
        echo '<textarea class="inputValue ' . $classRequired . '" name="fieldRegister_' . $key . '" namefield="' . $value . '">' . htmlspecialchars($fieldValue) . '</textarea>';
      } else {
        echo '<input type="text" class="inputValue ' . $classRequired . '" name="fieldRegister_' . $key . '" value="' . htmlspecialchars($fieldValue) . '" onkeypress="noSubmitEnter(event);" namefield="' . $value . '">';
      }

      echo '</div>';
      echo '</div>';
    }
  ?>
</div>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<script>
  function controlRequiredDesigner() {
    $('#registerName').css('border-color', '');
    var registerName = $('#registerName').val().trim();
    if (registerName == '') {
      $('#registerName').css('border-color', 'red');
      alert('Не заполнено поле "Наименование".');
      event.preventDefault();
      return false;
    }

    $('#registerComment').css('border-color', '');
    var registerComment = $('#registerComment').val().trim();
    if (registerComment == '') {
      $('#registerComment').css('border-color', 'red');
      alert('Не заполнено поле "Описание".');
      event.preventDefault();
      return false;
    }
  }
</script>


<script>
  function controlRequiredFieldCons() {
    $('#fieldName').css('border-color', '');
    var fieldName = $('#fieldName').val().trim();
    if (fieldName == '') {
      $('#fieldName').css('border-color', 'red');
      alert('Не заполнено поле "Наименование".');
      event.preventDefault();
      return false;
    }

    $('#fieldComment').css('border-color', '');
    var fieldComment = $('#fieldComment').val().trim();
    if (fieldComment == '') {
      $('#fieldComment').css('border-color', 'red');
      alert('Не заполнено поле "Описание".');
      event.preventDefault();
      return false;
    }

    $('#fieldType').css('border-color', '');
    var fieldType = $('#fieldType').val();
    if (fieldType == '') {
      $('#fieldType').css('border-color', 'red');
      alert('Не заполнено поле "Тип поля".');
      event.preventDefault();
      return false;
    }

    $('#fieldPriority').css('border-color', '');
    var fieldPriority = $('#fieldPriority').val().trim();
    if (fieldPriority == '') {
      $('#fieldPriority').css('border-color', 'red');
      alert('Не заполнено поле "Приоритет".');
      event.preventDefault();
      return false;
    }
  }
</script>


<script>
  function controlRequiredZapis() {
    $('.classRequired').each(function () {
      $(this).css('border-color', '');
      var fieldVal = $(this).val().trim();
      if (fieldVal == '') {
        $(this).css('border-color', 'red');
        alert('Не заполнено обязательное поле "' + $(this).attr("namefield") + '".');
        event.preventDefault();
        return false;
      }
    });
  }
</script>


<?php

require_once 'footer.php';

?>
