<?php

require_once './config.php';
require_once 'MySQL.php';
require_once 'functions.php'; //--- для отладки, потом можно убрать

class Paginator {
  var $baseURL         = '';
  var $totalRows       = '';
  var $perPage         = 0;
  var $numLinks        = 2;
  var $currentPage     = 1;
  var $firstLink       = '';
  var $nextLink        = '&gt;';
  var $prevLink        = '&lt;';
  var $lastLink        = '';
  var $fullTagOpen     = '<div class="pagination">';
  var $fullTagClose    = '</div>';
  var $firstTagOpen    = '';
  var $firstTagClose   = '';
  var $lastTagOpen     = '';
  var $lastTagClose    = '';
  var $curTagOpen      = '<b>';
  var $curTagClose     = '</b>';
  var $nextTagOpen     = '';
  var $nextTagClose    = '';
  var $prevTagOpen     = '';
  var $prevTagClose    = '';
  var $numTagOpen      = '';
  var $numTagClose     = '';
  var $anchorClass     = '';
  var $showCount       = true;
  var $contentDiv      = '';
  var $sortByField     = '';
  var $sortListFields  = [];
  var $filterBy        = '';
  var $listFilters     = [];
  var $whereId         = '';
  var $classPanel      = '';
  var $filterExtended  = '';


  function __construct($params = array()) {
    if (count($params) > 0) {
      $this->initialize($params);
    }
    if ($this->anchorClass != '') {
      $this->anchorClass = 'class="'.$this->anchorClass.'" ';
    }
  }

  function initialize($params = array()) {
    if (count($params) > 0) {
      foreach ($params as $key => $val) {
        if (isset($this->$key)) {
          $this->$key = $val;
        }
      }
      echo '<input type="hidden" id="hfPaginatorBaseURL" value="' . $this->baseURL . '">';
      echo '<input type="hidden" id="hfPaginatorCurrentPage" value="' . $this->currentPage . '">';
      echo '<input type="hidden" id="hfPaginatorSortByField" value="' . $this->sortByField . '">';
      echo '<input type="hidden" id="hfPaginatorFilterBy" value="' . htmlspecialchars($this->filterBy) . '">';
      echo '<input type="hidden" id="hfPaginatorFilterExtended" value="' . htmlspecialchars($this->filterExtended) . '">';
      echo '<input type="hidden" id="hfPaginatorWhereId" value="' . $this->whereId . '">';
    }
  }


  // * ----------------------------
  // * генерация ссылок пагинатора
  // * ----------------------------
  function createLinks() {
    // если общее число записей = 0, то не продолжать
    if ($this->totalRows == 0 || $this->perPage == 0) {
      return '';
    }

    // вычисляем общее количество страниц
    $numPages = ceil($this->totalRows / $this->perPage);

    // если только одна страница, то не продолжать
    if ($numPages == 1) {
      if ($this->showCount) {
        $info = '<div class="panelLinks ' . $this->classPanel . '"><span class="labelPageInfo">Всего элементов: ' . $this->totalRows . '</span></div>';
        return $info;
      } else {
        return '';
      }
    }

    // определить текущую страницу
    if (!is_numeric($this->currentPage)) {
      $this->currentPage = 1;
    }

    // строковая переменная содержимого ссылок
    $html = '<ul class="linksPage ' . $this->classPanel . '">';

    // отображение уведомлений о ссылках
    if ($this->showCount) {
      $html .= '<span class="labelPageInfo">Страница ' . $this->currentPage . ' из ' . $numPages . ' (Всего элементов: ' . $this->totalRows . ')</span>';
    }

    $this->numLinks = (int)$this->numLinks;

    // вычислить начальный и конечный номера
    $start = (($this->currentPage - $this->numLinks) > 0) ? $this->currentPage - $this->numLinks : 1;
    $end   = (($this->currentPage + $this->numLinks) < $numPages) ? $this->currentPage + $this->numLinks : $numPages;

    // рендерить "предыдущую" ссылку
    $enabled = $this->currentPage == 1 ? false : true;
    $html .= $this->prevTagOpen . $this->getAJAXlink($this->currentPage - 1, $this->prevLink, $enabled) . $this->prevTagClose;

    // рендерить "первую" ссылку
    if ($start > 1) {
      $html .= $this->firstTagOpen . $this->getAJAXlink(1, '1') . $this->firstTagClose;
    }
    if ($start > 2) {
      $html .= $this->getAJAXlink(0, '...', false);
    }

    // рендерить цифровые ссылки
    for ($loop = $start; $loop <= $end; $loop++) {
      if ($this->currentPage == $loop) {
        //$html .= $this->curTagOpen . $loop . $this->curTagClose;
        $html .= $this->numTagOpen . $this->getAJAXlink($loop, $loop, false, 'activePage') . $this->numTagClose;
      } else {
        $html .= $this->numTagOpen . $this->getAJAXlink($loop, $loop) . $this->numTagClose;
      }
    }

    // рендерить "последнюю" ссылку
    if ($end < $numPages - 1) {
      $html .= $this->getAJAXlink(0, '...', false);
    }
    if ($end < $numPages) {
      $html .= $this->lastTagOpen . $this->getAJAXlink($numPages, $numPages) . $this->lastTagClose;
    }

    // рендерить "следующую" ссылку
    $enabled = $this->currentPage == $numPages ? false : true;
    $html .= $this->nextTagOpen . $this->getAJAXlink($this->currentPage + 1, $this->nextLink, $enabled) . $this->nextTagClose;

    // удалить двойные слеши
    $html = preg_replace("#([^:])//+#", "\\1/", $html);

    $html .= '</ul>';

    // добавьте HTML-оболочку, если она существует
    $html = $this->fullTagOpen . $html . $this->fullTagClose;

    return $html;
  }


