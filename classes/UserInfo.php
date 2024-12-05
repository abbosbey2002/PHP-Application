<?php

require_once './config.php';
require_once './functions.php';
require_once 'MySQL.php';


class UserInfo {

  public $userId = 0;
  public $userLogin = '';
  public $userName = '';
  public $userRoleCode = '';
  public $userRoleName = '';
  public $userSelCity = false;  
  public $userIsBoss = false;
  public $userCessionId = 0;


  //===============================================================================================
  //--- конструктор -------------------------------------------------------------------------------
  //===============================================================================================
  public function __construct($login) {
    $this->userLogin = $login;
    $mysql = new MySQL();
    $sql = "select `id` as userId, `name` as userName, `role` as roleCode, `selCity`, `isBoss`, `cessionId` from `users` where `login` = '" . $login . "'";
    $userZap = $mysql->querySelect($sql);
    if ($userZap) {
      $this->userId = $userZap[0]['userId'];
      $this->userName = $userZap[0]['userName'];
      $this->userRoleCode = $userZap[0]['roleCode'];
      $this->userRoleName = getUserRoleName($userZap[0]['roleCode']);
      $this->userSelCity = $userZap[0]['selCity'] == 1 ? true : false;
      $this->userIsBoss = $userZap[0]['isBoss'] == 1 ? true : false;
      $this->userCessionId = $userZap[0]['cessionId'];
    }
  }

}

?>