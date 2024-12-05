<?php

//=================================================================================================
class GridViewTable {

  //-------------------->>> описание вариантов заполнения - внизу страницы <<<---------------------

  public $class; // дополнительный класс для таблицы
  public $headers; // шапка таблицы
  public $datarows; // данные для таблицы


  //--- конструктор -------------------------------------------------------------------------------
  public function __construct() {
    $this->headers = [];
    $this->datarows = [];
  }


  //--- отображение таблицы -----------------------------------------------------------------------
  public function Show() {
    if ($this->datarows) {
      echo '<div class="panelTableCommon">';
      echo '<div class="tableCommon ' . ($this->class ? $this->class : '') . '">';

      //--- шапка ---
      echo '<div class="tableRow">';
      for ($iiCol = 0; $iiCol < count($this->headers); $iiCol++) {
        $styleHead = '';
        if ($this->headers[$iiCol]->width > 0 || $this->headers[$iiCol]->minwidth > 0) {
          $styleHead = 'style="' . ($this->headers[$iiCol]->width ? 'width: ' . $this->headers[$iiCol]->width . ';' : '') . ' ' . ($this->headers[$iiCol]->minwidth ? 'min-width: ' . $this->headers[$iiCol]->minwidth . ';' : '') . '"';
        }
        echo '<div class="tableHeader" ' . $styleHead . '>' . $this->headers[$iiCol]->value . '</div>';
      }
      echo '</div>';

      //--- таблица ---
      $numBg = 1;
      foreach ($this->datarows as $datarow) {
        echo '<div class="tableRow">';
        foreach ($datarow as $column) {
          echo '<div class="tableCell ' . ($numBg == 1 ? 'tableCellBg1' : 'tableCellBg2') . '">';
          for ($iiCell = 0; $iiCell < count($column); $iiCell++) {
            $cell = $column[$iiCell];
            echo '<div ' . ($cell->classCell ? 'class="' . $cell->classCell . '"' : '') . '>';

            if ($cell->type == 'text') {
              echo '<span' . ($cell->class ? ' class="' . $cell->class . '"' : '') . '>' . $cell->value . '</span>';
            }

            if ($cell->type == 'link') {
              echo '<a ' . ($cell->class ? ' class="' . $cell->class . '"' : '') . ($cell->link ? ' href="' . $cell->link . '"' : '') . ($cell->title == '' ? '' : ' title="' . $cell->title . '"') . '>' . $cell->value . '</a>';
            }

            if ($cell->type == 'image') {
              if ($cell->link) {
                echo '<a ' . ($cell->link ? ' href="' . $cell->link . '"' : '') . '>';
              }
              echo '<img src="'. $cell->value . '" alt="" ' . ($cell->class ? ' class="' . $cell->class . '"' : '') . ($cell->onclick ? ' onclick="' . $cell->onclick . '" style="cursor: pointer;"' : '') . ($cell->title == '' ? '' : ' title="' . $cell->title . '"') . '>';
              if ($cell->link) {
                echo '</a>';
              }
            }

            if ($cell->type == 'button') {
              echo '<button type="button" ' . ($cell->name ? ' name="' . $cell->name . '"' : '') . ($cell->id ? ' id="' . $cell->id . '"' : '') . ($cell->class ? ' class="' . $cell->class . '"' : '') . ($cell->onclick ? ' onclick="' . $cell->onclick . '"' : '') . ($cell->title == '' ? '' : ' title="' . $cell->title . '"') . '>' . ($cell->image ? '<img src="' . $cell->image . '">' : '') . ($cell->value ? $cell->value : '') . '</button>';
            }

            if ($cell->type == 'submit') {
              echo '<button type="submit" ' . ($cell->name ? ' name="' . $cell->name . '"' : '') . ($cell->id ? ' id="' . $cell->id . '"' : '') . ($cell->class ? ' class="' . $cell->class . '"' : '') . ($cell->onclick ? ' onclick="' . $cell->onclick . '"' : '') . ($cell->title == '' ? '' : ' title="' . $cell->title . '"') . '>' . ($cell->image ? '<img src="' . $cell->image . '">' : '') . ($cell->value ? $cell->value : '') . '</button>';
            }

            echo '</div>';
            if ($iiCell < count($column) - 1) {
              echo '<div style="width: 1px; height: 1px;"></div>';
            }
          }
          echo '</div>';
        }
        echo '</div>';
        $numBg = 1 - $numBg;
      }

      echo '</div>'; // end tableCommon
      echo '</div>'; // end panelTableCommon
    } else {
      echo '<div><span class="spanNoData">Данные отсутствуют.</span></div>';
    }
  }

}


//=================================================================================================
class GridViewHead {
  public $value;    // заголовок
  public $width;    // ширина
  public $minwidth; // мин. ширина
}


//=================================================================================================
class GridViewCell {
  public $classCell; // дополнительный класс ячейки
  public $type;      // тип столбца
  public $value;     // значение
  public $class;     // класс элемента
  public $link;      // ссылка
  public $onclick;   // обработка нажатия
  public $title;     // всплывающая подсказка
  public $image;     // изображение
  public $id;        // id элемента
  public $name;      // name элемента
}


// тип = text (span)
// параметры: value (текст), class

// тип = link (a)
// параметры: value (текст ссылки), class, link (ссылка href)

// тип = image (img, вокруг него может быть ссылка (a))
// параметры: value (src), class, title (подсказка), onclick, link (ссылка href)

// тип = buttun (button с типом button)
// параметры: value (текст кнопки) или image (иконка кнопки), class, onclick, id, name

// тип = submit (button с типом submit)
// параметры: value (текст кнопки) или image (иконка кнопки), class, onclick, id, name


?>
