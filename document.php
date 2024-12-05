<?php

session_start();

require_once 'functions.php';
require_once 'functions_docs.php';
require_once 'classes/MySQL.php';

if ($controlIpAddress) {
  controlCorrectIpAddress();
}

controlLogin();

$mysql = new MySQL();

$messageError = '';

$visiblePanelListDocument = 'hidden';
$visiblePanelEditDocument = 'hidden';

if (isset($_GET['document'])) {
  $visiblePanelEditDocument = 'show';
} else {
  $visiblePanelListDocument = 'show';
}

$documentId = isset($_GET['document']) ? $_GET['document'] : 0;

$selectDocTemplate = isset($_POST['selectDocTemplate']) ? $_POST['selectDocTemplate'] : 0;
$documentTemplate = isset($_POST['documentTemplate']) ? $_POST['documentTemplate'] : 0;

$selectExtendedFilterTemplates = isset($_SESSION['selectExtendedFilterTemplates']) ? $_SESSION['selectExtendedFilterTemplates'] : '0';
$selectExtendedFilterTemplates = isset($_POST['selectExtendedFilterTemplates']) ? $_POST['selectExtendedFilterTemplates'] : $selectExtendedFilterTemplates;
$_SESSION['selectExtendedFilterTemplates'] = $selectExtendedFilterTemplates;

$selectExtendedFilterField1 = isset($_SESSION['selectExtendedFilterField1']) ? $_SESSION['selectExtendedFilterField1'] : '';
$selectExtendedFilterField1 = isset($_POST['selectExtendedFilterField1']) ? $_POST['selectExtendedFilterField1'] : $selectExtendedFilterField1;
$_SESSION['selectExtendedFilterField1'] = $selectExtendedFilterField1;

$inputExtendedFilterField1 = isset($_SESSION['inputExtendedFilterField1']) ? trim($_SESSION['inputExtendedFilterField1']) : '';
$inputExtendedFilterField1 = isset($_POST['inputExtendedFilterField1']) ? trim($_POST['inputExtendedFilterField1']) : $inputExtendedFilterField1;
$_SESSION['inputExtendedFilterField1'] = $inputExtendedFilterField1;

$displayExtendedFilter = isset($_SESSION['hfDisplayExtendedFilter']) ? $_SESSION['hfDisplayExtendedFilter'] : 'style="display: none;"';
$displayExtendedFilter = isset($_POST['hfDisplayExtendedFilter']) ? $_POST['hfDisplayExtendedFilter'] : $displayExtendedFilter;
$_SESSION['hfDisplayExtendedFilter'] = $displayExtendedFilter;


//--- кнопка Очистить расширенный фильтр ---
if (isset($_POST['clearExtendedFilter'])) {
  $selectExtendedFilterTemplates = '0';
  $selectExtendedFilterField1 = '';
  $inputExtendedFilterField1 = '';
}


//--- кнопка Сохранить документ или Сгенерировать документы ---
if (isset($_POST['buttonSaveDocument']) || isset($_POST['buttonGenerateDocument'])) {
  if ($documentId == 0) {
    if ($selectDocTemplate > 0) {
      $sql = "insert into `documents` (`templateId`, `userCreate`) values (" . $selectDocTemplate . ", '" . addslashes($_SESSION['userLogin']) . "')";
      $documentId = $mysql->queryInsert($sql);
      if ($documentId > 0) {
        header('Location: ?document=' . $documentId);
        exit();
      }
    }
  } else {
    $documentFieldValues = [];
    $documentHeaders = [];
    $documentFieldTypes = [];
    $documentFieldNames = [];
    if ($documentTemplate > 0) {
      $sql = "select `id`, `fieldName`, `fieldType`, `isRequired`, `header` from `templates_cons` where `documentId` = " . $documentTemplate . " order by `priority` desc, `fieldName`";
      $zapTemplateFields = $mysql->querySelect($sql);
      if ($zapTemplateFields) {
        foreach ($zapTemplateFields as $zapTemplateField) {
          $documentFieldValues[$zapTemplateField['id']] = null;
          $documentHeaders[$zapTemplateField['id']] = $zapTemplateField['header'];
          $documentFieldTypes[$zapTemplateField['id']] = $zapTemplateField['fieldType'];
          $documentFieldNames[$zapTemplateField['id']] = $zapTemplateField['fieldName'];
        }
      }
    }

    //--- цикл по переменным $_POST ---
    foreach ($_POST as $key => $value) {
      if (strpos($key, 'documentFieldValue_') !== false) {
        $idField = substr($key, 19);
        $documentFieldValues[$idField] = $value;
      }
    }

    // проверка типа поля
    foreach ($documentFieldValues as $key => $value) {
      $fieldType = $documentFieldTypes[$key];
      $fieldName = $documentFieldNames[$key];
      $verify = verificationTypeField($fieldType, trim($value), $fieldName);
      if ($verify != '') {
        $messageError .= ($messageError == '' ? '' : '<br>') . $verify;
      }
      if ($messageError != '') {
        $isCorrect = false;
      }
    }

    //--- проверка даты договора на период работы менеджера ---
    $dateDogovor = '';
    $dateUserBeg = '';
    $dateUserEnd = '2222-12-31';
    foreach ($documentFieldValues as $key => $value) {
      if ($documentFieldNames[$key] == 'Дата заключения договора') {
        $dateDogovor = $documentFieldValues[$key];
      }
      if ($documentFieldNames[$key] == 'Цессионарий') {
        $sql = "select d.`fieldValue` from `registers_data` d inner join `registers_cons` c on c.`id` = d.`fieldId` inner join `registers` r on c.`registerId` = r.`id` where r.`name` = 'Цессионарий' and c.`fieldName` = 'Дата приема' and d.`zapId` = " . $documentFieldValues[$key];
        $zapBegs = $mysql->querySelect($sql);
        if ($zapBegs) {
          $dateUserBeg = $zapBegs[0]['fieldValue'];
        }
        $sql = "select d.`fieldValue` from `registers_data` d inner join `registers_cons` c on c.`id` = d.`fieldId` inner join `registers` r on c.`registerId` = r.`id` where r.`name` = 'Цессионарий' and c.`fieldName` = 'Дата увольнения' and d.`zapId` = " . $documentFieldValues[$key];
        $zapEnds = $mysql->querySelect($sql);
        if ($zapEnds) {
          $dateUserEnd = $zapEnds[0]['fieldValue'] == '' ? $dateUserEnd : $zapEnds[0]['fieldValue'];
        }
      }
    }
    if ($dateDogovor < $dateUserBeg) {
      $messageError .= ($messageError == '' ? '' : '<br>') . 'Дата заключения договора меньше даты приема сотрудника на работу.';
    }
    if ($dateDogovor > $dateUserEnd) {
      $messageError .= ($messageError == '' ? '' : '<br>') . 'Дата заключения договора больше даты увольнения сотрудника.';
    }


    //--- запись в базу данных ---
    if ($messageError == '') {
      $headers = [];
      $headers[1] = '';
      $headers[2] = '';
      $headers[3] = '';
      $headers[4] = '';
      foreach ($documentFieldValues as $key => $value) {
        $sql = "select `id` from `documents_data` where `documentId` = " . $documentId . " and `fieldId` = " . $key;
        $zapExists = $mysql->querySelect($sql);
        if ($zapExists) {
          $sql = "update `documents_data` set `fieldValue` = '" . addslashes(trim($value)) . "' where `documentId` = " . $documentId . " and `fieldId` = " . $key;
          $mysql->queryUpdate($sql);
        } else {
          $sql = "insert into `documents_data` (`documentId`, `fieldId`, `fieldValue`) values (" . $documentId . ", " . $key . ", '" . addslashes(trim($value)) . "')";
          $mysql->queryInsert($sql);
        }
        if ($documentHeaders[$key] > 0) {
          if ($documentHeaders[$key] == 4) {
            if (mb_strlen($value) == 10 && mb_substr($value, 4, 1) == '-' && mb_substr($value, 7, 1) == '-') {
              $headerValue = mb_substr($value, 8, 2) . '.' . mb_substr($value, 5, 2) . '.' . mb_substr($value, 0, 4);
            } else {
              $headerValue = $value;
            }
          } else {
            $headerValue = $value;
          }
          if (trim($value) != '' && substr($documentFieldTypes[$key], 0, 6) == 'sprav_') {
            $idSprav = substr($documentFieldTypes[$key], 6);
            $sql = "select d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.`zapId` = " . trim($value) . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1)";
            $zapSpravs = $mysql->querySelect($sql);
            if ($zapSpravs) {
              $headerValue = $zapSpravs[0]['fieldValue'];
            }
          }

          $headers[$documentHeaders[$key]] .= ($headers[$documentHeaders[$key]] == '' ? '' : ', ') . $headerValue;
        }
      }

      $sql = "update `documents` set `clientName` = '" . addslashes(trim($headers[2])) . "', `comment` = '" . addslashes(trim($headers[3])) . "', `dogovorName` = '" . addslashes(trim($headers[4])) . "' where `id` = " . $documentId;
      $mysql->queryUpdate($sql);
    }
  }
}


