<?php

session_start();

require_once 'functions.php';
require_once 'classes/MySQL.php';
require_once 'classes/GridView.php';

if ($controlIpAddress) {
  controlCorrectIpAddress();
}

controlLogin();

$mysql = new MySQL();

$messageError = '';

$visiblePanelListDocument = 'hidden';
$visiblePanelEditDocument = 'hidden';
$visiblePanelEditField = 'hidden';

if (isset($_GET['field'])) {
  $visiblePanelEditField = 'show';
} elseif (isset($_GET['document'])) {
  $visiblePanelEditDocument = 'show';
} else {
  $visiblePanelListDocument = 'show';
}

$documentId = isset($_GET['document']) ? $_GET['document'] : 0;
$fieldId = isset($_GET['field']) ? $_GET['field'] : 0;

$documentName = isset($_POST['documentName']) ? $_POST['documentName'] : '';
$documentComment = isset($_POST['documentComment']) ? $_POST['documentComment'] : '';

$fieldName = isset($_POST['fieldName']) ? $_POST['fieldName'] : '';
$fieldComment = isset($_POST['fieldComment']) ? $_POST['fieldComment'] : '';
$fieldType = isset($_POST['fieldType']) ? $_POST['fieldType'] : '';
$fieldRequired = isset($_POST['fieldRequired']) ? $_POST['fieldRequired'] : 0;
$fieldPriority = isset($_POST['fieldPriority']) ? $_POST['fieldPriority'] : 0;
$fieldHeader = isset($_POST['fieldHeader']) ? $_POST['fieldHeader'] : 0;
$fieldExcel = isset($_POST['fieldExcel']) ? $_POST['fieldExcel'] : '';
$fieldWord = isset($_POST['fieldWord']) ? $_POST['fieldWord'] : '';

$fieldExcelS = [];
$fieldWordS = [];

if (strpos($fieldType, 'sprav_') !== false) {
  $regId = substr($fieldType, 6);
  $sql = "select `id`, `fieldName` from `registers_cons` where `registerId` = " . $regId . " order by `priority` desc, `fieldName`";
  $zapRegFields = $mysql->querySelect($sql);
  if ($zapRegFields) {
    foreach ($zapRegFields as $zapRegField) {
      $fieldExcelS[$zapRegField['id']] = '';
      $fieldWordS[$zapRegField['id']] = '';
    }
  }
  //--- цикл по переменным $_POST ---
  foreach ($_POST as $key => $value) {
    if (strpos($key, 'fieldExcel_') !== false) {
      $idField = substr($key, 11);
      $fieldExcelS[$idField] = $value;
    }
    if (strpos($key, 'fieldWord_') !== false) {
      $idField = substr($key, 10);
      $fieldWordS[$idField] = $value;
    }
  }
}


//--- кнопка Сохранить документ ---
if (isset($_POST['buttonSaveDocument'])) {
  $sql = "select `id` from `templates` where `name` = '" . addslashes($documentName) . "' and `id` <> " . $documentId;
  $zapDocs = $mysql->querySelect($sql);
  if ($zapDocs) {
    $messageError = 'Документ с наименованием "' . $documentName . '" уже существует.';
  } else {
    if ($documentId > 0) {
      $sql = "update `templates` set `name` = '" . addslashes($documentName) . "', `comment` = '" . addslashes($documentComment) . "' where `id` = " . $documentId;
      $mysql->queryUpdate($sql);
    } else {
      $sql = "insert into `templates` (`name`, `comment`) values ('" . addslashes($documentName) . "', '" . addslashes($documentComment) . "')";
      $documentId = $mysql->queryInsert($sql);
      if ($documentId > 0) {
        header('Location: ?document=' . $documentId);
        exit();
      }
    }
  }
}