  function getAJAXlink($count, $text, $enabled = true, $class = '') {
    if ($this->contentDiv == '')
      return '<a href="'. $this->anchorClass . ' ' . $this->baseURL . $count . '">'. $text .'</a>';

    $pageCount = $count ? $count : 1;

    $style = $enabled ? '' : 'style="pointer-events: none;"';

    $linka = "<a href=\"javascript:void(0);\" " . $this->anchorClass . " onclick=\"clickLinkPage(" . $count . ")\" " . $style . "><li class=\"linksPage " . $class . "\">" . $text ."</li></a>";
    return $linka;
}


  // * -------------------------
  // * генерация панели фильтра
  // * -------------------------
  function createFilter() {
    $html = '';
    $filterTitle = '';
    if ($this->listFilters) {
      $html .= '<div id="filterCriteria" class="filterCriteria" style="display: none;">';
      $html .= '<div class="filterCriteriaTable">';
      foreach ($this->listFilters as $oneFilter) {
        $partFilterUsl = '';
        $partFilterText = '';
        if ($this->filterBy) {
          $stroksFilterBy = json_decode($this->filterBy, true);
          foreach ($stroksFilterBy['filters'] as $strokaFilterBy) {
            if ($oneFilter[0] == $strokaFilterBy['field']) {
              $partFilterUsl = $strokaFilterBy['usl'];
              $partFilterText = $strokaFilterBy['text'];
              if (trim($strokaFilterBy['text']) != '') {
                $filterTitle .= ($filterTitle == '' ? '' : ', ') . mb_strtolower($oneFilter[1]) . ' ' . ($strokaFilterBy['usl'] == 'like' ? 'содержит' : $strokaFilterBy['usl']) . ' \'' . $strokaFilterBy['text'] . '\'';
              }
              break;
            }
          }
        }

        $html .= '<div class="filterCriteriaRow">';

        $html .= '<div class="filterCriteriaCell">';
        $html .= $oneFilter[1] . ':';
        $html .= '</div>';

        $html .= '<div class="filterCriteriaCell">';
        $html .= '<select id="filter_usl_' . $oneFilter[0] . '" class="filterCriteria">';
        $partUsls = explode(',', $oneFilter[2]);
        foreach ($partUsls as $partUsl) {
          $partUslName = '';
          switch ($partUsl) {
            case 'like': $partUslName = 'содержит';
              break;
            case '=': $partUslName = 'равно';
              break;
            case '<': $partUslName = 'меньше';
              break;
            case '>': $partUslName = 'больше';
              break;
          }
          $html .= '<option value="' . $partUsl . '" ' . ($partUsl == $partFilterUsl ? 'selected' : '') . '>' . $partUslName . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        $html .= '<div class="filterCriteriaCell">';
        $html .= '<input type="text" id="filter_text_' . $oneFilter[0] . '" class="filterCriteria filter_text" value="' . $partFilterText . '" onkeypress="noSubmitEnter(event);">';

        $html .= '</div>';

        $html .= '</div>';
      }
      $html .= '<div class="filterCriteriaRow">';
      $html .= '<div class="filterCriteriaCell"></div>';
      $html .= '<div class="filterCriteriaCell"></div>';
      $html .= '<div class="filterCriteriaCell"><button type="button" class="buttonFilter buttonFilterApply" style="width: 100%;" onclick="clickButtonFilter(this);">Применить фильтр</button></div>';
      $html .= '</div>';
      $html .= '</div>';
      $divFilter = '<div class="panelFilter ' . $this->classPanel . '">';
      $divFilter .= '<div><button type="button" id="buttonShowFilter" class="buttonFilter" onclick="showFilterCriteria(this)">Показать фильтр</button>' . ($filterTitle == '' ? '' : '<button type="button" class="buttonFilter" onclick="clearFilter();">Очистить фильтр</button>') . '<span class="labelPageInfo">Фильтр: ' . ($filterTitle == '' ? 'не определен' : $filterTitle) . '</span></div>';
      //--- надпись о сортировке ---
      $divFilter .= '<div><span class="labelPageInfo">&nbsp;&nbsp;Сортировать по:&nbsp;</span>';
      $divFilter .= '<select class="selectSort" onchange="changeSortByField(this);">';
      foreach ($this->sortListFields as $sortListField) {
        $divFilter .= '<option value="' . $sortListField[0] . '" ' . ($this->sortByField == $sortListField[0] ? 'selected' : '') . '>' . $sortListField[1] . '</option>';
      }
      $divFilter .= '</select></div>';
      $divFilter .= '</div>';
      $html = '<div>' . $divFilter . $html . '</div>';
      $html .= '</div>';
      $html .= '</div>';
      return $html;
    } else {
      return '';
    }
  }


}

?>


<script>
  function clickLinkPage(count) {
    $.post($('#hfPaginatorBaseURL').val(), {"page" : count, "sortby" : $('#hfPaginatorSortByField').val(), "filterby" : $('#hfPaginatorFilterBy').val(), "extfilterby" : $('#hfPaginatorFilterExtended').val(), "whereid" : $('#hfPaginatorWhereId').val() }, function(data) { $('#dataContainer').html(data); });
  }
</script>


<script>
  function changeSortByField(params) {
    $.post($('#hfPaginatorBaseURL').val(), {"sortby" : params.value, "filterby" : $('#hfPaginatorFilterBy').val(), "extfilterby" : $('#hfPaginatorFilterExtended').val(), "whereid" : $('#hfPaginatorWhereId').val() }, function(data) { $('#dataContainer').html(data); });
  }
</script>


<script>
  function clearFilter() {
    $.post($('#hfPaginatorBaseURL').val(), {"sortby" : $('#hfPaginatorSortByField').val(), "filterby" : "{ \"filters\":[ ] }", "extfilterby" : $('#hfPaginatorFilterExtended').val(), "whereid" : $('#hfPaginatorWhereId').val() }, function(data) { $('#dataContainer').html(data); });
  }
</script>


<script>
  function showFilterCriteria(params) {
    if ($('#filterCriteria').is(':visible')) {
      $('#filterCriteria').hide();
      $('#buttonShowFilter').text('Показать фильтр');
    } else {
      $('#filterCriteria').show();
      $('#buttonShowFilter').text('Скрыть фильтр');
    }
  }
</script>


<script>
  function clickButtonFilter(params) {
    var filter_texts = $('.filter_text');
    var filter = '';
    for (var ii = 0; ii < filter_texts.length; ii++) {
      if (filter_texts[ii].value != '') {
        var pos = filter_texts[ii].id.lastIndexOf('_');
        var field_name = filter_texts[ii].id.substring(pos + 1);
        filter += (filter == '' ? '' : ',') + '{"field":"' + field_name + '","usl":"' + $(`#filter_usl_${field_name}`).val() + '","text":"' + filter_texts[ii].value + '"}';
      }
    }
    var filters = '{ "filters":[ ' + filter + ' ] }';

    $.post($('#hfPaginatorBaseURL').val(), {"sortby" : $('#hfPaginatorSortByField').val(), "filterby" : filters, "extfilterby" : $('#hfPaginatorFilterExtended').val(), "whereid" : $('#hfPaginatorWhereId').val() }, function(data) { $('#dataContainer').html(data); });
  }
</script>


<script>
  function noSubmitEnter(event) {
    if (event.keyCode == 13 || event.which == 13) {
      event.preventDefault();
    }
  }
</script>
