<?php
/*
Plugin Name: Order auto complete for WooCommerce
Plugin URI : https://wppoet.com/
Description:  WooCommerce Order will automatically complete
Version:1.2.5
Author: kardi
Author URI : https://github.com/ikardi420
License : GPL v or later
Text Domain: wtt-woo-auto-complete
Domain Path : /languages/
WC requires at least: 4.2.0
WC tested up to: 10.4.3
*/

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {


    /**
     * Auto Complete WooCommerce orders, honoring the Order Status Automation settings.
     */
    add_action('woocommerce_thankyou', 'custom_woocommerce_auto_complete_order');
    function custom_woocommerce_auto_complete_order($order_id)
    {
        if (!$order_id) {
            return;
        }

        // Master on/off switch, enabled by default to preserve prior behavior.
        if (get_option('woodecor_autocomplete_enabled', '1') !== '1') {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->has_status('completed')) {
            return;
        }

        // Optionally restrict to orders containing only virtual/downloadable products.
        if (get_option('woodecor_autocomplete_virtual_only') === '1' && !woodecor_order_is_virtual($order)) {
            return;
        }

        $delay_minutes = absint(get_option('woodecor_autocomplete_delay', 0));
        if ($delay_minutes > 0) {
            if (!wp_next_scheduled('woodecor_delayed_autocomplete_order', array($order_id))) {
                wp_schedule_single_event(time() + ($delay_minutes * MINUTE_IN_SECONDS), 'woodecor_delayed_autocomplete_order', array($order_id));
            }
        } else {
            woodecor_complete_order($order_id);
        }
    }

    add_action('woodecor_delayed_autocomplete_order', 'woodecor_complete_order');
    /**
     * Mark a single order as completed. Used both for immediate and delayed completion.
     */
    function woodecor_complete_order($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->has_status('completed')) {
            return;
        }
        $order->update_status('completed', __('Order automatically marked as completed.', 'wtt-woo-auto-complete'));
    }

    /**
     * Whether every line item on the order is a virtual/downloadable product.
     */
    function woodecor_order_is_virtual($order)
    {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->is_virtual()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Send the customer communication (email + order note) once an order is completed.
     */
    add_action('woocommerce_order_status_completed', 'woodecor_send_order_communication');
    function woodecor_send_order_communication($order_id)
    {
        if (get_option('woodecor_communication_enabled') !== '1') {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $to = $order->get_billing_email();
        if (!$to) {
            return;
        }

        $subject = get_option('woodecor_communication_subject');
        if (empty($subject)) {
            $subject = __('Your order #{order_number} is complete', 'wtt-woo-auto-complete');
        }

        $message = get_option('woodecor_communication_message');
        if (empty($message)) {
            $message = __('Hi {customer_name}, your order #{order_number} is now {order_status}. Thank you for shopping with us!', 'wtt-woo-auto-complete');
        }

        $replacements = array(
            '{customer_name}' => $order->get_billing_first_name(),
            '{order_number}'  => $order->get_order_number(),
            '{order_status}'  => wc_get_order_status_name($order->get_status()),
        );
        $subject = strtr($subject, $replacements);
        $message = strtr($message, $replacements);

        $sent = wp_mail($to, $subject, wpautop(esc_html($message)), array('Content-Type: text/html; charset=UTF-8'));

        if ($sent) {
            $order->add_order_note(sprintf(__('Order completion email sent to customer: %s', 'wtt-woo-auto-complete'), $subject));
        }
    }
}

function wttwoodecor_settings_init()
{
    // Register a new setting for "woodecor" page.
    register_setting('woodecor', 'woodecor_options1');
    register_setting('woodecor', 'woodecor_options2');
    register_setting('woodecor', 'woodecor_hidenotice');

    // Order Status Automation settings.
    register_setting('woodecor', 'woodecor_autocomplete_enabled');
    register_setting('woodecor', 'woodecor_autocomplete_virtual_only');
    register_setting('woodecor', 'woodecor_autocomplete_delay', array('sanitize_callback' => 'absint'));

    // Order Communication settings.
    register_setting('woodecor', 'woodecor_communication_enabled');
    register_setting('woodecor', 'woodecor_communication_subject');
    register_setting('woodecor', 'woodecor_communication_message', array('sanitize_callback' => 'sanitize_textarea_field'));

    // Register a new section in the "woodecor" page.
    add_settings_section(
        'woodecor_section_developers',
        __('Here set your settings', 'wtt-woo-auto-complete'),
        'woodecor_section_developers_callback',
        'woodecor'
    );

    // Register the Order Status Automation section.
    add_settings_section(
        'woodecor_section_status_automation',
        __('Order Status Automation', 'wtt-woo-auto-complete'),
        'woodecor_section_status_automation_callback',
        'woodecor'
    );

    // Register the Order Communication section.
    add_settings_section(
        'woodecor_section_communication',
        __('Order Communication', 'wtt-woo-auto-complete'),
        'woodecor_section_communication_callback',
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

    // Order Status Automation fields.
    add_settings_field(
        'woodecor_field_autocomplete_enabled',
        __('Enable Auto Complete', 'wtt-woo-auto-complete'),
        'woodecor_field_autocomplete_enabled_cb',
        'woodecor',
        'woodecor_section_status_automation'
    );
    add_settings_field(
        'woodecor_field_autocomplete_virtual_only',
        __('Only Virtual/Downloadable Orders', 'wtt-woo-auto-complete'),
        'woodecor_field_autocomplete_virtual_only_cb',
        'woodecor',
        'woodecor_section_status_automation'
    );
    add_settings_field(
        'woodecor_field_autocomplete_delay',
        __('Delay Before Completing (minutes)', 'wtt-woo-auto-complete'),
        'woodecor_field_autocomplete_delay_cb',
        'woodecor',
        'woodecor_section_status_automation'
    );

    // Order Communication fields.
    add_settings_field(
        'woodecor_field_communication_enabled',
        __('Enable Order Communication', 'wtt-woo-auto-complete'),
        'woodecor_field_communication_enabled_cb',
        'woodecor',
        'woodecor_section_communication'
    );
    add_settings_field(
        'woodecor_field_communication_subject',
        __('Email Subject', 'wtt-woo-auto-complete'),
        'woodecor_field_communication_subject_cb',
        'woodecor',
        'woodecor_section_communication'
    );
    add_settings_field(
        'woodecor_field_communication_message',
        __('Email Message', 'wtt-woo-auto-complete'),
        'woodecor_field_communication_message_cb',
        'woodecor',
        'woodecor_section_communication'
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
 * Order Status Automation section callback function.
 *
 * @param array $args
 */
function woodecor_section_status_automation_callback($args)
{
    ?>
    <p><?php esc_html_e('Control when and which orders are automatically marked as completed.', 'wtt-woo-auto-complete'); ?></p>
    <?php
}

/**
 * Enable/disable auto-complete checkbox callback function.
 *
 * @param array $args
 */
function woodecor_field_autocomplete_enabled_cb($args)
{
    $enabled = get_option('woodecor_autocomplete_enabled', '1');
    ?>
    <label for="woodecor_field_autocomplete_enabled">
        <input type="hidden" name="woodecor_autocomplete_enabled" value="0" />
        <input type="checkbox" id="woodecor_field_autocomplete_enabled" name="woodecor_autocomplete_enabled" value="1" <?php checked($enabled, '1'); ?> />
        <?php esc_html_e('Automatically mark orders as completed', 'wtt-woo-auto-complete'); ?>
    </label>
    <p class="description"><?php esc_html_e('Uncheck to stop automatically completing orders and manage order status manually.', 'wtt-woo-auto-complete'); ?></p>
    <?php
}

/**
 * Virtual/downloadable only checkbox callback function.
 *
 * @param array $args
 */
function woodecor_field_autocomplete_virtual_only_cb($args)
{
    $virtual_only = get_option('woodecor_autocomplete_virtual_only');
    ?>
    <label for="woodecor_field_autocomplete_virtual_only">
        <input type="hidden" name="woodecor_autocomplete_virtual_only" value="0" />
        <input type="checkbox" id="woodecor_field_autocomplete_virtual_only" name="woodecor_autocomplete_virtual_only" value="1" <?php checked($virtual_only, '1'); ?> />
        <?php esc_html_e('Only auto-complete orders containing exclusively virtual/downloadable products', 'wtt-woo-auto-complete'); ?>
    </label>
    <p class="description"><?php esc_html_e('Orders with at least one physical product will be left for manual completion.', 'wtt-woo-auto-complete'); ?></p>
    <?php
}

/**
 * Auto-complete delay field callback function.
 *
 * @param array $args
 */
function woodecor_field_autocomplete_delay_cb($args)
{
    $delay = get_option('woodecor_autocomplete_delay', 0);
    ?>
    <input id='woodecor_field_autocomplete_delay' name='woodecor_autocomplete_delay' type='number' min="0" step="1" value="<?php echo esc_attr($delay); ?>" class="small-text" />
    <p class="description"><?php esc_html_e('Number of minutes to wait after checkout before completing the order. Use 0 to complete immediately.', 'wtt-woo-auto-complete'); ?></p>
    <?php
}

/**
 * Order Communication section callback function.
 *
 * @param array $args
 */
function woodecor_section_communication_callback($args)
{
    ?>
    <p><?php esc_html_e('Send customers an email when their order is automatically completed.', 'wtt-woo-auto-complete'); ?></p>
    <p class="description"><?php esc_html_e('Available placeholders: {customer_name}, {order_number}, {order_status}', 'wtt-woo-auto-complete'); ?></p>
    <?php
}

/**
 * Enable/disable order communication checkbox callback function.
 *
 * @param array $args
 */
function woodecor_field_communication_enabled_cb($args)
{
    $enabled = get_option('woodecor_communication_enabled');
    ?>
    <label for="woodecor_field_communication_enabled">
        <input type="hidden" name="woodecor_communication_enabled" value="0" />
        <input type="checkbox" id="woodecor_field_communication_enabled" name="woodecor_communication_enabled" value="1" <?php checked($enabled, '1'); ?> />
        <?php esc_html_e('Email the customer when an order is completed', 'wtt-woo-auto-complete'); ?>
    </label>
    <?php
}

/**
 * Communication email subject field callback function.
 *
 * @param array $args
 */
function woodecor_field_communication_subject_cb($args)
{
    $subject = get_option('woodecor_communication_subject');
    ?>
    <input id='woodecor_field_communication_subject' name='woodecor_communication_subject' type='text' class="regular-text" placeholder="<?php esc_attr_e('Your order #{order_number} is complete', 'wtt-woo-auto-complete'); ?>" value="<?php echo esc_attr($subject); ?>" />
    <?php
}

/**
 * Communication email message field callback function.
 *
 * @param array $args
 */
function woodecor_field_communication_message_cb($args)
{
    $message = get_option('woodecor_communication_message');
    ?>
    <textarea id='woodecor_field_communication_message' name='woodecor_communication_message' rows="4" class="large-text" placeholder="<?php esc_attr_e('Hi {customer_name}, your order #{order_number} is now {order_status}. Thank you for shopping with us!', 'wtt-woo-auto-complete'); ?>"><?php echo esc_textarea($message); ?></textarea>
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

    // Order Communication inbox submenu.
    $GLOBALS['woodecor_inbox_hook'] = add_submenu_page(
        'woodecor',
        __('Order Communication', 'wtt-woo-auto-complete'),
        __('Order Communication', 'wtt-woo-auto-complete'),
        'manage_options',
        'woodecor-inbox',
        'woodecor_inbox_page_html'
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

    // Sections are rendered as tabs; keep the tab label text identical to each
    // registered section title so the tabs read the same as before.
    $tabs = array(
        'general'       => __('Here set your settings', 'wtt-woo-auto-complete'),
        'automation'    => __('Order Status Automation', 'wtt-woo-auto-complete'),
        'communication' => __('Order Communication', 'wtt-woo-auto-complete'),
    );
    $tab_sections = array(
        'general'       => 'woodecor_section_developers',
        'automation'    => 'woodecor_section_status_automation',
        'communication' => 'woodecor_section_communication',
    );
    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? sanitize_key($_GET['tab']) : 'general';
?>
    <div class="wrap woodecor-admin-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <h2 class="nav-tab-wrapper woodecor-nav-tab-wrapper">
            <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'woodecor', 'tab' => $tab_key), admin_url('admin.php'))); ?>" class="nav-tab woodecor-tab-link<?php echo $active_tab === $tab_key ? ' nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr($tab_key); ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <form action="options.php" method="post" class="woodecor-settings-form">
            <?php
            // output security fields for the registered setting "woodecor"
            settings_fields('woodecor');

            foreach ($tab_sections as $tab_key => $section_id) :
                ?>
                <div class="woodecor-tab-panel" data-tab-panel="<?php echo esc_attr($tab_key); ?>"<?php echo $active_tab === $tab_key ? '' : ' hidden'; ?>>
                    <?php
                    // Run the section's own callback (e.g. the WooCommerce-missing notice), then its fields.
                    do_action("woodecor_settings_section_render_{$section_id}", array());
                    ?>
                    <table class="form-table" role="presentation">
                        <?php do_settings_fields('woodecor', $section_id); ?>
                    </table>
                </div>
            <?php endforeach; ?>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <script>
    (function () {
        var tabs = document.querySelectorAll('.woodecor-tab-link');
        var panels = document.querySelectorAll('.woodecor-tab-panel');

        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function (e) {
                e.preventDefault();
                var target = this.getAttribute('data-tab');

                for (var j = 0; j < tabs.length; j++) {
                    tabs[j].classList.remove('nav-tab-active');
                }
                this.classList.add('nav-tab-active');

                for (var k = 0; k < panels.length; k++) {
                    if (panels[k].getAttribute('data-tab-panel') === target) {
                        panels[k].removeAttribute('hidden');
                    } else {
                        panels[k].setAttribute('hidden', 'hidden');
                    }
                }

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', this.getAttribute('href'));
                }
            });
        }
    })();
    </script>
