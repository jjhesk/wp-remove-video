<?php

/*  Copyright 2013 Sutherland Boswell  (email : sutherland.boswell@gmail.com)

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

class Video_Scanning_Settings
{

    public $options;

    var $default_options = array(
        'pre_conditionfieldkey' => 'precon',
        'pre_conditionfieldval' => '1',
        'cron_job_interval' => '1d',
        'cron_job_scheduled' => false,
        'video_id_field' => 'video_id_from_platform',
        'post_types' => array('post'),
        'version' => VIDEO_REMOV_VERSION
    );

    function __construct()
    {
        // Activation and deactivation hooks
        register_activation_hook(VIDEO_REMOV_PATH . '/videoremoval.php', array(&$this, 'plugin_activation'));
        register_deactivation_hook(VIDEO_REMOV_PATH . '/videoremoval.php', array(&$this, 'plugin_deactivation'));


        // Set current options
        add_action('plugins_loaded', array(&$this, 'set_options'));
        // Add options page to menu
        add_action('admin_menu', array(&$this, 'admin_menu'));
        // Initialize options
        add_action('admin_init', array(&$this, 'initialize_options'));
        // Custom field detection callback
        //   add_action('wp_ajax_video_thumbnail_custom_field_detection', array(&$this, 'custom_field_detection_callback'));
        // Ajax clear all callback
        //   add_action('wp_ajax_clear_all_video_thumbnails', array(&$this, 'ajax_clear_all_callback'));
        // Ajax test callbacks
        //  add_action('wp_ajax_video_thumbnail_provider_test', array(&$this, 'provider_test_callback')); // Provider test
        //  add_action('wp_ajax_video_thumbnail_image_download_test', array(&$this, 'image_download_test_callback')); // Saving media test
        //  add_action('wp_ajax_video_thumbnail_delete_test_images', array(&$this, 'delete_test_images_callback')); // Delete test images
        //  add_action('wp_ajax_video_thumbnail_markup_detection_test', array(&$this, 'markup_detection_test_callback')); // Markup input test
        // Settings page actions
        if (isset ($_GET['page']) && ($_GET['page'] == 'video_thumbnails')) {
            // Admin scripts
            add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts'));
        }
        // Add "Go Pro" call to action to settings footer
        add_action('videoremove/settings_footer', array('Video_Scanning_Settings', 'settings_footer'));

    }


    function WPUnscheduleEventsByName($strEventName)
    {
        // this function removes registered WP Cron events by a specified event name.
        $arrCronEvents = _get_cron_array();
        foreach ($arrCronEvents as $nTimeStamp => $arrEvent) {
            if (isset($arrCronEvents[$nTimeStamp][$strEventName])) {
                unset($arrCronEvents[$nTimeStamp]);
            }
        }
        _set_cron_array($arrCronEvents);
    }

    // Activation hook
    function plugin_activation()
    {
        add_option(VIDEO_REMOV_SETTING_FIELD, $this->default_options);
    }

    // Deactivation hook
    function plugin_deactivation()
    {
        $this->WPUnscheduleEventsByName(VIDEO_CRON_JOB_TITLE);
        delete_option(VIDEO_REMOV_SETTING_FIELD);
    }

    // Set options & possibly upgrade
    function set_options()
    {
        // Get the current options from the database
        $options = get_option(VIDEO_REMOV_SETTING_FIELD);
        // If there aren't any options, load the defaults
        if (!$options) $options = $this->default_options;
        // Check if our options need upgrading
        $options = $this->upgrade_options($options);
        // Set the options class variable
        $this->options = $options;
    }

    function get_options()
    {
        return $this->options;
    }

    function upgrade_options($options)
    {

        // Boolean for if options need updating
        $options_need_updating = false;

        // If there isn't a settings version we need to check for pre 2.0 settings
        if (!isset($options['version'])) {

        }

        if (version_compare($options['version'], VIDEO_REMOV_VERSION, '<')) {
            $options['version'] = VIDEO_REMOV_VERSION;
            $options_need_updating = true;
        }

        // Save options to database if they've been updated
        if ($options_need_updating) {
            update_option(VIDEO_REMOV_SETTING_FIELD, $options);
        }

        return $options;

    }

    function admin_menu()
    {
        if (!defined('VIDEO_REMOV_SETTING_PAGE_ID'))
            define('VIDEO_REMOV_SETTING_PAGE_ID', "vidrmstgs");

        add_options_page(
            __('Video Removal Options', TEXTDOMAIN_VIDEO_RM),
            __('Video Removal', TEXTDOMAIN_VIDEO_RM),
            'manage_options',
            VIDEO_REMOV_SETTING_PAGE_ID,
            array(&$this, 'options_page')
        );
    }

    function admin_scripts()
    {
        wp_enqueue_script('video_thumbnails_settings', plugins_url('js/settings.js', VIDEO_REMOV_PATH . '/video-thumbnails.php'), array('jquery'), VIDEO_REMOV_VERSION);
        wp_localize_script('video_thumbnails_settings', 'video_thumbnails_settings_language', array(
            'detection_failed' => __('We were unable to find a video in the custom fields of your most recently updated post.', TEXTDOMAIN_VIDEO_RM),
            'working' => __('Working...', TEXTDOMAIN_VIDEO_RM),
            'clear_all_confirmation' => __('Are you sure you want to clear all video thumbnails? This cannot be undone.', TEXTDOMAIN_VIDEO_RM),
        ));
    }

    function custom_field_detection_callback()
    {
        if (current_user_can('manage_options')) {
            echo $this->detect_custom_field();
        }
        die();
    }

    function detect_custom_field()
    {
        global $video_listing_detection_n_removal;
        $latest_post = get_posts(array(
            'posts_per_page' => 1,
            'post_type' => $this->options['post_types'],
            'orderby' => 'modified',
        ));
        $latest_post = $latest_post[0];
        $custom = get_post_meta($latest_post->ID);

    }

    function ajax_clear_all_callback()
    {
        die();
    }

    function get_file_hash($url)
    {
        $response = wp_remote_get($url, array('sslverify' => false));
        if (is_wp_error($response)) {
            $result = false;
        } else {
            $result = md5($response['body']);
        }
        return $result;
    }

    function provider_test_callback()
    {

    } // End provider test callback

    function image_download_test_callback()
    {
        die();
    } // End saving media test callback

    function delete_test_images_callback()
    {
        die();
    } // End delete test images callback

    function markup_detection_test_callback()
    {
        die();
    } // End markup detection test callback

    function initialize_options()
    {
        $constructSetting = new class_setting_constructor(array(
            'page_id' => VIDEO_REMOV_SETTING_PAGE_ID,
            'field_name' => VIDEO_REMOV_SETTING_FIELD,
            'section_id' => 'vidrms1',
            'title' => __("General Settings", TEXTDOMAIN_VIDEO_RM),
            'field_options_data' => $this->options,
            'setting_group_id' => VIDEO_RM_SETTING_GROUP
        ));
        $constructSetting->start();
        $constructSetting->add_cron_job_status_box(VIDEO_CRON_JOB_TITLE);
        $constructSetting->add_checkbox_setting(
            'cron_job_scheduled',
            __('Cron Job has Scheduled', TEXTDOMAIN_VIDEO_RM),
            __('Checking this option will activate the cron job to be schedule on the defined time interval', TEXTDOMAIN_VIDEO_RM)
        );

        $constructSetting->add_selection_setting(
            'cron_job_interval',
            __('Cron Job Interval', TEXTDOMAIN_VIDEO_RM),
            __('Select the time interval that representing the action trigger between each time frame.', TEXTDOMAIN_VIDEO_RM)
            , array(
                'hourly' => 'every single hour',
                'twicedaily' => 'two times a day',
                'daily' => 'one time a day',
            ));

        // Get post types
        $post_types = get_post_types(null, 'names');
        // Remove certain post types from array
        $post_types = array_diff($post_types, array('attachment', 'revision', 'nav_menu_item'));
        $constructSetting->add_multicheckbox_setting(
            'post_types',
            __('Post Types', TEXTDOMAIN_VIDEO_RM),
            $post_types
        );
        $constructSetting->add_text_setting(
            'video_id_field',
            __('Custom Field(optional)', TEXTDOMAIN_VIDEO_RM),
            __('Enter the name of the custom field where your embed code or video URL is stored.', TEXTDOMAIN_VIDEO_RM)
        );
        $constructSetting->add_text_setting(
            'pre_conditionfieldkey',
            __('PreCon Custom Field Name (optional)', TEXTDOMAIN_VIDEO_RM),
            __('Enter the name of screening field name.', TEXTDOMAIN_VIDEO_RM)
        );
        $constructSetting->add_text_setting(
            'pre_conditionfieldval',
            __('PreCon Custom Field Value(optional)', TEXTDOMAIN_VIDEO_RM),
            __('Enter the value of expected screening value for that field name set as above.', TEXTDOMAIN_VIDEO_RM)
        );

        do_action('video_rm_cron_switcher');
    }


    function options_page()
    {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', TEXTDOMAIN_VIDEO_RM));
        }
        ?>
        <div class="wrap">
        <div id="icon-options-general" class="icon32"></div>
        <h2><?php _e('Video Rescanning Options', TEXTDOMAIN_VIDEO_RM); ?></h2>
        <?php $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general_settings'; ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=<?php echo VIDEO_REMOV_SETTING_PAGE_ID; ?>&tab=general_settings"
               class="nav-tab <?php echo $active_tab == 'general_settings' ? 'nav-tab-active' : ''; ?>">
                <?php _e('General', TEXTDOMAIN_VIDEO_RM); ?>
            </a>
        </h2>
        <?php
        // Main settings
        if ($active_tab == 'general_settings') {
            ?><h3><?php _e('Getting started', TEXTDOMAIN_VIDEO_RM); ?></h3>
            <p><?php _e('This is the panel to get the control of the rescanning feature. Check with each options to get the detail control of how the system works..', TEXTDOMAIN_VIDEO_RM); ?></p>
            <form method="post" action="options.php">
            <?php settings_fields(VIDEO_RM_SETTING_GROUP); ?>
            <?php do_settings_sections(VIDEO_REMOV_SETTING_PAGE_ID); ?>
            <?php submit_button(); ?>
            </form><?php

        }
        do_action('videoremove/settings_footer'); ?>
        </div><?php
    }

    public static function settings_footer()
    {
        ?>
        <div class="update-nag warning">
            <div>
                <p><?php _e('hesk development 2014.', TEXTDOMAIN_VIDEO_RM); ?></p>
            </div>
        </div>
    <?php
    }

}

?>