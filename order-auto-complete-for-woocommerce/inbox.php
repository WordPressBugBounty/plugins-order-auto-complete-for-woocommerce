<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Find orders for the inbox list, optionally filtered by an order number,
 * customer name or email search term.
 *
 * @param string $search
 * @param int    $limit
 * @return WC_Order[]
 */
function woodecor_inbox_query_orders($search = '', $limit = 20)
{
    $search = trim((string) $search);

    $args = array(
        'limit'   => $limit,
        'orderby' => 'date',
        'order'   => 'DESC',
        'return'  => 'objects',
    );

    if ($search !== '') {
        if (is_numeric($search)) {
            $order = wc_get_order((int) $search);
            if ($order) {
                return array($order);
            }
        }

        $args['meta_query'] = array(
            'relation' => 'OR',
            array('key' => '_billing_email', 'value' => $search, 'compare' => 'LIKE'),
            array('key' => '_billing_first_name', 'value' => $search, 'compare' => 'LIKE'),
            array('key' => '_billing_last_name', 'value' => $search, 'compare' => 'LIKE'),
        );
    }

    $orders = wc_get_orders($args);

    return is_array($orders) ? $orders : array();
}

/**
 * Best-effort display name for an order (billing name, falling back to email).
 *
 * @param WC_Order $order
 * @return string
 */
function woodecor_inbox_order_display_name($order)
{
    $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

    return $name !== '' ? $name : $order->get_billing_email();
}

/**
 * The stored communication history for an order.
 *
 * @param WC_Order $order
 * @return array
 */
function woodecor_get_communication_history($order)
{
    $history = $order->get_meta('_woodecor_communication_log', true);

    return is_array($history) ? $history : array();
}

/**
 * Append a communication log entry to an order and persist it.
 *
 * @param WC_Order $order
 * @param array    $entry
 */
function woodecor_add_communication_history_entry($order, $entry)
{
    $history   = woodecor_get_communication_history($order);
    $history[] = $entry;
    $order->update_meta_data('_woodecor_communication_log', $history);
    $order->save();
}

/**
 * Format a stored history entry for JSON output.
 *
 * @param array $entry
 * @return array
 */
function woodecor_format_history_entry($entry)
{
    $timestamp = isset($entry['time']) ? (int) $entry['time'] : time();

    return array(
        'time_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
        'subject'        => isset($entry['subject']) ? $entry['subject'] : '',
        'message'        => isset($entry['message']) ? $entry['message'] : '',
        'by'             => isset($entry['by']) ? $entry['by'] : '',
    );
}

/**
 * Top level menu callback function for the Order Communication inbox page.
 */
function woodecor_inbox_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $orders = woodecor_inbox_query_orders('', 20);
    ?>
    <div class="wrap woodecor-admin-wrap">
        <h1><?php esc_html_e('Order Communication', 'wtt-woo-auto-complete'); ?></h1>
        <p class="description"><?php esc_html_e('Message customers directly about their orders and keep a full history of every conversation.', 'wtt-woo-auto-complete'); ?></p>

        <div id="woodecor-inbox" class="woodecor-inbox">
            <div class="woodecor-inbox-list-pane">
                <div class="woodecor-inbox-search">
                    <input type="search" id="woodecor-inbox-search-input" placeholder="<?php esc_attr_e('Search by order #, name or email…', 'wtt-woo-auto-complete'); ?>" />
                </div>
                <ul id="woodecor-inbox-order-list" class="woodecor-inbox-order-list">
                    <?php foreach ($orders as $order) :
                        if (!$order instanceof WC_Order) {
                            continue;
                        }
                        ?>
                        <li class="woodecor-inbox-order-item" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <span class="woodecor-inbox-order-number">#<?php echo esc_html($order->get_order_number()); ?></span>
                            <span class="woodecor-inbox-order-name"><?php echo esc_html(woodecor_inbox_order_display_name($order)); ?></span>
                            <span class="woodecor-inbox-order-status woodecor-status-<?php echo esc_attr($order->get_status()); ?>"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($orders)) : ?>
                        <li class="woodecor-inbox-empty"><?php esc_html_e('No orders found.', 'wtt-woo-auto-complete'); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="woodecor-inbox-thread-pane">
                <div id="woodecor-inbox-placeholder" class="woodecor-inbox-placeholder">
                    <p><?php esc_html_e('Select an order from the list to start a conversation.', 'wtt-woo-auto-complete'); ?></p>
                </div>

                <div id="woodecor-inbox-thread" class="woodecor-inbox-thread" hidden>
                    <div class="woodecor-inbox-thread-header">
                        <h2 id="woodecor-inbox-thread-title"></h2>
                        <p id="woodecor-inbox-thread-meta" class="description"></p>
                    </div>

                    <div class="woodecor-inbox-compose">
                        <input type="text" id="woodecor-inbox-subject" placeholder="<?php esc_attr_e('Subject', 'wtt-woo-auto-complete'); ?>" />
                        <textarea id="woodecor-inbox-message" rows="3" placeholder="<?php esc_attr_e('Write a message to the customer…', 'wtt-woo-auto-complete'); ?>"></textarea>
                        <div class="woodecor-inbox-compose-actions">
                            <span id="woodecor-inbox-feedback" class="woodecor-inbox-feedback"></span>
                            <button type="button" id="woodecor-inbox-send" class="button button-primary"><?php esc_html_e('Send', 'wtt-woo-auto-complete'); ?></button>
                        </div>
                    </div>

                    <h3 class="woodecor-inbox-history-title"><?php esc_html_e('History', 'wtt-woo-auto-complete'); ?></h3>
                    <ul id="woodecor-inbox-history" class="woodecor-inbox-history"></ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * AJAX: search orders for the inbox list pane.
 */
