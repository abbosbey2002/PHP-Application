<?php

session_start();

require_once './config.php';
require_once 'classes/MySQL.php';
require_once 'classes/Paginator.php';
require_once 'classes/GridView.php';

$mysql = new MySQL();

//--- для пагинатора ---
$baseURL = 'registerEdit.php';
$limit = 20;
$curPage = empty($_POST['page']) ? 1 : $_POST['page'];
$registerId = isset($_POST['whereid']) ? $_POST['whereid'] : 0;

//--- извлечь записи ---
$data = [];
$fields = [];
$countZapAll = 0;
$sortListFields = [];
$listFilters = [];

$sql = "select `id`, `fieldName`, `fieldType` from `registers_cons` where `registerId` = " . $registerId . " order by `priority` desc, `fieldName`";
$zapFields = $mysql->querySelect($sql);
if ($zapFields) {
  foreach ($zapFields as $zapField) {
    $field = [];
    $field['id'] = $zapField['id'];
    $field['name'] = $zapField['fieldName'];
    $field['type'] = $zapField['fieldType'];
    $fields[] = $field;
    // список полей сортировки
    $sortItem = [];
    $sortItem[] = $zapField['id'] . ' asc';
    $sortItem[] = $zapField['fieldName'] . ' &uarr;';
    $sortListFields[] = $sortItem;
    $sortItem = [];
    $sortItem[] = $zapField['id'] . ' desc';
    $sortItem[] = $zapField['fieldName'] . ' &darr;';
    $sortListFields[] = $sortItem;
    // список полей для фильтра
    $filterItem = [];
    $filterItem[] = $zapField['id'];
    $filterItem[] = $zapField['fieldName'];
    $filterItem[] = 'like,=';
    $listFilters[] = $filterItem;
  }
}

//--- параметры сортировки ---
$sortBy = isset($_SESSION['registerEditSortBy_' . $registerId]) ? $_SESSION['registerEditSortBy_' . $registerId] : (isset($sortListFields[0][0]) ? $sortListFields[0][0] : '');
$sortBy = isset($_POST['sortby']) ? $_POST['sortby'] : $sortBy;
$_SESSION['registerEditSortBy_' . $registerId] = $sortBy;
$partSortBy = explode(' ', $sortBy);
$fieldSortBy = $partSortBy[0];
$orderSortBy = isset($partSortBy[1]) ? $partSortBy[1] : '';

//--- параметры фильтра ---
$filterBy = isset($_SESSION['registerEditFilterBy_' . $registerId]) ? $_SESSION['registerEditFilterBy_' . $registerId] : '{ "filters":[ ] }';
$filterBy = isset($_POST['filterby']) ? $_POST['filterby'] : $filterBy;
$_SESSION['registerEditFilterBy_' . $registerId] = $filterBy;
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
    $whereBy .= " and exists (select w.`id` from `registers_data` w where w.`registerId` = " . $registerId . " and w.`zapId` = d.`zapId` and w.`fieldId` = " . $filterField . " and w.`fieldValue` " . $filterUsl . " '" . $filterText . "')";
  }
}

