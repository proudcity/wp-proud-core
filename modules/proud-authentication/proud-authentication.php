<?php
/*
Plugin Name:        Proud Core
Plugin URI:         http://getproudcity.com
Description:        ProudCity distribution
Version:            1.0.0
Author:             ProudCity
Author URI:         http://getproudcity.com

License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/


namespace Proud\Core\ProudAuthentication;

class ProudAuthentication extends \ProudPlugin {

  function __construct() {

    $this->hook('init',  'checkAuthentication');
  }

  // Do we need to put this behind an HTTP-auth wall, or redirect them to login to WordPress?
  public function checkAuthentication() {

    $authRequired = getenv("AUTH_REQUIRED");
    if ($authRequired === 'wordpress') {

      if (!is_user_logged_in() && !($_GET['auth0'] == 1 && !empty($_GET['code'])) ) {
        global $wp;
        $current_url = home_url(add_query_arg(array(),$wp->request));
        header("Location: https://my.proudcity.com/login?msg=Please%20login%20to%20access%20your%20intranet&destination=" . urlencode($current_url));
        exit;
      }

    } elseif (getenv("AUTH_REQUIRED") && php_sapi_name() !== "cli") {

      $user = getenv("AUTH_USERNAME");
      $pass = getenv("AUTH_PASSWORD");

      if ($user != $_SERVER['PHP_AUTH_USER'] || $pass != $_SERVER['PHP_AUTH_PW']) {
        header('WWW-Authenticate: Basic realm="ProudCity Realm"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'This <a href="https://proudcity.com">ProudCity</a> website is invite only!';
        exit;
      }

    }
  }

}

new ProudAuthentication;
