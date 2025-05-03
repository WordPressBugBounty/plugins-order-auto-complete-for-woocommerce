<?php
/*
Plugin Name: Order auto complete for WooCommerce
Plugin URI : https://wppoet.com/
Description:  WooCommerce Order will automatically complete
Version:1.2.3
Author: kardi
Author URI : https://github.com/ikardi420
License : GPL v or later
Text Domain: wtt-woo-auto-complete
Domain Path : /languages/
WC requires at least: 4.2.0
WC tested up to: 9.8.1
*/

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {


    /**
     * Auto Complete all WooCommerce orders.
     */
    add_action('woocommerce_thankyou', 'custom_woocommerce_auto_complete_order');
    function custom_woocommerce_auto_complete_order($order_id)
    {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        $order->update_status('completed');
    }
}

function wttwoodecor_settings_init()
{
    // Register a new setting for "woodecor" page.
    register_setting('woodecor', 'woodecor_options1');
    register_setting('woodecor', 'woodecor_options2');
    register_setting('woodecor', 'woodecor_hidenotice');

    // Register a new section in the "woodecor" page.
    add_settings_section(
        'woodecor_section_developers',
        __('Here set your settings', 'wtt-woo-auto-complete'),
        'woodecor_section_developers_callback',
        'woodecor'
    );

    // Register a new field in the "woodecor_section_developers" section, inside the "woodecor" page.
    add_settings_field(
        'woodecor_field_cart', // As of WP 4.6 this value is used only internally.
        // Use $args' label_for to populate the id inside the callback.
        __('Add to Cart Button Text', 'wtt-woo-auto-complete'),
        'woodecor_field_cart_cb',
        'woodecor',
        'woodecor_section_developers'
    );
    add_settings_field(
        'woodecor_field_readmore',
        __('Out of Stock Button Text', 'wtt-woo-auto-complete'),
        'woodecor_field_readmore_cb',
        'woodecor',
        'woodecor_section_developers'
    );
    add_settings_field(
        'woodecor_field_hidenotice',
        __('Hide Admin Notice', 'wtt-woo-auto-complete'),
        'woodecor_field_hidenotice_cb',
        'woodecor',
        'woodecor_section_developers'
    );
}

/**
 * Register our woodecor_settings_init to the admin_init action hook.
 */
add_action('admin_init', 'wttwoodecor_settings_init');


/**
 * Custom option and settings:
 *  - callback functions
 */


/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */

function woodecor_section_developers_callback($args)
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) { ?>
        <div id="message" class="error">
            <p>Woocommerce Order Autocomplete plugin requires <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="<?php echo admin_url('/plugin-install.php?tab=search&amp;type=term&amp;s=WooCommerce'); ?>" target="">WooCommerce</a> first.</p>
        </div>
    <?php deactivate_plugins('/order-auto-complete-for-woocommerce/index.php');
    }
}

/**
 * Pill field callbakc function.
 *
 * WordPress has magic interaction with the following keys: label_for, class.
 * - the "label_for" key value is used for the "for" attribute of the <label>.
 * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
 * Note: you can add custom key value pairs to be used inside your callbacks.
 *
 * @param array $args
 */
function woodecor_field_cart_cb($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('woodecor_options1');
    ?>


    <input id='woodecor_field_cart' placeholder="Add To Cart" name='woodecor_options1' type='text' value="<?php echo esc_attr(sanitize_text_field($options)); ?>" />
    <p class="description">
    <div class="tooltip"><?php esc_html_e('here set your text.', 'wtt-woo-auto-complete'); ?>
        <span class="tooltiptext"><?php esc_html_e('Set Add to cart Button Text', 'wtt-woo-auto-complete'); ?></span>
    </div>

    </p>

<?php
}
function woodecor_field_readmore_cb($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('woodecor_options2');
?>


    <input id='woodecor_field_readmore' placeholder="Read More" name='woodecor_options2' type='text' value="<?php echo esc_attr(sanitize_text_field($options)); ?>" />
    <p class="description">
    <div class="tooltip"><?php esc_html_e('here set your text.', 'wtt-woo-auto-complete'); ?>
        <span class="tooltiptext"><?php esc_html_e('Set Out of Stock Button Text', 'wtt-woo-auto-complete');?></span>
    </div>

    </p>




<?php
}
function woodecor_field_hidenotice_cb($args)
{
    // Get the value of the setting we've registered with register_setting()
    $options = get_option('woodecor_hidenotice');

 

    // Get the value for the text field and checkbox
    $hide_notice = isset($options) ? (bool)$options : false;
    ?>


    <label for="woodecor_field_hide_notice">
        <input type="checkbox" id="woodecor_field_hide_notice" name="woodecor_hidenotice" value="1" <?php checked( $hide_notice, true ); ?> />
        <?php esc_html_e('Hide the admin notice', 'wtt-woo-auto-complete'); ?>
    </label>
    <p class="description"><?php esc_html_e('Check to hide the WooCommerce Auto Notification admin notice.', 'wtt-woo-auto-complete'); ?></p>

    <?php
}

