<?php
/**
 * @author ProudCity
 */

if ( ! class_exists( 'ProudPlugin' ) ) {
  
  class ProudPlugin {

    /////////////////////////////////////////////////////////////////////////////
    // PROPERTIES, PROTECTED
    /////////////////////////////////////////////////////////////////////////////

    /**
     * The plugins' text domain.
     *
     * @author Konstantin Obenland
     * @since  1.1 - 03.04.2011
     * @access protected
     *
     * @var    string
     */
    protected $textdomain;


    /**
     * The name of the calling plugin.
     *
     * @author Konstantin Obenland
     * @since  1.0 - 23.03.2011
     * @access protected
     *
     * @var    string
     */
    protected $plugin_name;

    /**
     * The path to the plugin file.
     *
     * /path/to/wp-content/plugins/{plugin-name}/{plugin-name}.php
     *
     * @author Konstantin Obenland
     * @since  2.0.0 - 30.05.2012
     * @access protected
     *
     * @var    string
     */
    protected $plugin_path;


    /**
     * The path to the plugin directory.
     *
     * /path/to/wp-content/plugins/{plugin-name}/
     *
     * @author Konstantin Obenland
     * @since  1.2 - 21.04.2011
     * @access protected
     *
     * @var    string
     */
    protected $plugin_dir_path;


    ///////////////////////////////////////////////////////////////////////////
    // METHODS, PUBLIC
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Constructor
     *
     * @author Konstantin Obenland
     * @since  1.0 - 23.03.2011
     * @access public
     *
     * @param  string $plugin_name
     * @param  string $donate_link_id
     *
     * @return Obenland_Wp_Plugins
     */
    public function __construct( $args = array() ) {

      // Set class properties
      $this->textdomain      = $args['textdomain'];
      $this->plugin_path     = $args['plugin_path'];
      $this->plugin_dir_path = plugin_dir_path( $args['plugin_path'] );
      $this->plugin_name     = plugin_basename( $args['plugin_path'] );

      load_plugin_textdomain( 'wp-proud', false, $this->textdomain . '/lang' );
    }

    /**
     * Sanitizes method names.
     *
     * @author Mark Jaquith
     * @see    http://sliwww.slideshare.net/markjaquith/creating-and-maintaining-wordpress-plugins
     * @since  1.5 - 12.02.2012
     * @access private
     *
     * @param  string $method Method name to be sanitized.
     *
     * @return string Sanitized method name
     */
    private function sanitize_method( $method ) {
      return str_replace( array( '.', '-' ), '_', $method );
    }


    /**
     * Hooks methods to their WordPress Actions and Filters.
     *
     * @example:
     * $this->hook( 'the_title' );
     * $this->hook( 'init', 5 );
     * $this->hook( 'omg', 'is_really_tedious', 3 );
     *
     * @author Mark Jaquith
     * @see    http://sliwww.slideshare.net/markjaquith/creating-and-maintaining-wordpress-plugins
     * @since  1.5 - 12.02.2012
     * @access protected
     *
     * @param  string $hook Action or Filter Hook name.
     *
     * @return boolean true
     */
    protected function hook( $hook ) {
      $priority = 10;
      $method   = $this->sanitize_method( $hook );
      $args     = func_get_args();
      unset( $args[0] ); // Filter name
      foreach ( (array) $args as $arg ) {
        if ( is_int( $arg ) )
          $priority = $arg;
        else
          $method   = $arg;
      }
      return add_action( $hook, array( $this, $method ), $priority , 999 );
    }
  }
}