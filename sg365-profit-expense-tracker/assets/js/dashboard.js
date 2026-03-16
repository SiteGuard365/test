(function($){
    function cleanText(value){
        if(value == null){
            return '';
        }
        return $('<div>').html(String(value)).text();
    }

    function chartTooltip(canvas){
        var $frame = $(canvas).closest('.wcpi-chart-frame');
        if(!$frame.length){
            $frame = $(canvas).parent();
        }
        if($frame.css('position') === 'static'){
            $frame.css('position', 'relative');
        }
        var $tooltip = $frame.find('.wcpi-chart-tooltip');
        if(!$tooltip.length){
            $tooltip = $('<div class="wcpi-chart-tooltip" />').appendTo($frame);
        }
        return $tooltip;
    }

    function drawLineChart(canvas, labels, lines, formatter){
        if(!canvas){
            return;
        }

        var dpr = window.devicePixelRatio || 1;
        var width = Math.max(20, canvas.clientWidth || $(canvas).width() || 640);
        var height = Math.max(220, canvas.clientHeight || $(canvas).height() || 280);
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        var ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        var padding = { top: 18, right: 18, bottom: 34, left: 42 };
        var plotWidth = width - padding.left - padding.right;
        var plotHeight = height - padding.top - padding.bottom;
        var values = [];
        var hoverIndex = canvas._wcpiHoverIndex;

        lines.forEach(function(line){
            values = values.concat(line.data || []);
        });

        var max = Math.max.apply(null, values.concat([1]));
        var min = Math.min.apply(null, values.concat([0]));
        if(max === min){
            max += 1;
            min -= 1;
        }
        min = Math.min(0, min);

        function x(index){
            return padding.left + ((plotWidth * index) / Math.max(1, labels.length - 1));
        }

        function y(value){
            return height - padding.bottom - (((value - min) / Math.max(1, max - min)) * plotHeight);
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        for(var grid = 0; grid < 4; grid++){
            var lineY = padding.top + ((plotHeight / 3) * grid);
            ctx.beginPath();
            ctx.moveTo(padding.left, lineY);
            ctx.lineTo(width - padding.right, lineY);
            ctx.strokeStyle = '#f1f5f9';
            ctx.lineWidth = 1;
            ctx.stroke();
        }

        ctx.font = '10px "Plus Jakarta Sans", "Segoe UI", sans-serif';
        ctx.fillStyle = '#9ca3af';
        ctx.fillText(labels[0] || '', padding.left, height - 10);
        if(labels.length > 1){
            var lastLabel = labels[labels.length - 1] || '';
            ctx.fillText(lastLabel, width - padding.right - Math.min(100, ctx.measureText(lastLabel).width), height - 10);
        }

        lines.forEach(function(line){
            var color = line.color || '#2563eb';
            var fillColor = line.fillColor || 'rgba(37, 99, 235, 0.10)';
            var data = line.data || [];

            if(data.length > 1){
                ctx.beginPath();
                data.forEach(function(value, index){
                    var pointX = x(index);
                    var pointY = y(parseFloat(value || 0));
                    if(index === 0){
                        ctx.moveTo(pointX, pointY);
                    } else {
                        ctx.lineTo(pointX, pointY);
                    }
                });
                ctx.lineTo(x(data.length - 1), height - padding.bottom);
                ctx.lineTo(x(0), height - padding.bottom);
                ctx.closePath();
                ctx.fillStyle = fillColor;
                ctx.fill();
            }

            ctx.beginPath();
            data.forEach(function(value, index){
                var pointX = x(index);
                var pointY = y(parseFloat(value || 0));
                if(index === 0){
                    ctx.moveTo(pointX, pointY);
                } else {
                    ctx.lineTo(pointX, pointY);
                }
            });
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.stroke();

            if(hoverIndex != null && hoverIndex >= 0 && hoverIndex < data.length){
                ctx.beginPath();
                ctx.arc(x(hoverIndex), y(parseFloat(data[hoverIndex] || 0)), 3, 0, Math.PI * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.stroke();
            }
        });

        if(hoverIndex != null && hoverIndex >= 0 && hoverIndex < labels.length){
            ctx.beginPath();
            ctx.moveTo(x(hoverIndex), padding.top);
            ctx.lineTo(x(hoverIndex), height - padding.bottom);
            ctx.strokeStyle = 'rgba(99, 102, 241, 0.20)';
            ctx.lineWidth = 1;
            ctx.stroke();
        }

        if(!canvas._wcpiBound){
            canvas._wcpiBound = true;
            $(canvas).on('mousemove', function(event){
                var offset = $(canvas).offset();
                var relativeX = event.pageX - offset.left;
                var nextHover = Math.round(((relativeX - padding.left) / Math.max(1, plotWidth)) * Math.max(1, labels.length - 1));
                nextHover = Math.max(0, Math.min(labels.length - 1, nextHover));
                canvas._wcpiHoverIndex = nextHover;
                drawLineChart(canvas, labels, lines, formatter);

                var rows = [];
                lines.forEach(function(line){
                    rows.push('<span><i style="background:' + WCPI.esc(line.color || '#2563eb') + '"></i>' + WCPI.esc(line.label || '') + '<strong>' + WCPI.esc((formatter || WCPI.numberValue)(line.data[nextHover])) + '</strong></span>');
                });

                chartTooltip(canvas)
                    .addClass('is-visible')
                    .css({ left: Math.min(width - 190, Math.max(12, relativeX + 10)), top: 14 })
                    .html('<strong>' + WCPI.esc(labels[nextHover] || '') + '</strong>' + rows.join(''));
            }).on('mouseleave', function(){
                canvas._wcpiHoverIndex = null;
                chartTooltip(canvas).removeClass('is-visible').empty();
                drawLineChart(canvas, labels, lines, formatter);
            });

            $(window).on('resize', function(){
                if($(canvas).is(':visible')){
                    drawLineChart(canvas, labels, lines, formatter);
                }
            });
        }
    }

    function insightStrip(items){
        if(!items || !items.length){
            return '';
        }
        return '<div class="wcpi-insight-strip">' + items.map(function(item){
            return '<article class="wcpi-mini-card"><span class="wcpi-mini-label">' + WCPI.esc(cleanText(item.label || '')) + '</span><strong>' + WCPI.esc(cleanText(item.value || '')) + '</strong><small>' + WCPI.esc(cleanText(item.meta || '')) + '</small></article>';
        }).join('') + '</div>';
    }

    function renderWaterfall(payload){
        var rows = payload.waterfall || {};
        var html = '<div class="wcpi-waterfall">';
        Object.keys(rows).forEach(function(label){
            html += '<div class="wcpi-waterfall-row"><span>' + WCPI.esc(label) + '</span><strong>' + WCPI.money(rows[label]) + '</strong></div>';
        });
        html += '</div>';
        return html;
    }

    function recommendationSummary(items){
        var counts = { alert: 0, warning: 0, success: 0, info: 0 };
        (items || []).forEach(function(item){
            var tone = (item.type === 'danger' || item.severity === 'critical') ? 'alert' : (item.type === 'warning' ? 'warning' : (item.type === 'success' || item.type === 'opportunity' ? 'success' : 'info'));
            counts[tone] += 1;
        });
        var score = Math.max(22, 94 - (counts.alert * 18) - (counts.warning * 9) - (counts.info * 4));
        return {
            score: score,
            counts: counts
        };
    }

    function formatHeaderRange(payload){
        if(!payload || !payload.from || !payload.to){
            return payload && payload.range_label ? payload.range_label : '';
        }

        var from = new Date(payload.from + 'T00:00:00');
        var to = new Date(payload.to + 'T00:00:00');
        if(Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())){
            return payload.range_label || '';
        }

        var sameYear = from.getFullYear() === to.getFullYear();
        var fromText = from.toLocaleDateString(undefined, sameYear ? { month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric', year: 'numeric' });
        var toText = to.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        return fromText + ' - ' + toText;
    }

    function renderExecutive(payload){
        var highlights = payload.performance_highlights || {};
        var summary = '';

        summary += WCPI.renderOverviewCards(payload);
        summary += insightStrip(payload.executive_insights || []);
        summary += '<div class="wcpi-charts-top">';
        summary += '<section class="wcpi-panel wcpi-panel-elevated"><div class="wcpi-panel-head"><div><h2>Revenue vs Net Profit</h2><p>See how top-line sales are converting into real take-home profit.</p></div><span class="wcpi-badge wcpi-badge-neutral">' + WCPI.money(payload.net_profit) + '</span></div><div class="wcpi-chart-frame"><canvas id="wcpiExecutiveRevenueChart" class="wcpi-chart" height="280"></canvas></div></section>';
        summary += '<section class="wcpi-panel wcpi-panel-elevated"><div class="wcpi-panel-head"><div><h2>Margin Trend</h2><p>Track profitability quality without opening multiple sections.</p></div><span class="wcpi-badge wcpi-badge-success">' + WCPI.percent(payload.margin_percent) + '</span></div><div class="wcpi-chart-frame"><canvas id="wcpiExecutiveMarginChart" class="wcpi-chart" height="280"></canvas></div></section>';
        summary += '</div>';
        summary += '<div class="wcpi-charts-bot">';
        summary += '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Budget Focus</h2><p>Over-budget categories are promoted first so attention is obvious.</p></div></div>' + WCPI.renderBudgetVsActual((payload.budget_vs_actual || []).slice(0, 5)) + '</section>';
        summary += '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Profit Waterfall</h2><p>A clean bridge from revenue to final net profit.</p></div></div>' + renderWaterfall(payload) + '<div class="wcpi-mini-card-grid">' + [
            highlights.best_sales_day ? '<div class="wcpi-mini-card"><span class="wcpi-mini-label">Best Day</span><strong>' + WCPI.esc(highlights.best_sales_day.summary_date || '') + '</strong><small>' + WCPI.esc(cleanText(WCPI.money(highlights.best_sales_day.gross_revenue || 0))) + '</small></div>' : '',
            highlights.top_product ? '<div class="wcpi-mini-card"><span class="wcpi-mini-label">Top Product</span><strong>' + WCPI.esc(highlights.top_product.name || '') + '</strong><small>' + WCPI.esc(cleanText(WCPI.money(highlights.top_product.profit || 0))) + '</small></div>' : '',
            highlights.top_category ? '<div class="wcpi-mini-card"><span class="wcpi-mini-label">Category Leader</span><strong>' + WCPI.esc(cleanText(highlights.top_category.category || '')) + '</strong><small>' + WCPI.esc(cleanText(WCPI.money(highlights.top_category.profit || 0))) + '</small></div>' : ''
        ].join('') + '</div></section>';
        summary += '</div>';

        return summary;
    }

    function renderRecommendations(payload){
        var items = payload.recommendations || [];
        var trend = payload.trend_intelligence || [];
        var summary = recommendationSummary(items);
        var list = items.length ? items : trend;
        var priority = list.slice(0, 2);
        var improvements = list.slice(2, 5);
        var actionCounts = '<div class="wcpi-action-counts"><span class="wcpi-badge wcpi-badge-danger">Critical ' + summary.counts.alert + '</span><span class="wcpi-badge wcpi-badge-warning">Warning ' + summary.counts.warning + '</span><span class="wcpi-badge wcpi-badge-success">Opportunity ' + summary.counts.success + '</span></div>';
        var cardMarkup = function(item){
            var tone = (item.type === 'danger' || item.severity === 'critical') ? 'alert' : (item.type === 'warning' ? 'warning' : (item.type === 'success' || item.type === 'opportunity' ? 'success' : 'info'));
            var cta = item.cta_url ? '<a class="button button-small" href="' + WCPI.esc(item.cta_url) + '">' + WCPI.esc(item.cta_label || 'Open') + '</a>' : '<span class="button button-small button-disabled">Review</span>';
            return '<article class="wcpi-recommendation-card wcpi-rec-' + WCPI.esc(tone) + '"><div class="wcpi-note-head"><strong>' + WCPI.esc(item.title) + '</strong><span class="wcpi-badge wcpi-badge-' + (tone === 'alert' ? 'danger' : (tone === 'warning' ? 'warning' : 'success')) + '">' + WCPI.esc(tone === 'alert' ? 'Critical' : (tone === 'warning' ? 'Warning' : 'Actionable')) + '</span></div><p>' + WCPI.esc(item.body) + '</p><div class="wcpi-card-actions">' + cta + '<span class="dashicons dashicons-arrow-right-alt2"></span></div></article>';
        };

        return '<div class="wcpi-grid wcpi-grid-3 wcpi-grid-top">' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>AI Recommendations</h2><p>Automated insights based on profit, cost, budget, and margin signals without changing your underlying data.</p></div></div><div class="wcpi-panel-note-block"><h3>Priority Alerts</h3><div class="wcpi-recommendation-grid">' + priority.map(cardMarkup).join('') + '</div></div><div class="wcpi-panel-note-block"><h3>Suggested Improvements</h3><div class="wcpi-recommendation-grid">' + (improvements.length ? improvements.map(cardMarkup).join('') : '<div class="wcpi-empty-inline">No additional improvements for this range.</div>') + '</div></div></section>' +
            '<aside class="wcpi-panel wcpi-ai-panel"><div class="wcpi-panel-head"><div><h2>AI Health Score</h2><p>Compact signal summary inspired by premium analytics apps.</p></div></div><div class="wcpi-score-ring"><span>' + WCPI.esc(summary.score) + '</span><small>Health Score</small></div>' + actionCounts + '<div class="wcpi-stack">' + (trend.length ? trend.slice(0, 3).map(function(item){ return '<article class="wcpi-mini-card"><strong>' + WCPI.esc(item.title) + '</strong><small>' + WCPI.esc(item.body) + '</small></article>'; }).join('') : '<div class="wcpi-empty-inline">No trend warnings for this range.</div>') + '</div></aside>' +
        '</div>';
    }

    function renderProducts(payload){
        return '<div class="wcpi-grid wcpi-grid-3 wcpi-grid-top">' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>Top Profitable Products</h2><p>Profit leaders appear first so winning catalog segments are easier to protect.</p></div></div>' + WCPI.renderTable(payload.top_products || []) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Missing Cost Audit</h2><p>Audit gaps that still block accurate profitability calculations.</p></div></div>' + WCPI.renderAudit(payload.missing_cost_audit || null) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Low Margin Products</h2><p>Products under pressure and likely leaking profit.</p></div></div>' + WCPI.renderTable(payload.low_margin || []) + '</section>' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>Category Margin Heatmap</h2><p>Premium heatmap cards for quick category scanning.</p></div></div>' + WCPI.renderHeatmap(payload.category_margin_heatmap || []) + '</section>' +
        '</div>';
    }

    function renderPlanning(payload){
        var notes = payload.report_notes || payload.notes || payload.active_notes || [];
        var nextActions = (payload.recommendations || []).slice(0, 4);

        return '<div class="wcpi-grid wcpi-grid-2">' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Profit Goals</h2><p>Goals stay visible without competing with executive metrics.</p></div></div>' + WCPI.renderGoals(payload.goals || []) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Budget vs Actual</h2><p>Monthly pressure areas and under-budget breathing room.</p></div></div>' + WCPI.renderBudgetVsActual(payload.budget_vs_actual || []) + '</section>' +
        '</div>' +
        '<div class="wcpi-grid wcpi-grid-2">' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Dashboard Notes</h2><p>Effective notes, expiry windows, and color-coded categories stay visible here and on the main WordPress dashboard.</p></div><div class="wcpi-inline-tools"><button class="button button-primary" type="button" data-wcpi-modal-open="note">Add Note</button><button class="button" type="button" data-wcpi-modal-open="expense">Add Expense</button></div></div><div id="wcpi-dashboard-active-notes-board">' + WCPI.renderNotes(notes) + '</div></section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Next Actions</h2><p>Compact planning prompts so the dashboard ends with clear movement.</p></div></div><div class="wcpi-stack">' + (nextActions.length ? nextActions.map(function(item){
                return '<article class="wcpi-focus-card"><strong>' + WCPI.esc(item.title) + '</strong><p>' + WCPI.esc(item.body) + '</p>' + (item.cta_url ? '<a class="button button-small" href="' + WCPI.esc(item.cta_url) + '">' + WCPI.esc(item.cta_label || 'Open') + '</a>' : '') + '</article>';
            }).join('') : '<div class="wcpi-empty-inline">No actions queued for this range.</div>') + '</div></section>' +
        '</div>';
    }

    function syncActiveNotes(payload){
        var activeNotes = payload.active_notes || [];
        WCPI.syncActiveNotesStrip(activeNotes);
        $('#wcpi-dashboard-notes-count').text(activeNotes.length || 0);
    }

    function updateMeta(payload){
        var refreshedText = new Date().toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit'
        });
        $('#wcpi-dashboard-range-label').text(formatHeaderRange(payload));
        $('#wcpi-dashboard-compare-label').text(payload.previous_period && payload.previous_period.days ? ('vs prev. ' + payload.previous_period.days + ' days') : 'Compare off');
        $('#wcpi-dashboard-last-updated').text('Refreshed: ' + refreshedText);
        syncActiveNotes(payload);
    }

    $(function(){
        var state = {
            payload: window.wcpiDashboardBootstrap || null,
            rendered: {}
        };

        var renderers = {
            executive: function(payload){ return renderExecutive(payload); },
            recommendations: function(payload){ return renderRecommendations(payload); },
            products: function(payload){ return renderProducts(payload); },
            planning: function(payload){ return renderPlanning(payload); }
        };

        function renderTab(tab){
            if(!state.payload || !renderers[tab]){
                return;
            }
            if(state.rendered[tab] && $('#wcpi-dashboard-' + tab).children().length){
                return;
            }
            $('#wcpi-dashboard-' + tab).html(renderers[tab](state.payload));
            state.rendered[tab] = true;

            if(tab === 'executive'){
                drawLineChart(document.getElementById('wcpiExecutiveRevenueChart'), state.payload.chart_labels || [], [
                    { label: 'Revenue', data: state.payload.chart_revenue || [], color: '#2563eb', fillColor: 'rgba(37, 99, 235, 0.12)' },
                    { label: 'Net Profit', data: state.payload.chart_profit || [], color: '#22c55e', fillColor: 'rgba(34, 197, 94, 0.08)' }
                ], WCPI.money);
                drawLineChart(document.getElementById('wcpiExecutiveMarginChart'), state.payload.chart_labels || [], [
                    { label: 'Margin %', data: state.payload.chart_margin || [], color: '#6366f1', fillColor: 'rgba(99, 102, 241, 0.10)' }
                ], WCPI.percent);
            }
        }

        function loadPayload(silent){
            $.post(wcpiAdmin.ajaxUrl, {
                action: 'wcpi_dashboard_data',
                nonce: wcpiAdmin.nonce,
                preset: $('#dashboard-preset').val(),
                from: $('#dashboard-from').val(),
                to: $('#dashboard-to').val(),
                compare: $('#dashboard-compare').is(':checked') ? 'yes' : 'no'
            }).done(function(response){
                if(!response.success){
                    WCPI.showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                    return;
                }

                state.payload = response.data;
                state.rendered = {};
                updateMeta(response.data);
                renderTab(tabs.current());
                WCPI.updateUrlParam('preset', $('#dashboard-preset').val(), true);
                WCPI.updateUrlParam('from', $('#dashboard-from').val(), true);
                WCPI.updateUrlParam('to', $('#dashboard-to').val(), true);
                WCPI.updateUrlParam('compare', $('#dashboard-compare').is(':checked') ? 'yes' : 'no', true);
                if(!silent){
                    WCPI.showToast('Dashboard updated.');
                }
            }).fail(function(){
                WCPI.showToast(wcpiAdmin.i18n.error, 'error');
            });
        }

        var tabs = WCPI.initTabs('#wcpi-dashboard-tabs', {
            onChange: function(tab){
                renderTab(tab);
            }
        });

        if(state.payload){
            updateMeta(state.payload);
            renderTab(tabs.current());
        }

        $('#dashboard-apply').on('click', function(event){
            event.preventDefault();
            loadPayload();
        });

        $('#wcpi-rebuild-trigger').on('click', function(event){
            event.preventDefault();
            $.post(wcpiAdmin.ajaxUrl, {
                action: 'wcpi_rebuild_summaries',
                nonce: wcpiAdmin.nonce,
                from: $('#dashboard-from').val(),
                to: $('#dashboard-to').val()
            }).done(function(response){
                WCPI.showToast((response.data && response.data.message) || 'Summaries rebuilt.');
                loadPayload(true);
            }).fail(function(){
                WCPI.showToast(wcpiAdmin.i18n.error, 'error');
            });
        });

        $(document).on('wcpi:dashboard-notes-updated', function(){
            loadPayload(true);
        });

        $(document).on('wcpi:expense-saved', function(){
            loadPayload(true);
        });
    });
})(jQuery);
