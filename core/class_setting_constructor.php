<?php

/**
 * Created by PhpStorm.
 * User: Hesk
 * Date: 14年5月9日
 * Time: 下午2:45
 */
class class_setting_constructor
{
    private $section_id;
    private $page_id;
    private $group_id;
    private $option_field_name;
    private $setting_group_title;
    private $options;

    /**
     * @param $args
     */
    function __construct($args)
    {
        $defaults = array(
            'field_options_data' => array()
        );
        $args = wp_parse_args($args, $defaults);
        extract($args);
        $this->page_id = $page_id;
        $this->option_field_name = $field_name;
        $this->section_id = $section_id;
        $this->group_id = $setting_group_id;
        $this->setting_group_title = $title;
        $this->options = $field_options_data;

    }

    public function start()
    {

        register_setting($this->group_id, $this->option_field_name,
            array(&$this, 'sanitize_callback'));

        add_settings_section($this->section_id, $this->setting_group_title, array(&$this, 'general_settings_callback'),
            $this->page_id);
    }

    /**
     * Default: array()
     * @resource http://codex.wordpress.org/Function_Reference/add_settings_field
     * @param $slug
     * @param $name
     * @param $options
     * @return $this
     */
    public function add_multicheckbox_setting($slug, $name, $options)
    {
        add_settings_field($slug, $name, array(&$this, 'multicheckbox_callback'),
            $this->page_id,
            $this->section_id,
            array(
                'slug' => $slug,
                'options' => $options
            )
        );

    }

    public function add_cron_job_status_box($cron_event_name)
    {
        $next_scheduled = !wp_next_scheduled($cron_event_name) ?
            __("This cron job is inactive.", TEXTDOMAIN_VIDEO_RM) :
            wp_next_scheduled($cron_event_name);

        add_settings_field($cron_event_name . "_slug", __("Cron Job Status", TEXTDOMAIN_VIDEO_RM),
            array(&$this, 'status_box_cb'),
            $this->page_id,
            $this->section_id,
            array(
                'cron_name' => $cron_event_name,
                'slug' => $cron_event_name . "_slug",
                'detail' => $next_scheduled,
                'description' => __("The title of the cron job event and its scheduled time. ", TEXTDOMAIN_VIDEO_RM)
            )
        );
    }


    public function add_selection_setting($slug, $name, $description, $options)
    {
        add_settings_field($slug, $name, array(&$this, 'selection_callback'),
            $this->page_id,
            $this->section_id,
            array(
                'slug' => $slug,
                'options' => $options,
                'description' => $description
            )
        );
        //  echo $this->page_id;
        //  return $this;
    }

    public function add_checkbox_setting($slug, $name, $description)
    {
        add_settings_field($slug, $name, array(&$this, 'checkbox_callback'),
            $this->page_id,
            $this->section_id,
            array(
                'slug' => $slug,
                'description' => $description
            )
        );
        // echo $this->page_id;
        //   return $this;
    }

    public function add_drop_down_select($slug, $name, $options)
    {
        add_settings_field($slug, $name, array(&$this, 'selectdropdown'),
            $this->page_id,
            $this->section_id,
            array(
                'slug' => $slug,
                'options' => $options
            )
        );
        //  echo $this->page_id;
        // return $this;
    }

    public function add_text_setting($slug, $name, $description)
    {
        add_settings_field($slug, $name, array(&$this, 'text_field_callback'),
            $this->page_id,
            $this->section_id,
            array(
                'slug' => $slug,
                'description' => $description
            )
        );
        //   echo $this->page_id;
        //   echo "| " . VIDEO_REMOV_SETTING_PAGE_ID;
        //// return $this;
    }


    public function selectdropdown($args)
    {
        if (is_array($this->options[$args['slug']])) {
            $selected_types = $this->options[$args['slug']];
        } else {
            $selected_types = $this->options[$args['slug']];
        }
        $html = '';
        foreach ($args['options'] as $option) {
            $checked = (in_array($option, $selected_types) ? 'checked="checked"' : '');
            $html .= '<label for="' . $args['slug'] . '_' . $option . '"><input type="checkbox" id="' . $args['slug'] . '_' . $option . '" name="' . $this->option_field_name . '[' . $args['slug'] . '][]" value="' . $option . '" ' . $checked . '/> ' . $option . '</label><br>';
        }
        echo $html;
    }

    public function status_box_cb($args)
    {
        $ui = '<code class="code">' . $args['cron_name'] . '</code>';
        //   $ui .= '<select name="' . $this->option_field_name . '[' . $args['slug'] . ']' . '">';
        $ui .= '&nbsp;<p><span class="detail highlight">' . $args['detail'] . "</span>";
        $ui .= $args['description'] . '</p>';
        echo $ui;
    }
    public function checkbox_callback($args)
    {
        $html = '<label for="' . $args['slug'] . '"><input type="checkbox" id="' . $args['slug'] . '" name="' . $this->option_field_name . '[' . $args['slug'] . ']" value="1" ' . checked(1, $this->options[$args['slug']], false) . '/> ' . $args['description'] . '</label>';
        echo $html;
    }


    public function multicheckbox_callback($args)
    {
        if (is_array($this->options[$args['slug']])) {
            $selected_types = $this->options[$args['slug']];
        } else {
            $selected_types = array();
        }
        $html = '';
        foreach ($args['options'] as $option) {
            $checked = (in_array($option, $selected_types) ? 'checked="checked"' : '');
            $html .= '<label for="' . $args['slug'] . '_' . $option . '"><input type="checkbox" id="' . $args['slug'] . '_' . $option . '" name="' . $this->option_field_name . '[' . $args['slug'] . '][]" value="' . $option . '" ' . $checked . '/> ' . $option . '</label><br>';
        }


        echo $html;
    }

    public function selection_callback($args)
    {

        $selected_val = $this->options[$args['slug']];

        $ui = '<label for="' . $args['slug'] . '">';
        $ui .= '<select name="' . $this->option_field_name . '[' . $args['slug'] . ']' . '">';
        $ui .= '<option value=""> empty selection </option>';
        foreach ($args['options'] as $option_val => $option_label) {
            $selected = selected($selected_val, $option_val, false);
            $ui .= '<option value="' . $option_val . '" ' . $selected . '>' . $option_label . '</option>';
        }
        $ui .= '</select>';
        $ui .= '<p>' . $args['description'] . '</p>' . print_r($this->options, true);
        echo $ui;
    }

    public function text_field_callback($args)
    {
        $html = '<input type="text" id="' . $args['slug'] . '" name="' . $this->option_field_name . '[' . $args['slug'] . ']" value="' . $this->options[$args['slug']] . '"/>';
        $html .= '<label for="' . $args['slug'] . '"> ' . $args['description'] . '</label>';
        echo $html;
    }

    public function general_settings_callback()
    {
        echo '<p>' . __('These options configure where the plugin will search for videos and what to do with thumbnails once found.', TEXTDOMAIN_VIDEO_RM) . '</p>';
    }


    public function sanitize_callback($input)
    {
        $current_settings = get_option($this->option_field_name);
        $output = array();

        foreach ($current_settings as $key => $value) {

            if ($key == 'cron_job_interval' && isset($value)) {
                $output[$key] = '1d';
            }
            if ($key == 'version' OR $key == 'providers') {
                $output[$key] = $current_settings[$key];
            } elseif (isset($input[$key])) {
                $output[$key] = $input[$key];
            } else {
                $output[$key] = '';
            }
        }

        return $output;
    }

} 