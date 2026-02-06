<?php

/**
 * Provides the displayable shortcodes for Policies
 *
 * @package WP_Proud_Core\ProudEventsFeed
 * @author  Curtis McHale, <curtis@proudcity.com>
 * @license https://opensource.org/licenses/gpl-license.php GNU Public License
 * @see     https://proudcity.com
 */
class ProudEventsFeed
{

    private static $_instance;

    /**
     * Spins up the instance of the plugin so that we don't get many instances running at once
     *
     * @since  1.0
     * @author Proudcity, Curtis McHale
     *
     * @uses $instance->init()                      The main get it running function
     *
     * @return null
     */
    public static function instance()
    {

        if (! self::$_instance) {
            self::$_instance = new ProudEventsFeed();
            self::$_instance->init();
        }
    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since  1.0
     * @author Proudcity, Curtis McHale
     *
     * @return null
     */
    public function init()
    {
        add_action('init', array($this, 'addEventsFeed'));
    } // init

    /**
     * Renders the events feed
     *
     * @since 2026.02.05
     * @author Curtis <curtis@proudcity.com>
     */
    public function addEventsFeed()
    {
        add_feed('eventsfeed', array($this, 'pcRenderEventsFeed'));
    }

    public function pcRenderEventsFeed()
    {

        // telling cache this may varry by query strings
        nocache_headers();

        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);

?>
        <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
            <channel>
                <title><?php echo esc_html(em_get_option('dbem_rss_main_title')); ?></title>
                <link><?php echo EM_URI; ?></link>
                <description><?php echo esc_html(em_get_option('dbem_rss_main_description')); ?></description>
                <docs>http://blogs.law.harvard.edu/tech/rss</docs>
                <pubDate><?php echo date('D, d M Y H:i:s +0000', em_get_option('em_last_modified')); ?></pubDate>
                <atom:link href="<?php echo esc_url(get_feed_link('eventsfeed')); ?>" rel="self" type="application/rss+xml" />

                <?php
                $description_format = str_replace(">", "&gt;", str_replace("<", "&lt;", em_get_option('dbem_rss_description_format')));
                $rss_limit = em_get_option('dbem_rss_limit');
                $page_limit = $rss_limit > 50 || !$rss_limit ? 50 : $rss_limit; //set a limit of 50 to output at a time, unless overall limit is lower		
                $args = !empty($args) ? $args : array(); /* @var $args array */
                $args = array_merge(array('scope' => em_get_option('dbem_rss_scope'), 'owner' => false, 'limit' => $page_limit, 'page' => 1, 'order' => em_get_option('dbem_rss_order'), 'orderby' => em_get_option('dbem_rss_orderby')), $args);
                $args = apply_filters('em_rss_template_args', $args);
                $EM_Events = EM_Events::get($args);
                $count = 0;
                while (count($EM_Events) > 0) {
                    foreach ($EM_Events as $EM_Event) {
                        /* @var $EM_Event EM_Event */
                        $description = $EM_Event->output($EM_Event->get_option('dbem_rss_description_format'), "rss");
                        $description = ent2ncr(convert_chars($description)); //Some RSS filtering
                        $event_url = $EM_Event->output('#_EVENTURL');
                ?>
                        <item>
                            <title><?php echo $EM_Event->output($EM_Event->get_option('dbem_rss_title_format'), "rss"); ?></title>
                            <link><?php echo $event_url; ?></link>
                            <guid><?php echo $event_url; ?></guid>
                            <pubDate><?php echo $EM_Event->start(true)->format('D, d M Y H:i:s +0000'); ?></pubDate>
                            <description>
                                <![CDATA[<?php echo $description; ?>]]>
                            </description>
                        </item>
                <?php
                        $count++;
                    }
                    if ($rss_limit != 0 && $count >= $rss_limit) {
                        //we've reached our limit, or showing one event only
                        break;
                    } else {
                        //get next page of results
                        $args['page']++;
                        $EM_Events = EM_Events::get($args);
                    }
                }
                ?>

            </channel>
        </rss>
<?php
        exit; // need to close cleanly for readers
    }
}

ProudEventsFeed::instance();
