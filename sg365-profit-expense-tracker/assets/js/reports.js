(function($){
    function fetchReports(callback){
        $.post(wcpiAdmin.ajaxUrl, {
            action: 'wcpi_reports_data',
            nonce: wcpiAdmin.nonce,
            preset: $('#reports-preset').val(),
            from: $('#reports-from').val(),
            to: $('#reports-to').val(),
            compare: $('#reports-compare').is(':checked') ? 'yes' : 'no'
        }).done(function(response){
            if(!response.success){
                WCPI.showToast((response.data && response.data.message) || wcpiAdmin.i18n.error, 'error');
                return;
            }
            callback(response.data);
        }).fail(function(){
            WCPI.showToast(wcpiAdmin.i18n.error, 'error');
        });
    }

    function renderExecutive(payload){
        return WCPI.renderOverviewCards(payload.overview || payload) +
            '<div class="wcpi-grid wcpi-grid-2">' +
                '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Profit Goals</h2><p>Goal progress and target pacing for the selected report range.</p></div></div>' + WCPI.renderGoals(payload.goals || []) + '</section>' +
                '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Budget vs Actual</h2><p>Budget pressure is reduced to the most important variance rows.</p></div></div>' + WCPI.renderBudgetVsActual(payload.budget_vs_actual || []) + '</section>' +
            '</div>' +
            '<div class="wcpi-grid wcpi-grid-2">' +
                '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Performance Highlights</h2><p>Best and worst business signals summarized for fast scanning.</p></div></div>' + WCPI.renderHighlights(payload.performance_highlights || null) + '</section>' +
                '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Trend Intelligence</h2><p>Momentum, margin, and expense movement without noisy repetition.</p></div></div>' + WCPI.renderTrendIntelligence(payload.trend_intelligence || []) + '</section>' +
            '</div>';
    }

    function renderProducts(payload){
        var highRevenue = payload.high_revenue || payload.highest_revenue || [];
        var mostSold = payload.most_sold || payload.most_sold_products || [];
        return '<div class="wcpi-grid wcpi-grid-3 wcpi-grid-top">' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>Top Profitable Products</h2><p>Profit leaders get the most space instead of fighting equal-size tables.</p></div></div>' + WCPI.renderTable(payload.top_products || []) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Low Margin Products</h2><p>Fast scan for margin leaks and risky movers.</p></div></div>' + WCPI.renderTable(payload.low_margin || []) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Highest Revenue Products</h2></div></div>' + WCPI.renderTable(highRevenue) + '</section>' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>Most Sold Products</h2><p>Volume leaders surfaced with the same premium table system.</p></div></div>' + WCPI.renderTable(mostSold) + '</section>' +
        '</div>';
    }

    function renderCategories(payload){
        var categories = payload.categories || payload.category_profit || [];
        var heatmap = payload.category_heatmap || payload.category_margin_heatmap || [];
        var audit = payload.cost_audit || payload.missing_cost_audit || null;
        return '<div class="wcpi-grid wcpi-grid-3 wcpi-grid-top">' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>Category Profitability</h2><p>Category performance with cleaner margin and profit readability.</p></div></div>' + WCPI.renderCategoryTable(categories) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Cost Audit Summary</h2><p>Audit gaps remain visible next to category analysis.</p></div></div>' + WCPI.renderAudit(audit) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Recommendations</h2><p>Action cards grouped beside categories instead of another equal-width table.</p></div></div>' + WCPI.renderRecommendations(payload.recommendations || []) + '</section>' +
            '<section class="wcpi-panel wcpi-span-2-panel"><div class="wcpi-panel-head"><div><h2>Category Margin Heatmap</h2><p>Premium heatmap cards for category quality review.</p></div></div>' + WCPI.renderHeatmap(heatmap) + '</section>' +
        '</div>';
    }

    function planningForms(){
        return '<div class="wcpi-grid wcpi-grid-2">' +
            '<section class="wcpi-panel">' +
                '<div class="wcpi-panel-head"><div><h2>Cost Change Impact Simulator</h2><p>Test supplier cost changes against the current historical sales baseline.</p></div></div>' +
                '<div class="wcpi-form-grid">' +
                    '<label class="wcpi-span-2"><span>Search Product or Variation</span><input type="search" id="wcpi-cost-sim-search" placeholder="Type at least 2 characters"></label>' +
                    '<div id="wcpi-cost-sim-results" class="wcpi-sim-search-results wcpi-span-2"></div>' +
                    '<input type="hidden" id="wcpi-cost-product-id" value="0">' +
                    '<input type="hidden" id="wcpi-cost-variation-id" value="0">' +
                    '<label><span>New Cost</span><input type="number" step="0.000001" id="wcpi-cost-sim-value"></label>' +
                    '<div class="wcpi-align-end"><button class="button button-primary" id="wcpi-run-cost-sim">Run Simulation</button></div>' +
                '</div>' +
                '<div id="wcpi-cost-sim-output"></div>' +
            '</section>' +
            '<section class="wcpi-panel">' +
                '<div class="wcpi-panel-head"><div><h2>Price Change Impact Simulator</h2><p>Explore cleaner pricing scenarios without changing live catalog prices.</p></div></div>' +
                '<div class="wcpi-form-grid">' +
                    '<label class="wcpi-span-2"><span>Search Product or Variation</span><input type="search" id="wcpi-price-sim-search" placeholder="Type at least 2 characters"></label>' +
                    '<div id="wcpi-price-sim-results" class="wcpi-sim-search-results wcpi-span-2"></div>' +
                    '<input type="hidden" id="wcpi-price-product-id" value="0">' +
                    '<input type="hidden" id="wcpi-price-variation-id" value="0">' +
                    '<label><span>New Price</span><input type="number" step="0.000001" id="wcpi-price-sim-value"></label>' +
                    '<div class="wcpi-align-end"><button class="button button-primary" id="wcpi-run-price-sim">Run Simulation</button></div>' +
                '</div>' +
                '<div id="wcpi-price-sim-output"></div>' +
            '</section>' +
        '</div>';
    }

    function renderPlanning(payload){
        var summaryRows = payload.summary_rows || [];
        var notes = payload.report_notes || payload.notes || [];
        return '<div class="wcpi-grid wcpi-grid-2">' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Daily Summary</h2><p>A complete bottom-layer view for the selected range.</p></div></div>' + WCPI.renderSummaryTable(summaryRows) + '</section>' +
            '<section class="wcpi-panel"><div class="wcpi-panel-head"><div><h2>Business Notes</h2><p>Contextual annotations for campaigns, supplier changes, and pricing events.</p></div></div>' + WCPI.renderNotes(notes) + '</section>' +
        '</div>' + planningForms();
    }

    function bindProductSearch(inputSelector, resultSelector, productFieldSelector, variationFieldSelector){
        var timer = null;

        $(document).on('input', inputSelector, function(){
            var term = $(this).val();
            var $results = $(resultSelector);
            clearTimeout(timer);

            if(!term || term.length < 2){
                $results.empty();
                return;
            }

            timer = window.setTimeout(function(){
                $.post(wcpiAdmin.ajaxUrl, {
                    action: 'wcpi_search_products',
                    nonce: wcpiAdmin.nonce,
                    term: term
                }).done(function(response){
                    if(!response.success || !response.data){
                        $results.html('<div class="wcpi-empty-inline">' + wcpiAdmin.i18n.noData + '</div>');
                        return;
                    }

                    var html = response.data.map(function(item){
                        return '<button type="button" class="button wcpi-sim-result-option" data-product-id="' + item.product_id + '" data-variation-id="' + item.variation_id + '" data-target-product="' + productFieldSelector + '" data-target-variation="' + variationFieldSelector + '" data-target-results="' + resultSelector + '" data-label="' + encodeURIComponent(item.label) + '">' + WCPI.esc(item.label) + '</button>';
                    }).join('');
                    $results.html(html || '<div class="wcpi-empty-inline">' + wcpiAdmin.i18n.noData + '</div>');
                });
            }, 250);
        });
    }

    function runSimulation(action, productFieldSelector, variationFieldSelector, valueSelector, outputSelector){
        var payload = {
            action: action,
            nonce: wcpiAdmin.nonce,
            from: $('#reports-from').val(),
            to: $('#reports-to').val(),
            product_id: $(productFieldSelector).val(),
            variation_id: $(variationFieldSelector).val()
        };

        if(action === 'wcpi_simulate_cost_change'){
            payload.new_cost = $(valueSelector).val();
        } else {
            payload.new_price = $(valueSelector).val();
        }

        $(outputSelector).html('<div class="wcpi-empty-inline">' + wcpiAdmin.i18n.loading + '</div>');
        $.post(wcpiAdmin.ajaxUrl, payload).done(function(response){
            if(!response.success){
                $(outputSelector).html('<div class="wcpi-empty-inline">' + wcpiAdmin.i18n.error + '</div>');
                return;
            }
            $(outputSelector).html(WCPI.renderSimulatorResult(response.data));
        }).fail(function(){
            $(outputSelector).html('<div class="wcpi-empty-inline">' + wcpiAdmin.i18n.error + '</div>');
        });
    }

    $(function(){
        if(!$('#wcpi-reports-page').length){
            return;
        }

        var state = {
            payload: window.wcpiReportsBootstrap || null,
            rendered: {}
        };

        var renderers = {
            executive: renderExecutive,
            products: renderProducts,
            categories: renderCategories,
            planning: renderPlanning
        };

        function renderTab(tab){
            if(!state.payload || !renderers[tab]){
                return;
            }
            if(state.rendered[tab] && $('#wcpi-report-' + (tab === 'categories' ? 'categories-panel' : tab)).children().length){
                return;
            }

            var targetId = tab === 'categories' ? '#wcpi-report-categories-panel' : '#wcpi-report-' + tab;
            $(targetId).html(renderers[tab](state.payload));
            state.rendered[tab] = true;
        }

        function refreshReports(){
            fetchReports(function(data){
                state.payload = data;
                state.rendered = {};
                $('#wcpi-reports-range-label').text((data.overview || {}).range_label || '');
                renderTab(tabs.current());
                WCPI.updateUrlParam('preset', $('#reports-preset').val(), true);
                WCPI.updateUrlParam('from', $('#reports-from').val(), true);
                WCPI.updateUrlParam('to', $('#reports-to').val(), true);
                WCPI.updateUrlParam('compare', $('#reports-compare').is(':checked') ? 'yes' : 'no', true);
                WCPI.showToast('Reports updated.');
            });
        }

        var tabs = WCPI.initTabs('#wcpi-reports-tabs', {
            onChange: function(tab){
                renderTab(tab);
            }
        });

        renderTab(tabs.current());

        $('#reports-apply').on('click', function(event){
            event.preventDefault();
            refreshReports();
        });

        bindProductSearch('#wcpi-cost-sim-search', '#wcpi-cost-sim-results', '#wcpi-cost-product-id', '#wcpi-cost-variation-id');
        bindProductSearch('#wcpi-price-sim-search', '#wcpi-price-sim-results', '#wcpi-price-product-id', '#wcpi-price-variation-id');

        $(document).on('click', '.wcpi-sim-result-option', function(){
            var $button = $(this);
            var label = decodeURIComponent($button.attr('data-label') || '');
            $($button.data('target-product')).val($button.data('product-id'));
            $($button.data('target-variation')).val($button.data('variation-id'));
            $($button.data('target-results')).html('<div class="wcpi-selected-product">' + WCPI.esc(label) + '</div>');
        });

        $(document).on('click', '#wcpi-run-cost-sim', function(event){
            event.preventDefault();
            runSimulation('wcpi_simulate_cost_change', '#wcpi-cost-product-id', '#wcpi-cost-variation-id', '#wcpi-cost-sim-value', '#wcpi-cost-sim-output');
        });

        $(document).on('click', '#wcpi-run-price-sim', function(event){
            event.preventDefault();
            runSimulation('wcpi_simulate_price_change', '#wcpi-price-product-id', '#wcpi-price-variation-id', '#wcpi-price-sim-value', '#wcpi-price-sim-output');
        });
    });
})(jQuery);
