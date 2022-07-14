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

    add_action( 'init', array( $this, 'login_redirect' ) );
    //apply_filters( 'login_url', array( $this, 'pc_dashboard_login_url' ), 10, 3 );

  }

  /**
   * Filters any calls to wp_login_url() so they go to our dashboard
   *
   * @since 2022.05.05
   * @author Curtis McHale
   * @access public
   *
   * @todo could improve this by adding $redirect value that is parsed by the dashboard so the user goes back to where they were
   *
   * @param   $login_url      string        required              Existing login_url
   * @param   $redirect       string        optional              Path to redirect to after successful login
   * @param   $force_reauth   bool          optional              TRUE to force reauthorization even if login cookie is present
   */
  public static function pc_dashboard_login_url( $login_url, $redirect, $force_reauth ){
    return 'my.proudcity.com';
  }

  /**
   * Takes requests for wp-login.php and sends users to the PC Dashboard and my.proudcity.com
   *
   * Sends any type of request to wp-login.php to PC Dashboard. This includes any password reset
   * any logout...it's all going to PC Dashboard.
   *
   * @since 2022.05.05
   * @author Curtis McHale
   */
  public static function login_redirect(){

    global $pagenow;

    $excluded = array( 'https://www.colma.ca.gov' );

    /**
     * $_GET['connection_id'] corresponds to Auth0 so that we don't have fatal site errors
     */
    if ( ! isset( $_GET['connection_id'] ) && 'production' === wp_get_environment_type() && 'wp-login.php' === $pagenow && ! in_array( site_url(), $excluded ) ){
      wp_redirect( 'https://my.proudcity.com' );
      exit;
    } // if wp-login.php

  } // login_redirect

  // Do we need to put this behind an HTTP-auth wall, or redirect them to login to WordPress?
  public function checkAuthentication() {

    if (php_sapi_name() === "cli") {
      return;
    }

    $authRequired = getenv("AUTH_REQUIRED");
    if ($authRequired === 'wordpress') {

      global $wp;
      $current_url = home_url(add_query_arg(array(),$wp->request));

      if ( !is_user_logged_in() && empty($_GET['auth0']) && strpos($_SERVER['REQUEST_URI'], 'wp-login') === false && strpos($_SERVER['REQUEST_URI'], 'wp-admin') === false ) {
        header("Location: https://my.proudcity.com/login?msg=Please%20login%20to%20access%20your%20intranet"); //&destination=" . urlencode($current_url));
        exit;
      }

    } elseif ($authRequired) {

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
