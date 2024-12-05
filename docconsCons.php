<?php

session_start();

require_once './config.php';
require_once 'classes/MySQL.php';
require_once 'classes/Paginator.php';
require_once 'classes/GridView.php';

$mysql = new MySQL();

//--- для пагинатора ---
$baseURL = 'docconsCons.php';
$limit = 100;
$curPage = empty($_POST['page']) ? 1 : $_POST['page'];
$documentId = isset($_POST['whereid']) ? $_POST['whereid'] : 0;

//--- параметры сортировки ---
$sortListFields = [ ['`priority` desc, `fieldName` asc', 'Приоритет &darr;'], ['`priority` asc, `fieldName` asc', 'Приоритет &uarr;'], ['`fieldName` asc', 'Наименование &uarr;'], ['`fieldName` desc', 'Наименование &darr;'], ['`comment` asc', 'Описание &uarr;'], ['`comment` desc', 'Описание &darr;'] ];
$sortBy = isset($_SESSION['docconsConsSortBy_' . $documentId]) ? $_SESSION['docconsConsSortBy_' . $documentId] : $sortListFields[0][0];
$sortBy = isset($_POST['sortby']) ? $_POST['sortby'] : $sortBy;
$_SESSION['docconsConsSortBy_' . $documentId] = $sortBy;
$orderBy = $sortBy == '' ? '' : ' order by ' . $sortBy;

//--- параметры фильтра ---
$listFilters = [ ['fieldName', 'Наименование', 'like,='], ['comment', 'Описание', 'like,='] ];
$filterBy = isset($_SESSION['docconsConsFilterBy_' . $documentId]) ? $_SESSION['docconsConsFilterBy_' . $documentId] : '{ "filters":[ ] }';
$filterBy = isset($_POST['filterby']) ? $_POST['filterby'] : $filterBy;
$_SESSION['docconsConsFilterBy_' . $documentId] = $filterBy;
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
    $whereBy .= ($whereBy == '' ? '' : ' and ') . "(`" . $filterField . "` " . $filterUsl . " '" . $filterText . "')";
  }
}
$whereByDocId = 'where `documentId` = ' . $documentId;
$whereBy = $whereByDocId . ' ' . ($whereBy == '' ? '' : ' and ' . $whereBy);

//--- извлечь записи ---
$sql = "select SQL_CALC_FOUND_ROWS * from `templates_cons` " . $whereBy . ' ' . $orderBy . " limit " . (($curPage - 1) * $limit) . ", " . $limit;
$data = $mysql->querySelect($sql);

//--- общее количество записей ---
$sql = "select FOUND_ROWS() as rowNum from `templates_cons` " . $whereByDocId;
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
  'whereId' => $documentId,
  'classPanel' => 'width100'
);
$paginator =  new Paginator($pagConfig);


//--- подготовить столбцы для таблицы ---
$gridView = new GridViewTable();

if ($data) {
  $gridView->class = 'width100';

  //--- заголовок ---
  $head = new GridViewHead(); $head->value = 'Наименование';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Описание';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Тип поля';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Обязательность';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Приоритет';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Заголовок';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;

  //--- строки ---
  foreach ($data as $row) {
    $datarow = [];

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $row['fieldName'];
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
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = getTypesFields(true, $row['fieldType']);
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'text';
    $column->value = $row['isRequired'] == 1 ? 'Да' : 'Нет';
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'text';
    $column->value = $row['priority'];
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $header = '';
    if ($row['header'] == 2) {
      $header = 'Клиент';
    } elseif ($row['header'] == 3) {
      $header = 'Комментарий';
    } elseif ($row['header'] == 4) {
      $header = 'Договор';
    }
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'text';
    $column->value = $header;
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'submit';
    $column->name = 'deleteField_' . $row['id'];
    $column->image = 'images/dele16.png';
    $column->class = 'imageTable';
    $column->onclick = 'return confirm(\'Удалить поле ' . $row['fieldName'] . '?\');';
    $column->title = 'Удаление поля';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'image';
    $column->value = 'images/desi16.png';
    $column->class = 'imageTable';
    $column->link = '?document=' . $documentId . '&field=' . $row['id'];
    $column->title = 'Настройка поля';
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
