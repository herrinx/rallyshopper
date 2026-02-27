(function($) {
    'use strict';
    
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
    
    // Save recipe
    $('#rallyshopper-save-recipe').on('click', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        
        const ingredients = [];
        $('.ingredient-row').each(function() {
            const $row = $(this);
            ingredients.push({
                name: $row.find('.ingredient-name').val(),
                amount: $row.find('.ingredient-amount').val(),
                unit: $row.find('.ingredient-unit').val(),
                kroger_product_id: $row.data('kroger-product-id') || null,
                kroger_upc: $row.data('kroger-upc') || null,
                kroger_description: $row.data('kroger-description') || null,
                kroger_image_url: $row.data('kroger-image') || null,
                kroger_price: $row.data('kroger-price') || null,
            });
        });
        
        const data = {
            action: 'rallyshopper_save_recipe',
            nonce: rallyshopper_ajax.nonce,
            title: $('#recipe-title').val(),
            description: $('#recipe-description').val(),
            instructions: $('#recipe-instructions').val(),
            servings: $('#recipe-servings').val(),
            prep_time: $('#recipe-prep-time').val(),
            cook_time: $('#recipe-cook-time').val(),
            difficulty: $('#recipe-difficulty').val(),
            ingredients: ingredients,
        };
        
        if ($('#recipe-post-id').val()) {
            data.post_id = $('#recipe-post-id').val();
        }
        
        $.post(rallyshopper_ajax.ajax_url, data, function(response) {
            $btn.prop('disabled', false).text('Save Recipe');
            
            if (response.success) {
                showToast('Recipe saved successfully!');
                if (response.data.post_id && !data.post_id) {
                    window.location.href = 'admin.php?page=rallyshopper-add&edit=' + response.data.post_id;
                }
            } else {
                showToast(response.data || 'Error saving recipe', 'error');
            }
        });
    });
    
    // Delete recipe
    $('.rallyshopper-delete-recipe').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this recipe?')) {
            return;
        }
        
        const $btn = $(this);
        const postId = $btn.data('id');
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_delete_recipe',
            nonce: rallyshopper_ajax.nonce,
            post_id: postId,
        }, function(response) {
            if (response.success) {
                showToast('Recipe deleted');
                $btn.closest('.rallyshopper-recipe-card').fadeOut();
            } else {
                showToast(response.data || 'Error deleting recipe', 'error');
            }
        });
    });
    
    // Kroger product search modal
    let pendingProductSelection = null;
    
    // Add ingredient - opens Kroger search FIRST
    $(document).on('click', '#rallyshopper-add-ingredient, .rallyshopper-add-ingredient', function() {
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
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_search_kroger',
            nonce: rallyshopper_ajax.nonce,
            query: query,
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
        const $container = $('#kroger-search-results');
        $container.empty();
        
        if (!products || !Array.isArray(products) || products.length === 0) {
            $container.html('<p>No products found.</p>');
            return;
        }
        
        products.forEach(function(product) {
            const $card = $(`
                <div class="product-card" data-product='${JSON.stringify(product).replace(/'/g, "&#39;")}'>
                    <img src="${product.image || ''}" alt="${product.description}">
                    <div class="product-name">${product.description}</div>
                    <div class="product-brand">${product.brand}</div>
                    <div class="product-price">${product.price ? '$' + product.price : ''}</div>
                </div>
            `);
            $container.append($card);
        });
    }
    
    // Select product - creates ingredient row
    $(document).on('click', '.product-card', function() {
        const product = $(this).data('product');
        
        // Check which page we're on (custom editor vs post editor)
        const isPostEditor = $('#rallyshopper-ingredients-list table').length > 0;
        
        if (isPostEditor) {
            // Post editor uses table structure
            const $tableBody = $('#rallyshopper-ingredients-list table tbody');
            if ($tableBody.length === 0) {
                // Create table if it doesn't exist
                $('#rallyshopper-ingredients-list').html(`
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Amount</th>
                                <th>Kroger Product</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                `);
            }
            
            const $row = $(`
                <tr class="ingredient-row" 
                    data-kroger-product-id="${product.productId}"
                    data-kroger-upc="${product.upc || ''}"
                    data-kroger-description="${product.description.replace(/"/g, '&quot;')}"
                    data-kroger-image="${product.image || ''}"
                    data-kroger-price="${product.price || ''}">
                    <td>
                        <span class="ingredient-name-display">${product.description}</span>
                        <input type="hidden" class="ingredient-name" value="${product.description.replace(/"/g, '&quot;')}">
                    </td>
                    <td><input type="text" class="ingredient-amount" placeholder="e.g. 2 cups" style="width:100px"></td>
                    <td>
                        <div class="kroger-product-display">
                            <img src="${product.image || ''}" alt="">
                            <div class="product-info">
                                <span class="product-name">${product.description}</span>
                            </div>
                        </div>
                    </td>
                    <td class="price-cell">${product.price ? '$' + product.price : '-'}</td>
                    <td class="actions-cell">
                        <button type="button" class="button rallyshopper-change-product">Change</button>
                        <span class="remove-ingredient dashicons dashicons-trash" title="Remove ingredient"></span>
                    </td>
                </tr>
            `);
            
            $('.no-ingredients').remove();
            $('#rallyshopper-ingredients-list table tbody').append($row);
            syncIngredientsToForm();
        } else {
            // Custom editor uses div structure
            const $row = $(`
                <div class="ingredient-row" 
                     data-kroger-product-id="${product.productId}"
                     data-kroger-upc="${product.upc || ''}"
                     data-kroger-description="${product.description.replace(/"/g, '&quot;')}"
                     data-kroger-image="${product.image || ''}"
                     data-kroger-price="${product.price || ''}">
                    
                    <div class="ingredient-product-display">
                        <img src="${product.image || ''}" alt="" class="product-thumb">
                        <div class="product-details">
                            <div class="product-name">${product.description}</div>
                            <div class="product-brand">${product.brand || ''}</div>
                            <div class="product-price">${product.price ? '$' + product.price : ''}</div>
                        </div>
                    </div>
                    
                    <div class="ingredient-inputs">
                        <input type="text" class="ingredient-amount" placeholder="Amount (e.g., 2)">
                        <input type="text" class="ingredient-unit" placeholder="Unit (e.g., cups)">
                        <input type="hidden" class="ingredient-name" value="${product.description.replace(/"/g, '&quot;')}">
                    </div>
                    
                    <button type="button" class="button rallyshopper-change-product">Change Product</button>
                    <span class="remove-ingredient dashicons dashicons-trash"></span>
                </div>
            `);
            
            $('.no-ingredients').remove();
            $('#ingredients-container').append($row);
        }
        
        // Close modal
        $('#kroger-search-modal').removeClass('active');
        showToast('Ingredient added! Enter amount and unit.');
    });
    
    // Change product on existing ingredient
    $(document).on('click', '.rallyshopper-change-product', function() {
        const $row = $(this).closest('.ingredient-row');
        pendingProductSelection = $row;
        $('#kroger-search-modal').addClass('active');
        $('#kroger-search-input').val('').focus();
        $('#kroger-search-results').html('<p>Search for a different product.</p>');
    });
    
    // Update product selection for existing row
    $(document).on('click', '.product-card', function() {
        if (!pendingProductSelection) return; // Already handled above for new rows
        
        const product = $(this).data('product');
        const $row = pendingProductSelection;
        
        $row.data('kroger-product-id', product.productId);
        $row.data('kroger-upc', product.upc);
        $row.data('kroger-description', product.description);
        $row.data('kroger-image', product.image);
        $row.data('kroger-price', product.price);
        
        $row.find('.ingredient-product-display').html(`
            <img src="${product.image || ''}" alt="" class="product-thumb">
            <div class="product-details">
                <div class="product-name">${product.description}</div>
                <div class="product-brand">${product.brand || ''}</div>
                <div class="product-price">${product.price ? '$' + product.price : ''}</div>
            </div>
        `);
        
        $row.find('.ingredient-name').val(product.description);
        
        $('#kroger-search-modal').removeClass('active');
        pendingProductSelection = null;
        
        showToast('Product updated!');
    });
    
    // Remove ingredient row
    $(document).on('click', '.remove-ingredient', function() {
        $(this).closest('.ingredient-row').remove();
        
        if ($('.ingredient-row').length === 0) {
            if ($('#ingredients-container').length) {
                $('#ingredients-container').html('<p class="no-ingredients">No ingredients added yet. Click "Add Ingredient" to search Kroger products.</p>');
            }
            if ($('#rallyshopper-ingredients-list').length) {
                $('#rallyshopper-ingredients-list').html('<p class="no-ingredients">No ingredients added yet.</p>');
            }
        }
        syncIngredientsToForm();
    });
    
    // Sync ingredients to hidden form fields before submit (for post editor)
    function syncIngredientsToForm() {
        // Remove existing hidden inputs
        $('input[name^="rallyshopper_ingredients["]').remove();
        
        $('.ingredient-row').each(function(index) {
            const $row = $(this);
            const prefix = `rallyshopper_ingredients[${index}]`;
            
            // Add hidden inputs for each ingredient
            const fields = {
                name: $row.find('.ingredient-name').val() || $row.data('kroger-description'),
                amount: $row.find('.ingredient-amount').val() || '',
                unit: $row.find('.ingredient-unit').val() || '',
                kroger_product_id: $row.data('kroger-product-id') || '',
                kroger_upc: $row.data('kroger-upc') || '',
                kroger_description: $row.data('kroger-description') || '',
                kroger_image_url: $row.data('kroger-image') || '',
                kroger_price: $row.data('kroger-price') || ''
            };
            
            Object.keys(fields).forEach(key => {
                $('<input>').attr({
                    type: 'hidden',
                    name: `${prefix}[${key}]`,
                    value: fields[key]
                }).appendTo('#post');
            });
        });
    }
    
    // Sync before form submit on post editor
    if ($('#post').length) {
        $('#post').on('submit', function() {
            syncIngredientsToForm();
        });
    }
    
    // Sync existing ingredients on page load (for post editor)
    if ($('#post').length && $('.ingredient-row').length > 0) {
        syncIngredientsToForm();
    }
    
    // Add to cart
    $('.rallyshopper-add-to-cart').on('click', function() {
        const $btn = $(this);
        const recipeId = $btn.data('recipe');
        
        $btn.addClass('loading').text('Adding...');
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_add_to_cart',
            nonce: rallyshopper_ajax.nonce,
            recipe_id: recipeId,
        }, function(response) {
            $btn.removeClass('loading').text('Add to Kroger Cart');
            
            if (response.success) {
                let message = 'Added ' + response.data.added + ' items to cart';
                if (response.data.errors && response.data.errors.length > 0) {
                    message += ' (' + response.data.errors.length + ' skipped)';
                }
                showToast(message);
            } else {
                showToast(response.data || 'Failed to add to cart', 'error');
            }
        });
    });
    
    // View cart
    $('#rallyshopper-view-cart').on('click', function() {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_cart',
            nonce: rallyshopper_ajax.nonce,
        }, function(response) {
            if (response.success) {
                console.log('Cart:', response.data);
                showToast('Cart loaded (check console)');
            } else {
                showToast(response.data || 'Failed to load cart', 'error');
            }
        });
    });
    
})(jQuery);