add_action('wp_ajax_woodecor_inbox_search_orders', 'woodecor_inbox_search_orders');
function woodecor_inbox_search_orders()
{
    check_ajax_referer('woodecor_inbox_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You are not allowed to do this.', 'wtt-woo-auto-complete')), 403);
    }

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $orders = woodecor_inbox_query_orders($search, 25);

    $out = array();
    foreach ($orders as $order) {
        if (!$order instanceof WC_Order) {
            continue;
        }
        $out[] = array(
            'id'     => $order->get_id(),
            'number' => $order->get_order_number(),
            'name'   => woodecor_inbox_order_display_name($order),
            'status' => wc_get_order_status_name($order->get_status()),
        );
    }

    wp_send_json_success(array('orders' => $out));
}

/**
 * AJAX: load a single order's details and communication history.
 */
add_action('wp_ajax_woodecor_inbox_get_order', 'woodecor_inbox_get_order');
function woodecor_inbox_get_order()
{
    check_ajax_referer('woodecor_inbox_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You are not allowed to do this.', 'wtt-woo-auto-complete')), 403);
    }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order    = $order_id ? wc_get_order($order_id) : false;

    if (!$order) {
        wp_send_json_error(array('message' => __('Order not found.', 'wtt-woo-auto-complete')), 404);
    }

    $history = array_map('woodecor_format_history_entry', woodecor_get_communication_history($order));
    $history = array_reverse($history);

    wp_send_json_success(array(
        'order' => array(
            'id'     => $order->get_id(),
            'number' => $order->get_order_number(),
            'name'   => woodecor_inbox_order_display_name($order),
            'email'  => $order->get_billing_email(),
            'status' => wc_get_order_status_name($order->get_status()),
            'date'   => $order->get_date_created() ? date_i18n(get_option('date_format'), $order->get_date_created()->getTimestamp()) : '',
        ),
        'history' => $history,
    ));
}

/**
 * AJAX: send an instant message to the customer and log it to the order history.
 */
add_action('wp_ajax_woodecor_inbox_send_message', 'woodecor_inbox_send_message');
function woodecor_inbox_send_message()
{
    check_ajax_referer('woodecor_inbox_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You are not allowed to do this.', 'wtt-woo-auto-complete')), 403);
    }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $subject  = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
    $message  = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    $order = $order_id ? wc_get_order($order_id) : false;
    if (!$order) {
        wp_send_json_error(array('message' => __('Order not found.', 'wtt-woo-auto-complete')), 404);
    }

    if ($subject === '' || $message === '') {
        wp_send_json_error(array('message' => __('Please enter a subject and message.', 'wtt-woo-auto-complete')));
    }

    $to = $order->get_billing_email();
    if (!$to) {
        wp_send_json_error(array('message' => __('This order has no customer email address.', 'wtt-woo-auto-complete')));
    }

    $replacements = array(
        '{customer_name}' => $order->get_billing_first_name(),
        '{order_number}'  => $order->get_order_number(),
        '{order_status}'  => wc_get_order_status_name($order->get_status()),
    );
    $final_subject = strtr($subject, $replacements);
    $final_message = strtr($message, $replacements);

    $sent = wp_mail($to, $final_subject, wpautop(esc_html($final_message)), array('Content-Type: text/html; charset=UTF-8'));

    if (!$sent) {
        wp_send_json_error(array('message' => __('The email could not be sent.', 'wtt-woo-auto-complete')));
    }

    $user = wp_get_current_user();
    $entry = array(
        'time'    => time(),
        'subject' => $final_subject,
        'message' => $final_message,
        'to'      => $to,
        'by'      => $user ? $user->display_name : '',
    );
    woodecor_add_communication_history_entry($order, $entry);

    $order->add_order_note(sprintf(
        /* translators: 1: message subject, 2: staff member display name */
        __('Message sent to customer by %2$s: %1$s', 'wtt-woo-auto-complete'),
        $final_subject,
        $entry['by']
    ));

    wp_send_json_success(array('entry' => woodecor_format_history_entry($entry)));
}