//--- кнопка Сгенерировать документы ---
if (isset($_POST['buttonGenerateDocument'])) {
  if ($messageError == '') {
    $sql = "select `fileName` from `templates_files` where `documentId` = " . $documentTemplate;
    $zapTempls = $mysql->querySelect($sql);
    if ($zapTempls) {
      foreach ($zapTempls as $zapTempl) {
        $fileName = trim($zapTempl['fileName']);
        $fileNameFull = 'templates/' . sprintf('%04d', $documentTemplate) . '/' . $fileName;
        $fileExt = mb_strtolower(mb_substr($fileName, -5));
        if ($fileName != '' && ($fileExt == '.xlsx' || $fileExt == '.docx')) {
          if (file_exists($fileNameFull)) {
            //--- проверить отметку файла для генерации
            $isSelected = false;
            foreach ($_POST as $key => $value) {
              if (strpos($key, 'templateFile_') !== false) {
                $numFile = substr($key, 13);
                if (isset($_POST['templateFile_' . $numFile]) && $_POST['templateFile_' . $numFile] == $fileName) {
                  if (isset($_POST['checkboxFile_' . $numFile])) {
                    $isSelected = true;
                  }
                }
              }
            }

            if ($isSelected) {
              $documentFieldValues = [];
              //--- цикл по переменным $_POST ---
              foreach ($_POST as $key => $value) {
                if (strpos($key, 'documentFieldValue_') !== false) {
                  $idField = substr($key, 19);
                  $documentFieldValues[$idField] = $value;
                }
              }

              if ($fileExt == '.xlsx') {
                generateXlsx($documentTemplate, $documentFieldValues, $fileNameFull, $documentId);
              }

              if ($fileExt == '.docx') {
                generateDocx($documentTemplate, $documentFieldValues, $fileNameFull, $documentId);
              }
            }
          }
        }
      }
    }
  }
}


//--- цикл по переменным $_POST ---
foreach ($_POST as $key => $value) {

  //--- кнопка Скачать файл (из списка) ---
  if (strpos($key, 'buttonDownloadGenFile_') !== false) {
    $codeFile = substr($key, 22);
    $idDocDown = substr($codeFile, 0, strpos($codeFile, '_'));
    $fileName = $value;
    $fileNameDown = 'documents/' . sprintf('%06d', $idDocDown) . '/' . $fileName;
    if (file_exists($fileNameDown)) {
      header("Location: download.php?path=$fileNameDown");
    }
  }

  //--- кнопка Удалить документ ---
  if (strpos($key, 'deleteDocument_') !== false) {
    $idForDel = substr($key, 15);
    $sql = "delete from `documents` where `id` = " . $idForDel;
    $mysql->queryDelete($sql);
  }

  //--- кнопка Удалить сгенерированный файл ---
  if (strpos($key, 'deleteFile_') !== false) {
    $idForDel = substr($key, 11);
    if (isset($_POST['generatedFile_' . $idForDel])) {
      $fileName = $_POST['generatedFile_' . $idForDel];
      $fileNameDel = 'documents/' . sprintf('%06d', $documentId) . '/' . $fileName;
      if (file_exists($fileNameDel)) {
        unlink($fileNameDel);
      }
    }
  }

  //--- кнопка Скачать файл ---
  if (strpos($key, 'downloadFile_') !== false) {
    $idForDown = substr($key, 13);
    if (isset($_POST['generatedFile_' . $idForDown])) {
      $fileName = $_POST['generatedFile_' . $idForDown];
      $fileNameDown = 'documents/' . sprintf('%06d', $documentId) . '/' . $fileName;
      if (file_exists($fileNameDown)) {
        header("Location: download.php?path=$fileNameDown");
      }
    }
  }

  //--- кнопка Перезагрузить файл ---
  if (strpos($key, 'buttonFileDocumentUpload_') !== false) {
    $idForDown = substr($key, 25);
    if (isset($_POST['generatedFile_' . $idForDown]) && isset($_FILES['fileDocumentUpload_' . $idForDown]) && $_FILES['fileDocumentUpload_' . $idForDown]['name'] != '') {
      $fileName = $_POST['generatedFile_' . $idForDown];
      $fileNameOld = 'documents/' . sprintf('%06d', $documentId) . '/' . $fileName;
      move_uploaded_file($_FILES['fileDocumentUpload_' . $idForDown]['tmp_name'], $fileNameOld);
    }
  }

}