if ($fields) {
  $sql = "select d.`zapId` from `registers_data` d where d.`registerId` = " . $registerId . " and d.`fieldId` = " . $fieldSortBy
    . $whereBy . " group by `zapId` order by `fieldValue` " . $orderSortBy;
  $zapZapIds = $mysql->querySelect($sql);
  if ($zapZapIds) {
    $countZapAll = count($zapZapIds);
    $sql = "select * from `registers_data` where `registerId` = " . $registerId;
    $zapRegs = $mysql->querySelect($sql);
    if ($zapRegs) {
      $countZap = 0;
      foreach ($zapZapIds as $zapZapId) {
        if (($countZap >= ($curPage - 1) * $limit) && ($countZap < $curPage * $limit)) {
          $dataRow = [];
          $dataRow[] = $zapZapId['zapId'];
          foreach ($fields as $field) {
            $value = '';
            foreach ($zapRegs as $zapReg) {
              if ($zapReg['zapId'] == $zapZapId['zapId'] && $zapReg['fieldId'] == $field['id']) {
                if ($field['type'] == 'date' && mb_strlen($zapReg['fieldValue']) == 10 && mb_substr($zapReg['fieldValue'], 4, 1) == '-' && mb_substr($zapReg['fieldValue'], 7, 1) == '-') {
                  $value = mb_substr($zapReg['fieldValue'], 8, 2) . '.' . mb_substr($zapReg['fieldValue'], 5, 2) . '.' . mb_substr($zapReg['fieldValue'], 0, 4);
                } elseif (substr($field['type'], 0, 6) == 'sprav_' && trim($zapReg['fieldValue']) != '') {
                  $idSprav = substr($field['type'], 6);
                  $sql = "select d.`zapId`, d.`fieldValue` from `registers_data` d where d.`registerId` = " . $idSprav . " and d.fieldId = (select c.id from registers_cons c where c.registerId = d.registerId order by c.priority desc, fieldName limit 1) and d.`zapId` = " . $zapReg['fieldValue'];
                  $zapSpravs = $mysql->querySelect($sql);
                  if ($zapSpravs) {
                    $value = $zapSpravs[0]['fieldValue'];
                  }
                } else {
                  $value = $zapReg['fieldValue'];
                }
                break;
              }
            }
            $dataRow[] = $value;
          }
          $data[] = $dataRow;
        }
        $countZap++;
      }
    }
  }
}

//--- инициализация пагинатора ---
$pagConfig = array(
  'baseURL' => $baseURL,
  'totalRows' => $countZapAll,
  'perPage' => $limit,
  'currentPage' => $curPage,
  'contentDiv' => 'dataContainer',
  'sortByField' => $sortBy,
  'sortListFields' => $sortListFields,
  'filterBy' => $filterBy,
  'listFilters' => $listFilters,
  'whereId' => $registerId,
  'classPanel' => 'width100'
);
$paginator =  new Paginator($pagConfig);

//--- подготовить столбцы для таблицы ---
$gridView = new GridViewTable();

if ($data) {
  $gridView->class = 'width100';

  //--- заголовок ---
  //$head = new GridViewHead(); $head->value = 'Id'; $head->width = '50px'; $gridView->headers[] = $head;
  for ($iiFld = 0; $iiFld < count($fields); $iiFld++) {
    $head = new GridViewHead(); $head->value = $fields[$iiFld]['name'];  $gridView->headers[] = $head;
  }
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;
  $head = new GridViewHead(); $head->value = ''; $head->width = '30px'; $gridView->headers[] = $head;

  //--- строки ---
  foreach ($data as $row) {
    $isLink = true;
    $datarow = [];
    for ($iiCell = 1; $iiCell < count($row); $iiCell++) {
      $cell = $row[$iiCell];
      //--- столбец ---
      $columns = [];
      $column = new GridViewCell();
      $column->type = 'text';
      $column->value = str_replace(PHP_EOL, '<br>', $cell);
      $column->class = 'textTable';
      if ($isLink) {
        $column->value = trim($cell) == '' ? '(не определено)' : str_replace(PHP_EOL, '<br>', $cell);
        $column->type = 'link';
        $column->class = 'linkTable';
        $column->link = '?register=' . $registerId . '&zap=' . $row[0];
      }
      $columns[] = $column;
      $datarow[] = $columns;
      $isLink = false;
    }

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'submit';
    $column->name = 'deleteZapis_' . $row[0];
    $column->image = 'images/dele16.png';
    $column->class = 'imageTable';
    $column->onclick = 'return confirm(\'Удалить запись?\');';
    $column->title = 'Удаление записи';
    $columns[] = $column;
    $datarow[] = $columns;

    //--- столбец ---
    $columns = [];
    $column = new GridViewCell();
    $column->classCell = "alignCenter";
    $column->type = 'image';
    $column->value = 'images/edit16.png';
    $column->class = 'imageTable';
    $column->link = '?register=' . $registerId . '&zap=' . $row[0];
    $column->title = 'Редактирование записи';
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
