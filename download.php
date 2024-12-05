<?php

require_once 'functions.php';

if(isset($_GET['path'])) {
  $filename = $_GET['path'];
  if (isset($_GET['name'])) {
    $basename = $_GET['name'];
  } else {
    $filepart = explode('/', $filename);
    $basename = end($filepart);
  }

  if(file_exists($filename)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: 0");
    header('Content-Disposition: attachment; filename="'.$basename.'"');
    header('Content-Length: ' . filesize($filename));
    header('Pragma: public');
    flush();
    readfile($filename);
    die();
  } else {
    toLog("---> ERROR: The file for download does not exist.");
  }
} else {
  echoError("---> ERROR: Filename for download not defined.");
}
?>