require_once 'header.php';

?>


<?php if ($visiblePanelListDocument == 'show') { ?>
<!-- ------------------------------ панель списка документов ---------------------------------- -->

<div class="pageTitle">Реестр документов</div>

<div class="panelAction">
    <a class="buttonAction" href="?document=0">Создать новый документ</a>
</div>

<!-- панель расширенного фильтра -->
<?php
  //--- значения расщиренного фильтра
  $extendedFilterBy = '';
  $extendedFilterTitle = '';
  if ($selectExtendedFilterTemplates != '0' && (($selectExtendedFilterField1 != '' && $inputExtendedFilterField1 != '') || ($selectExtendedFilterField1 != '' && $inputExtendedFilterField1 != '') || ($selectExtendedFilterField1 != '' && $inputExtendedFilterField1 != ''))) {
    $extendedFilters = '';
    if ($selectExtendedFilterField1 != '') {
      $extendedFilters .= ($extendedFilters == '' ? '' : ',') . '{ "field":"' . $selectExtendedFilterField1 . '","filter":"' . htmlspecialchars($inputExtendedFilterField1) . '" }';
      $sql = "select `fieldName` from `templates_cons` where `id` = " . $selectExtendedFilterField1;
      $zapFldNames = $mysql->querySelect($sql);
      if ($zapFldNames) {
        $extendedFilterTitle .= ($extendedFilterTitle == '' ? '' : ', ') . $zapFldNames[0]['fieldName'] . ' содержит \'' . $inputExtendedFilterField1 . '\'';
      }
    }
    if ($extendedFilters != '') {
      $extendedFilterBy = '{ "filters":[ ' . $extendedFilters . ' ] }';
    }
  }
  // список шаблонов документов
  $sql = "select `id`, `name` from `templates` order by `name`";
  $zapExtTempls = $mysql->querySelect($sql);
  // список полей шаблона документа
  $sql = "select `id`, `fieldName` from `templates_cons` where `documentId` = " . $selectExtendedFilterTemplates . " order by `fieldName`";
  $zapExtFields = $mysql->querySelect($sql);
?>
<input type="hidden" id="hfExtendedFilterBy" value="<?=htmlspecialchars($extendedFilterBy)?>">

<div class="panelFilter width100">
    <div>
        <button type="button" id="buttonShowExtendedFilter" class="buttonFilter"
            onclick="showExtendedFilterCriteria(this)">
            <?=($displayExtendedFilter == '' ? 'Скрыть фильтр' : 'Показать фильтр')?>
        </button>
        <?php if ($extendedFilterTitle != '') { ?>
        <button type="submit" class="buttonFilter" name="clearExtendedFilter">Очистить фильтр</button>
        <?php } ?>
        <span class="labelPageInfo">Расширенный фильтр:
            <?=($extendedFilterTitle == '' ? 'не определен' : $extendedFilterTitle)?></span>
    </div>
</div>

<input type="hidden" id="hfDisplayExtendedFilter" name="hfDisplayExtendedFilter" value="<?=$displayExtendedFilter?>">

<div id="panelExtendedFilter" class="filterCriteria" <?=$displayExtendedFilter?>>
    <div class="filterCriteriaRow">
        <div class="filterCriteriaCell" style="min-width: 50px !important;">Шаблон документов:</div>
        <div class="filterCriteriaCell">
            <select name="selectExtendedFilterTemplates" class="filterCriteria" onchange="this.form.submit();"
                style="width: 350px !important;">
                <option value="0" <?=($selectExtendedFilterTemplates == '0' ? 'selected' : '')?>></option>
                <?php
          if ($zapExtTempls) {
            foreach ($zapExtTempls as $zapExtTempl) {
              echo '<option value="' . $zapExtTempl['id'] . '" ' . ($selectExtendedFilterTemplates == $zapExtTempl['id'] ? 'selected' : '') . '>' . $zapExtTempl['name'] . '</option>';
            }
          }
        ?>
            </select>
        </div>
        <div class="filterCriteriaCell"></div>
        <div class="filterCriteriaCell"></div>
    </div>

    <div class="filterCriteriaRow">
        <div class="filterCriteriaCell" style="min-width: 50px !important;">Поле:</div>
        <div class="filterCriteriaCell">
            <select name="selectExtendedFilterField1" class="filterCriteria" style="width: 350px !important;">
                <option value="" <?=($selectExtendedFilterField1 == '' ? 'selected' : '')?>></option>
                <?php
          if ($zapExtFields) {
            foreach ($zapExtFields as $zapExtField) {
              echo '<option value="' . $zapExtField['id'] . '" ' . ($selectExtendedFilterField1 == $zapExtField['id'] ? 'selected' : '') . '>' . $zapExtField['fieldName'] . '</option>';
            }
          }
        ?>
            </select>
        </div>
        <div class="filterCriteriaCell" style="min-width: 50px !important;"> &nbsp;&nbsp;&nbsp;фильтр:</div>
        <div class="filterCriteriaCell">
            <input name="inputExtendedFilterField1" class="filterCriteria " value="<?=$inputExtendedFilterField1?>"
                onkeypress="noSubmitEnter(event);">
        </div>
    </div>

    <div class="filterCriteriaRow">
        <div class="filterCriteriaCell"></div>
        <div class="filterCriteriaCell"></div>
        <div class="filterCriteriaCell"></div>
        <div class="filterCriteriaCell" style="min-width: 50px !important;"><button type="submit"
                class="buttonFilter buttonFilterApply" style="width: 100%;">Применить фильтр</button></div>
    </div>
</div>


<!-- панель для табличной части -->
<div id="dataContainer"></div>

<script>
$.post('documentPage.php', {
    'page': 1,
    "extfilterby": $('#hfExtendedFilterBy').val()
}, function(data) {
    $('#dataContainer').html(data);
});
</script>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<?php if ($visiblePanelEditDocument == 'show') { ?>
<!-- ------------------------------ панель редактирования документа --------------------------- -->

<?php
  if ($documentId > 0 && !isset($_POST['documentTemplateName'])) {
    $sql = "select `templateId` from `documents` where `id` = " . $documentId;
    $zapDocs = $mysql->querySelect($sql);
    if ($zapDocs) {
      $documentTemplate = $zapDocs[0]['templateId'];
    }
  }

  $documentFieldValues = [];
  if ($documentTemplate > 0) {
    $sql = "select `id`, `fieldName`, `fieldType`, `isRequired`, `comment` from `templates_cons` where `documentId` = " . $documentTemplate . " order by `priority` desc, `fieldName`";
    $zapTemplateFields = $mysql->querySelect($sql);
    if ($zapTemplateFields) {
      foreach ($zapTemplateFields as $zapTemplateField) {
        $documentFieldValues[$zapTemplateField['id']] = null;
      }
    }
  }

  //--- цикл по переменным $_POST ---
  foreach ($_POST as $key => $value) {
    if (strpos($key, 'documentFieldValue_') !== false) {
      $idField = substr($key, 19);
      $documentFieldValues[$idField] = $value;
    }
  }

  if ($documentId > 0 && !isset($_POST['documentTemplateName'])) {
    $sql = "select `fieldId`, `fieldValue` from `documents_data` where `documentId` = " . $documentId;
    $zapFieldValues = $mysql->querySelect($sql);
    if ($zapFieldValues) {
      foreach ($zapFieldValues as $zapFieldValue) {
        $documentFieldValues[$zapFieldValue['fieldId']] = $zapFieldValue['fieldValue'];
      }
    }
  }

?>

<div class="pageTitle">Реестр документов</div>

<div class="parTitle">
    <?php
    if ($documentId > 0) {
      echo 'Документ № ' . $documentId;
    } else {
      echo 'Новый документ';
    }
  ?>
</div>

<div class="panelAction">
    <a class="buttonAction" href="?">Назад</a>
    <button type="submit" name="buttonSaveDocument" class="buttonAction"
        onclick="controlRequiredDocument()">Сохранить</button>
    <?php if ($documentId > 0) { ?>
    <button type="submit" name="buttonGenerateDocument" class="buttonAction"
        onclick="controlRequiredDocumentBeforeGenerate()">Сгенерировать документы</button>
    <?php } ?>
</div>

<div class="panelMsgError" <?=($messageError != '' ? 'style="display: block;"' : '')?>><?=$messageError?></div>

<?php
  if ($documentId == 0) {
?>
<div class="parTitle">Выберите шаблон документа</div>
<div class="panelEditor">
    <div class="panelEditorRow">
        <div class="panelEditorLabel">Шаблон документа:</div>
        <diiv class="panelEditorValue">
            <select class="inputValue" id="selectDocTemplate" name="selectDocTemplate">
                <option value="0" <?=($selectDocTemplate == '' ? 'selected' : '')?>></option>
                <?php
          $sql = "select `id`, `name` from `templates` order by `id`";
          $zapTemps = $mysql->querySelect($sql);
          if ($zapTemps) {
            foreach ($zapTemps as $zapTemp) {
              echo '<option value="' . $zapTemp['id'] . '" ' . ($selectDocTemplate == $zapTemp['id'] ? 'selected' : '') . '>' . $zapTemp['name'] . '</option>';
            }
          }
        ?>
            </select>
            <input type="text" name="name">
        </diiv>
    </div>
</div>
<?php
  } else {
?>

<?php
  // выбрать список шаблонных файлов
  $sql = "select `fileName` from `templates_files` where `documentId` = " . $documentTemplate . " order by `fileName`";
  $zapFiles = $mysql->querySelect($sql);
  if ($zapFiles) {
    // выбрать список сгенерированных файлов
    $dirDocs = 'documents/' . sprintf('%06d', $documentId) . '/';
    $listFiles = [];
    if (file_exists($dirDocs)) {
      $listFiles = scandir($dirDocs);
    }
    $kolvoListFiles = count($listFiles);
    // отобразить файлы
    echo '<div class="panelFiles">';
    echo '<div class="panelFilesRow">';
    echo '<div class="panelFilesIcon">'; echo '</div>';
    echo '<div class="panelFilesName">'; echo 'Шаблонный файл'; echo '</div>';
    echo '<div class="panelFilesIcon">'; echo '</div>';
    echo '<div class="panelFilesName">'; echo 'Сгенерированный файл'; echo '</div>';
    echo '<div class="panelFilesName">'; echo 'Дата генерации'; echo '</div>';
    echo '<div class="panelFilesIcon">'; echo '</div>';
    echo '<div class="panelFilesIcon">'; echo '</div>';
    echo '<div class="panelFilesIcon">'; echo '</div>';
    echo '</div>';
    $numFile = 1;
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
      // шаблонный файд
      echo '<div class="panelFilesName">';
      echo '<span class="">' . htmlspecialchars($fileName) . '</span>';
      echo '<input type="hidden" name="templateFile_' . $numFile . '" value="' . htmlspecialchars($fileName) . '">';
      echo '</div>';
      // чекбокс
      echo '<div class="panelFilesIcon">';
      echo '<input type="checkbox" id="checkboxFile_' . $numFile . '" name="checkboxFile_' . $numFile . '" ' . (isset($_POST['checkboxFile_' . $numFile]) ? 'checked' : '') . '>';
      echo '</div>';
      // сгенерированный файл
      for ($iiFile = 1; $iiFile <= $kolvoListFiles; $iiFile++) {
        if (isset($listFiles[$iiFile]) && is_file($dirDocs . $listFiles[$iiFile])) {
          if ($fileName == $listFiles[$iiFile]) {
            echo '<div class="panelFilesName">';
            echo '<span class="">' . htmlspecialchars($fileName) . '</span>';
            echo '<input type="hidden" name="generatedFile_' . $numFile . '" value="' . htmlspecialchars($fileName) . '">';
            echo '</div>';
            // дата генерации
            echo '<div class="panelFilesName">';
            echo '<span class="">' . date("d.m.Y H:i:s", filectime($dirDocs . $listFiles[$iiFile])) . '</span>';
            echo '</div>';
            // кнопка удалить
            echo '<div class="panelFilesIcon">';
            echo '<button type="submit" id="deleteFile_' . $numFile . '" name="deleteFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Удалить сгенерированный файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Удалить сгенерированный файл ' . htmlspecialchars($fileName) . '?\');">';
            echo '<img src="images/dele16.png" class="imageTable">';
            echo '</button>';
            echo '</div>';
            // кнопка скачать
            echo '<div class="panelFilesIcon">';
            echo '<button type="submit" id="downloadFile_' . $numFile . '" name="downloadFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Скачать файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Скачать файл ' . htmlspecialchars($fileName) . '?\');">';
            echo '<img src="images/down16.png" class="imageTable">';
            echo '</button>';
            echo '</div>';
            // кнопка перезагрузить файл
            echo '<div class="panelFilesIcon">';
            echo '<button type="button" id="uploadFile_' . $numFile . '" name="uploadFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Перезагрузить файл ' . htmlspecialchars($fileName) . '" onclick="uploadFileDocument(' . $numFile . ', \'' . htmlspecialchars($fileName) . '\')">';
            echo '<img src="images/refr16.png" class="imageTable">';
            echo '</button>';
            echo '</div>';
            $accept = '';
            if ($fileExt == '.xlsx') {
              $accept = 'accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"';
            }
            if ($fileExt == '.docx') {
              $accept = 'accept="application/vnd.openxmlformats-officedocument.wordprocessingml.document"';
            }
            echo '<div class="panelFilesIcon">';
            echo '<input type="file" name="fileDocumentUpload_' . $numFile . '" id="fileDocumentUpload_' . $numFile . '" class="fileDocumentUpload" hidden ' . $accept . '>';
            echo '<span id="panelFileDocumentUpload_' . $numFile . '" class="panelFileUpload" style="display:none;">';
            echo '<span>Файл для перезагрузки: </span><b><span id="labelFileForUpload_' . $numFile . '"></span></b>&nbsp;';
            echo '<button type="submit" class="buttonAction buttonActionYes" name="buttonFileDocumentUpload_' . $numFile . '" id="buttonFileDocumentUpload">Загрузить</button>&nbsp;';
            echo '<button type="submit" class="buttonAction" name="buttonFileDocumentCancel" id="buttonFileDocumentCancel_' . $numFile . '">Отмена</button>';
            echo '</span>';
            echo '</div>';

            unset($listFiles[$iiFile]);
          }
        }
      }
      echo '</div>';
      $numFile++;
    }

    // оставшиеся сгенерированные файлы
    for ($iiFile = 1; $iiFile <= $kolvoListFiles; $iiFile++) {
      if (isset($listFiles[$iiFile]) && is_file($dirDocs . $listFiles[$iiFile])) {
        $fileName = $listFiles[$iiFile];
        echo '<div class="panelFilesRow">';
        // иконка
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
        // шаблонный файд
        echo '<div class="panelFilesName">'; echo '</div>';
        // чекбокс
        echo '<div class="panelFilesIcon">'; echo '</div>';
        // сгенерированный файл
        echo '<div class="panelFilesName">';
        echo '<span class="">' . htmlspecialchars($fileName) . '</span>';
        echo '<input type="hidden" name="generatedFile_' . $numFile . '" value="' . htmlspecialchars($fileName) . '">';
        echo '</div>';
        // дата генерации
        echo '<div class="panelFilesName">';
        echo '<span class="">' . date("d.m.Y H:i:s", filectime($dirDocs . $fileName)) . '</span>';
        echo '</div>';
        // кнопка удалить
        echo '<div class="panelFilesIcon">';
        echo '<button type="submit" id="deleteFile_' . $numFile . '" name="deleteFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Удалить сгенерированный файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Удалить сгенерированный файл ' . htmlspecialchars($fileName) . '?\');">';
        echo '<img src="images/dele16.png" class="imageTable">';
        echo '</button>';
        echo '</div>';
        // кнопка скачать
        echo '<div class="panelFilesIcon">';
        echo '<button type="submit" id="downloadFile_' . $numFile . '" name="downloadFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Скачать файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Скачать файл ' . htmlspecialchars($fileName) . '?\');">';
        echo '<img src="images/down16.png" class="imageTable">';
        echo '</button>';
        echo '</div>';
        // кнопка перезагрузить файл
        echo '<div class="panelFilesIcon">';
        echo '<button type="button" id="uploadFile_' . $numFile . '" name="uploadFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Перезагрузить файл ' . htmlspecialchars($fileName) . '" onclick="uploadFileDocument(' . $numFile . ', \'' . htmlspecialchars($fileName) . '\')">';
        echo '<img src="images/refr16.png" class="imageTable">';
        echo '</button>';
        echo '</div>';
        $accept = '';
        if ($fileExt == '.xlsx') {
          $accept = 'accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"';
        }
        if ($fileExt == '.docx') {
          $accept = 'accept="application/vnd.openxmlformats-officedocument.wordprocessingml.document"';
        }
        echo '<div class="panelFilesIcon">';
        echo '<input type="file" name="fileDocumentUpload_' . $numFile . '" id="fileDocumentUpload_' . $numFile . '" class="fileDocumentUpload" hidden ' . $accept . '>';
        echo '<span id="panelFileDocumentUpload_' . $numFile . '" class="panelFileUpload" style="display:none;">';
        echo '<span>Файл для перезагрузки: </span><b><span id="labelFileForUpload_' . $numFile . '"></span></b>&nbsp;';
        echo '<button type="submit" class="buttonAction buttonActionYes" name="buttonFileDocumentUpload_' . $numFile . '" id="buttonFileDocumentUpload">Загрузить</button>&nbsp;';
        echo '<button type="submit" class="buttonAction" name="buttonFileDocumentCancel" id="buttonFileDocumentCancel_' . $numFile . '">Отмена</button>';
        echo '</span>';
        echo '</div>';

        echo '</div>';
        $numFile++;
      }
    }

    echo '</div>';
  }


/*
  $dirDocs = 'documents/' . sprintf('%06d', $documentId) . '/';
  $listFiles = [];
  if (file_exists($dirDocs)) {
    $listFiles = scandir($dirDocs);
  }
  $isFilesExists = false;
  foreach ($listFiles as $listFile) {
    if (is_file($dirDocs . $listFile)) {
      $isFilesExists = true;
    }
  }
  if ($isFilesExists) {
    echo '<div class="parTitle">Сгенерированные файлы</div>';
    echo '<div class="panelFiles">';
    $numFile = 1;
    foreach ($listFiles as $listFile) {
      if (is_file($dirDocs . $listFile)) {
        echo '<div class="panelFilesRow">';
        // иконка
        $fileName = $listFile;
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
        echo '<input type="hidden" name="generatedFile_' . $numFile . '" value="' . htmlspecialchars($fileName) . '">';
        echo '</div>';

        // дата и время файла
        echo '<div class="panelFilesName">';
        echo '<span class="">' . date("d.m.Y H:i:s", filectime($dirDocs . $listFile)) . '</span>';
        echo '</div>';

        // кнопка удалить
        echo '<div class="panelFilesIcon">';
        echo '<button type="submit" id="deleteFile_' . $numFile . '" name="deleteFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Удалить сгенерированный файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Удалить сгенерированный файл ' . htmlspecialchars($fileName) . '?\');">';
        echo '<img src="images/dele16.png" class="imageTable">';
        echo '</button>';
        echo '</div>';

        // кнопка скачать
        echo '<div class="panelFilesIcon">';
        echo '<button type="submit" id="downloadFile_' . $numFile . '" name="downloadFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Скачать файл ' . htmlspecialchars($fileName) . '" onclick="return confirm(\'Скачать файл ' . htmlspecialchars($fileName) . '?\');">';
        echo '<img src="images/down16.png" class="imageTable">';
        echo '</button>';
        echo '</div>';
        // кнопка перезагрузить файл
        echo '<div class="panelFilesIcon">';
        echo '<button type="button" id="uploadFile_' . $numFile . '" name="uploadFile_' . $numFile . '" class="imageTable" style="cursor: pointer;" title="Перезагрузить файл ' . htmlspecialchars($fileName) . '" onclick="uploadFileDocument(' . $numFile . ', \'' . htmlspecialchars($fileName) . '\')">';
        echo '<img src="images/refr16.png" class="imageTable">';
        echo '</button>';
        echo '</div>';
        $accept = '';
        if ($fileExt == '.xlsx') {
          $accept = 'accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"';
        }
        if ($fileExt == '.docx') {
          $accept = 'accept="application/vnd.openxmlformats-officedocument.wordprocessingml.document"';
        }
        echo '<div class="panelFilesIcon">';
        echo '<input type="file" name="fileDocumentUpload_' . $numFile . '" id="fileDocumentUpload_' . $numFile . '" class="fileDocumentUpload" hidden ' . $accept . '>';
        echo '<span id="panelFileDocumentUpload_' . $numFile . '" class="panelFileUpload" style="display:none;">';
        echo '<span>Файл для перезагрузки: </span><b><span id="labelFileForUpload_' . $numFile . '"></span></b>&nbsp;';
        echo '<button type="submit" class="buttonAction buttonActionYes" name="buttonFileDocumentUpload_' . $numFile . '" id="buttonFileDocumentUpload">Загрузить</button>&nbsp;';
        echo '<button type="submit" class="buttonAction" name="buttonFileDocumentCancel" id="buttonFileDocumentCancel_' . $numFile . '">Отмена</button>';
        echo '</span>';
        echo '</div>';

        echo '</div>';
        $numFile++;
      }
    }
    echo '</div>';
  }
  */
?>

<div class="panelEditor">
    <?php
    $documentTemplateName = '';
    $sql = "select `name` from `templates` where `id` = " . $documentTemplate;
    $zapTemps = $mysql->querySelect($sql);
    if ($zapTemps) {
      $documentTemplateName = $zapTemps[0]['name'];
    }
  ?>

    <div class="panelEditorRow">
        <div class="panelEditorLabel">Шаблон документа:</div>
        <div class="panelEditorIcon"></div>
        <div class="panelEditorValue">
            <input type="hidden" id="documentTemplate" name="documentTemplate"
                value="<?=htmlspecialchars($documentTemplate)?>">
            <input type="text" class="inputValue inputReadonly" id="documentTemplateName" name="documentTemplateName"
                readonly value="<?=htmlspecialchars($documentTemplateName)?>" onkeypress="noSubmitEnter(event);">
        </div>
    </div>

    <?php
    if ($zapTemplateFields) {
      foreach ($zapTemplateFields as $zapTemplateField) {
  ?>

    <div class="panelEditorRow">
        <div class="panelEditorLabel"><?=($zapTemplateField['fieldName'])?>:</div>
        <div class="panelEditorIcon"><img src="images/help16.png" title="<?=($zapTemplateField['comment'])?>"></div>
        <div class="panelEditorValue">
            <?php
        $classRequired = '';
        if ($zapTemplateField['isRequired'] == 1) {
          $classRequired = 'classRequired';
        }

        if (substr($zapTemplateField['fieldType'], 0, 6) == 'sprav_') {
          //--- тип поля СПРАВОЧНИК -----------------------------------------------------------------------------------------------------------------
          $idSprav = substr($zapTemplateField['fieldType'], 6);
          $isSelectSubmit = '';
          //--- наименование справочника
          $spravName = '';
          $sql = "select `name` from `registers` where `id` = " . $idSprav;
          $zapSprs = $mysql->querySelect($sql);
          if ($zapSprs) {
            $spravName = $zapSprs[0]['name'];
          }
          if ($spravName == 'Цессионарий') {
            //--- если справочник Цессионарий -------------------------------------------------------------------------------------------------------
            $isSelectSubmit = 'onchange="this.form.submit();"';
            // цессионарий по-умолчанию для пользователя
            if ($documentFieldValues[$zapTemplateField['id']] == '') {
              $sql = "select `cessionId` from `users` where `login` = '" . $userInfo->userLogin . "'";
              $zapUserCess = $mysql->querySelect($sql);
              if ($zapUserCess && $zapUserCess[0]['cessionId'] != 0) {
                $documentFieldValues[$zapTemplateField['id']] = $zapUserCess[0]['cessionId'];
              }
            }
            // отбор записей
            if ($userInfo->userRoleCode == 'admin' || $userInfo->userIsBoss) {
              $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) order by d.`fieldValue`";
            } else {
              $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`zapId` = " . $userInfo->userCessionId . " order by d.`fieldValue`";
            }
          } elseif ($spravName == 'Города заключения договора') {
            //--- если справочник Города ------------------------------------------------------------------------------------------------------------
            $isSelectSubmit = 'onchange="this.form.submit();"';
            // город по умолчанию для пользователя
            if ($documentFieldValues[$zapTemplateField['id']] == '') {
              $sql = "select `cessionId` from `users` where `login` = '" . $userInfo->userLogin . "'";
              $zapUserCess = $mysql->querySelect($sql);
              if ($zapUserCess && $zapUserCess[0]['cessionId'] != 0) {
                $sprCessionId = 0;
                $sql = "select `id` from `registers` where `name` = 'Цессионарий'";
                $zapCess = $mysql->querySelect($sql);
                if ($zapCess) {
                  $sprCessionId = $zapCess[0]['id'];
                }
                $sprFieldId = 0;
                $sql = "select `id` from `registers_cons` where `registerId` = " . $sprCessionId . " and `fieldName` = 'Город заключения договора'";
                $zapFlds = $mysql->querySelect($sql);
                if ($zapFlds) {
                  $sprFieldId = $zapFlds[0]['id'];
                }
                $cityDogId = 0;
                $sql = "select `fieldValue` from `registers_data` where `registerId` = " . $sprCessionId . " and `zapId` = " . $zapUserCess[0]['cessionId'] . " and `fieldId` = " . $sprFieldId;
                $zapCitys = $mysql->querySelect($sql);
                if ($zapCitys) {
                  $cityDogId = $zapCitys[0]['fieldValue'];
                }
                if (trim($cityDogId) != '') {
                  $documentFieldValues[$zapTemplateField['id']] = $cityDogId;
                }
              }
            }
            // отбор записей
            if ($userInfo->userRoleCode == 'admin' || $userInfo->userIsBoss) {
              $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) order by d.`fieldValue`";
            } else {
              $sprCessionId = 0;
              $sql = "select `id` from `registers` where `name` = 'Цессионарий'";
              $zapCess = $mysql->querySelect($sql);
              if ($zapCess) {
                $sprCessionId = $zapCess[0]['id'];
              }
              $sprFieldId = 0;
              $sql = "select `id` from `registers_cons` where `registerId` = " . $sprCessionId . " and `fieldName` = 'Город заключения договора'";
              $zapFlds = $mysql->querySelect($sql);
              if ($zapFlds) {
                $sprFieldId = $zapFlds[0]['id'];
              }
              $cityDogId = 0;
              $sql = "select `fieldValue` from `registers_data` where `registerId` = " . $sprCessionId . " and `zapId` = " . $userInfo->userCessionId . " and `fieldId` = " . $sprFieldId;
              $zapCitys = $mysql->querySelect($sql);
              if ($zapCitys) {
                $cityDogId = $zapCitys[0]['fieldValue'];
              }
              $cityDogId = trim($cityDogId) == '' ? 0 : $cityDogId;
              $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`zapId` = " . $cityDogId . " order by d.`fieldValue`";
            }

          } elseif ($spravName == 'Платежные реквизиты') {
            //--- если справочник Платежные реквизиты -----------------------------------------------------------------------------------------------
            $platTemplId = 0;
            $sql = "select `templateId` from `documents` where `id` = " . $documentId;
            $zapTemps = $mysql->querySelect($sql);
            if ($zapTemps) {
              $platTemplId = $zapTemps[0]['templateId'];
            }
            $platSprCessionId = 0;
            $sql = "select `id` from `registers` where `name` = 'Цессионарий'";
            $zapCess = $mysql->querySelect($sql);
            if ($zapCess) {
              $platSprCessionId = $zapCess[0]['id'];
            }
            $platFieldDocId = 0;
            $sql = "select `id` from `templates_cons` where `documentId` = " . $platTemplId . " and `fieldType` = 'sprav_" . $platSprCessionId . "'";
            $zapCons = $mysql->querySelect($sql);
            if ($zapCons) {
              $platFieldDocId = $zapCons[0]['id'];
            }
            if (isset($documentFieldValues[$platFieldDocId]) && $documentFieldValues[$platFieldDocId] != null) {
              $sql = "select d.`fieldValue` from `registers_data` d inner join `registers_cons` c on c.`id` = d.`fieldId` where d.`registerId` = " . $platSprCessionId . " and d.`zapId` = " . $documentFieldValues[$platFieldDocId] . " and c.`fieldName` = 'Юридическое лицо'";
              $zapFizs = $mysql->querySelect($sql);
              if ($zapFizs) {
                if (mb_strtolower($zapFizs[0]['fieldValue']) == 'нет') {
                  //--- физическое лицо
                  $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`fieldValue` = 'Физическое лицо' order by d.`fieldValue`";
                } else {
                  //--- юридическое лицо
                  $platSprCityId = 0;
                  $sql = "select `id` from `registers` where `name` = 'Города заключения договора'";
                  $zapCitys = $mysql->querySelect($sql);
                  if ($zapCitys) {
                    $platSprCityId = $zapCitys[0]['id'];
                  }
                  $platFieldDocId = 0;
                  $sql = "select `id` from `templates_cons` where `documentId` = " . $platTemplId . " and `fieldType` = 'sprav_" . $platSprCityId . "'";
                  $zapCons = $mysql->querySelect($sql);
                  if ($zapCons) {
                    $platFieldDocId = $zapCons[0]['id'];
                  }
                  if (isset($documentFieldValues[$platFieldDocId]) && $documentFieldValues[$platFieldDocId] != null) {
                    $sql = "select d.`fieldValue` from `registers_data` d inner join `registers_cons` c on c.`id` = d.`fieldId` where d.`registerId` = " . $platSprCityId . " and d.`zapId` = " . $documentFieldValues[$platFieldDocId] . " and c.`fieldName` = 'Головной офис'";
                    $zapUrlics = $mysql->querySelect($sql);
                    if ($zapUrlics) {
                      if (mb_strtolower($zapUrlics[0]['fieldValue']) == 'да') {
                        //--- головной офис
                        $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`fieldValue` = 'Юридическое лицо головной офис' order by d.`fieldValue`";
                      } else {
                        //--- подразделение
                        $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`fieldValue` = 'Юридическое лицо подразделения других городов' order by d.`fieldValue`";
                      }

                    } else {
                      $sql = "";
                    }
                  } else {
                    $sql = "";
                  }
                }
              } else {
                $sql = "";
              }
            } else {
              $sql = "";
            }
          } else {
            $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) order by d.`fieldValue`";
          }
          $zapSpravs = $sql == "" ? null : $mysql->querySelect($sql);
      ?>
            <select class="inputValue <?=$classRequired?>" id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>" namefield="<?=($zapTemplateField['fieldName'])?>"
                <?=$isSelectSubmit?>>
                <?php if ($spravName != 'Платежные реквизиты') { ?>
                <option value="" <?=($documentFieldValues[$zapTemplateField['id']] == '' ? 'selected' : '')?>></option>
                <?php } ?>
                <?php
          if ($zapSpravs) {
            foreach ($zapSpravs as $zapSprav) {
              echo '<option value="' . $zapSprav['zapId'] . '" ' . ($documentFieldValues[$zapTemplateField['id']] == $zapSprav['zapId'] ? 'selected' : '') . '>' . $zapSprav['fieldValue'] . '</option>';
            }
          }
        ?>
            </select>
            <?php } elseif (substr($zapTemplateField['fieldType'], 0, 5) == 'list_') {
        //--- тип поля СПРАВОЧНИК -----------------------------------------------------------------------------------------------------------------
        $idSprav = substr($zapTemplateField['fieldType'], 5);
        $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) order by d.`fieldValue`";
        $zapSpravs = $mysql->querySelect($sql);
      ?>
            <datalist id="automodels">
                <?php
        if ($zapSpravs) {
          foreach ($zapSpravs as $zapSprav) {
            echo '<option>' . $zapSprav['fieldValue'] . '</option>';
          }
        }
      ?>
            </datalist>
            <input type="text" list="automodels" class="inputValue <?=$classRequired?>"
                id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>"
                value="<?=htmlspecialchars(isset($documentFieldValues[$zapTemplateField['id']]) ? $documentFieldValues[$zapTemplateField['id']] : '')?>"
                onkeypress="noSubmitEnter(event);" namefield="<?=($zapTemplateField['fieldName'])?>">
            <?php } elseif ($zapTemplateField['fieldType'] == 'date') {
        //--- тип поля ДАТА -------------------------------------------------------------------------------------------------------------------------
      ?>
            <input type="date" class="inputValue <?=$classRequired?>"
                id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>"
                value="<?=htmlspecialchars(isset($documentFieldValues[$zapTemplateField['id']]) ? $documentFieldValues[$zapTemplateField['id']] : null)?>"
                onkeypress="noSubmitEnter(event);" namefield="<?=($zapTemplateField['fieldName'])?>">
            <?php } elseif ($zapTemplateField['fieldType'] == 'bool') {
        //--- тип поля BOOL -------------------------------------------------------------------------------------------------------------------------
      ?>
            <select class="inputValue <?=$classRequired?>" id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>"
                namefield="<?=($zapTemplateField['fieldName'])?>">
                <option value="" <?=($documentFieldValues[$zapTemplateField['id']] == '' ? 'selected' : '')?>></option>
                <option value="1" <?=($documentFieldValues[$zapTemplateField['id']] == '1' ? 'selected' : '')?>>Да
                </option>
                <option value="0" <?=($documentFieldValues[$zapTemplateField['id']] == '0' ? 'selected' : '')?>>Нет
                </option>
            </select>
            <?php } elseif ($zapTemplateField['fieldType'] == 'textarea') {
        //--- тип поля TEXTAREA ---------------------------------------------------------------------------------------------------------------------
      ?>
            <textarea class="inputValue <?=$classRequired?>" id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>"
                namefield="<?=($zapTemplateField['fieldName'])?>"><?=htmlspecialchars(isset($documentFieldValues[$zapTemplateField['id']]) ? $documentFieldValues[$zapTemplateField['id']] : '')?></textarea>
            <?php } elseif ($zapTemplateField['fieldType'] == 'time') {
        //--- тип поля TIME -------------------------------------------------------------------------------------------------------------------------
      ?>
            <input type="text" class="inputValue <?=$classRequired?> mask-time"
                id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>"
                value="<?=htmlspecialchars(isset($documentFieldValues[$zapTemplateField['id']]) ? $documentFieldValues[$zapTemplateField['id']] : '')?>"
                onkeypress="noSubmitEnter(event);" namefield="<?=($zapTemplateField['fieldName'])?>">
            <?php } else {
        //--- остальные типы полей ------------------------------------------------------------------------------------------------------------------
      ?>
            <input type="text" class="inputValue <?=$classRequired?>"
                id="documentFieldValue_<?=$zapTemplateField['id']?>"
                name="documentFieldValue_<?=$zapTemplateField['id']?>"
                value="<?=htmlspecialchars(isset($documentFieldValues[$zapTemplateField['id']]) ? $documentFieldValues[$zapTemplateField['id']] : '')?>"
                onkeypress="noSubmitEnter(event);" namefield="<?=($zapTemplateField['fieldName'])?>">
            <?php } ?>
        </div>
    </div>
    <?php
      }
    }
  ?>
