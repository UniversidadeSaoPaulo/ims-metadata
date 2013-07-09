<?php
/*
Plugin Name: IMS Metadata
Plugin URI: https://github.com/atp/ims-metadata
Version: 0.8
Description: Add IMS-metadata into post and media resource, this plugin is used to convert WP into a Learning Object Repository. The OAI-PMH specification is used to harvest the metadata through URI: HOME_URL/wp-content/plugins/ims-metadata/oai2.php?verb=Identify.
Author: geiser
Author URI: https://github.com/atp
*/

/*
GNU General Public License version 3

Copyright (c) 2011-2013 Marcel Bokhorst

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once('config.php');

class IMSMetadata {
    
    private $tab = 'post';
    
    public static $info = array('shortname'=>'imsmd', 'page'=>'ims-metadata_');
    public static $post_types = array('post'=>'Post', 'attachment'=>'Attachment');
    
    public static function install() {
    }
    
    public static function uninstall() {
        global $IMSMD_CONFIG;
        foreach ($IMSMD_CONFIG as $meta_key=>$meta_config) {
            unregister_setting('imsmd_options', $meta_key);
        }
    }
    
    public static function init() {
        // set filters and actions for post and media
        $options = get_option('imsmd_enable', array());
        if (isset($options['attachment']['select']) && trim($options['attachment']['select']) == 'enable') {
            add_filter('attachment_fields_to_edit', array('IMSMetadata', 'get_fields'), 10, 2);
            add_filter('attachment_fields_to_save', array('IMSMetadata', 'imsmd_save'), 11, 2);
            add_action('admin_print_scripts-media.php', array('IMSMetadata', 'add_script_style'));
        }
        if (isset($options['post']['select']) && trim($options['post']['select']) == 'enable') {
            add_action('wp_insert_post', array('IMSMetadata', 'imsmd_save_post'), 10, 1);
            add_action('add_meta_boxes', array('IMSMetadata', 'imsmd_add_meta_box'), 10, 1);
            add_action('admin_print_scripts-post.php', array('IMSMetadata', 'add_script_style'));
        }
        
        // if metadata is added, deleted or updated and config is wp_post or wp_categories
        $imsmd_enabled = false;
        foreach (self::$post_types as $post_type=>$post_value) {
            $options = get_option('imsmd_enable', array());
            if (isset($options[$post_type]) && trim($options[$post_type]['select']) == 'enable') {
                $imsmd_enabled = true;
            }
        }
        if ($imsmd_enabled) {
            add_action('added_post_meta', array('IMSMetadata', 'update_imsmd'), 10, 4);
            add_action('deleted_post_meta', array('IMSMetadata', 'update_imsmd'), 10, 4);
            add_action('updated_post_meta', array('IMSMetadata', 'update_imsmd'), 10, 4);
        }
        
        // set shortcodes and filters for its
        add_filter('the_title', 'do_shortcode');
        add_filter('single_post_title', 'do_shortcode');
        add_shortcode('meta', array('IMSMetadata', 'meta_shortcode'));
        add_shortcode('foreach_meta', array('IMSMetadata', 'foreach_meta_shortcode'));
        
        return new IMSMetadata();
    }
    
    public function __construct() {
        if (isset($_GET['page']) && !empty($_GET['page']) &&
            !strncmp($_GET['page'], self::$info['page'], strlen(self::$info['page']))) {
            $this->tab = str_replace(self::$info['page'], '', $_GET['page']);
        }
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }
    
    public function admin_init() {
        global $IMSMD_CONFIG;
        // register settings sections
        add_settings_section('imsmd_enable_settings_section', '',
            array($this, 'enable_settings_text'), 'imsmd_enable_settings_section');
        add_settings_section('imsmd_general_settings_section', 'General settings',
            array($this, 'general_settings_text'), 'imsmd_general_settings_section');
        add_settings_section('imsmd_lifecycle_settings_section', 'Lifecycle settings',
            array($this, 'lifecycle_settings_text'), 'imsmd_lifecycle_settings_section');
        add_settings_section('imsmd_technical_settings_section', 'Technical settings',
            array($this, 'technical_settings_text'), 'imsmd_technical_settings_section');
        add_settings_section('imsmd_educational_settings_section', 'Educational settings',
            array($this, 'educational_settings_text'), 'imsmd_educational_settings_section');
        add_settings_section('imsmd_rights_settings_section', 'Rights settings',
            array($this, 'rights_settings_text'), 'imsmd_rights_settings_section');
        
        // add fields and validate inputs
        register_setting('imsmd_options', 'imsmd_enable', array($this, 'imsmd_enable_validate'));
        add_settings_field('imsmd_enable_field', 'Enable', array($this, 'enable_field'),
            'imsmd_enable_settings_section', 'imsmd_enable_settings_section', array('option_field'=>'imsmd_enable'));        
        foreach ($IMSMD_CONFIG as $meta_key=>$meta_config) {
            $section = $meta_config['settings_section'];
            register_setting('imsmd_options', $meta_key, array($this, 'enable_validate'));
            $params = array('option_field' => $meta_key);
            if (isset($meta_config['setting']) && isset($meta_config['setting'][$this->tab])) {
                $setting = $meta_config['setting'][$this->tab];
                if (isset($setting['wp_fields'])) { $params['add_select'] = $setting['wp_fields']; }
                if (isset($setting['default_value'])) {
                    $params['default_value'] = $setting['default_value'];
                    if ($params['default_value'] && $meta_config['multiplicity'] == 'single' &&
                        isset($meta_config['domain']) && is_array($meta_config['domain'])) {
                        $params['default_value'] = array_combine($meta_config['domain'], $meta_config['domain']);
                    }
                }
            }
            add_settings_field($meta_key.'_field', $meta_config['label'], array($this, 'enable_field'), $section, $section, $params);
        }
    }
   
    /**
     * Print text in metadata (enable) setting section
     */ 
    public function enable_settings_text() {
        echo '<p>Enable settings</p>';
    }
    
    public function imsmd_enable_validate($input) {
        extract($input);
        if (!isset($default)) { $default = ''; }
        $options = get_option($option_field, array());
        $options[$tab] = array('select'=>$select, 'default'=>$default);
        return $options;
    }
    
    public function enable_field(array $args) {
        extract(wp_parse_args($args, array('add_select'=>array(),
                                           'default_value'=>false)), EXTR_SKIP);
        $options = get_option($option_field, array());
        $option = (isset($options[$this->tab]) ? $options[$this->tab] : array('select'=>'disable', 'default'=>''));
        
        echo '<input name="'.$option_field.'[tab]" type="hidden" value="'.$this->tab.'" />';
        echo '<input name="'.$option_field.'[option_field]" type="hidden" value="'.$option_field.'" />';
        echo '<select name="'.$option_field.'[select]">';
        $selected = ($option['select'] == 'disable' ? 'selected' : '');
        echo '<option value="disable" '.$selected.'>disable</option>';
        foreach ($add_select as $value=>$label) {
            $selected = ($option['select'] == $value ? 'selected' : '');
            echo '<option value="'.$value.'" '.$selected.'>'.$label.'</option>';
        }
        $selected = ($option['select'] == 'enable' ? 'selected' : '');
        echo '<option value="enable" '.$selected.'>enable</option>';
        echo '</select> ';
        if (is_array($default_value)) {
            echo 'Default <select name="'.$option_field.'[default]">';
            foreach ($default_value as $value=>$label) {
                $selected = ($option['default'] == $value ? 'selected' : '');
                echo '<option value ="'.$value.'" '.$selected.'>'.$label.'</option>';
            }
            echo '</select>';
        } else if  ($default_value) {
            echo 'Default <input name="'.$option_field.'[default]" size="45" type="text" value="'.$option['default'].'" />';
        }
    }
    
    /**
     * Print text in metadata->general setting section
     */
    public function general_settings_text() {
        echo '<p>Metadata-&gt;general settings</p>';
    }
    
    public function enable_validate($input) {
        extract($input);
        if (!isset($default)) { $default = ''; }
        $options = get_option($option_field, array());
        
        global $IMSMD_CONFIG;
        $meta_config = $IMSMD_CONFIG[$option_field];
        $is_single = ($meta_config['multiplicity'] == 'single' ? true : false); 
        if ($is_single && isset($meta_config['domain']) && is_array($meta_config['domain']) && !empty($meta_config['domain']) &&
            !in_array(trim($default), $meta_config['domain'])) {
            add_settings_error($option_field, 'invalid-value', __('Invalid value for parameter'.$option_field.'.'));
            return $options;
        }
        if (!$is_single && isset($meta_config['domain']) && is_array($meta_config['domain']) && !empty($meta_config['domain'])) {
            foreach (explode(',', $default) as $default_value) {
                if (trim($default_value) != '' && !in_array(trim($default_value), $meta_config['domain'])) {
                    add_settings_error($option_field, 'invalid-value', __('Invalid value for parameter '.$option_field.'.'));
                    return $options;
                }
            }
        }
        
        $options[$tab] = array('select'=>$select, 'default'=>$default);
        return $options;
    }
    
    /**
     * Print text in metadata->lifecycle setting section
     */
    public function lifecycle_settings_text() {
        echo '<p>Metadata-&gt;lifecycle settings</p>';
    }
    
    /**
     * Print text in metadata->technical setting section
     */
    public function technical_settings_text() {
        echo '<p>Metadata-&gt;technical settings</p>';
    }
    
    /**
     * Print text in metadata->educational setting section
     */
    public function educational_settings_text() {
        echo '<p>Metadata-&gt;educational settings</p>';
    }
    
    /**
     * Print text in metadata->rights setting section
     */
    public function rights_settings_text() {
        echo '<p>Metadata-&gt;rights settings</p>';
    }
    
    /**
     * Funtion that add buttons for administrator of sites
     */
    public function admin_menu() {
        add_options_page('IMS-Metadata Options', 'IMS-Metadata', 'manage_options',
                         self::$info['page'].$this->tab, array($this, 'options_page'));
    }
    
    /**
     * Print options page, setting for pluggin
     */  
    public function options_page() {
        echo '<h2>IMS-Metadata plugin</h2>';
        echo 'Options relating to the IMS-Metadata plugin.';
        echo '<div id="icon-themes" class="icon32"><br/></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach (self::$post_types as $tab=>$name) {
            $class = ($this->tab == $tab ? ' nav-tab-active' : '');
            echo '<a class="nav-tab'.$class.'" href="?page='.self::$info['page'].$tab.'">'.$name.'</a>';
        }
        echo '</h2>'; ?>
        <div>
            <form action="options.php" method="post">
                <?php settings_fields('imsmd_options'); ?>
                <?php do_settings_sections('imsmd_enable_settings_section'); ?>
                <?php do_settings_sections('imsmd_general_settings_section'); ?>
                <?php do_settings_sections('imsmd_lifecycle_settings_section'); ?>
                <?php do_settings_sections('imsmd_technical_settings_section'); ?>
                <?php do_settings_sections('imsmd_educational_settings_section'); ?>
                <?php do_settings_sections('imsmd_rights_settings_section'); ?>
                <input class="button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
            </form>
        </div><?php
    }
    
    /**
     * Print javascripts and stylesheet files
     */
    public static function add_script_style() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-position');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('jquery-effects-core');
        wp_enqueue_script('jquery-effects-blind');
        wp_enqueue_script('jquery-effects-highlight');
        wp_enqueue_script('tag-it', plugin_dir_url(__FILE__).'js/tag-it.min.js', false, null, true);
        
        wp_enqueue_style('jquery-ui-stylesheet', plugin_dir_url(__FILE__).'css/jquery-ui.css', false, null);
        wp_enqueue_style('tag-it-stylesheet', plugin_dir_url(__FILE__).'css/jquery.tagit.css', false, null);
    }
    
    public static function get_imsmd($post_id, $meta_key, $single=false) {
        $result = get_post_meta($post_id, $meta_key, $single);
        if (!$single) {
            $to_implode = array();
            foreach ($result as $data) {
                if (!in_array($data, $to_implode)) { $to_implode[] = $data; }
            }
            $result = implode(',', $to_implode);
        }
        return $result;
    }
    
    public static function save_imsmd($object_id, $meta_type, $attachment, $meta_key, $single = false) {
        if ($single) {
            $meta_value = trim($attachment[$meta_key]);
            update_metadata($meta_type, $object_id, $meta_key, $meta_value);
        } else {
            $inserted_values = array();
            delete_metadata($meta_type, $object_id, $meta_key);
            foreach (explode(',', $attachment[$meta_key]) as $meta_value) {
                $meta_value = trim($meta_value);
                if (!in_array($meta_value, $inserted_values) && !empty($meta_value)) {
                    array_push($inserted_values, $meta_value);
                    add_metadata($meta_type, $object_id, $meta_key, $meta_value);
                }
            }
        }
    }
    
    /**
     * This function show imsmd fields in media attachment
     */
    public static function get_fields($form_fields, $post) {
        global $IMSMD_CONFIG;
        foreach ($IMSMD_CONFIG as $meta_key=>$meta_config) {
            $is_single = ($meta_config['multiplicity'] == 'single' ? true : false);
            $options = get_option($meta_key, array());
            $option = array('select'=>'disable', 'default'=>'');
            if (isset($options[$post->post_type])) {  $option = $options[$post->post_type]; }
            if ($option['select'] == 'enable' || !strncmp($option['select'], 'wp_', 3)) {
                $value = self::get_imsmd($post->ID, $meta_key, $is_single);
                if ((!isset($value) || empty($value)) && !empty($options[$post->post_type]['default'])) {
                    $value = $options[$post->post_type]['default'];
                }
                $form_fields[$meta_key]['label'] = $meta_config['label'];
                $form_fields[$meta_key]['input'] = 'text';
                $form_fields[$meta_key]['value'] = $value;
                $form_fields[$meta_key]['helps'] = $meta_config['helps'];
                if ($is_single && isset($meta_config['domain']) && !empty($meta_config['domain'])) {
                    $form_fields[$meta_key]['input'] = 'html';
                    $form_fields[$meta_key]['html'] = '<select name="attachments['.$post->ID.']['.$meta_key.']">';
                    foreach ($meta_config['domain'] as $element) {
                        $selected = ($value == $element  ? 'selected' : '');
                        $form_fields[$meta_key]['html'] .= '<option value="'.$element.'" '.$selected.'>'.$element.'</option>';
                    }
                    $form_fields[$meta_key]['html'] .= '</select>';
                }
                if (!$is_single) {
                    $form_fields[$meta_key]['input'] = 'html';
                    $form_fields[$meta_key]['html'] = '<ul id="attachments_'.$post->ID.'_'.$meta_key.'_tags">';
                    foreach (explode(',', $value) as $item) {
                        $form_fields[$meta_key]['html'] .= '<li>'.trim($item).'</li>';
                    }
                    $form_fields[$meta_key]['html'] .= '</ul>';
                    $form_fields[$meta_key]['html'] .= '<script type="text/javascript">';
                    $form_fields[$meta_key]['html'] .= 'jQuery(document).ready(function($) { ';
                    $form_fields[$meta_key]['html'] .= '$("#attachments_'.$post->ID.'_'.$meta_key.'_tags").tagit({
                                                            fieldName: "attachments['.$post->ID.']['.$meta_key.']",';
                    if (isset($meta_config['domain']) && !empty($meta_config['domain'])) {
                        $form_fields[$meta_key]['html'] .= 'availableTags: ["'.implode('", "', $meta_config['domain']).'"],
                                                            beforeTagAdded: function(event, ui) {
                                                                var arr = ["'.implode('", "', $meta_config['domain']).'"];
                                                                if (jQuery.inArray(jQuery.trim(ui.tagLabel), arr) == -1) {
                                                                    console.log("Invalid value: "+ui.tagLabel+" for '.$meta_key.'");
                                                                    return false;
                                                                }
                                                            },';
                    }
                    $form_fields[$meta_key]['html'] .= '    removeConfirmation: true,
                                                            allowSpaces: true,
                                                            singleField: true,
                                                            singleFieldDelimiter: ","
                                                        });';
                    $form_fields[$meta_key]['html'] .= '});';
                    $form_fields[$meta_key]['html'] .= '</script>';
                }
                if ($is_single && isset($meta_config['input'])) {
                    $form_fields[$meta_key]['input'] = $meta_config['input'];
                }
            }
        }
        return $form_fields;
    }
    
    /**
     * This function save imsmd fields in  media attachment
     */
    public static function imsmd_save($post, $attachment) {
        global $IMSMD_CONFIG;
        $post_id = $post['ID'];
        $post_type = $post['post_type'];
        foreach ($IMSMD_CONFIG as $meta_key=>$meta_config) {
            $options = get_option($meta_key, array());
            $option = array('select'=>'disable', 'default'=>'');
            if (isset($options[$post_type])) { $option = $options[$post_type]; }
            if ($option['select'] == 'enable' || !strncmp($option['select'], 'wp_', 3)) {
                $is_single = ($meta_config['multiplicity'] == 'single' ? true : false);
                self::save_imsmd($post_id, 'post', $attachment, $meta_key, $is_single);
            }
        }
    }
    
    /**
     * This function show the IMS-Metadata in a meta box
     */
    public static function show_field_box($post_id, $key, $field) {
        echo '<tr class="'.$key.'">';
        echo '      <th valign="top" scope="row" class="label">';
        echo '      <label for="attachments['.$post_id.']['.$key.']"><span class="alignleft">'.$field['label'].'</span><br class="clear"></label>';
        echo '  </th>';
        echo '  <td class="field">'; 
        if ($field['input']=='html' && !empty($field['html'])) {
            echo $field['html'];
        } else if ($field['input'] == 'textarea') {
            echo '<textarea type="text" id="attachments['.$post_id.']['.$key.']" name="attachments['.$post_id.']['.$key.']" style="width: 460px;">'.$field['value'].'</textarea>';
        } else {
            echo '<input type="'.$field['input'].'" class="'.$field['input'].'" id="attachments['.$post_id.']['.$key.']"
                         name="attachments['.$post_id.']['.$key.']" value="'.$field['value'].'" style="width: 460px;" />';
        }
        if (!empty($field['helps'])) {
            echo '<p class="help" style="width: 460px;">'.$field['helps'].'</p>';
        }
        echo '  </td>';
        echo '</tr>';
    }

    public static function draw_imsmd_box($post, $metabox) {
        $lang = get_bloginfo('language');  
        $result = array();
        $form_fields = array();
        $form_fields = self::get_fields($form_fields, $post);
        echo '<table>';
        foreach ($form_fields as $key=>$field) {
            self::show_field_box($post->ID, $key, $field);
        }
        echo '</table>';
    }

    public static function imsmd_add_meta_box($output) {
        add_meta_box('imsmd_box_id', 'IMS-Metadata', array('IMSMetadata', "draw_imsmd_box"), 'post', 'normal', 'high');
    }

    /**
     * This functions save the IMS-Metadata of post
     */
    public static function imsmd_save_post($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }
        if (!isset($_POST['ID']) || !isset($_POST['post_type']) || !isset($_POST['attachments']) ||
            !isset($_POST['attachments'][$_POST['ID']])) {
            return $post_id;
        }
        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) { return $post_id; }
        } else if ('post' == $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) { return $post_id; }
        }
        $attachment = $_POST['attachments'][$_POST['ID']];
        $post = array('ID'=>$post_id, 'post_type'=>$_POST['post_type']);
        self::imsmd_save($post, $attachment);
        return $post_id;
    }
    
    /**
     * This function add categories when added imsmd, for deleted $meta_id is array()
     */
    public static function update_imsmd($meta_id, $object_id, $meta_key, $meta_value) {
        global $IMSMD_CONFIG;
        if (isset($IMSMD_CONFIG[$meta_key])) {
            $post_type = get_post_type($object_id);
            $options = get_option($meta_key, array());
            $option = array('select'=>'disable', 'default'=>'');
            if (isset($options[$post_type])) { $option = $options[$post_type]; }
            if ($option['select'] == 'wp_post_tag') {
                self::sync_object_terms($object_id, $post_type, 'post_tag');
            } else if ($option['select'] == 'wp_category') {
                self::sync_object_terms($object_id, $post_type, 'category');
            }
        }
    }
    
    private static function create_category_for_meta($meta_value, $meta_key) {
        require_once(ABSPATH.'/wp-admin/includes/taxonomy.php');
        global $IMSMD_CATEGORY, $IMSMD_CONFIG;
        $parent_id = 0;
        if (isset($IMSMD_CATEGORY) && !empty($IMSMD_CATEGORY)) {
            foreach ($IMSMD_CATEGORY as $category) {
                $parent_id = wp_create_category($category, $parent_id);
            }
        }
        $parent_id = wp_create_category($IMSMD_CONFIG[$meta_key]['label'], $parent_id);
        return wp_create_category($meta_value, $parent_id);
    }
    
    private static function sync_object_terms($object_id, $post_type = 'post', $taxonomy = 'post_tag') {
        global $IMSMD_CONFIG;
        
        $terms = array();
        foreach ($IMSMD_CONFIG as $meta_key=>$meta_config) {
            $options = get_option($meta_key, array());
            $option = array('select'=>'disable', 'default'=>'');
            if (isset($options[$post_type])) { $option = $options[$post_type]; }
            if ($option['select'] == 'wp_'.$taxonomy) {
                foreach (get_metadata($post_type, $object_id, $meta_key) as $meta_value) {
                    if ($taxonomy == 'category') {
                        $term_id = self::create_category_for_meta($meta_value, $meta_key);
                        array_push($terms, (int) $term_id);
                    } else if ($taxonomy == 'post_tag') {
                        array_push($terms, $meta_value);
                    }
                }
            }
        }
        
        wp_set_object_terms($object_id, null, $taxonomy);
        foreach ($terms as $term) {
            wp_set_object_terms($object_id, $term, $taxonomy, true);
        }
    }
    
    /**
     * This function print meta shortcode
     */
    public static function meta_shortcode($atts) {
        extract(shortcode_atts(array('key'=>null), $atts));
        return get_post_meta(get_the_ID(), $key, true);
    }
    
    public static function foreach_meta_shortcode($atts, $content = NULL) {
        $result = '';
        extract(shortcode_atts(array('key'=>null), $atts));
        $values = get_post_meta(get_the_ID(), $key, false);
        foreach ($values as $value) {
            $result .= preg_replace('#\{value\}#i', $value, $content);
        }
        return $result;
    }

    /**
     * This function add filter to oai-PMH
     */
    public static function filter_where($where = '') {
        global $from, $until;
        if (isset($from)) { $where .= " AND post_modified >= '".date('Y-m-d H:i:s', $from)."'"; }
        if (isset($until)) { $where .= " AND post_modified <= '".date('Y-m-d H:i:s', $until)."'"; }
        return $where;
    }
    
}

//-- install plugin
register_activation_hook(__FILE__, array('IMSMetadata', 'install'));
register_deactivation_hook(__FILE__, array('IMSMetadata', 'uninstall'));

//-- init plugin
add_filter('init', array('IMSMetadata', 'init'));

?>
