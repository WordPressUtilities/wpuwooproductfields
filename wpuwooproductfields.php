<?php
/*
Plugin Name: WPU Woo Product Fields
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Quickly add fields to WooCommerce product & variations : handle display & save
Version: 0.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUWooProductFields {

    private $fields = array();

    public function __construct() {
        add_action('init', array(&$this, 'init'));
    }

    public function init() {

        $this->fields = $this->get_fields();

        // Variation Settings : Add & Save
        add_action('woocommerce_product_after_variable_attributes', array(&$this, 'variation_settings_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array(&$this, 'save_variation_settings_fields'), 10, 2);

        // Common Settings : Add & Save
        add_action('woocommerce_product_options_general_product_data', array(&$this, 'add_settings_fields'), 10, 2);
        add_action('woocommerce_process_product_meta', array(&$this, 'save_settings_fields'), 10, 2);
    }

    /* ----------------------------------------------------------
      FIELDS
    ---------------------------------------------------------- */

    public function get_fields() {
        $fields = apply_filters('wpu_woo_variation_fields__fields', array());
        foreach ($fields as $id => $field) {
            /* Default label to ID */
            $field['label'] = !isset($field['label']) ? $id : $field['label'];
            /* Select : default to yes/no */
            if (!isset($field['options']) || !is_array($field['options'])) {
                $field['options'] = array(__('No'), __('Yes'));
            }
            // Set default field group to variation
            if (!isset($field['variation']) && !isset($field['group'])) {
                $field['group'] = 'general';
            }
            /* Get value */
            $fields[$id] = $field;
        }

        return $fields;
    }

    public function display_field($field) {
        switch ($field['type']) {
        case 'select':
            $field['style'] = 'display:block;';
            woocommerce_wp_select($field);
            break;
        case 'checkbox':
            woocommerce_wp_checkbox($field);
            break;
        case 'textarea':
            woocommerce_wp_textarea_input($field);
            break;
        case 'hidden':
            woocommerce_wp_hidden_input($field);
            break;
        default:
            woocommerce_wp_text_input($field);
        }
    }

    public function save_field_value($field_id, $field, $post_id, $tmp_val) {
        $_val = false;

        switch ($field['type']) {
        case 'select':
            if (array_key_exists($tmp_val, $field['options'])) {
                $_val = $tmp_val;
            }
            break;
        case 'number':
            if (is_numeric($tmp_val)) {
                $_val = $tmp_val;
            }
            break;
        case 'checkbox':
        case 'hidden':
        case 'text':
        case 'textarea':
            $_val = esc_attr($tmp_val);
            break;
        default:
        }

        if ($_val !== false) {
            update_post_meta($post_id, $field_id, esc_attr($_val));
        }
    }

    /* ----------------------------------------------------------
      COMMON
    ---------------------------------------------------------- */

    /**
     * Display fields
     */
    public function add_settings_fields() {
        global $woocommerce, $post;

        // Check correct post
        if (!is_object($post) || !$post->ID) {
            return;
        }

        // Get current group
        $current_group = str_replace(array('woocommerce_product_options_', '_product_data'), '', current_filter());

        echo '<div class="options_group">';

        foreach ($this->fields as $id => $field) {
            if (!isset($field['group']) || $field['group'] != $current_group) {
                continue;
            }
            $field['id'] = '_' . $id;
            $field['value'] = get_post_meta($post->ID, '_' . $id, true);
            $this->display_field($field);
        }

        echo '</div>';

    }

    /**
     * Save fields
     */
    public function save_settings_fields($post_id) {
        foreach ($this->fields as $id => $field) {
            $_id = '_' . $id;
            /* For non checkbox : test if field exists */
            if ($field['type'] != 'checkbox') {
                if (!isset($_POST[$_id])) {
                    continue;
                }
                $_tmp_val = $_POST[$_id];
            } else {
                $_tmp_val = isset($_POST[$_id]) ? 'yes' : 'no';
            }
            $this->save_field_value($_id, $field, $post_id, $_tmp_val);
        }
    }

    /* ----------------------------------------------------------
      VARIATIONS
    ---------------------------------------------------------- */

    /**
     * Create new fields for variations
     */
    public function variation_settings_fields($loop, $variation_data, $variation) {
        foreach ($this->fields as $id => $field) {
            if (!isset($field['variation'], $variation_data[$field['variation']])) {
                continue;
            }
            $field['id'] = '_' . $id . '[' . $variation->ID . ']';
            $field['value'] = get_post_meta($variation->ID, '_' . $id, true);
            $this->display_field($field);
        }
    }

    /**
     * Save new fields for variations
     */
    public function save_variation_settings_fields($post_id) {
        foreach ($this->fields as $id => $field) {
            $_id = '_' . $id;
            /* For non checkbox : test if field exists */
            if ($field['type'] != 'checkbox') {
                if (!isset($_POST[$_id], $_POST[$_id][$post_id])) {
                    continue;
                }
                $_tmp_val = $_POST[$_id][$post_id];
            } else {
                $_tmp_val = isset($_POST[$_id][$post_id]) ? 'yes' : 'no';
            }
            $this->save_field_value($_id, $field, $post_id, $_tmp_val);
        }
    }

}

$WPUWooProductFields = new WPUWooProductFields();

/*
add_filter('wpu_woo_variation_fields__fields', 'test_wpu_woo_variation_fields__fields', 10, 1);
function test_wpu_woo_variation_fields__fields($values) {
    $values['test_select'] = array(
        'variation' => 'attribute_pa_test_attribute',
        'type' => 'select',
        'label' => __('Select test'),
    );
    $values['test_text'] = array(
        'type' => 'text',
        'label' => __('Test text'),
    );
    return $values;
}
*/