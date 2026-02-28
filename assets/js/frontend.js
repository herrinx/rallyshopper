(function($) {
    'use strict';
    
    console.log('RallyShopper frontend.js loading...');
    
    // State
    let currentRecipe = null;
    let pendingIngredient = null;
    let stapleQueue = [];
    let stapleIndex = 0;
    let currentRecipeId = null;
    let lastSearchResults = [];
    let currentFilters = { brand: [], fulfillment: ['delivery'], price: [], size: [] };
    
    // Initialize when DOM is ready
    $(function() {
        console.log('RallyShopper DOM ready');
        
        // Load categories on init
        loadCategories();
        
        // Load meal plans on init
        loadMealPlans();
        
        // Live search with debounce
        let searchTimeout;
        $(document).on('input', '#rs-live-search', function() {
            const query = $(this).val().trim();
            clearTimeout(searchTimeout);
            
            if (query.length === 0) {
                // Show all recipes
                $('.recipe-card').show().removeClass('filtered-out');
                $('.rs-search-results-info').remove();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                performLiveSearch(query);
            }, 300); // 300ms debounce
        });
        
        // Category filter
        $(document).on('change', '#rs-category-filter', function() {
            filterByCategory($(this).val());
        });
        
        // Manage categories button
        $(document).on('click', '#rs-btn-manage-categories', function() {
            $('#rs-modal-categories').addClass('active');
            loadCategoryList();
        });
        
        // Save category
        $(document).on('click', '#rs-btn-save-category', function() {
            saveCategory();
        });
        
        // Meal plan button
        $(document).on('click', '#rs-btn-meal-plan', function() {
            showView('meal-plan');
        });

        // Back from meal plan
        $(document).on('click', '#rs-btn-back-from-plan', function() {
            showView('list');
        });
        
        // New meal plan
        $(document).on('click', '#rs-btn-new-plan', function() {
            const name = prompt('Enter plan name (e.g., "Week of March 1"):');
            if (name) {
                createMealPlan(name);
            }
        });
        
        // Meal plan selector
        $(document).on('change', '#rs-meal-plan-selector', function() {
            loadMealPlanRecipes($(this).val());
        });
        
        // Modal close buttons
        $(document).on('click', '.rs-modal-close', function() {
            const modalId = $(this).data('modal');
            $('#' + modalId).removeClass('active');
        });
        
        // Close modal on background click
        $(document).on('click', '.rs-modal', function(e) {
            if ($(e.target).hasClass('rs-modal')) {
                $(this).removeClass('active');
            }
        });
        
        // Add to meal plan confirmation
        $(document).on('click', '#rs-btn-confirm-add-to-plan', function() {
            confirmAddToMealPlan();
        });
    });
    
    // Add to Cart - delegated handler
    let isAddingToCart = false;
    $(document).on('click', '.rs-btn-cart', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (isAddingToCart) return;
        isAddingToCart = true;
        const id = $(this).data('id');
        console.log('Add to Cart clicked for recipe:', id);
        addRecipeToCart(id, $(this), function() {
            isAddingToCart = false;
        });
    });
    
    function addRecipeToCart(recipeId, $btn, callback) {
        currentRecipeId = recipeId;
        $btn.prop('disabled', true).text('Adding...');
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_recipe',
            nonce: rallyshopper_ajax.nonce,
            recipe_id: recipeId
        }, function(res) {
            if (!res.success) {
                toast('Failed to load recipe', 'error');
                $btn.prop('disabled', false).text('Add to Cart');
                return;
            }
            
            const ingredients = res.data.ingredients.filter(i => i.kroger_product_id);
            if (ingredients.length === 0) {
                toast('No linked ingredients');
                $btn.prop('disabled', false).text('Add to Cart');
                return;
            }
            
            stapleQueue = [];
            let checked = 0;
            
            console.log('Ingredients from API:', ingredients);
            ingredients.forEach(function(ing) {
                console.log('Processing ingredient:', ing);
                $.post(rallyshopper_ajax.ajax_url, {
                    action: 'rallyshopper_check_staple',
                    nonce: rallyshopper_ajax.nonce,
                    recipe_id: recipeId,
                    product_id: ing.kroger_product_id
                }, function(stapleRes) {
                    checked++;
                    
                    // Parse quantity from amount (e.g., "2 lbs" → 2)
                    let qty = 1;
                    if (ing.amount) {
                        const match = ing.amount.match(/^(\d+\.?\d*)/);
                        if (match) {
                            qty = Math.max(1, Math.ceil(parseFloat(match[1])));
                        }
                    }
                    
                    const item = {
                        product_id: ing.kroger_product_id,
                        upc: ing.kroger_upc,
                        name: ing.kroger_description || ing.name,
                        quantity: qty
                    };
                    
                    console.log('Staple check for', item.name, ':', stapleRes);
                    if (stapleRes.success && stapleRes.data.is_staple) {
                        // Staple: Skip from main flow, will show as individual button
                        console.log('Adding to stapleQueue:', item.name);
                        stapleQueue.push({...item, staple_count: stapleRes.data.count});
                    } else {
                        // Non-staple: Add to cart immediately
                        addItemsToCart([item], $btn, recipeId);
                    }
                    
                    if (checked === ingredients.length) {
                        console.log('All checked. stapleQueue length:', stapleQueue.length);
                        // All done - non-staples added, staples queued for individual buttons
                        // Find the recipe card container
                        const $card = $btn.closest('.recipe-card');
                        $card.find('.rs-staples-list').remove();
                        if (stapleQueue.length > 0) {
                            console.log('Calling renderStapleButtons');
                            renderStapleButtons(stapleQueue, recipeId, $card);
                        }
                        $btn.prop('disabled', false).text('Add to Cart');
                        if (callback) callback();
                    }
                });
            });
        });
    }
    
    function renderStapleButtons(staples, recipeId, $container) {
        console.log('renderStapleButtons called with', staples.length, 'staples');
        console.log('Container found:', $container.length);
        let html = '<div class="rs-staples-list"><h4>Staples (add individually)</h4>';
        staples.forEach(function(item, idx) {
            html += '<div class="rs-staple-item">' +
                '<span>' + item.name + '</span>' +
                '<button class="rs-btn rs-btn-sm rs-add-staple" data-idx="' + idx + '">+ Add</button>' +
                '</div>';
        });
        html += '</div>';
        $container.append(html);
        
        // Scope click handler to this container only
        $container.find('.rs-add-staple').on('click', function() {
            const idx = $(this).data('idx');
            const item = staples[idx];
            addItemsToCart([item], $(this), recipeId);
            $(this).prop('disabled', true).text('Added');
        });
    }
    
    function showStapleModal($btn, recipeId) {
        if (stapleIndex >= stapleQueue.length) {
            $('#rs-modal-staple').removeClass('active');
            $btn.prop('disabled', false).text('Add to Cart');
            return;
        }
        const item = stapleQueue[stapleIndex];
        $('#rs-staple-name').text(item.name);
        $('#rs-staple-count').text(item.staple_count);
        $('#rs-modal-staple').addClass('active');
    }
    
    $(document).on('click', '#rs-staple-yes', function() {
        addItemsToCart([stapleQueue[stapleIndex]], $('.rs-btn-cart:disabled'), currentRecipeId);
        stapleIndex++;
        showStapleModal($('.rs-btn-cart:disabled'), currentRecipeId);
    });
    
    $(document).on('click', '#rs-staple-no', function() {
        stapleIndex++;
        showStapleModal($('.rs-btn-cart:disabled'), currentRecipeId);
    });
    
    function addItemsToCart(items, $btn, recipeId) {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_add_to_cart',
            nonce: rallyshopper_ajax.nonce,
            recipe_id: recipeId,
            items: items
        }, function(res) {
            if (res.success) {
                toast('Added ' + items.length + ' items to cart');
            } else {
                toast('Failed to add: ' + (res.data || 'Unknown error'), 'error');
                console.error('Add to cart error:', res);
            }
            if ($btn) $btn.prop('disabled', false).text('Add to Cart');
        }).fail(function(xhr) {
            toast('Failed to add items', 'error');
            console.error('AJAX error:', xhr.responseText);
            if ($btn) $btn.prop('disabled', false).text('Add to Cart');
        });
    }
    
    // View switching
    function showView(viewName) {
        $('.view').removeClass('active');
        $('#rs-view-' + viewName).addClass('active');
    }
    
    $(document).on('click', '#rs-btn-back', function() {
        showView('list');
        location.reload();
    });
    
    // New recipe
    $(document).on('click', '#rs-btn-new', function() {
        currentRecipe = null;
        $('#rs-recipe-id').val('');
        $('#rs-editor-title').text('New Recipe');
        $('#rs-title').val('');
        $('#rs-description').val('');
        $('#rs-instructions').val('');
        $('#rs-servings').val(4);
        $('#rs-prep-time').val(0);
        $('#rs-cook-time').val(0);
        $('#rs-difficulty').val('medium');
        $('#rs-ingredients-list').empty();
        $('#rs-featured-image').val('');
        $('#rs-image-preview').empty();
        $('#rs-btn-select-image').text('Select Image');
        $('#rs-btn-remove-image').hide();
        showView('editor');
    });
    
    // Edit recipe
    $(document).on('click', '.rs-btn-edit', function() {
        const id = $(this).data('id');
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_recipe',
            nonce: rallyshopper_ajax.nonce,
            recipe_id: id
        }, function(res) {
            if (!res.success) { toast('Failed to load', 'error'); return; }
            
            currentRecipe = res.data;
            $('#rs-recipe-id').val(currentRecipe.post.ID);
            $('#rs-editor-title').text('Edit: ' + currentRecipe.post.post_title);
            $('#rs-title').val(currentRecipe.post.post_title);
            $('#rs-description').val(currentRecipe.post.post_excerpt);
            $('#rs-instructions').val(currentRecipe.post.post_content);
            $('#rs-servings').val(currentRecipe.meta.servings);
            $('#rs-prep-time').val(currentRecipe.meta.prep_time);
            $('#rs-cook-time').val(currentRecipe.meta.cook_time);
            $('#rs-difficulty').val(currentRecipe.meta.difficulty);
            
            if (currentRecipe.thumbnail) {
                $('#rs-image-preview').html('<img src="' + currentRecipe.thumbnail + '" style="max-width:300px">');
                $('#rs-featured-image').val(currentRecipe.post.featured_image_id || '');
                $('#rs-btn-select-image').text('Change Image');
                $('#rs-btn-remove-image').show();
            } else {
                $('#rs-image-preview').empty();
                $('#rs-featured-image').val('');
                $('#rs-btn-select-image').text('Select Image');
                $('#rs-btn-remove-image').hide();
            }
            
            $('#rs-ingredients-list').empty();
            currentRecipe.ingredients.forEach(addIngredientRow);
            showView('editor');
        });
    });
    
    // Delete recipe
    $(document).on('click', '.rs-btn-delete', function() {
        if (!confirm('Delete?')) return;
        const id = $(this).data('id');
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_delete_recipe',
            nonce: rallyshopper_ajax.nonce,
            recipe_id: id
        }, function() { location.reload(); });
    });
    
    // Ingredients
    function addIngredientRow(ing) {
        ing = ing || {};
        // Force is_staple to proper boolean (handles string "0" or number 0)
        const isStaple = parseInt(ing.is_staple) === 1;
        const html = '<div class="ingredient-row" data-id="' + (ing.id || '') + '">' +
            '<div class="ing-product">' +
            (ing.kroger_product_id 
                ? '<img src="' + (ing.kroger_image_url || '') + '" class="prod-img"><span class="prod-name">' + (ing.kroger_description || '') + '</span><button type="button" class="button rs-btn-change-prod">Change</button>'
                : '<button type="button" class="button rs-btn-link-prod">Link Product</button>') +
            '</div>' +
            '<input type="text" class="ing-amount" placeholder="Amount" value="' + (ing.amount || '') + '">' +
            '<label class="staple-label"><input type="checkbox" class="ing-is-staple" ' + (isStaple ? 'checked' : '') + '> Staple</label>' +
            '<span class="ing-price">' + (ing.kroger_price ? '$' + parseFloat(ing.kroger_price).toFixed(2) : '') + '</span>' +
            '<button type="button" class="button rs-btn-remove-ing">&times;</button>' +
            '<input type="hidden" class="ing-name" value="' + (ing.name || '') + '">' +
            '<input type="hidden" class="ing-prod-id" value="' + (ing.kroger_product_id || '') + '">' +
            '<input type="hidden" class="ing-upc" value="' + (ing.kroger_upc || '') + '">' +
            '<input type="hidden" class="ing-prod-name" value="' + (ing.kroger_description || '') + '">' +
            '<input type="hidden" class="ing-prod-img" value="' + (ing.kroger_image_url || '') + '">' +
            '<input type="hidden" class="ing-prod-price" value="' + (ing.kroger_price || '') + '">' +
            '</div>';
        $('#rs-ingredients-list').append(html);
    }
    
    $(document).on('click', '#rs-btn-add-ing', function() { addIngredientRow(); });
    $(document).on('click', '.rs-btn-remove-ing', function() { $(this).closest('.ingredient-row').remove(); });
    
    // Product search modal
    $(document).on('click', '.rs-btn-link-prod, .rs-btn-change-prod', function() {
        pendingIngredient = $(this).closest('.ingredient-row');
        $('#rs-modal-search').addClass('active');
        $('#rs-search-input').val('').focus();
        $('#rs-search-results').empty();
        $('#rs-brand-filters, #rs-size-filters').hide();
        lastSearchResults = [];
        currentFilters = { brand: [], fulfillment: ['delivery'], price: [], size: [] };
        $('.rs-filter').prop('checked', false);
        $('.rs-filter[data-filter="fulfillment"][value="delivery"]').prop('checked', true);
    });
    
    $(document).on('click', '.modal-close', function() {
        $('.rs-modal').removeClass('active');
        pendingIngredient = null;
    });
    
    // Search
    $(document).on('click', '#rs-btn-search', doSearch);
    $(document).on('keypress', '#rs-search-input', function(e) { if (e.which === 13) doSearch(); });
    
    function doSearch() {
        const query = $('#rs-search-input').val().trim();
        if (!query) return;
        $('#rs-btn-search').prop('disabled', true).text('Searching...');
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_search_kroger',
            nonce: rallyshopper_ajax.nonce,
            query: query
        }, function(res) {
            $('#rs-btn-search').prop('disabled', false).text('Search');
            if (!res.success) {
                $('#rs-search-results').html('<p class="error">' + res.data + '</p>');
                return;
            }
            lastSearchResults = res.data;
            generateFilters(lastSearchResults);
            applyFilters();
        });
    }
    
    function generateFilters(products) {
        const brands = {}, sizes = {};
        products.forEach(function(p) {
            if (p.brand) brands[p.brand] = (brands[p.brand] || 0) + 1;
            if (p.size) sizes[p.size] = (sizes[p.size] || 0) + 1;
        });
        
        let brandHtml = '';
        Object.entries(brands).sort((a,b) => b[1]-a[1]).slice(0,10).forEach(function([b,c]) {
            brandHtml += '<label><input type="checkbox" class="rs-filter" data-filter="brand" value="' + b + '"> ' + b + ' (' + c + ')</label>';
        });
        $('#rs-brand-list').html(brandHtml);
        $('#rs-brand-filters').toggle(brandHtml !== '');
        
        let sizeHtml = '';
        Object.entries(sizes).sort((a,b) => b[1]-a[1]).slice(0,10).forEach(function([s,c]) {
            sizeHtml += '<label><input type="checkbox" class="rs-filter" data-filter="size" value="' + s + '"> ' + s + ' (' + c + ')</label>';
        });
        $('#rs-size-list').html(sizeHtml);
        $('#rs-size-filters').toggle(sizeHtml !== '');
    }
    
    $(document).on('change', '.rs-filter', function() {
        const type = $(this).data('filter'), val = $(this).val();
        if ($(this).is(':checked')) currentFilters[type].push(val);
        else currentFilters[type] = currentFilters[type].filter(v => v !== val);
        applyFilters();
    });
    
    $(document).on('click', '#rs-clear-filters', function() {
        $('.rs-filter').prop('checked', false);
        currentFilters = { brand: [], fulfillment: [], price: [], size: [] };
        applyFilters();
    });
    
    function applyFilters() {
        const filtered = lastSearchResults.filter(function(p) {
            if (currentFilters.brand.length && !currentFilters.brand.includes(p.brand)) return false;
            if (currentFilters.size.length && !currentFilters.size.includes(p.size)) return false;
            if (currentFilters.price.length) {
                const price = parseFloat(p.price) || 0;
                const inRange = currentFilters.price.some(r => {
                    if (r === 'under5') return price < 5;
                    if (r === '5to10') return price >= 5 && price < 10;
                    if (r === '10to20') return price >= 10 && price < 20;
                    if (r === 'over20') return price >= 20;
                    return false;
                });
                if (!inRange) return false;
            }
            return true;
        });
        renderProducts(filtered);
    }
    
    function renderProducts(products) {
        if (!products.length) {
            $('#rs-search-results').html('<p>No products match filters.</p>');
            return;
        }
        let html = '<div class="product-grid">';
        products.forEach(function(p) {
            const img = p.images.small || p.images.thumbnail || '';
            html += '<div class="product-card" data-product=\'' + JSON.stringify(p).replace(/'/g, "&#39;") + '\'>' +
                '<img src="' + img + '">' +
                '<div class="prod-info">' +
                '<div class="prod-price">' + (p.price ? '$' + parseFloat(p.price).toFixed(2) : '') + '</div>' +
                '<div class="prod-title">' + p.description + '</div>' +
                '<div class="prod-brand">' + p.brand + '</div>' +
                '</div>' +
                '<button class="button button-primary rs-btn-select">Select</button>' +
                '</div>';
        });
        html += '</div>';
        $('#rs-search-results').html(html);
    }
    
    $(document).on('click', '.rs-btn-select', function() {
        if (!pendingIngredient) return;
        const p = $(this).closest('.product-card').data('product');
        const img = p.images.small || '';
        pendingIngredient.find('.ing-product').html('<img src="' + img + '" class="prod-img"><span class="prod-name">' + p.description + '</span><button type="button" class="button rs-btn-change-prod">Change</button>');
        pendingIngredient.find('.ing-prod-id').val(p.productId);
        pendingIngredient.find('.ing-upc').val(p.upc);
        pendingIngredient.find('.ing-prod-name').val(p.description);
        pendingIngredient.find('.ing-prod-img').val(img);
        pendingIngredient.find('.ing-prod-price').val(p.price);
        $('#rs-modal-search').removeClass('active');
        pendingIngredient = null;
    });
    
    // Save recipe
    $(document).on('click', '#rs-btn-save', function() {
        const ingredients = [];
        $('#rs-ingredients-list .ingredient-row').each(function() {
            const $r = $(this);
            const isStapleChecked = $r.find('.ing-is-staple').is(':checked');
            console.log('Saving ingredient:', $r.find('.ing-name').val(), 'staple checked:', isStapleChecked);
            ingredients.push({
                name: $r.find('.ing-name').val(),
                amount: $r.find('.ing-amount').val(),
                kroger_product_id: $r.find('.ing-prod-id').val(),
                kroger_upc: $r.find('.ing-upc').val(),
                kroger_description: $r.find('.ing-prod-name').val(),
                kroger_image_url: $r.find('.ing-prod-img').val(),
                kroger_price: $r.find('.ing-prod-price').val(),
                is_staple: isStapleChecked ? 1 : 0
            });
        });
        
        console.log('Sending ingredients:', ingredients);
        ingredients.forEach((ing, i) => {
            console.log('Ingredient', i, 'is_staple:', ing.is_staple);
        });
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_save_recipe',
            nonce: rallyshopper_ajax.nonce,
            post_id: $('#rs-recipe-id').val(),
            title: $('#rs-title').val(),
            description: $('#rs-description').val(),
            instructions: $('#rs-instructions').val(),
            servings: $('#rs-servings').val(),
            prep_time: $('#rs-prep-time').val(),
            cook_time: $('#rs-cook-time').val(),
            difficulty: $('#rs-difficulty').val(),
            featured_image: $('#rs-featured-image').val(),
            ingredients_json: JSON.stringify(ingredients)
        }, function(res) {
            console.log('Save response:', res);
            if (res.success) {
                toast('Saved!');
                console.log('Debug:', res.data.debug);
                if (!$('#rs-recipe-id').val()) $('#rs-recipe-id').val(res.data.post_id);
            } else {
                toast('Save failed: ' + res.data, 'error');
            }
        });
    });
    
    // Featured image
    $(document).on('click', '#rs-btn-select-image', function() {
        const $btn = $(this);
        const $input = $('<input type="file" accept="image/*" style="display:none">');
        $('body').append($input);
        
        $input.on('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            const fd = new FormData();
            fd.append('action', 'rallyshopper_upload_image');
            fd.append('nonce', rallyshopper_ajax.nonce);
            fd.append('image', file);
            
            $btn.prop('disabled', true).text('Uploading...');
            
            $.ajax({
                url: rallyshopper_ajax.ajax_url,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        $('#rs-image-preview').html('<img src="' + res.data.url + '" style="max-width:300px">');
                        $('#rs-featured-image').val(res.data.attachment_id);
                        $btn.text('Change Image');
                        $('#rs-btn-remove-image').show();
                        toast('Image uploaded');
                    } else {
                        toast('Upload failed', 'error');
                    }
                },
                error: function() { toast('Upload failed', 'error'); },
                complete: function() {
                    $btn.prop('disabled', false);
                    $input.remove();
                }
            });
        });
        
        $input.trigger('click');
    });
    
    $(document).on('click', '#rs-btn-remove-image', function() {
        $('#rs-image-preview').empty();
        $('#rs-featured-image').val('');
        $('#rs-btn-select-image').text('Select Image');
        $(this).hide();
    });
    
    // Live search function
    function performLiveSearch(query) {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_search_recipes_live',
            nonce: rallyshopper_ajax.nonce,
            query: query
        }, function(res) {
            if (!res.success) {
                toast('Search failed', 'error');
                return;
            }
            
            const results = res.data.results;
            const allRecipeIds = res.data.all_ids || [];
            
            // Hide all first
            $('.recipe-card').hide();
            
            // Show matching recipes
            results.forEach(function(r) {
                $('.recipe-card[data-id="' + r.id + '"]').show();
            });
            
            // Show results info
            $('.rs-search-results-info').remove();
            const info = results.length + ' of ' + allRecipeIds.length + ' recipes match "' + query + '"';
            $('#rs-recipe-grid').before('<div class="rs-search-results-info">' + info + '</div>');
            
        }).fail(function() {
            toast('Search failed', 'error');
        });
    }
    
    // Toast
    function toast(msg, type) {
        type = type || 'success';
        const $t = $('<div class="rs-toast ' + type + '">' + msg + '</div>');
        $('#rs-toasts').append($t);
        setTimeout(() => $t.fadeOut(() => $t.remove()), 3000);
    }

    // Category Functions
    function loadCategories() {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_categories',
            nonce: rallyshopper_ajax.nonce
        }, function(res) {
            if (res.success) {
                const categories = res.data;
                // Populate filter dropdown
                const $filter = $('#rs-category-filter');
                $filter.html('<option value="">All Categories</option>');
                categories.forEach(function(cat) {
                    $filter.append('<option value="' + cat.id + '">' + cat.name + '</option>');
                });
                
                // Populate category selector in editor
                const $selector = $('#rs-categories');
                $selector.empty();
                categories.forEach(function(cat) {
                    $selector.append(
                        '<label class="rs-category-checkbox" data-id="' + cat.id + '">' +
                        '<input type="checkbox" value="' + cat.id + '"> ' +
                        '<span>' + cat.name + '</span>' +
                        '</label>'
                    );
                });
            }
        });
    }

    function loadCategoryList() {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_categories',
            nonce: rallyshopper_ajax.nonce
        }, function(res) {
            if (res.success) {
                const categories = res.data;
                const $list = $('#rs-category-list');
                $list.empty();
                categories.forEach(function(cat) {
                    $list.append(
                        '<div class="category-item" style="border-color:' + cat.color + '">' +
                        '<span class="category-item-name">' + cat.name + '</span>' +
                        '<div class="category-item-actions">' +
                        '<button class="button" onclick="editCategory(' + cat.id + ', \'' + cat.name + '\', \'' + cat.color + '\')">Edit</button>' +
                        '<button class="button" onclick="deleteCategory(' + cat.id + ')">Delete</button>' +
                        '</div>' +
                        '</div>'
                    );
                });
            }
        });
    }

    function saveCategory() {
        const id = $('#rs-category-id').val();
        const name = $('#rs-category-name').val();
        const color = $('#rs-category-color').val();
        
        if (!name) {
            toast('Please enter a category name', 'error');
            return;
        }
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_save_category',
            nonce: rallyshopper_ajax.nonce,
            id: id,
            name: name,
            color: color
        }, function(res) {
            if (res.success) {
                toast('Category saved');
                $('#rs-category-id').val('');
                $('#rs-category-name').val('');
                $('#rs-category-color').val('#0073aa');
                loadCategoryList();
                loadCategories();
            } else {
                toast('Failed to save category', 'error');
            }
        });
    }

    window.editCategory = function(id, name, color) {
        $('#rs-category-id').val(id);
        $('#rs-category-name').val(name);
        $('#rs-category-color').val(color);
    };

    window.deleteCategory = function(id) {
        if (!confirm('Delete this category? Recipes will keep their other categories.')) return;
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_delete_category',
            nonce: rallyshopper_ajax.nonce,
            id: id
        }, function(res) {
            if (res.success) {
                toast('Category deleted');
                loadCategoryList();
                loadCategories();
            } else {
                toast('Failed to delete category', 'error');
            }
        });
    };

    function filterByCategory(categoryId) {
        if (!categoryId) {
            $('.recipe-card').show();
            return;
        }
        
        // Hide all first
        $('.recipe-card').hide();
        
        // Show recipes in category (would need backend support)
        // For now, just show all
        $('.recipe-card').show();
        toast('Category filter: ' + categoryId);
    }

    // Meal Plan Functions
    function loadMealPlans() {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_meal_plans',
            nonce: rallyshopper_ajax.nonce
        }, function(res) {
            if (res.success) {
                const plans = res.data;
                const $selector = $('#rs-meal-plan-selector, #rs-add-plan-selector');
                $selector.html('<option value="">Select Plan...</option>');
                plans.forEach(function(plan) {
                    $selector.append('<option value="' + plan.id + '">' + plan.name + '</option>');
                });
            }
        });
    }

    function createMealPlan(name) {
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_save_meal_plan',
            nonce: rallyshopper_ajax.nonce,
            name: name
        }, function(res) {
            if (res.success) {
                toast('Meal plan created');
                loadMealPlans();
                $('#rs-meal-plan-selector').val(res.data.id).trigger('change');
            } else {
                toast('Failed to create meal plan', 'error');
            }
        });
    }

    function loadMealPlanRecipes(planId) {
        if (!planId) {
            $('.meal-recipes').empty();
            return;
        }
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_get_meal_plan_recipes',
            nonce: rallyshopper_ajax.nonce,
            plan_id: planId
        }, function(res) {
            if (res.success) {
                const recipes = res.data;
                // Clear all slots
                $('.meal-recipes').empty();
                
                // Populate slots
                recipes.forEach(function(item) {
                    const selector = '.meal-day[data-day="' + item.day_of_week + '"] .meal-slot[data-meal="' + item.meal_type + '"]';
                    const $slot = $(selector + ' .meal-recipes');
                    $slot.append(
                        '<div class="meal-recipe-item" data-id="' + item.id + '">' +
                        '<span>' + item.recipe_title + '</span>' +
                        '<button onclick="removeFromMealPlan(' + item.plan_id + ', ' + item.recipe_id + ')">&times;</button>' +
                        '</div>'
                    );
                });
            }
        });
    }

    window.addToMealPlan = function(recipeId, recipeTitle) {
        $('#rs-add-plan-recipe-id').val(recipeId);
        $('#rs-modal-add-to-plan').addClass('active');
    };

    function confirmAddToMealPlan() {
        const planId = $('#rs-add-plan-selector').val();
        const recipeId = $('#rs-add-plan-recipe-id').val();
        const day = $('#rs-add-plan-day').val();
        const meal = $('#rs-add-plan-meal').val();
        
        if (!planId) {
            toast('Please select a meal plan', 'error');
            return;
        }
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_add_to_meal_plan',
            nonce: rallyshopper_ajax.nonce,
            plan_id: planId,
            recipe_id: recipeId,
            day_of_week: day,
            meal_type: meal
        }, function(res) {
            if (res.success) {
                toast('Recipe added to meal plan');
                $('#rs-modal-add-to-plan').removeClass('active');
                
                // If this plan is currently selected, refresh it
                if ($('#rs-meal-plan-selector').val() === planId) {
                    loadMealPlanRecipes(planId);
                }
            } else {
                toast('Failed to add to meal plan', 'error');
            }
        });
    }

    window.removeFromMealPlan = function(planId, recipeId) {
        if (!confirm('Remove this recipe from the meal plan?')) return;
        
        $.post(rallyshopper_ajax.ajax_url, {
            action: 'rallyshopper_remove_from_meal_plan',
            nonce: rallyshopper_ajax.nonce,
            plan_id: planId,
            recipe_id: recipeId
        }, function(res) {
            if (res.success) {
                toast('Recipe removed from meal plan');
                loadMealPlanRecipes(planId);
            } else {
                toast('Failed to remove from meal plan', 'error');
            }
        });
    };
    
    console.log('RallyShopper loaded');
})(jQuery);