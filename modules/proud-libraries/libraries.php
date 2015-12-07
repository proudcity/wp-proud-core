<?php

namespace Proud\Core\Libraries;

class ProudLibaries {

  protected static $libraries = [];

  function __construct() {
    $this->initLibraries();
    $this->addCommon();
  }

  /**
   * @TODO register hooks
   */
  protected function initLibraries() {

    $path = plugins_url('includes/',__FILE__);

    self::$libraries['lodash'] = [
      'title' => 'Lodash',
      'website' => 'http://getproudcity.com',
      'js' => [
        'lodash' => $path . 'lodash/lodash.min.js'
      ],
      'deps' => ['underscore'],
      'js_footer' => true
    ];

    self::$libraries['proud-common'] = [
      'title' => 'Proud Common',
      'js' => [
        'waitForImages' => $path . 'waitForImages/dist/jquery.waitforimages.min.js',
        'equalizeHeight' => $path . 'jquery.equalizeHeight/jquery.equalizeHeight.js',
        'proud-common' => $path . 'proud-common.js'
      ],
      'js_footer' => true,
      'deps' => ['jquery','proud']
    ];

    // Angular.js
    self::$libraries['angular'] = [
      'title' => 'Angular',
      'js' => [
        'angular' => $path . 'angular/angular.min.js'
      ],
      'js_footer' => true,
      'deps' => ['lodash']
    ];

    // Angular core has sanitize, resource, bindonce
    self::$libraries['angular-core'] = [
      'title' => 'Angular core scripts',
      'js' => [
        'angular-resource' => $path . 'angular-resource/angular-resource.min.js',
        'angular-sanitize' => $path . 'angular-sanitize/angular-sanitize.min.js',
        'angular-touch' => $path . 'angular-touch/angular-touch.min.js'
      ],
      'js_footer' => true,
      'deps' => ['angular']
    ];

    // Angular core has angular, sanitize, resource, bindonce
    self::$libraries['angular-router-animate'] = [
      'title' => 'Angular router animate scripts',
      'js' => [
        'angular-ui-router' => $path . 'angular-ui-router/release/angular-ui-router.min.js',
        'angular-animate' => $path . 'angular-animate/angular-animate.min.js'
      ],
      'js_footer' => true,
      'deps' => ['angular']
    ];

    self::$libraries['angular-lazy'] = [
      'title' => 'Angular lazy loading scripts',
      'js' => [
        'angular-inview' => $path . 'angular-inview/angular-inview.js',
        'angular-lazycompile' => $path . 'angular-lazycompile/dist/angular-lazycompile.min.js'
      ],
      'js_footer' => true,
      'deps' => ['angular']
    ];
  }

  /**
   *  Registers depenencies with wp_register_script
   */
  public function registerLibaries() {
    foreach (self::$libraries as $bundle => $options) {
      if(!empty($options['js'])) {
        $deps = !empty($options['deps']) ? $options['deps'] : [];
        foreach($options['js'] as $name => $file) {
          wp_register_script($name, $file, $deps, false, $options['js_footer']);
          // Add this script to the dependencies
          $deps[] = $name;
        }
      }
    }
  }

  /**
   *  Sets a bundle to be loaded with ::loadLibraries()
   */
  public function addBundleToLoad($library) {
    self::$libraries[$library]['load'] = true;
  }

  /**
   * Helper function loads common proud scripts().
   */
  public function addCommon() {
    $this->addBundleToLoad('proud-common');
  }

  /**
   * Helper function loads common proud scripts().
   */
  public function addAngular($core, $router_animate, $lazy) {
    // Load angular
    $this->addBundleToLoad('angular');
    // Core?
    if($core) {
      $this->addBundleToLoad('angular-core');
    }
    // Router?
    if($router_animate) {
      $this->addBundleToLoad('angular-router-animate');
    }
    if($lazy) {
      $this->addBundleToLoad('angular-lazy');
    }
  }

  /**
   *  Function called on wp_enqueue_scripts
   */
  public function loadLibraries() {
    $this->registerLibaries();
    foreach (self::$libraries as $bundle => $options) {
      if(!empty($options['load'])) {
        if(!empty($options['js'])) {
          foreach($options['js'] as $name => $file) {
            wp_enqueue_script($name);
          }
          if(!empty($options['dequeue'])) {
            foreach($options['dequeue'] as $name) {
              wp_dequeue_script($name);
            }
          }
        }
        if(!empty($options['css'])) {
          foreach($options['css'] as $file) {
            wp_enqueue_style($file);
          }
        }
      }
    }
  }
}
