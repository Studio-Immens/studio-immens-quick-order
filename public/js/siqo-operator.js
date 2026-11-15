(function ($) {
    'use strict';

    var MOBILE_BP = 900;
    var refreshTimer = null;
    var resizeTimer = null;
    var wasMobile = null;
    var currency = new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR'
    });

    function installLegacyAliases() {
        var aliases = {
            FO_calc_totals: 'SIQO_calc_totals',
            FO_tab_Card_show: 'SIQO_tab_Card_show',
            FO_tab_Card_hide: 'SIQO_tab_Card_hide',
            FO_filter_tab_story: 'siqo_filter_tab_story',
            fo_filter_tab_story: 'siqo_filter_tab_story',
            FO_change_order_status: 'siqo_change_order_status',
            FO_clear_all_tables: 'siqo_clear_all_tables',
            FO_catering_show: 'SIQO_catering_show',
            FO_catering_hide: 'SIQO_catering_hide',
            FO_catering_save: 'SIQO_catering_save',
            FO_show_temp: 'SIQO_show_temp',
            fo_tab_add_product_to_order: 'siqo_tab_add_product_to_order',
            fo_tab_parse_price: 'siqo_tab_parse_price',
            fo_tab_parse_qty: 'siqo_tab_parse_qty',
            fo_tab_parse_temp: 'siqo_tab_parse_temp',
            fo_tab_prod_remove: 'siqo_tab_prod_remove',
            fo_tab_hystory_space: 'siqo_tab_hystory_space',
            fo_keyboard_price: 'siqo_keyboard_price',
            fo_keyboard_qty_value: 'siqo_keyboard_qty_value',
            fo_tab_price_ac: 'siqo_tab_price_ac',
            fo_tab_price_default: 'siqo_tab_price_default',
            fo_tab_price_del: 'siqo_tab_price_del',
            fo_change_order_status: 'siqo_change_order_status',
            fo_clear_all_tables: 'siqo_clear_all_tables',
            Show_FO_Front_Float: 'Show_SIQO_Front_Float',
            Hide_FO_Front_Float: 'Hide_SIQO_Front_Float',
            fop_show_order_info_tab: 'SIQOP_show_order_info_tab',
            fop_hide_order_info_tab: 'SIQOP_hide_order_info_tab',
            fop_stat_update: 'SIQOP_stat_update'
        };

        Object.keys(aliases).forEach(function (legacy) {
            var modern = aliases[legacy];
            if (typeof window[legacy] === 'undefined' && typeof window[modern] === 'function') {
                window[legacy] = window[modern];
            }
        });
    }

    installLegacyAliases();

    function isMobile() {
        return window.matchMedia('(max-width: ' + MOBILE_BP + 'px)').matches;
    }

    function text(value) {
        return $.trim(String(value == null ? '' : value));
    }

    function number(value) {
        var parsed = parseFloat(String(value == null ? '' : value).replace(',', '.'));
        return isNaN(parsed) ? 0 : parsed;
    }

    function statusLabel(status) {
        if (status === '1') {
            return 'Occupato';
        }
        if (status === '2') {
            return 'Da incassare';
        }
        return 'Libero';
    }

    function defaultStatuses() {
        $('#SIQO_Front_Content .fo_table_cell').each(function () {
            var $card = $(this);
            var status = text($card.attr('fotable_status'));

            if (status === '' || status === 'null' || status === 'undefined') {
                status = '0';
                $card.attr('fotable_status', '0');
            }

            var $status = $card.find('.fo_status_string').first();
            if ($status.length && text($status.text()) === '') {
                $status.text(statusLabel(status));
            }

            var tableName = text($card.attr('fotable')) || text($card.find('h6').first().text()) || 'Tavolo';
            $card.attr({
                role: 'button',
                tabindex: '0',
                'aria-label': 'Apri ' + tableName + ', ' + (text($status.text()) || statusLabel(status))
            });
        });
    }

    function updateSummary() {
        var free = 0;
        var busy = 0;

        $('#SIQO_Front_Content .fo_table_cell').each(function () {
            var status = text($(this).attr('fotable_status'));
            if (status === '1' || status === '2') {
                busy += 1;
            } else {
                free += 1;
            }
        });

        $('.siqo-count-free').text(free);
        $('.siqo-count-busy').text(busy);
    }

    function updateCartRows() {
        var totalQty = 0;
        var $cart = $('#SIQO_Front_Content .fo_column_riepilogo');
        var $items = $cart.find('.fo_tab_prod');

        $items.each(function () {
            var $item = $(this);
            var qty = number($item.find('[fo_tab_target="qty"]').first().val());
            var price = number($item.find('[fo_tab_target="price"]').first().val());

            if (qty <= 0) {
                qty = 1;
            }
            if (price <= 0) {
                price = number($item.attr('foprodtot'));
            }

            totalQty += qty;
            $item.attr({
                'data-qty-label': qty + '×',
                'data-price-label': currency.format(price * qty)
            });
        });

        $cart.toggleClass('siqo-cart-empty', $items.length === 0);

        var total = text($cart.find('.fo_tab_tool_total strong').first().text());
        if (total === '' || total === '-') {
            total = currency.format(0);
        }

        $('.siqo-footer-count').text(totalQty === 1 ? '1 articolo' : totalQty + ' articoli');
        $('.siqo-footer-total').text(total);

        return totalQty;
    }

    function updatePayButtonState() {
        var $pay = $('.FO_flash_tab_footer .fo_button_fix[onclick*="SIQO_tab_go_order"]');
        var empty = $('#SIQO_Front_Content .fo_column_riepilogo').hasClass('siqo-cart-empty');

        $pay.toggleClass('siqo-pay-disabled', empty).attr('aria-disabled', empty ? 'true' : 'false');
        if (empty) {
            $pay.attr('title', 'Aggiungi almeno un articolo');
        } else {
            $pay.removeAttr('title');
        }
    }

    function updateVariantState() {
        var $col = $('#SIQO_Front_Content .fo_tab_col_2');
        if (!$col.length) {
            return;
        }

        var actual = text($('#SIQO_Front_Content .fo_actual_prod').text());
        $col.toggleClass('siqo-variants-empty', actual === '');
    }

    function normalizeQtyLabel() {
        $('#SIQO_Front_Content .fo_tab_col_2 .FO_flash_tab_qty').each(function () {
            $(this).contents().each(function () {
                if (this.nodeType === 3 && /Quantit/i.test(this.nodeValue)) {
                    this.nodeValue = 'Quantità ';
                }
            });
        });
    }

    function normalizeMetaLabels() {
        $('#SIQO_Front_Content .siqo-sheet-head strong, #SIQO_Front_Content .fo_tab_col_2 .fovariant > strong').each(function () {
            var current = text($(this).text());
            if (/^varianti/i.test(current)) {
                $(this).text('Varianti e quantità');
            } else if (/^siqo_temperatures$/i.test(current)) {
                $(this).text('Temperatura');
            } else if (/^opzione$/i.test(current)) {
                $(this).text('Opzione');
            }
        });
    }

    function normalizeCartTotal() {
        $('#SIQO_Front_Content .fo_column_riepilogo .fo_tab_tool_total strong').each(function () {
            var raw = text($(this).text()).replace(/[^0-9.,\-]/g, '');
            if (raw.indexOf(',') !== -1) {
                raw = raw.replace(/\./g, '').replace(',', '.');
            }
            var amount = parseFloat(raw);
            if (!isFinite(amount)) {
                return;
            }

            var formatted = new Intl.NumberFormat('it-IT', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);

            if (text($(this).text()) !== formatted) {
                $(this).text(formatted);
            }
        });
    }

    function observeCartTotal() {
        var node = document.querySelector('#SIQO_Front_Content .fo_column_riepilogo .fo_tab_tool_total');
        if (!node || node.getAttribute('data-siqo-observed') === '1') {
            return;
        }

        node.setAttribute('data-siqo-observed', '1');
        new MutationObserver(normalizeCartTotal).observe(node, {
            childList: true,
            characterData: true,
            subtree: true
        });
    }

    function updateStoryState() {
        var $story = $('#SIQO_Front_Content .fo_column_story');
        if (!$story.length) {
            return;
        }

        var visible = $story.find('.fo_story').filter(function () {
            return $(this).css('display') !== 'none' && text($(this).text()) !== '';
        }).length;

        $story.toggleClass('siqo-story-empty', visible === 0);
    }

    function ensureFooterSummary() {
        if ($('.siqo-footer-summary').length) {
            return;
        }

        var $summary = $(
            '<div class="siqo-footer-summary" aria-live="polite">' +
                '<span class="siqo-footer-count">0 articoli</span>' +
                '<strong class="siqo-footer-total">€ 0,00</strong>' +
            '</div>'
        );
        var $primary = $('.FO_flash_tab_footer [fo_order_type="new_order"]').first();

        if ($primary.length) {
            $primary.before($summary);
        } else {
            $('.FO_flash_tab_footer').first().append($summary);
        }
    }

    function ensureSheetChrome() {
        var $col2 = $('#SIQO_Front_Content .fo_tab_col_2');
        var $col3 = $('#SIQO_Front_Content .fo_tab_col_3');

        if ($col2.length && !$col2.attr('data-siqo-sheet')) {
            $col2.attr('data-siqo-sheet', '1').prepend(
                '<div class="siqo-sheet-head"><strong>Varianti e quantità</strong><button type="button" class="siqo-sheet-close" data-siqo-close>Chiudi</button></div>'
            );
        }

        if ($col3.length && !$col3.attr('data-siqo-sheet')) {
            $col3.attr('data-siqo-sheet', '1').prepend(
                '<div class="siqo-sheet-head"><strong>Tastierino prezzo</strong><button type="button" class="siqo-sheet-close" data-siqo-close>Chiudi</button></div>'
            );
        }

        var $modal = $('.FO_flash_tab_order_container').first();
        if ($modal.length && !$modal.find('.siqo-sheet-backdrop').length) {
            $modal.append('<button type="button" class="siqo-sheet-backdrop" data-siqo-backdrop aria-label="Chiudi pannello"></button>');
        }
    }

    function syncSheets() {
        var show2 = text($('.fo_tab_col_2').attr('fo_show')) === 'show';
        var show3 = text($('.fo_tab_col_3').attr('fo_show')) === 'show';
        var open = isMobile() && (show2 || show3);

        $('body').toggleClass('siqo-sheet-open', open);
        $('.fo_variant_show_button').attr('aria-expanded', show2 ? 'true' : 'false');
        $('.fo_pay_show_keyboard_button').attr('aria-expanded', show3 ? 'true' : 'false');
    }

    function closeSheets() {
        var $cols = $('.fo_tab_col_2, .fo_tab_col_3');

        $cols.attr('fo_show', 'hide');

        if (isMobile()) {
            $cols.hide();
        } else {
            $cols.css('display', '');
        }

        $('body').removeClass('siqo-sheet-open');
        $('.fo_variant_show_button, .fo_pay_show_keyboard_button').attr('aria-expanded', 'false');
    }

    function initSheets() {
        $('#SIQO_Front_Content .fo_tab_col_2, #SIQO_Front_Content .fo_tab_col_3').each(function () {
            if (!$(this).attr('fo_show')) {
                $(this).attr('fo_show', 'hide');
            }
        });

        ensureSheetChrome();
        syncSheets();
    }

    function makeAccessible() {
        $('#SIQO_Front_Content .fo_table_cell').each(function () {
            var $card = $(this);
            if (!$card.attr('role')) {
                $card.attr({ role: 'button', tabindex: '0' });
            }
        });

        $('#SIQO_Front_Content .fo_tab_prod').each(function () {
            var $tile = $(this);
            $tile.find('img, svg').attr('draggable', 'false');
            if (!$tile.attr('role')) {
                var label = text($tile.find('.FO_prod_name_manage').first().text()) || 'Prodotto';
                $tile.attr({
                    role: 'button',
                    tabindex: '0',
                    'aria-label': label
                });
            }
        });

        $('#SIQO_Front_Content .fo_cat_butt').each(function () {
            var $button = $(this);
            if (!$button.attr('role')) {
                var label = text($button.text()) || 'Categoria';
                $button.attr({
                    role: 'button',
                    tabindex: '0',
                    'aria-label': label
                });
            }
        });

        if (!$('#SIQO_Front_Content .fo_cat_butt.siqo-active').length) {
            $('#SIQO_Front_Content .fo_cat_butt').first().addClass('siqo-active');
        }
    }

    function setActiveCategory(target) {
        var $button = $(target).closest('.fo_cat_butt');
        if (!$button.length) {
            return;
        }

        $('#SIQO_Front_Content .fo_cat_butt').removeClass('siqo-active');
        $button.addClass('siqo-active');
    }

    function refresh() {
        defaultStatuses();
        updateSummary();
        ensureFooterSummary();
        normalizeQtyLabel();
        normalizeMetaLabels();
        normalizeCartTotal();
        observeCartTotal();
        updateCartRows();
        updatePayButtonState();
        updateVariantState();
        updateStoryState();
        makeAccessible();
    }

    function scheduleRefresh() {
        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(refresh, 80);
    }

    function wrapGlobal(name, before, after) {
        var original = window[name];

        window[name] = function () {
            if (typeof before === 'function') {
                before.apply(this, arguments);
            }

            var result;
            if (typeof original === 'function') {
                result = original.apply(this, arguments);
            }

            if (typeof after === 'function') {
                after.apply(this, arguments);
            }

            return result;
        };
    }

    $(function () {
        installLegacyAliases();
        document.documentElement.classList.add('siqo-operator-mode');
        $('body').addClass('siqo-operator-mode');

        defaultStatuses();
        ensureFooterSummary();
        initSheets();
        refresh();
        wasMobile = isMobile();

        wrapGlobal('SIQO_tab_Card_show', function () {
            $('body').addClass('siqo-table-open');
            closeSheets();
        }, function () {
            window.setTimeout(refresh, 80);
        });

        wrapGlobal('SIQO_tab_Card_hide', null, function () {
            $('body').removeClass('siqo-table-open');
            closeSheets();
            window.setTimeout(refresh, 50);
        });

        wrapGlobal('SIQO_tab_go_order', null, function () {
            $('body').removeClass('siqo-table-open');
            closeSheets();
        });

        wrapGlobal('fo_tab_show_keyboard_button', null, function () {
            window.setTimeout(syncSheets, 0);
        });

        wrapGlobal('fo_tab_show_variation_button', null, function () {
            window.setTimeout(syncSheets, 0);
        });

        document.addEventListener('click', function (event) {
            if (event.target.closest('.siqo-pay-disabled')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            var close = event.target.closest('[data-siqo-close]');
            if (close) {
                event.preventDefault();
                event.stopPropagation();
                closeSheets();
                return;
            }

            var backdrop = event.target.closest('[data-siqo-backdrop]');
            if (backdrop) {
                event.preventDefault();
                closeSheets();
                return;
            }

            if (event.target.closest('.fo_cat_butt')) {
                setActiveCategory(event.target);
            }
        }, true);

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            var target = event.target.closest('.fo_table_cell, .fo_tab_prod, .fo_cat_butt');
            if (!target) {
                return;
            }

            event.preventDefault();
            target.click();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && $('body').hasClass('siqo-sheet-open')) {
                closeSheets();
            }
        });

        $(document).on('ajaxComplete', scheduleRefresh);

        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                var meaningful = mutations.some(function (mutation) {
                    return mutation.type === 'childList' ||
                        (mutation.type === 'attributes' &&
                            (mutation.attributeName === 'fotable_status' || mutation.attributeName === 'fo_show'));
                });

                if (meaningful) {
                    scheduleRefresh();
                }
            });

            var target = document.getElementById('SIQO_Front_Content');
            if (target) {
                observer.observe(target, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['fotable_status', 'fo_show']
                });
            }
        }

        $(window).on('resize orientationchange', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                var mobile = isMobile();

                if (mobile !== wasMobile) {
                    closeSheets();
                    wasMobile = mobile;
                }

                syncSheets();
                refresh();
            }, 120);
        });
    });
})(jQuery);