<?php
}

/**
 * Bridge so each settings section's own callback still runs inside its tab
 * panel, even though do_settings_sections() is no longer used to render them.
 */
add_action('woodecor_settings_section_render_woodecor_section_developers', 'woodecor_section_developers_callback');
add_action('woodecor_settings_section_render_woodecor_section_status_automation', 'woodecor_section_status_automation_callback');
add_action('woodecor_settings_section_render_woodecor_section_communication', 'woodecor_section_communication_callback');

function woodecor_enqueue_scripts($hook)
{

    wp_register_style('woodecor-stylesheet',  plugin_dir_url(__FILE__) . 'assets/css/wtt-style.css');
    wp_enqueue_style('woodecor-stylesheet');

    // Load the inbox assets only on the Order Communication page.
    if (!empty($GLOBALS['woodecor_inbox_hook']) && $hook === $GLOBALS['woodecor_inbox_hook']) {
        wp_enqueue_style('woodecor-inbox-stylesheet', plugin_dir_url(__FILE__) . 'assets/css/wtt-inbox.css', array('woodecor-stylesheet'), '1.0');
        wp_enqueue_script('woodecor-inbox-script', plugin_dir_url(__FILE__) . 'assets/js/wtt-inbox.js', array('jquery'), '1.0', true);
        wp_localize_script('woodecor-inbox-script', 'woodecorInbox', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('woodecor_inbox_nonce'),
            'i18n'    => array(
                'send'     => __('Send', 'wtt-woo-auto-complete'),
                'sending'  => __('Sending…', 'wtt-woo-auto-complete'),
                'sent'     => __('Message sent.', 'wtt-woo-auto-complete'),
                'error'    => __('Something went wrong. Please try again.', 'wtt-woo-auto-complete'),
                'noHistory' => __('No messages sent yet for this order.', 'wtt-woo-auto-complete'),
                'noOrders' => __('No orders found.', 'wtt-woo-auto-complete'),
                'by'       => __('by', 'wtt-woo-auto-complete'),
            ),
        ));
    }
}
add_action('admin_enqueue_scripts', 'woodecor_enqueue_scripts');
function woodecor_enqueue_front_scripts()
{

    wp_register_style('woodecor-front-stylesheet',  plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_style('woodecor-front-stylesheet');
}
add_action('wp_enqueue_scripts', 'woodecor_enqueue_front_scripts');

require_once('function.php');
require_once('inbox.php');

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
