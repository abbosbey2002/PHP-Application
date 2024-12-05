<?php

require_once 'functions.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ГЕНЕРАТОР-ДОКУМЕНТОВ.РФ</title>
  <link rel="stylesheet" type="text/css" href="styles.css">
  <link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
  <script src="lib/jquery-3.5.1.min.js"></script>
  <script src="lib/jquery.maskedinput.min.js"></script>
</head>

<body>
<form id="formMain" name="formMain" method="post" enctype="multipart/form-data" style="height: 100%;">

<div class="loginMain">
  <div class="loginPanel" style="width: 350px !important; height: 300px !important;">

    <div class="loginTitle">
      ГЕНЕРАТОР-ДОКУМЕНТОВ.РФ
    </div>

    <div class="noaccTitle">
      ДОСТУП ЗАПРЕЩЕН
    </div>

    <div class="noaccText">
      Сайт доступен только для зарегистрированных пользователей и зарегистрированных IP-адресов.
    </div>

    <div class="noaccIp">
      <?php
        $ipaddrs = getIpAddressesClient();
        $strIps = '';
        foreach ($ipaddrs as $ipaddr) {
          if ($ipaddr != '') {
            $strIps .= ($strIps == '' ? '' : ', ') . $ipaddr;
          }
        }
        echo $strIps;
      ?>
    </div>

  </div>
</div>

<?php
  //echo '<pre>'; var_dump($_SERVER); echo '</pre>';
?>

</form>
</body>
</html>