//--- кнопка Сохранить поле ---
if (isset($_POST['buttonSaveField'])) {
  if ($fieldId > 0) {
    $sql = "update `templates_cons` set `fieldName` = '" . addslashes($fieldName) . "', `comment` = '" . addslashes($fieldComment) . "', `fieldType` = '" . $fieldType . "', `isRequired` = " . $fieldRequired . ", `priority` = " . $fieldPriority . ", `header` = " . $fieldHeader . " where `id` = " . $fieldId;
    $mysql->queryUpdate($sql);
  } else {
    $sql = "insert into `templates_cons` (`documentId`, `fieldName`, `comment`, `fieldType`, `isRequired`, `priority`, `header`) values (" . $documentId . ", '" . addslashes($fieldName) . "', '" . addslashes($fieldComment) . "', '" . $fieldType . "', " . $fieldRequired . ", " . $fieldPriority . ", " . $fieldHeader . ")";
    $fieldId = $mysql->queryInsert($sql);
  }

  if ($fieldId > 0) {
    if (strpos($fieldType, 'sprav_') !== false) {
      foreach ($fieldExcelS as $key => $value) {
        $sql = "select `id` from `templates_cell` where `typeField` = 'S' and `typeCell` = 'E' and `fieldId` = " . $key . " and `fieldDocId` = " . $fieldId;
        $zapExts = $mysql->querySelect($sql);
        if ($zapExts) {
          $sql = "update `templates_cell` set `fieldValue` = '" . addslashes($value) . "' where `id` = " . $zapExts[0]['id'];
          $mysql->queryUpdate($sql);
        } else {
          $sql = "insert into `templates_cell` (`typeField`, `typeCell`, `fieldId`, `fieldDocId`, `fieldValue`) values ('S', 'E', " . $key . ", " . $fieldId . ", '" . addslashes($value) . "')";
          $mysql->queryInsert($sql);
        }
      }
      foreach ($fieldWordS as $key => $value) {
        $sql = "select `id` from `templates_cell` where `typeField` = 'S' and `typeCell` = 'W' and `fieldId` = " . $key . " and `fieldDocId` = " . $fieldId;
        $zapExts = $mysql->querySelect($sql);
        if ($zapExts) {
          $sql = "update `templates_cell` set `fieldValue` = '" . addslashes($value) . "' where `id` = " . $zapExts[0]['id'];
          $mysql->queryUpdate($sql);
        } else {
          $sql = "insert into `templates_cell` (`typeField`, `typeCell`, `fieldId`, `fieldDocId`, `fieldValue`) values ('S', 'W', " . $key . ", " . $fieldId . ", '" . addslashes($value) . "')";
          $mysql->queryInsert($sql);
        }
      }
    } else {
      $sql = "select `id` from `templates_cell` where `typeField` = 'D' and `typeCell` = 'E' and `fieldId` = " . $fieldId;
      $zapExts = $mysql->querySelect($sql);
      if ($zapExts) {
        $sql = "update `templates_cell` set `fieldValue` = '" . addslashes($fieldExcel) . "' where `id` = " . $zapExts[0]['id'];
        $mysql->queryUpdate($sql);
      } else {
        $sql = "insert into `templates_cell` (`typeField`, `typeCell`, `fieldId`, `fieldDocId`, `fieldValue`) values ('D', 'E', " . $fieldId . ", " . $fieldId . ", '" . addslashes($fieldExcel) . "')";
        $mysql->queryInsert($sql);
      }
      $sql = "select `id` from `templates_cell` where `typeField` = 'D' and `typeCell` = 'W' and `fieldId` = " . $fieldId;
      $zapExts = $mysql->querySelect($sql);
      if ($zapExts) {
        $sql = "update `templates_cell` set `fieldValue` = '" . addslashes($fieldWord) . "' where `id` = " . $zapExts[0]['id'];
        $mysql->queryUpdate($sql);
      } else {
        $sql = "insert into `templates_cell` (`typeField`, `typeCell`, `fieldId`, `fieldDocId`, `fieldValue`) values ('D', 'W', " . $fieldId . ", " . $fieldId . ", '" . addslashes($fieldWord) . "')";
        $mysql->queryInsert($sql);
      }
    }
    //header('Location: ?document=' . $documentId . '&field=' . $fieldId);
    //exit();
  }
  header('Location: ?document=' . $documentId);
  exit();
}


