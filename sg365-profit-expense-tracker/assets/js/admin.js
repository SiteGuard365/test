window.WCPI = window.WCPI || {};
(function($){
    function esc(value){
        return $('<div>').text(value == null ? '' : value).html();
    }

    function currencyFormatter(){
        var currency = (window.wcpiAdmin && wcpiAdmin.currency) || {};
        if(window.Intl && currency.code){
            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: currency.code,
                    minimumFractionDigits: currency.decimals != null ? currency.decimals : 2,
                    maximumFractionDigits: currency.decimals != null ? currency.decimals : 2
                });
            } catch (e) {}
        }
        return null;
    }

    var formatter = currencyFormatter();

    function money(value){
        var amount = Number(value || 0);
        if(formatter){
            return formatter.format(amount);
        }

        var currency = (window.wcpiAdmin && wcpiAdmin.currency) || {};
        var decimals = currency.decimals != null ? currency.decimals : 2;
        var fixed = amount.toFixed(decimals);
        return (currency.symbol || '$') + fixed;
    }

    function numberValue(value, decimals){
        return Number(value || 0).toFixed(decimals == null ? 2 : decimals);
    }

    function percent(value){
        return Number(value || 0).toFixed(1) + '%';
    }

    function truncateText(value, limit){
        var text = String(value || '');
        if(text.length <= limit){
            return text;
        }
        return text.substring(0, Math.max(0, limit - 3)) + '...';
    }

    function noteStatusTone(status){
        if(status === 'ended'){
            return 'warning';
        }
        if(status === 'hidden'){
            return 'neutral';
        }
        return 'success';
    }

    function encodeNotePayload(item){
        try {
            return encodeURIComponent(JSON.stringify(item || {}));
        } catch (e){
            return '';
        }
    }

    function decodeNotePayload(value){
        if(!value){
            return null;
        }
        try {
            return JSON.parse(decodeURIComponent(String(value)));
        } catch (e){
            return null;
        }
    }

    function noteEmailSummary(item){
        if(Number(item && item.email_enabled || 0) === 1 && item && item.email_recipient){
            return 'Email to ' + item.email_recipient;
        }
        return 'No email notification';
    }

    function noteStatusMarkup(item){
        var status = (item && item.note_status) || 'active';
        if(status === 'active'){
            return '';
        }
        return '<span class="wcpi-badge wcpi-badge-' + esc(noteStatusTone(status)) + '">' + esc(item.status_label || (status === 'hidden' ? (wcpiAdmin.i18n.removedStatus || 'Removed') : (wcpiAdmin.i18n.endedStatus || 'Ended'))) + '</span>';
    }

    function noteInfoBadges(item){
        var categoryLabel = item.category_label || ((wcpiAdmin.noteCategories || {})[item.note_category] || 'Update');
        var html = '<span class="wcpi-badge wcpi-badge-neutral">' + esc(categoryLabel) + '</span>';
        html += noteStatusMarkup(item);
        if(item.note_tag){
            html += '<span class="wcpi-badge wcpi-badge-neutral">' + esc(item.note_tag) + '</span>';
        }
        if(Number(item.email_enabled || 0) === 1){
            html += '<span class="wcpi-badge wcpi-badge-success">Email</span>';
        }
        return html;
    }

    function activeNoteMarkup(item){
        var categoryLabel = item.category_label || ((wcpiAdmin.noteCategories || {})[item.note_category] || 'Update');
        return '<article class="wcpi-active-note wcpi-note-theme-' + esc(item.note_color || 'blue') + '" data-note-id="' + esc(item.id || 0) + '" data-note-status="' + esc(item.note_status || 'active') + '">' +
            '<div class="wcpi-active-note-top"><strong>' + esc(item.note_title || '') + '</strong><button type="button" class="wcpi-note-menu" aria-label="Note actions" data-note="' + esc(encodeNotePayload(item)) + '"><span class="dashicons dashicons-ellipsis"></span></button></div>' +
            '<p>' + esc(item.note_body || '') + '</p>' +
            '<div class="wcpi-active-note-footer"><small>' + esc(item.date_range_label || '') + '</small><span class="wcpi-active-note-chip">' + esc(categoryLabel) + '</span></div>' +
        '</article>';
    }

    function setActiveNoteCountClass($strip, count){
        $strip.removeClass('wcpi-active-notes-count-1 wcpi-active-notes-count-2 wcpi-active-notes-count-3 wcpi-active-notes-count-4');
        if(count > 0){
            $strip.addClass('wcpi-active-notes-count-' + Math.max(1, Math.min(4, count)));
        }
    }

    function syncDashboardNoteSurfaces(payload){
        var notes = payload && payload.notes ? payload.notes : [];
        var activeNotes = payload && payload.active_notes ? payload.active_notes : [];

        if($('#wcpi-dashboard-notes-list').length){
            $('#wcpi-dashboard-notes-list').html(WCPI.renderNotes(notes));
        }

        if($('#wcpi-dashboard-active-notes-board').length){
            $('#wcpi-dashboard-active-notes-board').html(WCPI.renderNotes(notes.length ? notes : activeNotes));
        }

        WCPI.syncActiveNotesStrip(activeNotes);

        if($('#wcpi-dashboard-notes-count').length){
            $('#wcpi-dashboard-notes-count').text(activeNotes.length || 0);
        }
    }

    function ensureToast(){
        if(!$('#wcpi-toast').length){
            $('body').append('<div id="wcpi-toast" class="wcpi-toast" role="status" aria-live="polite"></div>');
        }
        return $('#wcpi-toast');
    }

    function showToast(message, tone){
        var $toast = ensureToast();
        $toast.removeClass('is-success is-error is-visible').addClass('is-visible ' + (tone === 'error' ? 'is-error' : 'is-success')).text(message || wcpiAdmin.i18n.saved);
        window.clearTimeout($toast.data('timer'));
        $toast.data('timer', window.setTimeout(function(){
            $toast.removeClass('is-visible');
        }, 2800));
    }

    function currentUrl(){
        try {
            return new URL(window.location.href);
        } catch (e){
            return null;
        }
    }

    function updateUrlParam(key, value, replace){
        var url = currentUrl();
        if(!url){
            return;
        }

        if(value == null || value === ''){
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }

        if(replace !== false && window.history && window.history.replaceState){
            window.history.replaceState({}, '', url.toString());
        }
    }

    function urlParam(key){
        var url = currentUrl();
        return url ? url.searchParams.get(key) : null;
    }

    function trendMarkup(trend, inverse){
        if(!trend){
            return '';
        }
        var tone = inverse ? (trend.direction === 'up' ? 'negative' : 'positive') : (trend.tone || 'positive');
        return '<span class="wcpi-trend-pill wcpi-trend-' + esc(tone) + '">' + (trend.direction === 'down' ? '&darr;' : '&uarr;') + ' ' + esc((trend.percentage >= 0 ? '+' : '') + Number(trend.percentage || 0).toFixed(1)) + '%</span>';
    }

    function recommendationTone(item){
        var type = item && (item.type || item.severity || 'info');
        if(type === 'danger' || type === 'critical'){
            return 'alert';
        }
        if(type === 'warning'){
            return 'warning';
        }
        if(type === 'success' || type === 'opportunity'){
            return 'success';
        }
        return 'info';
    }

    function expenseDeleteUrl(id){
        return (wcpiAdmin.postUrl || '') + '?action=wcpi_delete_expense&expense_id=' + encodeURIComponent(id) + '&_wcpi_nonce=' + encodeURIComponent((wcpiAdmin.nonces || {}).deleteExpense || '');
    }

    function costStatusBadges(status, lowMargin, variationGap){
        var labels = {
            healthy: wcpiAdmin.i18n.healthy || 'Healthy',
            warning: wcpiAdmin.i18n.zeroCost || 'Zero Cost',
            missing: wcpiAdmin.i18n.missingCost || 'Missing Cost',
            incomplete: wcpiAdmin.i18n.incompleteRow || 'Incomplete Row'
        };
        var tones = {
            healthy: 'success',
            warning: 'warning',
            missing: 'danger',
            incomplete: 'warning'
        };
        var html = '<span class="wcpi-badge wcpi-badge-' + esc(tones[status] || 'neutral') + '">' + esc(labels[status] || labels.healthy) + '</span>';
        if(lowMargin){
            html += '<span class="wcpi-badge wcpi-badge-warning">' + esc(wcpiAdmin.i18n.lowMargin || 'Low Margin') + '</span>';
        }
        if(variationGap){
            html += '<span class="wcpi-badge wcpi-badge-neutral">' + esc(wcpiAdmin.i18n.variationGap || 'Variation Gap') + '</span>';
        }
        return html;
    }

    function refreshCostRow($row){
        var total = 0;
        $row.find('.wcpi-cost-part').each(function(){
            total += parseFloat($(this).val() || 0);
        });

        var price = parseFloat($row.data('price') || 0);
        var threshold = parseFloat($row.data('margin-threshold') || 0);
        var hasRow = String($row.attr('data-has-row') || '') === '1';
        var variationGap = String($row.attr('data-variation-gap') || '') === '1';
        var margin = price > 0 && total > 0 ? (((price - total) / price) * 100) : 0;
        var lowMargin = price > 0 && total > 0 && margin < threshold;
        var status = 'healthy';

        $row.find('.wcpi-total-cost').val(total.toFixed(6));

        if(!hasRow && total <= 0){
            status = 'missing';
        } else if(total <= 0){
            status = 'warning';
        }

        $row.removeClass('wcpi-row-healthy wcpi-row-warning wcpi-row-missing wcpi-row-incomplete').addClass('wcpi-row-' + status);
        $row.find('.wcpi-status-stack').html(costStatusBadges(status, lowMargin, variationGap));
        $row.find('.wcpi-est-margin').text(price > 0 && total > 0 ? percent(margin) : '--');
        $row.addClass('is-dirty');
        $row.find('.wcpi-row-state').text(wcpiAdmin.i18n.unsavedState || 'Unsaved').removeClass('wcpi-badge-neutral wcpi-badge-success').addClass('wcpi-badge-warning');
    }

    WCPI.esc = esc;
    WCPI.money = money;
    WCPI.numberValue = numberValue;
    WCPI.percent = percent;
    WCPI.showToast = showToast;
    WCPI.updateUrlParam = updateUrlParam;
    WCPI.urlParam = urlParam;

    WCPI.initTabs = function(root, options){
        var $root = $(root);
        if(!$root.length){
            return null;
        }
        var syncInputSelector = $root.data('syncInput') || '';

        var settings = $.extend({
            queryKey: $root.data('queryKey') || 'tab',
            defaultTab: $root.data('defaultTab') || '',
            buttonSelector: '[data-tab-target]',
            panelSelector: '[data-tab-panel]',
            onChange: null
        }, options || {});

        var $buttons = $root.find(settings.buttonSelector);
        var $panels = $root.find(settings.panelSelector);
        if(!$buttons.length){
            return null;
        }

        if(!settings.defaultTab){
            settings.defaultTab = $buttons.first().attr('data-tab-target');
        }

        function activate(tab, sync){
            var target = tab;
            if(!target || !$buttons.filter('[data-tab-target="' + target + '"]').length){
                target = settings.defaultTab;
            }

            $buttons.each(function(){
                var $button = $(this);
                var active = $button.attr('data-tab-target') === target;
                $button.toggleClass('is-active', active).attr('aria-selected', active ? 'true' : 'false').attr('tabindex', active ? '0' : '-1');
            });

            $panels.each(function(){
                var $panel = $(this);
                var active = $panel.attr('data-tab-panel') === target;
                $panel.prop('hidden', !active).attr('aria-hidden', active ? 'false' : 'true');
            });

            if(syncInputSelector){
                $(syncInputSelector).val(target);
            }

            $root.attr('data-active-tab', target);

            if(sync !== false){
                updateUrlParam(settings.queryKey, target, true);
            }

            if(typeof settings.onChange === 'function'){
                settings.onChange(target, $root);
            }
        }

        $buttons.off('click.wcpiTabs').on('click.wcpiTabs', function(event){
            event.preventDefault();
            activate($(this).attr('data-tab-target'));
        });

        activate(urlParam(settings.queryKey) || $root.data('activeTab') || settings.defaultTab, false);

        return {
            activate: activate,
            current: function(){
                return $root.attr('data-active-tab') || settings.defaultTab;
            }
        };
    };

    WCPI.renderTable = function(rows){
        var html = '<div class="wcpi-table-wrap"><table class="widefat wcpi-table"><thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin %</th><th>Health</th></tr></thead><tbody>';
        if(!rows || !rows.length){
            html += '<tr><td colspan="8"><div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div></td></tr>';
        } else {
            rows.forEach(function(row){
                var profitClass = parseFloat(row.profit) >= 0 ? 'wcpi-text-success' : 'wcpi-text-danger';
                var healthMeta = row.health_label ? '<small>' + esc(row.health_label) + '</small>' : '';
                html += '<tr>' +
                    '<td><strong>' + esc(row.name || '') + '</strong></td>' +
                    '<td>' + esc(row.sku || '') + '</td>' +
                    '<td>' + esc(row.quantity_sold || 0) + '</td>' +
                    '<td>' + esc(money(row.revenue)) + '</td>' +
                    '<td>' + esc(money(row.cost)) + '</td>' +
                    '<td class="' + profitClass + '">' + esc(money(row.profit)) + '</td>' +
                    '<td><span class="wcpi-metric-chip">' + esc(percent(row.margin_percent)) + '</span></td>' +
                    '<td><span class="wcpi-badge wcpi-badge-neutral">' + esc(row.health_score || 0) + '</span>' + healthMeta + '</td>' +
                '</tr>';
            });
        }
        html += '</tbody></table></div>';
        return html;
    };

    WCPI.renderSummaryTable = function(rows){
        var html = '<div class="wcpi-table-wrap"><table class="widefat wcpi-table"><thead><tr><th>Date</th><th>Orders</th><th>Items</th><th>Revenue</th><th>Expenses</th><th>Net Profit</th><th>Margin %</th></tr></thead><tbody>';
        if(!rows || !rows.length){
            html += '<tr><td colspan="7"><div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div></td></tr>';
        } else {
            rows.forEach(function(row){
                html += '<tr>' +
                    '<td>' + esc(row.summary_date) + '</td>' +
                    '<td>' + esc(row.orders_count) + '</td>' +
                    '<td>' + esc(row.items_sold) + '</td>' +
                    '<td>' + esc(money(row.gross_revenue)) + '</td>' +
                    '<td>' + esc(money(row.expenses_total)) + '</td>' +
                    '<td>' + esc(money(row.net_profit)) + '</td>' +
                    '<td>' + esc(percent(row.margin_percent)) + '</td>' +
                '</tr>';
            });
        }
        html += '</tbody></table></div>';
        return html;
    };

    WCPI.renderCategoryTable = function(rows){
        var html = '<div class="wcpi-table-wrap"><table class="widefat wcpi-table"><thead><tr><th>Category</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin %</th></tr></thead><tbody>';
        if(!rows || !rows.length){
            html += '<tr><td colspan="6"><div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div></td></tr>';
        } else {
            rows.forEach(function(row){
                html += '<tr><td><strong>' + esc(row.category) + '</strong></td><td>' + esc(Number(row.qty_sold || 0).toFixed(2)) + '</td><td>' + esc(money(row.revenue)) + '</td><td>' + esc(money(row.cost)) + '</td><td>' + esc(money(row.profit)) + '</td><td><span class="wcpi-heatmap-pill wcpi-heatmap-' + esc(row.heatmap_band || 'watch') + '">' + esc(percent(row.margin_percent)) + '</span></td></tr>';
            });
        }
        html += '</tbody></table></div>';
        return html;
    };

    WCPI.renderRecommendations = function(items){
        if(!items || !items.length){
            return '<div class="wcpi-empty-inline">No recommendations for this range.</div>';
        }
        return items.map(function(item){
            var cta = item.cta_url ? '<p><a class="button button-small" href="' + esc(item.cta_url) + '">' + esc(item.cta_label || 'Open') + '</a></p>' : '';
            return '<article class="wcpi-recommendation wcpi-rec-' + esc(recommendationTone(item)) + '"><strong>' + esc(item.title) + '</strong><p>' + esc(item.body) + '</p>' + cta + '</article>';
        }).join('');
    };

    WCPI.renderAudit = function(audit){
        if(!audit){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }
        return '<div class="wcpi-audit-block">' +
            '<div class="wcpi-progress"><span style="width:' + Math.max(4, parseFloat(audit.completeness_percent || 0)) + '%"></span></div>' +
            '<p><strong>' + esc(percent(audit.completeness_percent)) + '</strong> cost completeness</p>' +
            '<ul class="wcpi-bulletless">' +
            '<li>' + esc(audit.products_with_costs || 0) + ' products with costs</li>' +
            '<li>' + esc(audit.missing_products || 0) + ' products missing cost</li>' +
            '<li>' + esc(audit.zero_cost_products || 0) + ' zero-cost products</li>' +
            '<li>' + esc(audit.variation_missing || audit.variations_missing || 0) + ' variations missing cost rows</li>' +
            '<li>' + esc(audit.incomplete_rows || 0) + ' incomplete cost rows</li>' +
            '</ul>' +
            (audit.review_url ? '<p><a class="button button-small" href="' + esc(audit.review_url) + '">Review Missing Costs</a></p>' : '') +
        '</div>';
    };

    WCPI.renderOverviewCards = function(payload){
        if(!payload){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }

        var primary = [
            { label: 'Revenue', value: money(payload.revenue), trend: payload.comparison ? payload.comparison.revenue : null, helper: 'Top-line sales for the selected range' },
            { label: 'Net Profit', value: money(payload.net_profit), trend: payload.comparison ? payload.comparison.net_profit : null, helper: 'After product costs and expenses' },
            { label: 'Expenses', value: money(payload.expenses), trend: payload.comparison ? payload.comparison.expenses : null, helper: 'Tracked operating spend', inverse: true },
            { label: 'Margin %', value: percent(payload.margin_percent), trend: payload.comparison ? payload.comparison.margin_percent : null, helper: 'Net profit quality' },
            { label: 'Orders', value: numberValue(payload.orders_count, 0), trend: payload.comparison ? payload.comparison.orders_count : null, helper: 'Paid and processing orders' },
            { label: 'Refunds', value: money(payload.refunds_total), trend: payload.comparison ? payload.comparison.refunds_total : null, helper: 'Refund impact this range', inverse: true }
        ];
        var secondary = [
            { label: 'Average Order Value', value: money(payload.aov), trend: payload.comparison ? payload.comparison.aov : null, helper: 'Average revenue per order' },
            { label: 'Units Sold', value: numberValue(payload.units_sold, 0), trend: payload.comparison ? payload.comparison.units_sold : null, helper: 'Total units sold' },
            { label: 'Gross Profit', value: money(payload.gross_profit), trend: payload.comparison ? payload.comparison.gross_profit : null, helper: 'Before operating expenses' }
        ];

        function cardMarkup(card, accent){
            return '<section class="wcpi-stat-card ' + accent + '"><span class="wcpi-stat-label">' + esc(card.label) + '</span><strong class="wcpi-stat-value">' + esc(card.value) + '</strong>' + trendMarkup(card.trend, card.inverse) + '<span class="wcpi-stat-helper">' + esc(card.helper || '') + '</span></section>';
        }

        return '<div class="wcpi-stat-grid wcpi-stat-grid-6 wcpi-primary-stats">' + primary.map(function(card){ return cardMarkup(card, 'wcpi-card-primary'); }).join('') + '</div>' +
            '<div class="wcpi-stat-grid wcpi-stat-grid-3 wcpi-secondary-stats">' + secondary.map(function(card){ return cardMarkup(card, 'wcpi-card-secondary'); }).join('') + '</div>';
    };

    WCPI.renderGoals = function(goals){
        if(!goals || !goals.length){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }
        return goals.map(function(goal){
            var actual = goal.format === 'percent' ? percent(goal.actual) : money(goal.actual);
            var target = goal.format === 'percent' ? percent(goal.goal) : money(goal.goal);
            var tone = Number(goal.progress || 0) >= 100 ? 'success' : 'neutral';
            return '<div class="wcpi-goal"><div class="wcpi-goal-head"><strong>' + esc(goal.label) + '</strong><span class="wcpi-badge wcpi-badge-' + tone + '">' + esc(goal.status || '') + '</span></div><div class="wcpi-progress"><span style="width:' + Math.max(2, parseFloat(goal.progress || 0)) + '%"></span></div><small>' + esc(actual) + ' / ' + esc(target) + ' (' + esc(Number(goal.progress || 0).toFixed(1)) + '%)</small></div>';
        }).join('');
    };

    WCPI.renderBudgetVsActual = function(rows){
        if(!rows || !rows.length){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }
        return '<div class="wcpi-budget-list">' + rows.map(function(row){
            var varianceClass = parseFloat(row.variance || 0) > 0 ? 'warning' : 'success';
            var label = parseFloat(row.variance || 0) > 0 ? 'Over budget' : 'Under budget';
            return '<div class="wcpi-budget-row"><div><strong>' + esc(row.display_name || row.category || '') + '</strong><small>' + esc(money(row.total)) + ' / ' + esc(money(row.budget)) + '</small></div><span class="wcpi-badge wcpi-badge-' + varianceClass + '">' + esc(label + ' ' + money(Math.abs(parseFloat(row.variance || 0)))) + '</span></div>';
        }).join('') + '</div>';
    };

    WCPI.renderHighlights = function(highlights){
        if(!highlights){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }

        var items = [];
        if(highlights.best_sales_day){ items.push({ label: 'Best Sales Day', value: highlights.best_sales_day.summary_date || '', meta: money(highlights.best_sales_day.gross_revenue) }); }
        if(highlights.highest_profit_day){ items.push({ label: 'Highest Profit Day', value: highlights.highest_profit_day.summary_date || '', meta: money(highlights.highest_profit_day.net_profit) }); }
        if(highlights.top_category){ items.push({ label: 'Top Category', value: highlights.top_category.category || '', meta: money(highlights.top_category.profit) }); }
        if(highlights.top_product){ items.push({ label: 'Top Product', value: highlights.top_product.name || '', meta: money(highlights.top_product.profit) }); }
        if(highlights.worst_margin_product){ items.push({ label: 'Worst Margin Product', value: highlights.worst_margin_product.name || '', meta: percent(highlights.worst_margin_product.margin_percent) }); }

        if(!items.length){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }

        return '<div class="wcpi-mini-card-grid">' + items.map(function(item){
            return '<div class="wcpi-mini-card"><span class="wcpi-mini-label">' + esc(item.label) + '</span><strong>' + esc(item.value) + '</strong><small>' + esc(item.meta || '') + '</small></div>';
        }).join('') + '</div>';
    };

    WCPI.renderTrendIntelligence = function(items){
        if(!items || !items.length){
            return '<div class="wcpi-empty-inline">No trend insights for this range.</div>';
        }
        return items.map(function(item){
            return '<article class="wcpi-recommendation wcpi-rec-' + esc(recommendationTone(item)) + '"><strong>' + esc(item.title) + '</strong><p>' + esc(item.body) + '</p></article>';
        }).join('');
    };

    WCPI.renderNotes = function(items){
        if(!items || !items.length){
            return '<div class="wcpi-empty-inline">No notes added for this range.</div>';
        }
        return '<div class="wcpi-notes">' + items.map(function(item){
            var color = item.note_color || 'blue';
            var chips = '<div class="wcpi-inline-tools">' + noteInfoBadges(item) + '</div>';
            var dateText = item.date_range_label ? '<small>' + esc(item.date_range_label) + '</small>' : '';
            return '<article class="wcpi-note wcpi-note-theme-' + esc(color) + '"><div class="wcpi-note-head"><strong>' + esc(item.note_title || '') + '</strong>' + chips + '</div>' + dateText + '<p>' + esc(item.note_body || '') + '</p></article>';
        }).join('') + '</div>';
    };

    WCPI.renderActiveNotesStrip = function(items){
        if(!items || !items.length){
            return '';
        }
        return items.slice(0, 4).map(activeNoteMarkup).join('');
    };

    WCPI.syncActiveNotesStrip = function(items){
        var $strip = $('#wcpi-dashboard-active-notes');
        if(!$strip.length){
            return;
        }
        var activeNotes = items || [];
        setActiveNoteCountClass($strip, activeNotes.length);
        $strip.html(WCPI.renderActiveNotesStrip(activeNotes)).prop('hidden', !activeNotes.length);
    };

    WCPI.renderHeatmap = function(rows){
        if(!rows || !rows.length){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }
        return '<div class="wcpi-heatmap-grid">' + rows.map(function(row){
            return '<article class="wcpi-heatmap-card wcpi-heatmap-' + esc(row.heatmap_band || 'watch') + '"><strong>' + esc(row.category || '') + '</strong><span>' + esc(money(row.revenue)) + ' revenue</span><span>' + esc(money(row.profit)) + ' profit</span><span>' + esc(percent(row.margin_percent)) + ' margin</span></article>';
        }).join('') + '</div>';
    };

    WCPI.renderSimulatorResult = function(data){
        if(!data || !data.baseline){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }
        var metricLabel = data.scenario_type === 'cost' ? 'New Cost' : 'New Price';
        var metricValue = data.scenario_type === 'cost' ? money(data.new_cost) : money(data.new_price);
        return '<div class="wcpi-sim-result"><p><strong>' + esc(data.baseline.product_name || '') + '</strong></p><p>Monthly units baseline: ' + esc(Number(data.baseline.monthly_units || 0).toFixed(2)) + '</p><p>' + esc(metricLabel) + ': ' + esc(metricValue) + '</p><p>Current margin: ' + esc(percent(data.current_margin)) + '</p><p>Projected margin: ' + esc(percent(data.projected_margin)) + '</p><p>Projected monthly profit change: ' + esc(money(data.estimated_monthly_delta)) + '</p><p>' + esc(data.summary || '') + '</p></div>';
    };

    WCPI.renderExpenseSummary = function(summary){
        if(!summary){
            return '<div class="wcpi-empty-inline">' + esc(wcpiAdmin.i18n.noData) + '</div>';
        }
        return '<div class="wcpi-stat-grid wcpi-stat-grid-5"><section class="wcpi-stat-card wcpi-card-primary"><span class="wcpi-stat-label">This Month Expenses</span><strong class="wcpi-stat-value">' + esc(money(summary.this_period_total)) + '</strong></section><section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label">Largest Category</span><strong class="wcpi-stat-value">' + esc((summary.largest_category && summary.largest_category.category_label) || summary.largest_category_label || summary.largest_category_display || '') + '</strong></section><section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label">Last Added Expense</span><strong class="wcpi-stat-value">' + esc((summary.latest && summary.latest.expense_name) || 'No expenses yet') + '</strong></section><section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label">Recurring Total</span><strong class="wcpi-stat-value">' + esc(money(summary.recurring_total)) + '</strong></section><section class="wcpi-stat-card wcpi-card-secondary"><span class="wcpi-stat-label">Expense Entries</span><strong class="wcpi-stat-value">' + esc(numberValue(summary.entries, 0)) + '</strong></section></div>';
    };

    WCPI.renderExpenseTable = function(query){
        var items = query && query.items ? query.items : [];
        var html = '<div class="wcpi-table-wrap"><table class="widefat wcpi-table wcpi-table-sticky"><thead><tr><th>Name</th><th>Category</th><th>Source</th><th>Amount</th><th>Date</th><th>Attachment</th><th>Actions</th></tr></thead><tbody>';
        if(!items.length){
            html += '<tr><td colspan="7"><div class="wcpi-empty-state"><span class="dashicons dashicons-money-alt"></span><h3>No expenses added yet</h3><p>Start recording business costs to see true store profit.</p></div></td></tr>';
        } else {
            items.forEach(function(item){
                var editUrl = ((wcpiAdmin.pages || {}).expenses || 'admin.php?page=wcpi-expenses') + '&edit_expense=' + encodeURIComponent(item.id) + '&tab=add-expense';
                html += '<tr><td><strong>' + esc(item.expense_name) + '</strong><br><small>' + esc(item.notes || '') + '</small></td><td>' + esc(item.category_label || item.category || '') + '</td><td><span class="wcpi-badge wcpi-badge-neutral">' + esc(item.source_label || item.source_type || '') + '</span></td><td>' + esc(money(item.amount)) + '</td><td>' + esc(item.expense_date) + '</td><td>' + (item.attachment_path ? 'Uploaded' : '<span class="wcpi-muted">None</span>') + '</td><td><a class="button button-small" href="' + esc(editUrl) + '">Edit</a> <a class="button button-small" href="' + esc(expenseDeleteUrl(item.id)) + '" onclick="return confirm(\'Delete this expense?\');">Delete</a></td></tr>';
            });
        }
        html += '</tbody></table></div>';
        return html;
    };

    function modalByName(name){
        return $('[data-wcpi-modal="' + name + '"]');
    }

    function defaultModalView($modal){
        return $modal.data('modalDefaultView') || $modal.find('[data-modal-view]').first().data('modalView') || 'form';
    }

    function switchModalView($modal, view){
        $modal.find('[data-modal-view]').prop('hidden', true);
        $modal.find('[data-modal-view="' + view + '"]').prop('hidden', false);
    }

    function openModal(name){
        var $modal = modalByName(name);
        if(!$modal.length){
            return;
        }
        $modal.prop('hidden', false).addClass('is-open');
        switchModalView($modal, defaultModalView($modal));
        $('body').addClass('wcpi-modal-open');
        window.setTimeout(function(){
            $modal.find('input, select, textarea, button').filter(':visible').first().trigger('focus');
        }, 20);
    }

    function resetModal(name){
        var $modal = modalByName(name);
        if(!$modal.length){
            return;
        }
        $modal.find('form').each(function(){
            if(this.reset){
                this.reset();
            }
        });
        switchModalView($modal, defaultModalView($modal));
    }

    function closeModal(name){
        var $modal = modalByName(name);
        if(!$modal.length){
            return;
        }
        resetModal(name);
        $modal.removeClass('is-open').prop('hidden', true);
        if(!$('.wcpi-modal.is-open').length){
            $('body').removeClass('wcpi-modal-open');
        }
    }

    function finishModalForm($form, message){
        var modalName = $form.data('wcpiModalForm');
        if(!modalName){
            return;
        }
        var $modal = modalByName(modalName);
        if(!$modal.length){
            return;
        }
        if($form[0] && $form[0].reset){
            $form[0].reset();
        }
        $modal.find('.wcpi-modal-success-message').text(message || wcpiAdmin.i18n.saved);
        switchModalView($modal, 'success');
    }

    var noteActionState = {
        note: null,
        action: ''
    };

    function noteMenu(){
        return $('#wcpi-note-actions-popover');
    }

    function closeNoteMenu(){
        noteMenu().prop('hidden', true).removeClass('is-visible').removeData('note');
        $('.wcpi-note-menu.is-open').removeClass('is-open');
    }

    function openNoteMenu($button){
        var note = decodeNotePayload($button.attr('data-note'));
        if(!note || !note.id){
            return;
        }

        var $menu = noteMenu();
        if(!$menu.length){
            return;
        }

        var rect = $button[0].getBoundingClientRect();
        var wasOpen = $button.hasClass('is-open') && !$menu.prop('hidden');
        closeNoteMenu();

        if(wasOpen){
            return;
        }

        $button.addClass('is-open');
        $menu
            .data('note', note)
            .css({
                top: Math.round(rect.bottom + 10),
                left: Math.max(16, Math.round(rect.right - 190))
            })
            .prop('hidden', false)
            .addClass('is-visible');
    }

    function populateNoteView(note){
        var badges = noteInfoBadges(note);
        $('#wcpi-note-detail-heading').text(note.note_title || 'Dashboard note');
        $('#wcpi-note-detail-badges').html(badges);
        $('#wcpi-note-detail-dates').text(note.date_range_label || 'Effective immediately');
        $('#wcpi-note-detail-email').text(noteEmailSummary(note));
        $('#wcpi-note-detail-body').html('<p>' + esc((note.note_body || '').replace(/\n/g, '\n')).replace(/\n/g, '<br>') + '</p>');
    }

    function openNoteConfirmation(action, note){
        noteActionState.note = note;
        noteActionState.action = action;

        var isEnd = action === 'end';
        $('#wcpi-note-confirm-title').text(isEnd ? (wcpiAdmin.i18n.confirmEndNoteTitle || 'End note now?') : (wcpiAdmin.i18n.confirmRemoveNoteTitle || 'Remove note from active surfaces?'));
        $('#wcpi-note-confirm-text').text(isEnd ? (wcpiAdmin.i18n.confirmEndNoteBody || '') : (wcpiAdmin.i18n.confirmRemoveNoteBody || ''));
        $('#wcpi-note-confirm-submit')
            .text(isEnd ? (wcpiAdmin.i18n.endNote || 'End Note') : (wcpiAdmin.i18n.removeNote || 'Remove'))
            .toggleClass('button-primary', true)
            .toggleClass('wcpi-button-danger', isEnd);
        openModal('note-confirm');
    }

    function executeNoteAction(action, note, $button){
        if(!note || !note.id || !action){
            return;
        }

        var ajaxAction = action === 'end' ? 'wcpi_end_dashboard_note' : 'wcpi_hide_dashboard_note';
        if($button && $button.length){
            $button.prop('disabled', true).text(wcpiAdmin.i18n.saving || 'Saving...');
        }
        $.post(wcpiAdmin.ajaxUrl, {
            action: ajaxAction,
            nonce: wcpiAdmin.nonce,
            note_id: note.id
        }).done(function(response){
            if(!response.success){
                showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                return;
            }

            syncDashboardNoteSurfaces(response.data || {});
            closeModal('note-confirm');
            showToast((response.data && response.data.message) || (action === 'end' ? wcpiAdmin.i18n.noteEnded : wcpiAdmin.i18n.noteRemoved));
            $(document).trigger('wcpi:dashboard-notes-updated', [response.data.active_notes || [], response.data.notes || []]);
        }).fail(function(){
            showToast(wcpiAdmin.i18n.error, 'error');
        }).always(function(){
            noteActionState.action = '';
            noteActionState.note = null;
            if($button && $button.length){
                $button.prop('disabled', false).removeClass('wcpi-button-danger').addClass('button-primary').text(wcpiAdmin.i18n.confirm || 'Confirm');
            }
        });
    }

    function runNoteAction(){
        executeNoteAction(noteActionState.action, noteActionState.note, $('#wcpi-note-confirm-submit'));
    }

    function bindExpenseAjax(){
        $(document).on('submit', 'form#wcpi-expense-form, form.wcpi-expense-ajax-form', function(event){
            event.preventDefault();
            var form = this;
            var $form = $(form);
            var data = new FormData(form);
            data.append('action', 'wcpi_save_expense');
            data.append('nonce', wcpiAdmin.nonce);
            data.append('filter_search', $('#wcpi-expense-filter-search').val() || '');
            data.append('filter_category', $('#wcpi-expense-filter-category').val() || '');
            data.append('filter_source_type', $('#wcpi-expense-filter-source').val() || '');
            data.append('filter_from', $('#wcpi-expense-filter-from').val() || '');
            data.append('filter_to', $('#wcpi-expense-filter-to').val() || '');
            data.append('filter_paged', $('#wcpi-expense-filter-paged').val() || '1');

            var $button = $form.find('button[type="submit"]').first();
            $button.prop('disabled', true).text(wcpiAdmin.i18n.saving);

            $.ajax({
                url: wcpiAdmin.ajaxUrl,
                method: 'POST',
                data: data,
                processData: false,
                contentType: false
            }).done(function(response){
                if(!response.success){
                    showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                    return;
                }

                if($('#wcpi-expenses-summary').length){
                    $('#wcpi-expenses-summary').html(WCPI.renderExpenseSummary(response.data.summary));
                }
                if($('#wcpi-expenses-budget-panel').length){
                    $('#wcpi-expenses-budget-panel').html(WCPI.renderBudgetVsActual((response.data.summary || {}).budget_vs_actual || []));
                }
                if($('#wcpi-expenses-table').length){
                    $('#wcpi-expenses-table').html(WCPI.renderExpenseTable(response.data.query));
                }

                if($form.data('wcpiModalForm')){
                    finishModalForm($form, response.data.message || wcpiAdmin.i18n.saved);
                } else if(form.reset) {
                    form.reset();
                }
                showToast(response.data.message || wcpiAdmin.i18n.saved);
                $(document).trigger('wcpi:expense-saved', [response.data]);
            }).fail(function(){
                showToast(wcpiAdmin.i18n.error, 'error');
            }).always(function(){
                $button.prop('disabled', false).text($button.data('default-label') || 'Save Expense');
            });
        });

        $(document).on('submit', '#wcpi-budget-form', function(event){
            event.preventDefault();
            var data = $(this).serializeArray();
            data.push({ name: 'action', value: 'wcpi_save_expense_budget' });
            data.push({ name: 'nonce', value: wcpiAdmin.nonce });
            $.post(wcpiAdmin.ajaxUrl, data).done(function(response){
                if(!response.success){
                    showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                    return;
                }
                if($('#wcpi-expenses-summary').length){
                    $('#wcpi-expenses-summary').html(WCPI.renderExpenseSummary(response.data.summary));
                }
                if($('#wcpi-expenses-budget-panel').length){
                    $('#wcpi-expenses-budget-panel').html(WCPI.renderBudgetVsActual((response.data.summary || {}).budget_vs_actual || []));
                }
                showToast(response.data.message || wcpiAdmin.i18n.saved);
                $(document).trigger('wcpi:budget-saved', [response.data]);
            }).fail(function(){
                showToast(wcpiAdmin.i18n.error, 'error');
            });
        });
    }

    function bindDashboardNotes(){
        $(document).on('submit', 'form#wcpi-dashboard-note-form, form.wcpi-note-ajax-form', function(event){
            event.preventDefault();
            var $form = $(this);
            var data = $form.serializeArray();
            data.push({ name: 'action', value: 'wcpi_save_dashboard_note' });
            data.push({ name: 'nonce', value: wcpiAdmin.nonce });
            $.post(wcpiAdmin.ajaxUrl, data).done(function(response){
                if(!response.success){
                    showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                    return;
                }
                syncDashboardNoteSurfaces(response.data || {});
                if($form.data('wcpiModalForm')){
                    var message = response.data.message || wcpiAdmin.i18n.saved;
                    if(response.data.note && response.data.note.email_sent){
                        message += ' ' + (wcpiAdmin.i18n.noteEmailSent || '');
                    }
                    finishModalForm($form, message);
                } else if($form[0] && $form[0].reset){
                    $form[0].reset();
                }
                showToast(response.data.message || wcpiAdmin.i18n.saved);
                $(document).trigger('wcpi:dashboard-notes-updated', [response.data.active_notes || response.data.notes || []]);
            }).fail(function(){
                showToast(wcpiAdmin.i18n.error, 'error');
            });
        });
    }

    function bindProductCosts(){
        $(document).on('input', '.wcpi-cost-row .wcpi-cost-part', function(){
            refreshCostRow($(this).closest('.wcpi-cost-row'));
        });

        $(document).on('click', '#wcpi-save-visible-costs-ajax', function(event){
            event.preventDefault();
            var $form = $('#wcpi-product-costs-form');
            if(!$form.length){
                return;
            }

            var $button = $(this);
            var payload = $form.serializeArray();
            payload.push({ name: 'action', value: 'wcpi_save_product_costs' });
            payload.push({ name: 'nonce', value: wcpiAdmin.nonce });
            $button.prop('disabled', true).text(wcpiAdmin.i18n.saving);

            $.post(wcpiAdmin.ajaxUrl, payload).done(function(response){
                if(!response.success){
                    showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                    return;
                }

                $('.wcpi-cost-row').each(function(){
                    var $row = $(this);
                    $row.attr('data-has-row', '1').removeClass('is-dirty');
                    $row.find('.wcpi-row-state').text(wcpiAdmin.i18n.savedState || 'Saved').removeClass('wcpi-badge-warning').addClass('wcpi-badge-success');
                    refreshCostRow($row);
                    $row.removeClass('is-dirty');
                    $row.find('.wcpi-row-state').text(wcpiAdmin.i18n.savedState || 'Saved').removeClass('wcpi-badge-warning').addClass('wcpi-badge-success');
                });

                if(response.data.audit){
                    $('.wcpi-audit-products-with-costs').text(numberValue(response.data.audit.products_with_costs, 0));
                    $('.wcpi-audit-missing-products').text(numberValue(response.data.audit.missing_products, 0));
                    $('.wcpi-audit-variation-gaps').text(numberValue(response.data.audit.variation_missing || 0, 0));
                    $('.wcpi-audit-completeness').text(percent(response.data.audit.completeness_percent));
                    $('.wcpi-audit-progress').css('width', Math.max(4, parseFloat(response.data.audit.completeness_percent || 0)) + '%');
                }
                showToast(response.data.message || wcpiAdmin.i18n.saved);
            }).fail(function(){
                showToast(wcpiAdmin.i18n.error, 'error');
            }).always(function(){
                $button.prop('disabled', false).text($button.data('default-label') || 'Save Visible Costs');
            });
        });
    }

    $(function(){
        $(document).on('click', '[data-wcpi-modal-open]', function(event){
            event.preventDefault();
            closeNoteMenu();
            openModal($(this).data('wcpiModalOpen'));
        });

        $(document).on('click', '[data-wcpi-modal-close]', function(event){
            event.preventDefault();
            closeModal($(this).data('wcpiModalClose'));
        });

        $(document).on('click', '[data-wcpi-modal-reset]', function(event){
            event.preventDefault();
            resetModal($(this).data('wcpiModalReset'));
        });

        $(document).on('mousedown', '.wcpi-modal', function(event){
            if($(event.target).is('.wcpi-modal')){
                closeModal($(this).data('wcpiModal'));
            }
        });

        $(document).on('click', '.wcpi-note-menu', function(event){
            event.preventDefault();
            event.stopPropagation();
            openNoteMenu($(this));
        });

        $(document).on('click', '[data-wcpi-note-action]', function(event){
            event.preventDefault();
            event.stopPropagation();
            var action = $(this).data('wcpiNoteAction');
            var note = noteMenu().data('note');
            closeNoteMenu();

            if(!note || !note.id){
                return;
            }

            if(action === 'view'){
                populateNoteView(note);
                openModal('note-view');
                return;
            }

            if(action === 'remove'){
                executeNoteAction('remove', note, null);
                return;
            }

            if(action === 'end'){
                openNoteConfirmation(action, note);
            }
        });

        $(document).on('click', function(event){
            if($(event.target).closest('#wcpi-note-actions-popover, .wcpi-note-menu').length){
                return;
            }
            closeNoteMenu();
        });

        $(document).on('keydown', function(event){
            if(event.key === 'Escape'){
                closeNoteMenu();
                var $open = $('.wcpi-modal.is-open').last();
                if($open.length){
                    closeModal($open.data('wcpiModal'));
                }
            }
        });

        $(window).on('resize scroll', function(){
            closeNoteMenu();
        });

        $(document).on('click', '.wcpi-category-chip', function(){
            var target = $(this).data('category');
            $(this).closest('form').find('select[name="category"]').val(target);
        });

        $(document).on('click', '.wcpi-maintenance-button', function(){
            return window.confirm(wcpiAdmin.i18n.confirmClear);
        });

        $(document).on('click', '.wcpi-range-chip', function(event){
            event.preventDefault();
            $('#dashboard-preset').val($(this).data('preset'));
            $('#dashboard-apply').trigger('click');
        });

        $('[data-auto-tabs="yes"]').each(function(){
            WCPI.initTabs(this);
        });

        $('#wcpi-expense-form button[type="submit"], .wcpi-expense-ajax-form button[type="submit"], .wcpi-note-ajax-form button[type="submit"], #wcpi-save-visible-costs-ajax').each(function(){
            $(this).data('default-label', $(this).text());
        });

        $('#wcpi-note-confirm-submit').data('default-label', $('#wcpi-note-confirm-submit').text());
        $('#wcpi-note-confirm-submit').on('click', function(event){
            event.preventDefault();
            runNoteAction();
        });

        bindExpenseAjax();
        bindDashboardNotes();
        bindProductCosts();
    });
})(jQuery);
