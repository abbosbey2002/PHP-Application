<?php

require_once 'classes/MySQL.php';
require_once 'lib/phpspreadsheet/vendor/autoload.php';
require_once 'lib/phpword/vendor/autoload.php';


//--- генерирование документов Excel ---
function generateXlsx($documentTemplate, $documentFieldValues, $fileNameFull, $documentId) {
  $mysql = new MySQL();

  $reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
  $spreadsheet = $reader->load($fileNameFull);
  $worksheet = $spreadsheet->getSheetByName('Форма');

  $sql = "select c.`typeField`, c.`fieldId`, c.`fieldDocId`, c.`fieldValue`, k.`fieldType` from `templates_cell` c inner join `templates_cons` k on k.`id` = c.`fieldDocId` where k.`documentId` = " . $documentTemplate . " and c.`typeCell` = 'E' and c.`fieldValue` <> ''";
  $zapFields = $mysql->querySelect($sql);
  if ($zapFields) {
    foreach ($zapFields as $zapField) {
      if ($zapField['typeField'] == 'S') {
        $cellValue = '';
        $sql = "select `fieldValue` from `registers_data` where `zapId` = " . $documentFieldValues[$zapField['fieldDocId']] . " and `fieldId` = " . $zapField['fieldId'];
        $zapVals = $mysql->querySelect($sql);
        if ($zapVals) {
          $cellValue = trim($zapVals[0]['fieldValue']);
        }
      } else {
        $cellValue = trim($documentFieldValues[$zapField['fieldDocId']]);
      }
      if ($zapField['fieldType'] == 'date' && $cellValue != '' && mb_strlen($cellValue) == 10 && mb_substr($cellValue, 4, 1) == '-' && mb_substr($cellValue, 7, 1) == '-') {
        $cellValue = mb_substr($cellValue, 8, 2) . '.' . mb_substr($cellValue, 5, 2) . '.' . mb_substr($cellValue, 0, 4);
      }
      $worksheet->setCellValue($zapField['fieldValue'], $cellValue);
    }
  }

  //--- автовысота строк ---
  foreach ($spreadsheet->getAllSheets() as $worksheet) {
    $sheetName = $worksheet->getTitle();

    if (mb_strtolower($sheetName) == 'форма' || mb_strpos(mb_strtolower($sheetName), 'обложка') !== false)
      continue;

    $maxRows = $worksheet->getHighestRow();
    $maxCols = $worksheet->getHighestColumn();
    $maxColIndex = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCols);
    for ($iiRow = 1; $iiRow <= $maxRows; $iiRow++) {
      $heightMax = 14;
      for ($iiCell = 1; $iiCell <= $maxColIndex; $iiCell++) {
        $cellName = chr(64 + $iiCell) . $iiRow;
        $isMerged = checkMergedCell($worksheet, $cellName);
        if ($isMerged == 2) {
          continue;
        }
        if ($isMerged == 0) {
          $widthCell = $worksheet->getColumnDimensionByColumn($iiCell)->getWidth();
        } else {
          $widthCell = 0;
          $mergedRange = $worksheet->getCell($cellName)->getMergeRange();
          $iiCellRng = $iiCell;
          while ($worksheet->getCell(chr(64 + $iiCellRng) . $iiRow)->isInRange($mergedRange)) {
            $widthCell += $worksheet->getColumnDimensionByColumn($iiCellRng)->getWidth();
            $iiCellRng++;
          }
        }

        $fontSize = $worksheet->getStyle($cellName)->getFont()->getSize();

        $text = $worksheet->getCell($cellName)->getCalculatedValue();
        $heightCell = calculateHeightCell($widthCell, $text, $fontSize);

        $heightMax = $heightMax < $heightCell ? $heightCell : $heightMax;
      }
      $worksheet->getRowDimension($iiRow)->setRowHeight($heightMax);
    }
  }

  //--- скрыть лист Форма ---
  foreach ($spreadsheet->getAllSheets() as $worksheet) {
    $sheetName = $worksheet->getTitle();
    if ($sheetName == 'Форма') {
      $worksheet->setSheetState('hidden');
    }
  }

  //--- сохранить файл ---
  $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
  $fileNameParts = explode('/', $fileNameFull);
  $fileNameDir = 'documents/' . sprintf('%06d', $documentId) . '/';
  $fileName = $fileNameDir . end($fileNameParts);
  if (!file_exists($fileNameDir)) {
    mkdir($fileNameDir, 0777, true);
  }
  $writer->save($fileName);

  $writer = null;
  $reader = null;
}


