<?php

namespace Proud\Core;

class ProudMenuBoldOption extends \ProudPlugin
{

    private static $instance;

    public function __construct()
    {

        parent::__construct(array(
            'textdomain'     => 'proud_menu_bold',
            'plugin_path'    => __FILE__,
        ));
    }

    /**
     * Spins up the instance of the plugin so that we don't get many instances running at once
     *
     * @since 2026.25.1058
     * @author Proudcity, Curtis McHale
     *
     * @uses $instance->init()                      The main get it running function
     */
    public static function instance()
    {

        if (! self::$instance) {
            self::$instance = new ProudMenuBoldOption();
            self::$instance->init();
        }
    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since 1.0
     * @author SFNdesign, Curtis McHale
     */
    public function init()
    {
        add_action('customize_register', array($this, 'register_menu_bold_control'));

        add_action('wp_head', array($this, 'outputBoldMenuCss'));
    } // init


    public function register_menu_bold_control(\WP_Customize_Manager $wp_customize)
    {

        // 1. Add a new section for Menu Appearance (in main left sidebar)
        $wp_customize->add_section(
            'proud_menu_appearance',
            array(
                'title'       => __('Menu Typography', 'proud_menu_bold'),
                'priority'    => 50, // Just below "Static Front Page", before "Nav Menus"
                'panel'       => '', // No panel — sits in main Customizer sidebar
                'description' => __('Customize the visual styling of your site’s navigation menus.', 'proud_menu_bold'),
            )
        );

        // 2. Add the setting
        $wp_customize->add_setting(
            'proud_menu_bold',
            array(
                'default'           => false,
                'type'              => 'option',
            )
        );

        // 3. Add the checkbox control
        $wp_customize->add_control(
            'proud_menu_bold',
            array(
                'label'       => __('Bold Menu Font', 'proud_menu_bold'),
                'description' => __('Make menu item text bold in the main navigation.', 'proud_menu_bold'),
                'section'     => 'proud_menu_appearance',
                'type'        => 'checkbox',
            )
        );
    }

    public function outputBoldMenuCss()
    {

        if (get_option('proud_menu_bold', false)) {
            echo 'boldmenu'; ?>
            <style type="text/css">
                #main-menu li a {
                    font-weight: 800 !important;
                }
            </style>
<?php
        }
    }
}

ProudMenuBoldOption::instance();
