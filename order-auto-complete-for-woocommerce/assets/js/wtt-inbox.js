(function ($) {
    'use strict';

    var currentOrderId = null;
    var searchTimer = null;

    function renderOrderList(orders) {
        var $list = $('#woodecor-inbox-order-list');
        $list.empty();

        if (!orders.length) {
            $list.append('<li class="woodecor-inbox-empty"></li>');
            $list.find('.woodecor-inbox-empty').text(woodecorInbox.i18n.noOrders);
            return;
        }

        orders.forEach(function (order) {
            var $item = $('<li class="woodecor-inbox-order-item"></li>').attr('data-order-id', order.id);
            $('<span class="woodecor-inbox-order-number"></span>').text('#' + order.number).appendTo($item);
            $('<span class="woodecor-inbox-order-name"></span>').text(order.name).appendTo($item);
            $('<span class="woodecor-inbox-order-status"></span>').text(order.status).appendTo($item);
            $list.append($item);
        });
    }

    function historyItem(entry) {
        var $entry = $('<li class="woodecor-inbox-history-item"></li>');
        var $meta = $('<div class="woodecor-inbox-history-meta"></div>');
        $('<strong></strong>').text(entry.subject).appendTo($meta);
        $('<span></span>').text(entry.time_formatted).appendTo($meta);
        $entry.append($meta);
        $('<div class="woodecor-inbox-history-message"></div>').text(entry.message).appendTo($entry);
        if (entry.by) {
            $('<div class="woodecor-inbox-history-by"></div>').text(woodecorInbox.i18n.by + ' ' + entry.by).appendTo($entry);
        }
        return $entry;
    }

    function renderHistory(history) {
        var $history = $('#woodecor-inbox-history');
        $history.empty();

        if (!history.length) {
            $history.append('<li class="woodecor-inbox-empty"></li>');
            $history.find('.woodecor-inbox-empty').text(woodecorInbox.i18n.noHistory);
            return;
        }

        history.forEach(function (entry) {
            $history.append(historyItem(entry));
        });
    }

    function loadOrder(orderId) {
        currentOrderId = orderId;
        $('.woodecor-inbox-order-item').removeClass('is-active');
        $('.woodecor-inbox-order-item[data-order-id="' + orderId + '"]').addClass('is-active');

        $.post(woodecorInbox.ajaxUrl, {
            action: 'woodecor_inbox_get_order',
            nonce: woodecorInbox.nonce,
            order_id: orderId
        }).done(function (response) {
            if (!response.success) {
                return;
            }
            var order = response.data.order;
            $('#woodecor-inbox-placeholder').attr('hidden', true);
            $('#woodecor-inbox-thread').removeAttr('hidden');
            $('#woodecor-inbox-thread-title').text('#' + order.number + ' — ' + order.name);
            $('#woodecor-inbox-thread-meta').text(order.email + ' • ' + order.status + ' • ' + order.date);
            $('#woodecor-inbox-subject').val('');
            $('#woodecor-inbox-message').val('');
            $('#woodecor-inbox-feedback').text('').removeClass('is-error');
            renderHistory(response.data.history);
        });
    }

    function sendMessage() {
        if (!currentOrderId) {
            return;
        }
        var subject = $('#woodecor-inbox-subject').val().trim();
        var message = $('#woodecor-inbox-message').val().trim();
        var $feedback = $('#woodecor-inbox-feedback');
        var $button = $('#woodecor-inbox-send');

        if (!subject || !message) {
            $feedback.text(woodecorInbox.i18n.error).addClass('is-error');
            return;
        }

        $button.prop('disabled', true).text(woodecorInbox.i18n.sending);
        $feedback.text('').removeClass('is-error');

        $.post(woodecorInbox.ajaxUrl, {
            action: 'woodecor_inbox_send_message',
            nonce: woodecorInbox.nonce,
            order_id: currentOrderId,
            subject: subject,
            message: message
        }).done(function (response) {
            $button.prop('disabled', false).text(woodecorInbox.i18n.send);
            if (!response.success) {
                $feedback.text((response.data && response.data.message) || woodecorInbox.i18n.error).addClass('is-error');
                return;
            }
            $feedback.text(woodecorInbox.i18n.sent);
            $('#woodecor-inbox-subject').val('');
            $('#woodecor-inbox-message').val('');
            var $history = $('#woodecor-inbox-history');
            $history.find('.woodecor-inbox-empty').remove();
            $history.prepend(historyItem(response.data.entry));
        }).fail(function () {
            $button.prop('disabled', false).text(woodecorInbox.i18n.send);
            $feedback.text(woodecorInbox.i18n.error).addClass('is-error');
        });
    }

    $(function () {
        $('#woodecor-inbox-order-list').on('click', '.woodecor-inbox-order-item', function () {
            var orderId = $(this).data('order-id');
            if (orderId) {
                loadOrder(orderId);
            }
        });

        $('#woodecor-inbox-send').on('click', sendMessage);

        $('#woodecor-inbox-search-input').on('input', function () {
            var term = $(this).val();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                $.post(woodecorInbox.ajaxUrl, {
                    action: 'woodecor_inbox_search_orders',
                    nonce: woodecorInbox.nonce,
                    search: term
                }).done(function (response) {
                    if (response.success) {
                        renderOrderList(response.data.orders);
                    }
                });
            }, 300);
        });
    });
})(jQuery);