//--- проверка вхождения ячейки в объединение ячеек ---
//--- 0 - не входит, 1 - первая ячейка, 2 - не первая ячейка
function checkMergedCell($sheet, $cellName){
  foreach ($sheet->getMergeCells() as $cells) {
    if ($sheet->getCell($cellName)->isInRange($cells)) {
      if ($sheet->getCell($cellName)->isMergeRangeValueCell()) {
        return 1;
      } else {
        return 2;
      }
    }
  }
  return 0;
}

/*
//--- расчет высоты ячейки по содержимому ---
function calculateHeightCell($widthCell, $text, $fontSize) {
  // общая ширина текста
  $textWidth = mb_strlen($text) * 0.75 * $fontSize; // 0.7 - примерное соотношение ширины и высоты символа шрифта
  // количество строк
  $kolvoRows = floor($textWidth / ($widthCell * 7.5)) + 1; // 7.5 - количество точек в одном пункте ширины
  // расчетная высота ячейки
  $heightCell = $kolvoRows * ($fontSize * 1.3) + $fontSize * 0.2; // 1.3 - коэффициент для высоты строки с межстрочным интервалом
  //echo $text . '<br>textWidth=' . $textWidth . ' kolvoRows=' . $kolvoRows . ' heightCell=' . $heightCell . ' widthCell=' . $widthCell . '<br>';
  return $heightCell;
}
*/

//--- расчет высоты ячейки по содержимому ---
function calculateHeightCell($widthCell, $text, $fontSize) {
  $textParts = explode(PHP_EOL, $text);
  $kolvoRows = 0;
  foreach ($textParts as $textPart) {
    // ширина текста
    $textWidth = mb_strlen($textPart) * 0.75 * $fontSize; // 0.7 - примерное соотношение ширины и высоты символа шрифта
    // количество строк
    $kolvoRows += floor($textWidth / ($widthCell * 7.5)) + 1; // 7.5 - количество точек в одном пункте ширины
  }
  // расчетная высота ячейки
  $heightCell = $kolvoRows * ($fontSize * 1.3) + $fontSize * 0.2; // 1.3 - коэффициент для высоты строки с межстрочным интервалом
  //echo $text . '<br>textWidth=' . $textWidth . ' kolvoRows=' . $kolvoRows . ' heightCell=' . $heightCell . ' widthCell=' . $widthCell . '<br>';
  return $heightCell;
}


//--- генерирование документов Word ---
function generateDocx($documentTemplate, $documentFieldValues, $fileNameFull, $documentId) {
  $mysql = new MySQL();

  $templateWord = new PhpOffice\PhpWord\TemplateProcessor($fileNameFull);

  $sql = "select c.`typeField`, c.`fieldId`, c.`fieldDocId`, c.`fieldValue`, k.`fieldType` from `templates_cell` c inner join `templates_cons` k on k.`id` = c.`fieldDocId` where k.`documentId` = " . $documentTemplate . " and c.`typeCell` = 'W' and c.`fieldValue` <> ''";
  $zapFields = $mysql->querySelect($sql);
  if ($zapFields) {
    foreach ($zapFields as $zapField) {
      if ($zapField['typeField'] == 'S') {
        $cellValue = '';
        $sql = "select `fieldValue` from `registers_data` where `zapId` = " . $documentFieldValues[$zapField['fieldDocId']] . " and `fieldId` = " . $zapField['fieldId'];
        $zapVals = $mysql->querySelect($sql);
        if ($zapVals) {
          $cellValue = trim($zapVals[0]['fieldValue']);
        }
      } else {
        $cellValue = trim($documentFieldValues[$zapField['fieldDocId']]);
      }
      if ($zapField['fieldType'] == 'date' && $cellValue != '' && mb_strlen($cellValue) == 10 && mb_substr($cellValue, 4, 1) == '-' && mb_substr($cellValue, 7, 1) == '-') {
        $cellValue = mb_substr($cellValue, 8, 2) . '.' . mb_substr($cellValue, 5, 2) . '.' . mb_substr($cellValue, 0, 4);
      }
      $cellValue = htmlspecialchars($cellValue);
      $cellValue = str_replace(array("\n", "\r\n"), '</w:t></w:r></w:p><w:p><w:r><w:t>', $cellValue);
      $templateWord->setValue($zapField['fieldValue'], mb_convert_encoding($cellValue, 'UTF-8', 'UTF-8'));
    }
  }

  $fileNameParts = explode('/', $fileNameFull);
  $fileNameDir = 'documents/' . sprintf('%06d', $documentId) . '/';
  $fileName = $fileNameDir . end($fileNameParts);
  if (!file_exists($fileNameDir)) {
    mkdir($fileNameDir, 0777, true);
  }
  $templateWord->saveAs($fileName);

  $templateWord = null;
}


?>
