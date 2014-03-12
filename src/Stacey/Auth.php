<?php

class Auth
{
  public static $password = '';

  public function __construct($pasword)
  {
    self::$password = $pasword;
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
      header('WWW-Authenticate: Basic realm="This is a password protected area, please submit your password to enter."');
      header('HTTP/1.0 401 Unauthorized');
      echo 'Not authorised.';
      exit;
    } elseif ($_SERVER['PHP_AUTH_PW'] != self::$password) {
      echo 'Not authorised.';
      exit;
    }
  }

}