//--- кнопка Добавить шаблонный файл ---
if (isset($_POST['buttonFileTemplateUpload'])) {
  if (isset($_FILES['fileTemplateUpload']) && $_FILES['fileTemplateUpload']['name'] != '') {
    $dirForUpload = 'templates/' . sprintf('%04d', $documentId);
    if (!file_exists($dirForUpload)) {
      mkdir($dirForUpload, 0777, true);
    }
    $fileForUpload = $dirForUpload . '/' . $_FILES['fileTemplateUpload']['name'];
    move_uploaded_file($_FILES['fileTemplateUpload']['tmp_name'], $fileForUpload);
    // запись в базу данных
    $sql = "select `id` from `templates_files` where `documentId` = " . $documentId . " and `fileName` = '" . addslashes($_FILES['fileTemplateUpload']['name']) . "'";
    $zapFiles = $mysql->querySelect($sql);
    if (!$zapFiles) {
      $sql = "insert into `templates_files` (`documentId`, `fileName`) values (" . $documentId . ", '" . addslashes($_FILES['fileTemplateUpload']['name']) . "')";
      $mysql->queryInsert($sql);
    }
  }
}


//--- цикл по переменным $_POST ---
foreach ($_POST as $key => $value) {

  //--- кнопка Удалить шаблонный файл ---
  if (strpos($key, 'deleteFile_') !== false) {
    $idForDel = substr($key, 11);
    $sql = "select `fileName` from `templates_files` where `id` = " . $idForDel;
    $zapFiles = $mysql->querySelect($sql);
    if ($zapFiles) {
      $fileName = 'templates/' . sprintf('%04d', $documentId) . '/' . $zapFiles[0]['fileName'];
      $sql = "delete from `templates_files` where `id` = " . $idForDel;
      $mysql->queryDelete($sql);
      if (file_exists($fileName)) {
        unlink($fileName);
      }
    }
  }


  //--- кнопка Скачать шаблонный файл ---
  if (strpos($key, 'downloadFile_') !== false) {
    $idForDown = substr($key, 13);
    $sql = "select `fileName` from `templates_files` where `id` = " . $idForDown;
    $zapFiles = $mysql->querySelect($sql);
    if ($zapFiles) {
      $fileName = $zapFiles[0]['fileName'];
      $fileNameDown = 'templates/' . sprintf('%04d', $documentId) . '/' . $fileName;
      if (file_exists($fileNameDown)) {
        header("Location: download.php?path=$fileNameDown");
      }
    }
  }


  //--- кнопка Удалить шаблон документа ---
  if (strpos($key, 'deleteTemplate_') !== false) {
    $idForDel = substr($key, 15);
    // проверка на возможность удаления
    $sql = "select `id` from `templates_cons` where `documentId` = " . $idForDel;
    $zapExsts = $mysql->querySelect($sql);
    if ($zapExsts) {
      $messageError = 'Удаление документа невозможно, так как его значения используются в созданных документах.';
    } else {
      $sql = "delete from `templates` where `id` = " . $idForDel;
      $mysql->queryDelete($sql);
    }
  }


  //--- кнопка Удалить поле документа ---
  if (strpos($key, 'deleteField_') !== false) {
    $idForDel = substr($key, 12);
    // проверка на возможность удаления
    $sql = "select `id` from `documents_data` where `fieldId` = " . $idForDel;
    $zapExsts = $mysql->querySelect($sql);
    if ($zapExsts) {
      $messageError = 'Удаление поля документа невозможно, так как оно используется в созданных документах.';
    } else {
      $sql = "delete from `templates_cons` where `id` = " . $idForDel;
      $mysql->queryDelete($sql);
    }
  }

}


require_once 'header.php';

?>