</div>


<?php } ?>

<!-- ------------------------------------------------------------------------------------------ -->
<?php } ?>


<script>
function controlRequiredDocument() {
    var res = true;
    $('#selectDocTemplate').css('border-color', '');
    var selectDocTemplate = $('#selectDocTemplate').val();
    if (selectDocTemplate != null && selectDocTemplate == 0) {
        $('#selectDocTemplate').css('border-color', 'red');
        //alert('Выберите щаблон документа.');
        event.preventDefault();
        res = false;
    }

    $('.classRequired').each(function() {
        $(this).css('border-color', '');
        var fieldVal = $(this).val();
        if (fieldVal == null || fieldVal.trim() == '') {
            $(this).css('border-color', 'red');
            //alert('Не заполнено обязательное поле "' + $(this).attr("namefield") + '".');
            event.preventDefault();
            res = false;
        }
    });
    if (!res) {
        alert('Не заполнены обязательные поля.');
    }
    return res;
}
</script>


<script>
function controlRequiredDocumentBeforeGenerate() {
    if (controlRequiredDocument()) {
        if (!confirm('Сгенерировать документы по файлам-шаблонам?')) {
            event.preventDefault();
            return false;
        }
    } else {
        event.preventDefault();
        return false;
    }
}
</script>


<script>
function uploadFileDocument(numFile, fileName) {
    if (!confirm('Перезагрузить файл ' + fileName + '?')) {
        event.preventDefault();
        return false;
    } else {
        $('#fileDocumentUpload_' + numFile).click();
    }
}
</script>


<script>
$('.fileDocumentUpload').change(function() {
    var elemName = this.name;
    var pos = elemName.indexOf('_');
    if (pos != -1) {
        elemNumber = elemName.substring(pos + 1);
        myfiles = this.files;
        $('#labelFileForUpload_' + elemNumber).text(myfiles[0].name);
        $('#panelFileDocumentUpload_' + elemNumber).show();
    }
});
</script>


<script>
function showExtendedFilterCriteria(params) {
    if ($('#panelExtendedFilter').is(':visible')) {
        $('#panelExtendedFilter').hide();
        $('#buttonShowExtendedFilter').text('Показать фильтр');
        $('#hfDisplayExtendedFilter').val('style="display: none;"');
    } else {
        $('#panelExtendedFilter').show();
        $('#buttonShowExtendedFilter').text('Скрыть фильтр');
        $('#hfDisplayExtendedFilter').val('');
    }
}
</script>


<?php

require_once 'footer.php';

?>