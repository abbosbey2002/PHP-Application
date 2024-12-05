<?php

session_start();

require_once './config.php';
require_once 'classes/MySQL.php';
require_once 'classes/Paginator.php';
require_once 'classes/GridView.php';

$mysql = new MySQL();

//--- для пагинатора ---
$baseURL = 'userPage.php';
$limit = 20;
$curPage = empty($_POST['page']) ? 1 : $_POST['page'];

//--- параметры сортировки ---
$sortListFields = [ ['login asc', 'Логин &uarr;'], ['login desc', 'Логин &darr;'], ['name asc', 'Имя &uarr;'], ['name desc', 'Имя &darr;'], ['role asc', 'Роль &uarr;'], ['role desc', 'Роль &darr;'] ];
$sortBy = isset($_SESSION['userPageSortBy']) ? $_SESSION['userPageSortBy'] : $sortListFields[0][0];
$sortBy = isset($_POST['sortby']) ? $_POST['sortby'] : $sortBy;
$_SESSION['userPageSortBy'] = $sortBy;
$partSortBy = explode(' ', $sortBy);
$orderBy = $sortBy == '' ? '' : ' order by `' . $partSortBy[0] . '` ' . $partSortBy[1];

//--- параметры фильтра ---
$listFilters = [ ['login', 'Логин', 'like,='], ['name', 'Имя', 'like,='] ];
$filterBy = isset($_SESSION['userPageFilterBy']) ? $_SESSION['userPageFilterBy'] : '{ "filters":[ ] }';
$filterBy = isset($_POST['filterby']) ? $_POST['filterby'] : $filterBy;
$_SESSION['userPageFilterBy'] = $filterBy;
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
$sql = "select SQL_CALC_FOUND_ROWS * from `users` " . $whereBy . ' ' . $orderBy . " limit " . (($curPage - 1) * $limit) . ", " . $limit;
$data = $mysql->querySelect($sql);

//--- общее количество записей ---
$sql = "select FOUND_ROWS() as rowNum from `users`";
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
  $head = new GridViewHead(); $head->value = 'Логин';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Имя пользователя';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Роль пользователя';  $gridView->headers[] = $head;
  /* $head = new GridViewHead(); $head->value = 'Выбор города';  $gridView->headers[] = $head; */
  $head = new GridViewHead(); $head->value = 'Генеральный директор';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = 'Цессионарий';  $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;

  //--- строки ---
  foreach ($data as $row) {
    $datarow = [];

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'link';
    $column->value = $row['login'];
    $column->class = 'linkTable';
    $column->link = '?user=' . $row['id'];
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $row['name'];
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = getUserRoleName($row['role']);
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    /*
    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'text';
    $column->value = $row['selCity'] == 1 ? 'Да' : 'Нет';
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;
*/

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'text';
    $column->value = $row['isBoss'] == 1 ? 'Да' : 'Нет';
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $cessionName = '';
    $sql = "select d.`fieldValue` from `registers_data` d inner join `registers` r on r.`id` = d.`registerId` where r.`name` = 'Цессионарий' and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`zapId` = " . $row['cessionId'];
    $zapCess = $mysql->querySelect($sql);
    if ($zapCess) {
      $cessionName = $zapCess[0]['fieldValue'];
    }
    $columns = [];
    $column = new GridViewCell();
    $column->type = 'text';
    $column->value = $cessionName;
    $column->class = 'textTable';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'submit';
    $column->name = 'deleteUser_' . $row['id'];
    $column->image = 'images/dele16.png';
    $column->class = 'imageTable';
    $column->onclick = 'return confirm(\'Удалить пользователя ' . $row['login'] . '?\');';
    $column->title = 'Удаление пользователя';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'image';
    $column->value = 'images/edit16.png';
    $column->class = 'imageTable';
    $column->link = '?user=' . $row['id'];
    $column->title = 'Редактирование пользователя';
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
