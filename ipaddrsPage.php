<?php

session_start();

require_once './config.php';
require_once 'classes/MySQL.php';
require_once 'classes/Paginator.php';
require_once 'classes/GridView.php';

$mysql = new MySQL();

//--- для пагинатора ---
$baseURL = 'ipaddrsPage.php';
$limit = 20;
$curPage = empty($_POST['page']) ? 1 : $_POST['page'];

//--- параметры сортировки ---
$sortListFields = [ ['ipaddress asc', 'IP-адрес &uarr;'], ['ipaddress desc', 'IP-адрес &darr;'], ['comment asc', 'Комментарий &uarr;'], ['comment desc', 'Комментарий &darr;'] ];
$sortBy = isset($_SESSION['ipaddrPageSortBy']) ? $_SESSION['ipaddrPageSortBy'] : $sortListFields[0][0];
$sortBy = isset($_POST['sortby']) ? $_POST['sortby'] : $sortBy;
$_SESSION['ipaddrPageSortBy'] = $sortBy;
$partSortBy = explode(' ', $sortBy);
$orderBy = $sortBy == '' ? '' : ' order by `' . $partSortBy[0] . '` ' . $partSortBy[1];

//--- параметры фильтра ---
$listFilters = [ ['ipaddress', 'IP-адрес', 'like,='], ['comment', 'Комментарий', 'like,='] ];
$filterBy = isset($_SESSION['ipaddrPageFilterBy']) ? $_SESSION['ipaddrPageFilterBy'] : '{ "filters":[ ] }';
$filterBy = isset($_POST['filterby']) ? $_POST['filterby'] : $filterBy;
$_SESSION['ipaddrPageFilterBy'] = $filterBy;
$whereBy = "`comment` <> 'developer'";
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
$sql = "select SQL_CALC_FOUND_ROWS * from `ipaddrs` " . $whereBy . ' ' . $orderBy . " limit " . (($curPage - 1) * $limit) . ", " . $limit;
$data = $mysql->querySelect($sql);

//--- общее количество записей ---
$sql = "select FOUND_ROWS() as rowNum from `ipaddrs`";
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
  $head = new GridViewHead(); $head->value = 'IP-адрес';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Комментарий';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;

  //--- строки ---
  foreach ($data as $row) {
    $datarow = [];

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'link';
    $column->value = $row['ipaddress'];
    $column->class = 'linkTable';
    $column->link = '?ipaddr=' . $row['id'];
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
    $column->type = 'submit';
    $column->name = 'deleteAddr_' . $row['id'];
    $column->image = 'images/dele16.png';
    $column->class = 'imageTable';
    $column->onclick = 'return confirm(\'Удалить IP-адрес ' . $row['ipaddress'] . '?\');';
    $column->title = 'Удаление IP-адреса';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'image';
    $column->value = 'images/edit16.png';
    $column->class = 'imageTable';
    $column->link = '?ipaddr=' . $row['id'];
    $column->title = 'Редактирование IP-адреса';
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