/**
 * Add the top level menu page.
 */


function woodecor_options_page()
{
    add_menu_page(
        'Order auto complete Option Page',
        'Auto Complete',
        'manage_options',
        'woodecor',
        'woodecor_options_page_html'
    );
    // Add the Upgrade to Pro submenu with custom CSS
    add_submenu_page(
        'woodecor', // Parent slug
        'Upgrade to Pro', // Page title
        '<a href="https://wppoet.com/woocommerce-order-auto-notification/" target="_blank" style="background:orange;color:white;"> <span class="woodecor-pro-link">Upgrade to Pro</span></a>', // Menu title with HTML
        'manage_options', // Capability
        'woodecor-pro', // Menu slug
        'woodecor_pro_page' // Callback function
    );
}


/**
 * Register our woodecor_options_page to the admin_menu action hook.
 */
add_action('admin_menu', 'woodecor_options_page');


/**
 * Top level menu callback function
 */
function woodecor_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // add error/update messages

    // check if the user have submitted the settings
    // WordPress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
        // add settings saved message with the class of "updated"
        add_settings_error('woodecor_messages', 'woodecor_message', __('Settings Saved', 'wtt-woo-auto-complete'), 'updated');
    }

    // show error/update messages
    settings_errors('woodecor_messages');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "woodecor"
            settings_fields('woodecor');
            // output setting sections and their fields
            // (sections are registered for "woodecor", each field is registered to a specific section)
            do_settings_sections('woodecor');
            // output save settings button
            submit_button('Save Settings');
            ?>
        </form>
    </div>
<?php
}

function woodecor_enqueue_scripts()
{

    wp_register_style('woodecor-stylesheet',  plugin_dir_url(__FILE__) . 'assets/css/wtt-style.css');
    wp_enqueue_style('woodecor-stylesheet');
}
add_action('admin_enqueue_scripts', 'woodecor_enqueue_scripts');
function woodecor_enqueue_front_scripts()
{

    wp_register_style('woodecor-front-stylesheet',  plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_style('woodecor-front-stylesheet');
}
add_action('wp_enqueue_scripts', 'woodecor_enqueue_front_scripts');

require_once('function.php');

// Add action to show admin notice after login
add_action('admin_init', 'woodecor_check_show_admin_notice');



/**
 * Check if we should show the admin notice
 */
function woodecor_check_show_admin_notice() {
    // Get current user
    $user_id = get_current_user_id();
    
    // Check if user just logged in
    if (get_user_meta($user_id, 'woodecor_login_notice_shown', true) !== date('Y-m-d')) {
        // Set flag to show notice
        update_user_meta($user_id, 'woodecor_show_notice', true);
        // Update last shown date
        update_user_meta($user_id, 'woodecor_login_notice_shown', date('Y-m-d'));
    }
}



// Handle AJAX dismiss
add_action('wp_ajax_woodecor_dismiss_notice', 'woodecor_dismiss_notice_handler');

/**
 * Handle the notice dismissal
 */
function woodecor_dismiss_notice_handler() {
    // Verify nonce
    check_ajax_referer('woodecor_dismiss_notice', 'nonce');
    
    // Get current user
    $user_id = get_current_user_id();
    
    // Update user meta to indicate notice was dismissed
    update_user_meta($user_id, 'woodecor_show_notice', false);
    
    wp_die();
}

// Add action to show admin notice
add_action('admin_notices', 'woodecor_show_plugin_notice');

function woodecor_show_plugin_notice() {
    $options = get_option('woodecor_hidenotice');
    if ( isset($options) && $options ) {
        return; // Do not show the notice
    }
    // Get current screen
    $screen = get_current_screen();
    
    // Only show on dashboard
    if ($screen->id === 'dashboard') {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                📢 <?php esc_html_e('WooCommerce Order Auto Notification plugin now available! ', 'wtt-woo-auto-complete'); ?>
                <a href="https://wppoet.com/woocommerce-order-auto-notification/" target="_blank">Try it now</a>
            </p>
        </div>
        <?php
    }
}
