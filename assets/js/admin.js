(function($) {
    'use strict';
    
    // Store all products for client-side filtering
    let allSearchResults = [];
    let currentFilters = {
        brand: [],
        fulfillment: [],
        category: [],
        price: [],
        size: []
    };
    
    // Toast notification
    function showToast(message, type = 'success') {
        const toast = $('<div class="rallyshopper-toast ' + type + '">' + message + '</div>');
        $('body').append(toast);
        
        setTimeout(() => toast.addClass('show'), 10);
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Kroger product search modal
    let pendingProductSelection = null;
    
    // Add ingredient - opens Kroger search FIRST
    $('#rallyshopper-add-ingredient').on('click', function() {
        pendingProductSelection = null;
        $('#kroger-search-modal').addClass('active');
        $('#kroger-search-input').val('').focus();
        $('#kroger-search-results').html('<p>Search for a product to add as an ingredient.</p>');
    });
    
    // Close modal
    $('.modal-close, .modal-cancel').on('click', function() {
        $('#kroger-search-modal').removeClass('active');
        pendingProductSelection = null;
    });
    
    // Search Kroger products
    $('#kroger-search-btn').on('click', performSearch);
    $('#kroger-search-input').on('keypress', function(e) {
        if (e.which === 13) {
            performSearch();
        }
    });
    
    function performSearch() {
        const query = $('#kroger-search-input').val().trim();
        if (!query) return;
        
        const $btn = $('#kroger-search-btn');
        $btn.prop('disabled', true).text('Searching...');
        
        // Collect filter parameters
        const filters = {
            brand: $('#kroger-filter-brand').val() || '',
            category: $('#kroger-filter-category').val() || '',
            fulfillmentType: $('#kroger-filter-fulfillment').val() || 'delivery',
            priceMin: $('#kroger-filter-price-min').val() || '',
            priceMax: $('#kroger-filter-price-max').val() || '',
            size: $('#kroger-filter-size').val() || ''
        };
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_search_kroger',
            nonce: rallyshopper_ajax.nonce,
            query: query,
            filters: filters
        }, function(response) {
            $btn.prop('disabled', false).text('Search');
            
            if (response.success) {
                displaySearchResults(response.data);
            } else {
                console.error('Search failed:', response.data);
                showToast(response.data || 'Search failed', 'error');
                $('#kroger-search-results').html('<p class="search-error">Error: ' + (response.data || 'Search failed') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).text('Search');
            console.error('AJAX error:', status, error, xhr.responseText);
            showToast('Network error - check console', 'error');
            $('#kroger-search-results').html('<p class="search-error">Network error. Check browser console.</p>');
        });
    }
    
    function displaySearchResults(products) {
        allSearchResults = products;
        const $container = $('#kroger-search-results');
        $container.empty();
        
        // Create results layout with filter sidebar
        const $resultsLayout = $(`
            <div class="search-results-layout" style="display: flex; gap: 20px; height: 500px;">
                <div class="filter-sidebar" style="width: 220px; flex-shrink: 0; overflow-y: auto; border-right: 1px solid #c3c4c7; padding-right: 15px;">
                    <h3 style="margin-top: 0;">Filter Results</h3>
                    <button type="button" id="kroger-clear-filters" class="button" style="width: 100%; margin-bottom: 15px;">Clear All</button>
                    
                    <div class="filter-section" data-filter="brand">
                        <h4 class="filter-header" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #c3c4c7;">
                            Brand <span class="toggle-icon">-</span>
                        </h4>
                        <div class="filter-options" id="filter-brand-options" style="max-height: 200px; overflow-y: auto;"></div>
                    </div>
                    
                    <div class="filter-section" data-filter="fulfillment">
                        <h4 class="filter-header" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #c3c4c7;">
                            Fulfillment Type <span class="toggle-icon">-</span>
                        </h4>
                        <div class="filter-options" id="filter-fulfillment-options"></div>
                    </div>
                    
                    <div class="filter-section" data-filter="price">
                        <h4 class="filter-header" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #c3c4c7;">
                            Price <span class="toggle-icon">+</span>
                        </h4>
                        <div class="filter-options" id="filter-price-options" style="display: none;"></div>
                    </div>
                    
                    <div class="filter-section" data-filter="size">
                        <h4 class="filter-header" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #c3c4c7;">
                            Size <span class="toggle-icon">+</span>
                        </h4>
                        <div class="filter-options" id="filter-size-options" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>
                
                <div class="products-container" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; overflow-y: auto;">
                    <div id="products-list" class="products-list" style="display: flex; flex-wrap: wrap; gap: 15px;"></div>
                </div>
            </div>
        `);
        $container.append($resultsLayout);
        
        // Generate filter options from products
        generateFilterOptions(products);
        
        // Render products
        renderProducts(products);
    }
    
    function generateFilterOptions(products) {
        // Collect unique values and counts
        const brands = {};
        const fulfillments = {delivery: 0, curbside: 0};
        const priceRanges = {
            'under5': {label: 'Under $5', count: 0, min: 0, max: 5},
            '5to10': {label: '$5 - $10', count: 0, min: 5, max: 10},
            '10to20': {label: '$10 - $20', count: 0, min: 10, max: 20},
            'over20': {label: '$20+', count: 0, min: 20, max: Infinity}
        };
        const sizes = {};
        
        products.forEach(product => {
            // Brand
            if (product.brand) {
                brands[product.brand] = (brands[product.brand] || 0) + 1;
            }
            
            // Fulfillment (Kroger API returns 'delivery' and 'curbside')
            if (product.fulfillment) {
                if (product.fulfillment.delivery) fulfillments.delivery++;
                if (product.fulfillment.curbside) fulfillments.curbside++;
            }
            
            // Price ranges
            const price = parseFloat(product.price) || 0;
            if (price < 5) priceRanges.under5.count++;
            else if (price < 10) priceRanges['5to10'].count++;
            else if (price < 20) priceRanges['10to20'].count++;
            else priceRanges.over20.count++;
            
            // Size
            if (product.size) {
                sizes[product.size] = (sizes[product.size] || 0) + 1;
            }
        });
        
        // Render brand options
        const brandHtml = Object.entries(brands)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 10)
            .map(([brand, count]) => `
                <label class="filter-option" style="display: flex; align-items: center; padding: 5px 0; cursor: pointer;">
                    <input type="checkbox" value="${brand}" data-filter="brand" style="margin-right: 8px;">
                    <span>${brand} (${count})</span>
                </label>
            `).join('');
        $('#filter-brand-options').html(brandHtml || '<p style="color: #666; font-size: 12px; padding: 5px;">No brands available</p>');
        
        // Render fulfillment options (Kroger API returns 'delivery' and 'curbside')
        const fulfillmentLabels = {
            'delivery': 'Delivery',
            'curbside': 'Curbside Pickup'
        };
        const fulfillmentHtml = Object.entries(fulfillments)
            .map(([type, count]) => `
                <label class="filter-option" style="display: flex; align-items: center; padding: 5px 0; cursor: pointer;">
                    <input type="checkbox" value="${type}" data-filter="fulfillment" style="margin-right: 8px;">
                    <span>${fulfillmentLabels[type]} (${count})</span>
                </label>
            `).join('');
        $('#filter-fulfillment-options').html(fulfillmentHtml);
        
        // Render price options
        const priceHtml = Object.entries(priceRanges)
            .filter(([_, data]) => data.count > 0)
            .map(([key, data]) => `
                <label class="filter-option" style="display: flex; align-items: center; padding: 5px 0; cursor: pointer;">
                    <input type="checkbox" value="${key}" data-filter="price" style="margin-right: 8px;">
                    <span>${data.label} (${data.count})</span>
                </label>
            `).join('');
        $('#filter-price-options').html(priceHtml || '<p style="color: #666; font-size: 12px; padding: 5px;">No price data</p>');
        
        // Render size options
        const sizeHtml = Object.entries(sizes)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 10)
            .map(([size, count]) => `
                <label class="filter-option" style="display: flex; align-items: center; padding: 5px 0; cursor: pointer;">
                    <input type="checkbox" value="${size}" data-filter="size" style="margin-right: 8px;">
                    <span>${size} (${count})</span>
                </label>
            `).join('');
        $('#filter-size-options').html(sizeHtml || '<p style="color: #666; font-size: 12px; padding: 5px;">No sizes available</p>');
    }
    
    function applyClientFilters() {
        // Get selected filters
        currentFilters = {
            brand: [],
            fulfillment: [],
            category: [],
            price: [],
            size: []
        };
        
        $('.filter-options input[type="checkbox"]:checked').each(function() {
            const filterType = $(this).data('filter');
            const value = $(this).val();
            if (currentFilters[filterType]) {
                currentFilters[filterType].push(value);
            }
        });
        
        // Filter products
        const filtered = allSearchResults.filter(product => {
            // Brand filter
            if (currentFilters.brand.length > 0 && !currentFilters.brand.includes(product.brand)) {
                return false;
            }
            
            // Fulfillment filter - skip if no fulfillment data in products
            if (currentFilters.fulfillment.length > 0 && product.fulfillment) {
                const hasFulfillment = currentFilters.fulfillment.some(type => {
                    return product.fulfillment[type];
                });
                if (!hasFulfillment) return false;
            }
            
            // Price filter
            if (currentFilters.price.length > 0) {
                const price = parseFloat(product.price) || 0;
                const inRange = currentFilters.price.some(range => {
                    const ranges = {
                        'under5': {min: 0, max: 5},
                        '5to10': {min: 5, max: 10},
                        '10to20': {min: 10, max: 20},
                        'over20': {min: 20, max: Infinity}
                    };
                    const r = ranges[range];
                    return price >= r.min && price < r.max;
                });
                if (!inRange) return false;
            }
            
            // Size filter
            if (currentFilters.size.length > 0 && !currentFilters.size.includes(product.size)) {
                return false;
            }
            
            return true;
        });
        
        renderProducts(filtered);
    }
    
    function renderProducts(products) {
        const $container = $('#products-list');
        $container.empty();
        
        if (!products || products.length === 0) {
            $container.html('<p class="no-results" style="text-align: center; padding: 40px; color: #646970;">No products match your filters. Try clearing some filters.</p>');
            return;
        }
        
        products.forEach(function(product) {
            // Get best image
            let imageUrl = product.image || '';
            if (!imageUrl && product.images) {
                imageUrl = product.images.large || product.images.xlarge || product.images.medium || product.images.small || product.images.thumbnail || '';
            }
            
            // Format price with promo
            let priceDisplay = '';
            if (product.price) {
                priceDisplay = '$' + product.price;
                if (product.promo_price && product.promo_price < product.price) {
                    priceDisplay += ' <span style="color: #d63638; font-weight: bold; margin-left: 8px;">Sale $' + product.promo_price + '</span>';
                }
            }
            
            // Stock indicator
            let stockIndicator = '';
            if (product.stock_level) {
                const stockColor = product.stock_level === 'HIGH' ? '#00a32a' : (product.stock_level === 'MEDIUM' ? '#dba617' : '#d63638');
                stockIndicator = `<span style="color: ${stockColor}; font-size: 12px; display: block; margin-top: 5px;">Stock: ${product.stock_level}</span>`;
            }
            
            const $card = $(`
                <div class="product-card-modern" data-product='${JSON.stringify(product).replace(/'/g, "&#39;")}' style="display: flex; flex-direction: column; padding: 12px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; width: 180px;">
                    <div class="product-image" style="width: 100%; height: 100px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center;">
                        <img src="${imageUrl}" alt="${product.description}" loading="lazy" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                    <div class="product-info" style="flex: 1;">
                        <div class="product-price-main" style="font-size: 16px; font-weight: 600; color: #1d2327; margin-bottom: 5px;">${priceDisplay}</div>
                        <div class="product-name-main" style="font-size: 13px; font-weight: 500; margin-bottom: 3px; line-height: 1.3;">${product.description}</div>
                        <div class="product-brand" style="font-size: 11px; color: #646970; margin-bottom: 3px;">${product.brand || ''}</div>
                        <div class="product-details" style="font-size: 11px; color: #646970;">
                            ${product.size ? `<span>${product.size}</span>` : ''}
                        </div>
                        ${stockIndicator}
                    </div>
                    <button type="button" class="button button-primary add-product-btn" style="margin-top: 10px; width: 100%;">Add to Recipe</button>
                </div>
            `);
            $container.append($card);
        });
    }
    
    // Filter section toggle
    $(document).on('click', '.filter-header', function() {
        const $section = $(this).closest('.filter-section');
        const $options = $section.find('.filter-options');
        const $icon = $(this).find('.toggle-icon');
        $options.slideToggle();
        $icon.text($options.is(':visible') ? '-' : '+');
    });
    
    // Clear filters
    $(document).on('click', '#kroger-clear-filters', function() {
        $('.filter-options input[type="checkbox"]').prop('checked', false);
        applyClientFilters();
    });
    
    // Filter checkbox change
    $(document).on('change', '.filter-options input[type="checkbox"]', applyClientFilters);
    
    // Add product to recipe (button click) - FIXED VERSION
    $(document).on('click', '.add-product-btn', function(e) {
        e.stopPropagation();
        const $card = $(this).closest('.product-card-modern');
        const product = $card.data('product');
        
        const imageUrl = product.images ? (product.images.large || product.images.medium || product.images.small || '') : '';
        const priceDisplay = product.price ? '$' + parseFloat(product.price).toFixed(2) : '-';
        
        const $row = $(`<tr class="ingredient-row" data-kroger-product-id="${product.productId}" data-kroger-upc="${product.upc || ''}" data-kroger-description="${product.description.replace(/"/g, '&quot;')}" data-kroger-image="${imageUrl}" data-kroger-price="${product.price || ''}"><td><input type="text" class="ingredient-name" value="${product.description.replace(/"/g, '&quot;')}" style="width:100%"></td><td><input type="text" class="ingredient-amount" placeholder="e.g. 2 cups" style="width:100px"></td><td><div style="display:flex;align-items:center;gap:10px;"><img src="${imageUrl}" alt="" style="width:50px;height:50px;object-fit:contain;"><span>${product.description}</span></div></td><td class="price-cell">${priceDisplay}</td><td class="actions-cell"><button type="button" class="button rallyshopper-change-product">Change</button><span class="remove-ingredient dashicons dashicons-trash" style="color:#b32d2e;cursor:pointer;margin-left:10px;" title="Remove"></span></td></tr>`);
        
        $('.no-ingredients').remove();
        
        const $tbody = $('#rallyshopper-ingredients-list table tbody');
        if ($tbody.length) {
            $tbody.append($row);
        } else {
            $('#rallyshopper-ingredients-list').html('<table class="widefat"><thead><tr><th>Ingredient</th><th>Amount</th><th>Kroger Product</th><th>Price</th><th>Actions</th></tr></thead><tbody></tbody></table>');
            $('#rallyshopper-ingredients-list table tbody').append($row);
        }
        
        syncIngredientsToForm();
        $('#kroger-search-modal').removeClass('active');
        showToast('Ingredient added!');
    });
    
    // Remove ingredient row - FIXED
    $(document).on('click', '.remove-ingredient', function() {
        $(this).closest('.ingredient-row').remove();
        
        const rowCount = $('#rallyshopper-ingredients-list table tbody .ingredient-row').length;
        if (rowCount === 0) {
            $('#rallyshopper-ingredients-list').html('<p class="no-ingredients">No ingredients added yet. Click "Add Ingredient" to search Kroger products.</p>');
        }
        
        syncIngredientsToForm();
    });
    
    // Sync ingredients to hidden form fields before submit (for post editor)
    function syncIngredientsToForm() {
        // Remove existing hidden inputs
        $('input[name^="rallyshopper_ingredients["]').remove();
        
        const $form = $('#post');
        if (!$form.length) {
            console.error('Sync failed: #post form not found');
            return;
        }
        
        // Look for all rows in the tbody
        const rows = $('#rallyshopper-ingredients-list table tbody tr');
        console.log('Syncing', rows.length, 'ingredient rows');
        
        rows.each(function(index) {
            const $row = $(this);
            
            // Skip if this is not an ingredient row
            if (!$row.hasClass('ingredient-row') && !$row.find('.ingredient-name').length) {
                return;
            }
            
            const fields = {
                name: $row.find('.ingredient-name').val() || $row.data('kroger-description') || '',
                amount: $row.find('.ingredient-amount').val() || '',
                unit: '',
                kroger_product_id: $row.data('kroger-product-id') || '',
                kroger_upc: $row.data('kroger-upc') || '',
                kroger_description: $row.data('kroger-description') || '',
                kroger_image_url: $row.data('kroger-image') || '',
                kroger_price: $row.data('kroger-price') || ''
            };
            
            // Create hidden inputs for each field
            Object.keys(fields).forEach(key => {
                $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', `rallyshopper_ingredients[${index}][${key}]`)
                    .val(fields[key])
                    .appendTo($form);
            });
            
            console.log(`Synced ingredient ${index}:`, fields.name);
        });
        
        // CRITICAL FIX: If no rows, add a marker so PHP knows we intentionally have 0 ingredients
        if (rows.length === 0) {
            $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'rallyshopper_ingredients_empty')
                .val('1')
                .appendTo($form);
            console.log('Sync complete: 0 ingredients (marked as empty)');
        } else {
            // Count total hidden inputs for verification
            const inputCount = $('input[name^="rallyshopper_ingredients["]').length;
            console.log('Sync complete:', inputCount / 8, 'ingredients synced (', inputCount, 'hidden inputs)');
        }
    }
    
    // Sync before form submit on post editor - CRITICAL FIX
    if ($('#post').length) {
        // Sync on any submit attempt
        $('#post').on('submit', function(e) {
            syncIngredientsToForm();
            console.log('Form submit - synced ingredients');
        });
        
        // Also sync when Update button is clicked (catches early submit)
        $('#publish, #save-post').on('click', function(e) {
            syncIngredientsToForm();
            console.log('Update/Save button clicked - synced ingredients');
        });
    }
    
    // Sync on page load
    syncIngredientsToForm();
    console.log('Initial sync complete');
    
    // Find nearby stores on settings page
    $('#rallyshopper-find-stores').on('click', function() {
        const zip = $('#kroger_zip_code').val();
        if (!zip) {
            showToast('Please enter a zip code', 'error');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Searching...');
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_find_stores',
            nonce: rallyshopper_ajax.nonce,
            zip: zip,
        }, function(response) {
            $btn.prop('disabled', false).text('Find Nearby Stores');
            
            if (response.success) {
                const stores = response.data;
                let html = '<div style="margin-top: 15px;"><h4>Select a Store:</h4>';
                stores.forEach(function(store) {
                    html += `<div class="store-option" style="padding: 10px; border: 1px solid #ccc; margin-bottom: 5px; cursor: pointer;" data-location-id="${store.locationId}">`;
                    html += `<strong>${store.name}</strong><br>`;
                    html += `${store.address.addressLine1}<br>`;
                    html += `${store.address.city}, ${store.address.state} ${store.address.zipCode}`;
                    html += '</div>';
                });
                html += '</div>';
                $('#rallyshopper-store-results').html(html);
                
                $('.store-option').on('click', function() {
                    const locationId = $(this).data('location-id');
                    $('#kroger_location_id').val(locationId);
                    showToast('Store selected! Click Save Settings to save.');
                    $('#rallyshopper-store-results').empty();
                });
            } else {
                showToast(response.data || 'Failed to find stores', 'error');
            }
        });
    });
    
})(jQuery);
