<?php

session_start();

require_once './config.php';
require_once 'classes/MySQL.php';
require_once 'classes/Paginator.php';
require_once 'classes/GridView.php';

$mysql = new MySQL();

//--- для пагинатора ---
$baseURL = 'registerPage.php';
$limit = 20;
$curPage = empty($_POST['page']) ? 1 : $_POST['page'];

//--- очистить критерии сортировки и фильтра дочек ---
foreach ($_SESSION as $key => $value) {
  if (strpos($key, 'registerEditSortBy_') !== false || strpos($key, 'registerEditFilterBy_') !== false || strpos($key, 'registerConsSortBy_') !== false || strpos($key, 'registerConsFilterBy_') !== false) {
    unset($_SESSION[$key]);
  }
}

//--- параметры сортировки ---
$sortListFields = [ ['name asc', 'Наименование &uarr;'], ['name desc', 'Наименование &darr;'], ['comment asc', 'Описание &uarr;'], ['comment desc', 'Описание &darr;'] ];
$sortBy = isset($_SESSION['registerPageSortBy']) ? $_SESSION['registerPageSortBy'] : $sortListFields[0][0];
$sortBy = isset($_POST['sortby']) ? $_POST['sortby'] : $sortBy;
$_SESSION['registerPageSortBy'] = $sortBy;
$partSortBy = explode(' ', $sortBy);
$orderBy = $sortBy == '' ? '' : ' order by `' . $partSortBy[0] . '` ' . $partSortBy[1];

//--- параметры фильтра ---
$listFilters = [ ['name', 'Наименование', 'like,='], ['comment', 'Описание', 'like,='] ];
$filterBy = isset($_SESSION['registerPageFilterBy']) ? $_SESSION['registerPageFilterBy'] : '{ "filters":[ ] }';
$filterBy = isset($_POST['filterby']) ? $_POST['filterby'] : $filterBy;
$_SESSION['registerPageFilterBy'] = $filterBy;

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
$whereBy = $whereBy == '' ? '' : ' where ' . $whereBy;

//--- извлечь записи ---
$sql = "select SQL_CALC_FOUND_ROWS * from `registers` " . $whereBy . ' ' . $orderBy . " limit " . (($curPage - 1) * $limit) . ", " . $limit;
$data = $mysql->querySelect($sql);

//--- общее количество записей ---
$sql = "select FOUND_ROWS() as rowNum from `registers`";
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
  $head = new GridViewHead(); $head->value = 'Тип справочника';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;

  //--- строки ---
  foreach ($data as $row) {
    $datarow = [];

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'link';
    $column->value = $row['name'];
    $column->class = 'linkTable';
    $column->link = '?register=' . $row['id'];
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
    $column->classCell = "alignCenter";
    $column->type = 'text';
    $column->value = $row['typeSprav'] == 1 ? 'Справочник' : 'Перечень';
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'submit';
    $column->name = 'deleteRegister_' . $row['id'];
    $column->image = 'images/dele16.png';
    $column->class = 'imageTable';
    $column->onclick = 'return confirm(\'Удалить справочник ' . $row['name'] . '?\');';
    $column->title = 'Удаление справочника';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'image';
    $column->value = 'images/desi16.png';
    $column->class = 'imageTable';
    $column->link = '?designer=' . $row['id'];
    $column->title = 'Конструктор справочника';
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
