<?php

/**
 * Created by PhpStorm.
 * User: Hesk
 * Date: 14年4月30日
 * Time: 下午2:47
 */
class video_process
{
    private $_process, $field_name;
    private $job_id;
    private $post_id_queue;
    private $option, $query_setup;
    private $url_youtube_head = "http://gdata.youtube.com/feeds/api/videos/";

    function __construct($option)
    {
        $this->option = $option;
        $this->field_name = $option["video_id_field"];

        //  add_acion("video_process_response_failure", array(&$this, "failure"), 10, 3);
        //  add_acion("video_process_response_success", array(&$this, "success"), 10, 2);
    }

    function init_config($additional = array())
    {

        $this->query_setup = array(
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'post_type' => $this->option["post_types"],
            'ignore_sticky_posts' => true,
            //'meta_key'=> $this->field_name,
            'meta_query' => array(
                array(
                    'key' => $this->field_name,
                    'value' => '',
                    'compare' => 'NOT LIKE'
                )
            )
        );

        $this->query_setup = wp_parse_args($additional, $this->query_setup);
    }

    public function run()
    {
        $fd_query = new WP_Query($this->query_setup);
        while ($fd_query->have_posts()) : $fd_query->the_post();
            $this->scan_post($fd_query->post->ID);
        endwhile;
        wp_reset_postdata(); // reset the query
    }

    function success($reponse, $post_id)
    {

    }


    /**
     * when the youtube or the external platform does not list this video ID anymore, this action will be triggered
     * @param $response
     * @param $post_id
     * @param $code
     */
    private function failure($response, $post_id, $code)
    {
        if (!$this->check_post_status($post_id, 'trash')) {
            if (class_exists('inno_log_db'))
                inno_log_db::log_email_activity('scan video and moved to trash vid:' . $post_id);
            wp_trash_post($post_id);
        }
    }

    /**
     * scan the individual video post clip from youtube
     * @param $post_id
     * @return int
     */
    function scan_post($post_id)
    {
        $field = empty($this->field_name) ? 'video_id_from_platform' : $this->field_name;
        // return $field;
        $video_id = get_post_meta($post_id, $field, true);
        $response = wp_remote_head($this->url_youtube_head . $video_id);
        $code = intval(wp_remote_retrieve_response_code($response));
        switch ($code) {
            case 404:
                do_action("video_process_response_failure", $response, $post_id, 404);
                $this->failure($response, $post_id, 404);
                break;
            case 200:
                do_action("video_process_response_success", $response, $post_id);
                break;
            default:
                do_action("video_process_response_failure", $response, $post_id, $code);
                $this->failure($response, $post_id, 404);
                break;
        }
        return $code;

    }

    /**
     * check the post status for specific ID
     * @param $post_id
     * @param string $status
     * @return bool
     */
    private function check_post_status($post_id, $status = 'publish')
    {
        global $wpdb;
        $pre = $wpdb->prepare("SELECT * FROM " . $wpdb->posts . " WHERE ID=%d AND post_status=%s", $post_id, $status);
        $result = $wpdb->get_row($pre);
        return $result;
    }
} 