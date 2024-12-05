<?php

session_start();

require_once './config.php';
require_once 'classes/MySQL.php';
require_once 'classes/Paginator.php';
require_once 'classes/GridView.php';

$mysql = new MySQL();

//--- для пагинатора ---
$baseURL = 'documentPage.php';
$limit = 10;
$curPage = empty($_POST['page']) ? 1 : $_POST['page'];

//--- параметры сортировки ---
$sortListFields = [ ['id desc', 'Номер &darr;'], ['id asc', 'Номер &uarr;'] ];
$sortBy = isset($_SESSION['documentPageSortBy']) ? $_SESSION['documentPageSortBy'] : $sortListFields[0][0];
$sortBy = isset($_POST['sortby']) ? $_POST['sortby'] : $sortBy;
$_SESSION['documentPageSortBy'] = $sortBy;
$partSortBy = explode(' ', $sortBy);
$orderBy = $sortBy == '' ? '' : ' order by d.`' . $partSortBy[0] . '` ' . $partSortBy[1];

//--- параметры фильтра ---
$listFilters = [ ['id', 'Номер', 'like,='], ['dogovorName', 'Договор', 'like,='], ['clientName', 'Клиент', 'like,='], ['comment', 'Комментарий', 'like,='] ];
$filterBy = isset($_SESSION['documentPageFilterBy']) ? $_SESSION['documentPageFilterBy'] : '{ "filters":[ ] }';
$filterBy = isset($_POST['filterby']) ? $_POST['filterby'] : $filterBy;
$_SESSION['documentPageFilterBy'] = $filterBy;
$whereBy = '';
if ($filterBy) {
  $stroksFilterBy = json_decode($filterBy, true);
  foreach ($stroksFilterBy['filters'] as $strokaFilterBy) {
    $filterField = $strokaFilterBy['field'];
    $filterUsl = $strokaFilterBy['usl'];
    $filterText = $strokaFilterBy['text'];
    if ($filterUsl == 'like') {
      $filterText = '%' . $filterText . '%';
    }
    $whereBy .= ($whereBy == '' ? '' : ' and ') . "(d.`" . $filterField . "` " . $filterUsl . " '" . $filterText . "')";
  }
}

//--- расширенный фильтр ---
$extFilterBy = isset($_POST['extfilterby']) ? $_POST['extfilterby'] : '';
if ($extFilterBy) {
  $stroksExtFilterBy = json_decode($extFilterBy, true);
  foreach ($stroksExtFilterBy['filters'] as $strokaExtFilterBy) {
    $extFilterField = $strokaExtFilterBy['field'];
    $extFilterFilter = $strokaExtFilterBy['filter'];
    $sql = "select `fieldType` from `templates_cons` where `id` = " . $extFilterField;
    $zapTypes = $mysql->querySelect($sql);
    if ($zapTypes) {
      if (strpos($zapTypes[0]['fieldType'], 'sprav_') !== false) {
        $idSprav = substr($zapTypes[0]['fieldType'], 6);
        $whereBy .= ($whereBy == '' ? '' : ' and ') . "exists (select dd.`id` from `documents_data` dd inner join `registers_data` rd on rd.`registerId` = " . $idSprav ." and rd.`zapId` = dd.`fieldValue` where dd.`documentId` = d.`id` and dd.`fieldId` = " . $extFilterField . " and rd.fieldId = (select rc.`id` from `registers_cons` rc where rc.`registerId` = rd.`registerId` order by rc.`priority` desc, rc.`fieldName` limit 1) and rd.`fieldValue` like '%" . $extFilterFilter . "%')";
      } else {
        $whereBy .= ($whereBy == '' ? '' : ' and ') . "exists (select dd.`id` from `documents_data` dd where dd.`documentId` = d.`id` and dd.`fieldId` = " . $extFilterField . " and dd.`fieldValue` like '%" . $extFilterFilter . "%')";
      }
    }
  }
}