<?php if ($visiblePanelListDocument == 'show') { ?>
<!-- ------------------------------ панель списка документов ---------------------------------- -->

<div class="pageTitle">Конструктор документов</div>

<div class="panelAction">
  <a class="buttonAction" href="?document=0">Создать новый документ</a>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<!-- панель для табличной части -->
<div id="dataContainer"></div>

<script>
  $.post('docconsPage.php', {'page' : 1}, function(data) { $('#dataContainer').html(data); });
</script>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditDocument == 'show') { ?>
<!-- ------------------------------ панель конструктора документов ---------------------------- -->

<?php
  if ($documentId > 0 && !isset($_POST['documentName'])) {
    $sql = "select * from `templates` where `id` = " . $documentId;
    $zapDocs = $mysql->querySelect($sql);
    if ($zapDocs) {
      $documentName = $zapDocs[0]['name'];
      $documentComment = $zapDocs[0]['comment'];
    }
  }
?>

<div class="pageTitle">Конструктор документов</div>

<div class="parTitle">
  <?php
    if ($documentId > 0) {
      echo 'Конструктор документа "' . $documentName . '"';
    } else {
      echo 'Конструктор нового документа';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?">Назад</a>
  <button type="submit" name="buttonSaveDocument" class="buttonAction" onclick="controlRequiredDocument()">Сохранить</button>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<div class="panelEditor">
  <div class="panelEditorRow">
    <div class="panelEditorLabel">Наименование:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue fontBold" id="documentName" name="documentName" value="<?=htmlspecialchars($documentName)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">Описание:</div>
    <div class="panelEditorValue">
      <input type="text" class="inputValue" id="documentComment" name="documentComment" value="<?=htmlspecialchars($documentComment)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>
</div>

<?php if ($documentId > 0) { ?>

<div class="parTitle">
  Поля документа
</div>

<div class="panelAction">
  <a class="buttonAction" href="?document=<?=$documentId?>&field=<?=$fieldId?>">Создать новое поле</a>
</div>

<!-- табличная часть -->
<div id="dataContainer"></div>

<script>
  $.post('docconsCons.php', {'page' : 1, 'whereid' : <?=$documentId?>}, function(data) { $('#dataContainer').html(data); });
</script>

<div class="parTitle">
  Шаблонные файлы
</div>

<div class="panelAction">
  <button type="button" name="buttonAddFile" class="buttonAction" onclick="addFileTemplate();">Добавить шаблонный файл</button>
  <span id="panelFileTemplateUpload" class="panelFileUpload" style="display:none;">
    <span>Шаблонный файл для добавления: </span><b><span id="labelFileForUpload"></span></b>&nbsp;
    <button type="submit" class="buttonAction buttonActionYes" name="buttonFileTemplateUpload" id="buttonFileTemplateUpload">Загрузить</button>
    <button type="submit" class="buttonAction" name="buttonFileTemplateCancel" id="buttonFileTemplateCancel">Отмена</button>
</span>
</div>
<input type="file" name="fileTemplateUpload" id="fileTemplateUpload" hidden accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.openxmlformats-officedocument.wordprocessingml.document">

<?php
  $sql = "select * from `templates_files` where `documentId` = " . $documentId . " order by `fileName`";
  $zapFiles = $mysql->querySelect($sql);
  if ($zapFiles) {
    echo '<div class="panelFiles">';
    foreach ($zapFiles as $zapFile) {
      echo '<div class="panelFilesRow">';
      // иконка
      $fileNameFull = $zapFile['fileName'];
      $fileNameParts = explode('/', $fileNameFull);
      $fileName = end($fileNameParts);
      $fileExt = '';
      if (mb_strlen($fileName) > 5) {
        $fileExt = mb_strtolower(mb_substr($fileName, -5));
      }
      $imgIcon = '';
      if ($fileExt == '.xlsx') {
        $imgIcon = '<img src="images/excl16.png" class="imageTable">';
      }
      if ($fileExt == '.docx') {
        $imgIcon = '<img src="images/word16.png" class="imageTable">';
      }
      echo '<div class="panelFilesIcon">';
      echo $imgIcon;
      echo '</div>';
      // имя файла
      echo '<div class="panelFilesName">';
      echo '<span class="">' . htmlspecialchars($fileName) . '</span>';
      echo '</div>';
      // кнопка удалить
      echo '<div class="panelFilesIcon">';
      echo '<button id="deleteFile_' . $zapFile['id'] . '" name="deleteFile_' . $zapFile['id'] . '" class="imageTable" style="cursor: pointer;" title="Удалить шаблонный файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Удалить шаблонный файл ' . htmlspecialchars($fileName) . '?\');">';
      echo '<img src="images/dele16.png" class="imageTable">';
      echo '</button>';
      echo '</div>';
      // кнопка скачать
      echo '<div class="panelFilesIcon">';
      echo '<button id="downloadFile_' . $zapFile['id'] . '" name="downloadFile_' . $zapFile['id'] . '" class="imageTable" style="cursor: pointer;" title="Скачать шаблонный файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Скачать шаблонный файл ' . htmlspecialchars($fileName) . '?\');">';
      echo '<img src="images/down16.png" class="imageTable">';
      echo '</button>';
      echo '</div>';

      echo '</div>';
    }
    echo '</div>';
  }
?>



<?php } ?>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditField == 'show') { ?>
<!-- ------------------------------ панель редактирования поля -------------------------------- -->

<?php
  if ($fieldId > 0 && !isset($_POST['fieldName'])) {
    $sql = "select * from `templates_cons` where `id` = " . $fieldId;
    $zapFields = $mysql->querySelect($sql);
    if ($zapFields) {
      $fieldName = $zapFields[0]['fieldName'];
      $fieldComment = $zapFields[0]['comment'];
      $fieldType = $zapFields[0]['fieldType'];
      $fieldRequired = $zapFields[0]['isRequired'];
      $fieldPriority = $zapFields[0]['priority'];
      $fieldHeader = $zapFields[0]['header'];

      if (strpos($fieldType, 'sprav_') !== false) {
        $fieldExcelS = [];
        $fieldWordS = [];
        $regId = substr($fieldType, 6);
        $sql = "select `id`, `fieldName` from `registers_cons` where `registerId` = " . $regId . " order by `priority` desc, `fieldName`";
        $zapRegFields = $mysql->querySelect($sql);
        if ($zapRegFields) {
          foreach ($zapRegFields as $zapRegField) {
            $fieldExcelS[$zapRegField['id']] = '';
            $sql = "select `fieldValue` from `templates_cell` where `typeField` = 'S' and `typeCell` = 'E' and `fieldId` = " . $zapRegField['id'] . " and `fieldDocId` = " . $fieldId;
            $zapVals = $mysql->querySelect($sql);
            if ($zapVals) {
              $fieldExcelS[$zapRegField['id']] = $zapVals[0]['fieldValue'];
            }
            $fieldWordS[$zapRegField['id']] = '';
            $sql = "select `fieldValue` from `templates_cell` where `typeField` = 'S' and `typeCell` = 'W' and `fieldId` = " . $zapRegField['id'] . " and `fieldDocId` = " . $fieldId;
            $zapVals = $mysql->querySelect($sql);
            if ($zapVals) {
              $fieldWordS[$zapRegField['id']] = $zapVals[0]['fieldValue'];
            }
          }
        }
      } else {
        $sql = "select `fieldValue` from `templates_cell` where `typeField` = 'D' and `typeCell` = 'E' and `fieldId` = " . $fieldId;
        $zapVals = $mysql->querySelect($sql);
        if ($zapVals) {
          $fieldExcel = $zapVals[0]['fieldValue'];
        }
        $sql = "select `fieldValue` from `templates_cell` where `typeField` = 'D' and `typeCell` = 'W' and `fieldId` = " . $fieldId;
        $zapVals = $mysql->querySelect($sql);
        if ($zapVals) {
          $fieldWord = $zapVals[0]['fieldValue'];
        }
      }
    }
  }

  $documentName = '';
  $sql = "select `name` from `templates` where `id` = " . $documentId;
  $zapDocs = $mysql->querySelect($sql);
  if ($zapDocs) {
    $documentName = $zapDocs[0]['name'];
  }
?>

<div class="pageTitle">Конструктор документов</div>

<div class="parTitle">
  <?php
    echo 'Конструктор документа "' . $documentName . '" - ';
    if ($fieldId > 0) {
      echo 'поле "' . $fieldName . '"';
    } else {
      echo 'новое поле';
    }
  ?>
</div>

<div class="panelAction">
  <a class="buttonAction" href="?document=<?=$documentId?>">Назад</a>
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

  <?php
    $isReadonlyType = '';
    $sql = "select `id` from `documents_data` where `fieldId` = " . $fieldId;
    $zapFldYes = $mysql->querySelect($sql);
    if ($zapFldYes) {
      $isReadonlyType = 'selectReadonly';
    }
  ?>
  <div class="panelEditorRow">
    <div class="panelEditorLabel">Тип поля:</div>
    <div class="panelEditorValue">
      <select class="inputValue <?=$isReadonlyType?>" id="fieldType" name="fieldType" onchange="this.form.submit();">
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
    <div class="panelEditorLabel">Приоритет:</div>
    <div class="panelEditorValue">
      <input type="number" class="inputValue" id="fieldPriority" name="fieldPriority" value="<?=htmlspecialchars($fieldPriority)?>" onkeypress="noSubmitEnter(event);">
    </div>
  </div>

  <div class="panelEditorRow">
    <div class="panelEditorLabel">В заголовок:</div>
    <div class="panelEditorValue">
      <select class="inputValue" id="fieldHeader" name="fieldHeader">
        <option value="0" <?=($fieldHeader == 0 ? 'selected' : '')?>></option>
        <option value="4" <?=($fieldHeader == 4 ? 'selected' : '')?>>Договор</option>
        <option value="2" <?=($fieldHeader == 2 ? 'selected' : '')?>>Клиент</option>
        <option value="3" <?=($fieldHeader == 3 ? 'selected' : '')?>>Комментарий</option>
      </select>
    </div>
  </div>

  <?php if ($fieldType != '' && strpos($fieldType, 'sprav_') === false) { ?>
    <div class="panelEditorRow">
      <div class="panelEditorLabel">Ячейки Excel:</div>
      <div class="panelEditorValue">
        <input type="text" class="inputValue" id="fieldExcel" name="fieldExcel" value="<?=htmlspecialchars($fieldExcel)?>" onkeypress="noSubmitEnter(event);">
      </div>
    </div>

    <div class="panelEditorRow">
      <div class="panelEditorLabel">Шаблонные поля Word:</div>
      <div class="panelEditorValue">
        <input type="text" class="inputValue" id="fieldWord" name="fieldWord" value="<?=htmlspecialchars($fieldWord)?>" onkeypress="noSubmitEnter(event);">
      </div>
    </div>

  <?php } ?>

</div>

<?php if (strpos($fieldType, 'sprav_') !== false) { ?>

<div class="parTitle">Ячейки Excel</div>

<div class="panelEditor">
<?php
  if ($zapRegFields) {
    foreach ($zapRegFields as $zapField) {
      echo '<div class="panelEditorRow">';
      echo '<div class="panelEditorLabel">' . $zapField['fieldName'] . ':</div>';
      echo '<div class="panelEditorValue">';
      echo '<input type="text" class="inputValue" id="fieldExcel_' . $zapField['id'] . '" name="fieldExcel_' . $zapField['id'] . '" value="' . htmlspecialchars($fieldExcelS[$zapField['id']]) . '" onkeypress="noSubmitEnter(event);">';
      echo '</div>';
      echo '</div>';
    }
  }
?>
</div>

<div class="parTitle">Шаблонные поля Word</div>

<div class="panelEditor">
<?php
  if ($zapRegFields) {
    foreach ($zapRegFields as $zapField) {
      echo '<div class="panelEditorRow">';
      echo '<div class="panelEditorLabel">' . $zapField['fieldName'] . ':</div>';
      echo '<div class="panelEditorValue">';
      echo '<input type="text" class="inputValue" id="fieldWord_' . $zapField['id'] . '" name="fieldWord_' . $zapField['id'] . '" value="' . htmlspecialchars($fieldWordS[$zapField['id']]) . '" onkeypress="noSubmitEnter(event);">';
      echo '</div>';
      echo '</div>';
    }
  }
?>
</div>

<?php } ?>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>



<script>
  function controlRequiredDocument() {
    $('#documentName').css('border-color', '');
    var documentName = $('#documentName').val().trim();
    if (documentName == '') {
      $('#documentName').css('border-color', 'red');
      alert('Не заполнено поле "Наименование".');
      event.preventDefault();
      return false;
    }

    $('#documentComment').css('border-color', '');
    var documentComment = $('#documentComment').val().trim();
    if (documentComment == '') {
      $('#documentComment').css('border-color', 'red');
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
  function addFileTemplate() {
    $('#fileTemplateUpload').click();
  }
</script>


<script>
    $('#fileTemplateUpload').change(function() {
    myfiles = this.files;
    $('#labelFileForUpload').text(myfiles[0].name);
    $('#panelFileTemplateUpload').show();
  });
</script>


<?php

require_once 'footer.php';

?>
