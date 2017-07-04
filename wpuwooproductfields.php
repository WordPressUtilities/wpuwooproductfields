<?php
/*
Plugin Name: WPU Woo Product Fields
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Quickly add fields to WooCommerce product & variations : handle display & save
Version: 0.5.0
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
        add_action('woocommerce_save_product_variation', array(&$this, 'save_variation_settings_fields'), 10, 1);

        // Common Settings : Add & Save
        $hooks_ = array(
            'woocommerce_product_options_general_product_data',
            'woocommerce_product_options_inventory_product_data',
            'woocommerce_product_options_shipping',
            'woocommerce_product_options_attributes',
            'woocommerce_product_options_related',
            'woocommerce_product_options_advanced'
        );
        foreach ($hooks_ as $hook) {
            add_action($hook, array(&$this, 'add_settings_fields'), 10);
        }
        add_action('woocommerce_process_product_meta', array(&$this, 'save_settings_fields'), 10, 1);
    }

    /* ----------------------------------------------------------
      FIELDS
    ---------------------------------------------------------- */

    public function get_fields() {
        $fields = apply_filters('wpu_woo_variation_fields__fields', array());
        foreach ($fields as $id => $field) {
            $field['type'] = isset($field['type']) ? $field['type'] : 'text';
            /* Default label to ID */
            $field['label'] = !isset($field['label']) ? $id : $field['label'];
            /* Select : default to yes/no */
            if (!isset($field['options']) || !is_array($field['options'])) {
                $field['options'] = array(__('No'), __('Yes'));
            }
            /* Field is prefixed */
            if (!isset($field['no_prefix_meta'])) {
                $field['no_prefix_meta'] = false;
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
        default:
            $_val = esc_attr($tmp_val);
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
            $field['id'] = $field['no_prefix_meta'] ? '' . $id : '_' . $id;
            $field['value'] = get_post_meta($post->ID, $field['id'], true);
            if (isset($field['separator_before']) && $field['separator_before']) {
                echo '</div><div class="options_group">';
            }
            $this->display_field($field);
            if (isset($field['separator_after']) && $field['separator_after']) {
                echo '</div><div class="options_group">';
            }
        }

        echo '</div>';

    }

    /**
     * Save fields
     */
    public function save_settings_fields($post_id) {
        foreach ($this->fields as $id => $field) {
            $_id = $field['no_prefix_meta'] ? '' . $id : '_' . $id;
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
        echo '<div class="form-row form-row-full">';
        foreach ($this->fields as $id => $field) {
            if (!isset($field['variation'], $variation_data[$field['variation']])) {
                continue;
            }
            $field['name'] = $field['no_prefix_meta'] ? $id : '_' . $id . '[' . $variation->ID . ']';
            $field['id'] = $field['no_prefix_meta'] ? $id : '_' . $id . '_' . $variation->ID . '_';
            $field['value'] = get_post_meta($variation->ID, $field['no_prefix_meta'] ? '' . $id : '_' . $id, true);
            $this->display_field($field);
        }
        echo '</div>';
    }

    /**
     * Save new fields for variations
     */
    public function save_variation_settings_fields($post_id) {
        foreach ($this->fields as $id => $field) {
            $_id = $field['no_prefix_meta'] ? '' . $id : '_' . $id;
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