//--- если нет расширенного фильтра - фильтровать по пользователю ---
if (!$extFilterBy) {
  $sql = "select `id` from `users` where `login` = '" . addslashes($_SESSION['userLogin']) . "' and (`role` = 'admin' or `isBoss` = 1)";
  $zapAdms = $mysql->querySelect($sql);
  if (!$zapAdms) {
    $whereBy .= ($whereBy == '' ? '' : ' and ') . "(d.`userCreate` = '" . addslashes($_SESSION['userLogin']) . "')";
  }
}

$whereBy = $whereBy == '' ? '' : ' where ' . $whereBy;

//--- извлечь записи ---
$sql = "select SQL_CALC_FOUND_ROWS * from `documents` d " . $whereBy . ' ' . $orderBy . " limit " . (($curPage - 1) * $limit) . ", " . $limit;
$data = $mysql->querySelect($sql);

//--- общее количество записей ---
$sql = "select FOUND_ROWS() as rowNum from `documents`";
$zapKols = $mysql->querySelect($sql);
$rowCount= $zapKols ? $zapKols[0]['rowNum'] : 0;


//--- инициализация пагинатора ---
$pagConfig = array(
  'baseURL' => $baseURL,
  'totalRows' => $rowCount,
  'perPage' => $limit,
  'currentPage' => $curPage,
  'contentDiv' => 'dataContainer',
  'sortByField' => $sortBy,
  'sortListFields' => $sortListFields,
  'filterBy' => $filterBy,
  'listFilters' => $listFilters,
  'classPanel' => 'width100',
  'filterExtended' => $extFilterBy
);
$paginator =  new Paginator($pagConfig);


//--- подготовить столбцы для таблицы ---
$gridView = new GridViewTable();

if ($data) {
  $gridView->class = 'width100';

  //--- заголовок ---
  $head = new GridViewHead(); $head->value = 'Номер';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Шаблон документа';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Договор';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Клиент';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Комментарий';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Сгенерированные документы';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;

  //--- строки ---
  foreach ($data as $row) {
    $datarow = [];

    $templateName = '';
    $sql = "select `name` from `templates` where `id` = " . $row['templateId'];
    $zapTemps = $mysql->querySelect($sql);
    if ($zapTemps) {
      $templateName = $zapTemps[0]['name'];
    }

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'link';
    $column->value = $row['id'];
    $column->class = 'linkTable';
    $column->link = '?document=' . $row['id'];
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $templateName;
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $row['dogovorName'];
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $row['clientName'];
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $row['comment'];
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $dirDocs = 'documents/' . sprintf('%06d', $row['id']) . '/';
    $listFiles = [];
    if (file_exists($dirDocs)) {
      $listFiles = scandir($dirDocs);
    }
    $listFilesText = '';
    $numFile = 1;
    foreach ($listFiles as $listFile) {
      if (is_file($dirDocs . $listFile)) {
        $listFilesText .= ($listFilesText == '' ? '' : '<br>') . '<button type="submit" name="buttonDownloadGenFile_' . $row['id'] . '_' . $numFile . '" class="linkTable" onclick="return confirm(\'Скачать файл ' . htmlspecialchars($listFile) . '\');" title="Скачать файл ' . htmlspecialchars($listFile) . '" value="' . htmlspecialchars($listFile) . '">' . htmlspecialchars($listFile) . '</button>';
        $numFile++;
      }
    }
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $listFilesText;
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'submit';
    $column->name = 'deleteDocument_' . $row['id'];
    $column->image = 'images/dele16.png';
    $column->class = 'imageTable';
    $column->onclick = 'return confirm(\'Удалить документ ' . $row['id'] . '?\');';
    $column->title = 'Удаление документа';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'image';
    $column->value = 'images/edit16.png';
    $column->class = 'imageTable';
    $column->link = '?document=' . $row['id'];
    $column->title = 'Редактирование документа';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- добавить строку в строки ---
    $gridView->datarows[] = $datarow;
  }
}

//--- панель фильтра ---
echo $paginator->createFilter();

//--- отобразить табличную часть ---
$gridView->Show();

//--- показать ссылки пагинатора ---
echo $paginator->createLinks();


?>
