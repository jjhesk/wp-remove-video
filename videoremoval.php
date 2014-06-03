<?php
/*
Plugin Name: Video Removals
Plugin URI: http://hkmlab.wordpress.com/
Description: This module will automatically detect the videos ID on youtube and determine if the video are listed or no longer listed. Further action will be included for the removal of the listing of video item on the post listing.
Author: Hesk Kam
Author URI: http://hkmlab.wordpress.com/
Version: 0.1.4
License: GPL2
Text Domain: video-removals
Domain Path: /languages/

------------------------------------------------------------------------
  Copyright 2014 Heskeyo Kam  (email : developmenthesk@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Define

define('VIDEO_REMOV_PATH', dirname(__FILE__));
define('VIDEO_ID_FIELD', '_video_thumbnail');
define('VIDEO_REMOV_VERSION', '0.1.4');
define('VIDEO_REMOV_SETTING_FIELD', "video_scanning_removal");
define('VIDEO_CRON_JOB_TITLE', "scan_n_remove_video_job");
define('VIDEO_REMOV_SETTING_PAGE_ID', "vidrmstgs");
define('VIDEO_RM_SETTING_GROUP', 'vidrmstgg');
define('TEXTDOMAIN_VIDEO_RM', 'video-thumbnails');
// Providers
//require_once(VIDEO_REMOV_PATH . '/core/providers/class-video-thumbnails-providers.php');
// Extensions
//require_once(VIDEO_REMOV_PATH . '/core/extensions/extensions.php');
// Settings
require_once(VIDEO_REMOV_PATH . '/core/class_setting_constructor.php');
require_once(VIDEO_REMOV_PATH . '/core/class-video-removal-settings.php');
require_once(VIDEO_REMOV_PATH . '/core/video_process.php');
//require_once(VIDEO_REMOV_PATH . '/core/MySettingsPage.php');

// Class

class Video_Scan_n_Remove
{

    var $providers = array();
    var $settings;

    function __construct()
    {

        // Load translations
        add_action('plugins_loaded', array(&$this, 'plugin_textdomain'));

        // Create provider array
        $this->providers = apply_filters('video_thumbnail_providers', $this->providers);

        // Settings
        $this->settings = new Video_Scanning_Settings();

        // Initialize meta box

        // Add actions to save video thumbnails when saving
        add_action('save_post', array(&$this, 'save_video_thumbnail'), 100, 1);

        // Add actions to save video thumbnails when posting from XML-RPC (this action passes the post ID as an argument so 'get_video_thumbnail' is used instead)
        add_action('xmlrpc_publish_post', 'get_video_thumbnail', 10, 1);

        // Add action for Ajax reset script on edit pages
        if (in_array(basename($_SERVER['PHP_SELF']), apply_filters('video_thumbnails_editor_pages', array('post-new.php', 'page-new.php', 'post.php', 'page.php')))) {
            add_action('admin_head', array(&$this, 'ajax_reset_script'));
        }

        // Add action for Ajax reset callback
        add_action('wp_ajax_reset_video_thumbnail', array(&$this, 'ajax_reset_callback'));

        // Add admin menus
        add_action('admin_menu', array(&$this, 'admin_menu'));

        // Add JavaScript and CSS to admin pages
        add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts'), 20, 1);
        // Get the posts to be scanned in bulk
        add_action('wp_ajax_video_thumbnails_bulk_posts_query', array(&$this, 'bulk_posts_query_callback'));
        // Get the thumbnail for an individual post
        //  add_action('wp_ajax_video_thumbnails_get_thumbnail_for_post', array(&$this, 'get_thumbnail_for_post_callback'));
        add_action('wp_ajax_get_scan_post_with_id', array(&$this, 'scan_post_thumb'));
        add_action('video_rm_cron_switcher', array(&$this, 'cron_switcher'));

        add_action(VIDEO_CRON_JOB_TITLE, array(&$this, 'start_job'));

        add_filter('video_rm/additional_query', array(&$this, 'additional_query_conditions'), 10, 2);

    }

    public function additional_query_conditions($args, $opts)
    {

        if (!empty($opts['video_id_field']) && !empty($opts['pre_conditionfieldkey']) && !empty($opts['pre_conditionfieldval']))
            $args['meta_query'] = array(
              /*  array(
                    'key' => $opts['video_id_field'],
                    'value' => '',
                    'compare' => 'NOT LIKE'
                ),*/
                array(
                    'key' => $opts['pre_conditionfieldkey'],
                    'value' => $opts['pre_conditionfieldval'],
                    'compare' => 'LIKE'
                )
            );

        return $args;

    }

    public function cron_switcher()
    {
        $options = get_option(VIDEO_REMOV_SETTING_FIELD);
        $next_scheduled = wp_next_scheduled(VIDEO_CRON_JOB_TITLE);
        if ($options['cron_job_scheduled']) {
            /**
             * hourly
             * twicedaily
             * daily
             */
            if (!$next_scheduled) {
                //   wp_clear_scheduled_hook(VIDEO_CRON_JOB_TITLE);
                //   date_default_timezone_set('Europe/London');
                //   $date = new DateTime('09/20/2012 02:00');
                $date = new DateTime();
                $date->modify('+3 hour');
                // echo date_format($date, 'Y-m-d h:i');
                $timeset = $date->getTimestamp();
                wp_schedule_event($timeset, $options['cron_job_interval'], VIDEO_CRON_JOB_TITLE);
            } else {
                //$next_scheduled
            }
        } else {
            if ($next_scheduled) {
                wp_clear_scheduled_hook(VIDEO_CRON_JOB_TITLE);
            }
        }
    }

    public function start_job()
    {
        $options = get_option(VIDEO_REMOV_SETTING_FIELD);
        $actived_job = $options['cron_job_scheduled'];
        if ($actived_job) {
            new video_process($options);
        }
    }

    /**
     * Load language files
     */
    function plugin_textdomain()
    {
        load_plugin_textdomain('video-thumbnails', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Adds the admin menu items
     */
    function admin_menu()
    {
        $title = __('Auto Video Delist', 'video-thumbnails');
        $menu = __('Auto Video Delist', 'video-thumbnails');
        add_management_page($title, $menu, 'manage_options', 'video-remove-auto', array(&$this, 'bulk_scanning_page'));
    }

    function admin_scripts($hook)
    {
        // Bulk tool page
        if ('tools_page_video-remove-auto' == $hook) {
            wp_enqueue_script('vide-rm', plugins_url('/js/scancycle.js', __FILE__), array('jquery'), VIDEO_REMOV_VERSION);
            wp_localize_script('vide-rm', 'video_thumbnails_bulk_language', array(
                'working' => __('Working...', 'video-thumbnails'),
                'started' => __('Started Scanning', 'video-thumbnails'),
                'resumed' => __('Resumed Scanning', 'video-thumbnails'),
                'paused' => __('Paused Scanning', 'video-thumbnails'),
                'done' => __('Done!', 'video-thumbnails'),
                'final_count_singular' => __('Scanned 1 post', 'video-thumbnails'),
                'final_count_plural' => __('Scanned %d posts', 'video-thumbnails'),
                'queue_singular' => __('1 post in queue', 'video-thumbnails'),
                'queue_plural' => __('%d posts in queue', 'video-thumbnails'),
                'scanning_of' => __('Scanning %1$s of %2$s', 'video-thumbnails'),
                'no_thumbnail' => __('No thumbnail', 'video-thumbnails'),
                'found_invalid' => __('Invalid video found', 'video-thumbnails'),
                'found_valid' => __('Valid video found', 'video-thumbnails'),
                'unlisted' => __('Unlisted post', 'video-thumbnails'),
                'new_thumbnail' => __('New thumbnail:', 'video-thumbnails'),
                'existing_thumbnail' => __('Existing thumbnail:', 'video-thumbnails'),
                'error' => __('Error:', 'video-thumbnails'),
            ));
            wp_enqueue_style('vide-rm-css', plugins_url('/css/bulk.css', __FILE__), false, VIDEO_REMOV_VERSION);
        }
    }

    /**
     * A usort() callback that sorts videos by offset
     */
    function compare_by_offset($a, $b)
    {
        return $a['offset'] - $b['offset'];
    }

    /**
     * Find all the videos in a post
     * @param  string $markup Markup to scan for videos
     * @return array          An array of video information
     */
    function find_videos($markup)
    {
        $videos = array();
        // Filter to modify providers immediately before scanning
        $providers = apply_filters('video_thumbnail_providers_pre_scan', $this->providers);
        foreach ($providers as $key => $provider) {
            $provider_videos = array();
            if (empty($provider_videos)) continue;
            foreach ($provider_videos as $video) {
                $videos[] = array(
                    'id' => $video[0],
                    'provider' => $key,
                    'offset' => $video[1]
                );
            }
        }
        usort($videos, array(&$this, 'compare_by_offset'));
        return $videos;
    }


    // Post editor Ajax reset script
    function ajax_reset_script()
    {
        echo '<!-- Video Thumbnails Ajax Search -->' . PHP_EOL;
        echo '<script type="text/javascript">' . PHP_EOL;
        echo 'function video_thumbnails_reset(id) {' . PHP_EOL;
        echo '  var data = {' . PHP_EOL;
        echo '    action: "reset_video_thumbnail",' . PHP_EOL;
        echo '    post_id: id' . PHP_EOL;
        echo '  };' . PHP_EOL;
        echo '  document.getElementById(\'video-thumbnails-preview\').innerHTML=\'' . __('Working...', 'video-thumbnails') . ' <img src="' . home_url('wp-admin/images/loading.gif') . '"/>\';' . PHP_EOL;
        echo '  jQuery.post(ajaxurl, data, function(response){' . PHP_EOL;
        echo '    document.getElementById(\'video-thumbnails-preview\').innerHTML=response;' . PHP_EOL;
        echo '  });' . PHP_EOL;
        echo '};' . PHP_EOL;
        echo '</script>' . PHP_EOL;
    }

    // Ajax reset callback
    function ajax_reset_callback()
    {
        global $wpdb; // this is how you get access to the database
        $post_id = $_POST['post_id'];

        die();
    }

    /**
     * wp_ajax_video_thumbnails_bulk_posts_query
     */
    function bulk_posts_query_callback()
    {
        // Some default args
        $args = array(
            'posts_per_page' => -1,
            'post_type' => $this->settings->options['post_types'],
            'fields' => 'ids',
            'status' => 'publish'
        );


        // Setup an array for any form data and parse the jQuery serialized data
       // $form_data = array();
        // parse_str($_POST['params'], $form_data);
        $args = apply_filters('video_rm/additional_query', $args, $this->settings->options);
        $query = new WP_Query($args);
        $this->ajax_json_output($query);
    }

    /**
     * wp_ajax_get_scan_post_with_id
     * get_scan_post_with_id
     */
    function scan_post_thumb()
    {
        $post_id = $_POST['post_id'];
        $vid = new video_process($this->settings->get_options());
        $code = $vid->scan_post($post_id);
        if ($code == 200) {
            $result = array("result" => "success", "status" => "okay", "timestamp" => time(), "code" => $code, 'post_id' => $post_id);
        } else {
            $result = array("result" => "failure", "status" => "okay", "timestamp" => time(), "code" => $code, 'post_id' => $post_id);
        }
        $this->ajax_json_output($result);
    }

    /**
     * @param $mix
     */
    function ajax_json_output($mix)
    {
        header('Content-Type: application/json');
        echo json_encode($mix);
        die();
    }

    function bulk_scanning_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'video-thumbnails'));
        }
        ?>
        <div class="wrap">
            <div id="icon-tools" class="icon32"></div>
            <h2><?php _e('Automatic remove video listing', 'video-thumbnails'); ?></h2>

            <p><?php _e('Use this tool to scan all of your posts for Video ID such as Youtube find if that video is still available on their listing and do the necessary actions followed by the time trigger.', 'video-thumbnails'); ?></p>

            <form id="video-scan-remove-options">
                <table class="form-table">
                    <tbody><?php //do_action('video_thumbnails/bulk_options_form'); ?>
                    <tr valign="top">
                        <th scope="row"><span id="queue-count">...</span></th>
                        <td><input type="submit" value="<?php esc_attr_e('Scan Now', 'video-thumbnails'); ?>"
                                   class="button button-primary"></td>
                    </tr>
                    </tbody>
                </table>
            </form>
            <div id="vt-bulk-scan-results">
                <div class="progress-bar-container">
                    <span class="percentage">0%</span>

                    <div class="progress-bar">&nbsp;</div>
                </div>
                <table class="stats">
                    <thead>
                    <tr>
                        <th><?php _e('Scanned', 'video-thumbnails'); ?></th>
                        <th><?php _e('Invalid Video', 'video-thumbnails'); ?></th>
                        <th><?php _e('Normal Video', 'video-thumbnails'); ?></th>
                    </tr>
                    </thead>
                    <tr>
                        <td class="scanned">0</td>
                        <td class="found-new">0</td>
                        <td class="found-existing">0</td>
                    </tr>
                </table>
                <ul class="log"></ul>
            </div>
        </div>
    <?php
    }
}

$video_listing_detection_n_removal = new Video_Scan_n_Remove();
do_action('video_scan_n_remove_loaded');
// End class
//if (is_admin())
// $my_settings_page = new MySettingsPage();
?>