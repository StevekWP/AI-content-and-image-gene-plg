jQuery(document).ready(function ($) {
    var $manualImagePrompt = $('#aiccgen_google_image_prompt_manual');

    var $settingsPageWrap = $('.wrap'); // Use a container common to settings page elements
    var $resultArea = $('#aiccgen-google-result-area');
    var $generateForm = $('#aiccgen-google-generate-form');
    var $generateButton = $generateForm.find('input[type="submit"]');
    var $generateLoader = $('#aiccgen-google-loader');
    var $categorySelect = $('#aiccgen_google_category_to_generate');
    var adminUrl = aiccgen_google_vars.ajax_url;
    var adminNonce = aiccgen_google_vars.nonce;
    var pluginData = aiccgen_google_vars;

    //  State Variables (Manual Flow) 
    var currentManualContent = '';
    var currentManualCategoryId = null;
    var currentManualImageId = null; // Store generated image ID
    var currentManualImageUrl = null; // Store generated image URL for preview

    var currentRefineImageDraftId = null;
    var currentRefineImageAllGeneratedIds = [];

    //  Manual Test: Generate Button Click 
    $generateForm.on('submit', function (e) {
        e.preventDefault();
        var categoryId = $categorySelect.val();
        var manualImagePromptText = $manualImagePrompt.val().trim(); 

        // Reset UI and State
        $resultArea.html('').hide();
        currentManualContent = '';
        currentManualCategoryId = null;
        currentManualImageId = null; // Reset image ID
        currentManualImageUrl = null; // Reset image URL
        $generateLoader.show();
        $generateButton.prop('disabled', true);
        $categorySelect.prop('disabled', true);
        $manualImagePrompt.prop('disabled', true);

        // Validate Category Selection
        if (!categoryId) {
            showError(aiccgen_google_vars.error_no_category);
            $generateLoader.hide();
            $generateButton.prop('disabled', false);
            $categorySelect.prop('disabled', false);
            $manualImagePrompt.prop('disabled', false); 
            return;
        }
        currentManualCategoryId = categoryId;

        // Prepare AJAX Data
        var data = {
            action: aiccgen_google_vars.ajax_generate_action, // Use localized var
            _ajax_nonce: adminNonce,
            category_id: currentManualCategoryId,
            image_prompt: manualImagePromptText
        };

        // Send AJAX Request
        $.post(adminUrl, data, function (response) {
            $generateLoader.hide();
            $generateButton.prop('disabled', false);
            $categorySelect.prop('disabled', false);
            $manualImagePrompt.prop('disabled', false); 

            if (response.success) {
                currentManualContent = response.data.content; // Store generated content for potential refinement/post creation
                currentManualImageId = response.data.image_attachment_id; // Store ID (can be null)
                currentManualImageUrl = response.data.image_url; // Store URL (can be null)
                var htmlOutput = buildManualTestResultHtml(response.data);
                $resultArea.html(htmlOutput).slideDown();
            } else {
                showError(response.data && response.data.message ? response.data.message : aiccgen_google_vars.error_ajax);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            handleAjaxError(jqXHR, textStatus, errorThrown, 'Manual Generate'); // Use helper for error handling
            $generateLoader.hide();
            $generateButton.prop('disabled', false);
            $categorySelect.prop('disabled', false);
            $manualImagePrompt.prop('disabled', false); 
            showError(aiccgen_google_vars.error_ajax); // Show generic error in UI
        });
    });

    // Manual: Refine Button Click (within generated results)
    // Event delegation used as this button is added dynamically
    $settingsPageWrap.on('click', '#aiccgen-google-refine-button', function() {
        var $refineButton = $(this);
        var $refineLoader = $('#aiccgen-google-refine-loader');
        var $refinePrompt = $('#aiccgen-google-refine-prompt');
        var $refineStatus = $('#aiccgen-google-refine-status');
        var $generatedTextarea = $('#aiccgen-google-generated-text');
        var refinementPromptText = $refinePrompt.val().trim();

        // Clear status and validate
        $refineStatus.html('').removeClass('success error');
        if (!currentManualContent) {
            $refineStatus.text(aiccgen_google_vars.error_refine_no_original).addClass('error');
            return;
        }
        if (!refinementPromptText) {
            $refineStatus.text(aiccgen_google_vars.error_refine_no_prompt).addClass('error');
            $refinePrompt.focus();
            return;
        }

        // Show loader, disable UI
        $refineLoader.show();
        $refineButton.prop('disabled', true);
        $refinePrompt.prop('disabled', true);

        // Prepare AJAX Data
        var refineData = {
            action: aiccgen_google_vars.ajax_refine_action, // Use localized var
            _ajax_nonce: adminNonce,
            original_content: currentManualContent, // Use the content generated in the manual test
            refinement_prompt: refinementPromptText
        };

        // Send AJAX Request
        $.post(adminUrl, refineData, function(response) {
            $refineLoader.hide();
            $refineButton.prop('disabled', false);
            $refinePrompt.prop('disabled', false);

            if (response.success) {
                currentManualContent = response.data.content; // Update the stored manual content
                $generatedTextarea.val(currentManualContent); // Update the main textarea
                $refineStatus.text(aiccgen_google_vars.refine_success).addClass('success');
                // Scroll to show the result (optional)
                 $('html, body').animate({ scrollTop: $generatedTextarea.offset().top - 50 }, 300);
            } else {
                 var errorMsg = response.data && response.data.message ? response.data.message : aiccgen_google_vars.error_ajax_refine;
                 $refineStatus.text(aiccgen_google_vars.error_title + ': ' + errorMsg).addClass('error');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
             handleAjaxError(jqXHR, textStatus, errorThrown, 'Manual Refine Test'); // Use helper
             $refineLoader.hide();
             $refineButton.prop('disabled', false);
             $refinePrompt.prop('disabled', false);
             $refineStatus.text(aiccgen_google_vars.error_ajax_refine).addClass('error');
        });
    });

    // Manual: Create Post Button Click (within generated results)
    // Event delegation used as this button is added dynamically
    $settingsPageWrap.on('click', '#aiccgen-google-create-post-button', function() {
        var $createButton = $(this);
        var $createLoader = $('#aiccgen-google-create-post-loader');
        var $createStatus = $('#aiccgen-google-create-post-status');
        var $titleInput = $('#aiccgen-google-post-title');
        var $contentTextArea = $('#aiccgen-google-generated-text');

        var postTitle = $titleInput.val().trim();
        var postContent = $contentTextArea.val(); // Get the potentially refined content

        // Clear status and validate
        $createStatus.html('').removeClass('success error'); // Use html() to allow link later
        if (!currentManualCategoryId) {
             $createStatus.text(aiccgen_google_vars.error_create_post_no_category).addClass('error');
             return;
        }
        if (!postTitle) {
             $createStatus.text(aiccgen_google_vars.error_create_post_no_title).addClass('error');
             $titleInput.focus();
             return;
        }
         if (!postContent) { // Should have content if generation/refinement worked
             $createStatus.text(aiccgen_google_vars.error_create_post_no_content).addClass('error');
             return;
        }

        // Show loader, disable UI
        $createLoader.show();
        $createButton.prop('disabled', true);
        $titleInput.prop('disabled', true);

        // Prepare AJAX Data
        var createData = {
            action: aiccgen_google_vars.ajax_create_post_action, // Use localized var
            _ajax_nonce: adminNonce,
            category_id: currentManualCategoryId,
            post_title: postTitle,
            post_content: postContent, // Send the latest content from the textarea
            image_attachment_id: currentManualImageId // Send image ID
        };

        // Send AJAX Request
        $.post(adminUrl, createData, function(response) {
              $createLoader.hide();
              $createButton.prop('disabled', false);
              $titleInput.prop('disabled', false);

             if (response.success) {
                 // Success message includes HTML link from backend, allow it.
                 $createStatus.html(escapeHtml(response.data.message) + ' ' + response.data.edit_link_html).addClass('success');
             } else {
                 var errorMsg = response.data && response.data.message ? response.data.message : aiccgen_google_vars.error_ajax_create_post;
                 $createStatus.text(aiccgen_google_vars.error_title + ': ' + errorMsg).addClass('error');
             }
        }).fail(function(jqXHR, textStatus, errorThrown) {
             handleAjaxError(jqXHR, textStatus, errorThrown, 'Manual Create Post'); // Use helper
             $createLoader.hide();
             $createButton.prop('disabled', false);
             $titleInput.prop('disabled', false);
             $createStatus.text(aiccgen_google_vars.error_ajax_create_post).addClass('error');
        });
    });

    // --- Immediate Refine Draft Button Click (in main settings section) ---
    // Event delegation used as these buttons are part of the WP settings fields API output
    $settingsPageWrap.on('click', '.aiccgen-refine-draft-button', function(e) {
        e.preventDefault();
        var $button = $(this);

        // Get data from button attributes
        var categoryId = $button.data('category-id');
        var textareaId = $button.data('textarea-id');
        var statusId = $button.data('status-id');
        var loaderId = $button.data('loader-id');

        // Find related elements
        var $textarea = $('#' + textareaId);
        var $status = $('#' + statusId);
        var $loader = $('#' + loaderId);

        // Get instructions from the associated textarea
        var instructions = $textarea.val().trim();

        // Clear previous status
        $status.html('').removeClass('success error');

        // Validation
        if (!categoryId) {
            console.error('Refine Draft Button Error: Missing category ID.');
            $status.text('Internal error: Category ID missing.').addClass('error');
            return;
        }
        if (!instructions) {
             $status.text(aiccgen_google_vars.refine_draft_no_instructions).addClass('error');
             $textarea.focus();
            return;
        }

        // Disable button, show loader
        $button.prop('disabled', true);
        $loader.show();

        // Prepare AJAX Data
        var data = {
            action: aiccgen_google_vars.ajax_refine_draft_action, // Use localized var for the specific action
            _ajax_nonce: adminNonce,
            category_id: categoryId,
            instructions: instructions
        };

        // Send AJAX Request
        $.post(adminUrl, data, function(response) {
            $button.prop('disabled', false);
            $loader.hide();

            if (response.success) {
                // Success message contains HTML link, allow it
                 $status.html(response.data.message).addClass('success');
            } else {
                 var errorMsg = response.data && response.data.message ? response.data.message : aiccgen_google_vars.refine_draft_ajax_error;
                 $status.text(errorMsg).addClass('error');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            handleAjaxError(jqXHR, textStatus, errorThrown, 'Immediate Refine Draft');
            $button.prop('disabled', false);
            $loader.hide();

            // Try to extract error message from response
            var errorMsg = aiccgen_google_vars.refine_draft_ajax_error;
            if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                errorMsg = jqXHR.responseJSON.data.message;
            } else if (jqXHR.responseText) {
                try {
                    var resp = JSON.parse(jqXHR.responseText);
                    if (resp.data && resp.data.message) {
                        errorMsg = resp.data.message;
                    }
                } catch (e) {
                    // Not JSON, keep default error
                }
            }
            $status.text(errorMsg).addClass('error');
        });
    });

    // Display Error in Manual Test Area
    function showError(message) {
        // Allow basic HTML like links if present, otherwise escape
        var safeMessage = message.includes('<a ') || message.includes('<strong') || message.includes('<code') ? message : escapeHtml(message);
        $resultArea.html('<div class="notice notice-error is-dismissible"><p><strong>' + escapeHtml(aiccgen_google_vars.error_title) + ':</strong> ' + safeMessage + '</p></div>').slideDown();
    }

    // Manul Post Results
    function buildManualTestResultHtml(data) {
        // Manual Test Result
        var contentHtml = '<div class="notice notice-info is-dismissible" style="padding-bottom: 10px;">';
        contentHtml += '<h3>' + escapeHtml(aiccgen_google_vars.success_title) + ' (Manual Test)</h3>';
        contentHtml += '<p>' + aiccgen_google_vars.for_category.replace('%s', '<strong>' + escapeHtml(data.category_name) + '</strong>') + '</p>';
        contentHtml += '<textarea id="aiccgen-google-generated-text" rows="15" class="large-text">' + escapeHtml(data.content) + '</textarea>';
        contentHtml += '</div>';
 
        // Manul Image Part
        var imageHtml = '<div id="aiccgen-google-image-result" style="margin-top: 15px; padding: 15px; border: 1px solid #ccd0d4; background-color: #fff;">';
         imageHtml += '<h4>' + escapeHtml(aiccgen_google_vars.generated_image_preview) + '</h4>';
        if (data.image_url && data.image_attachment_id) {
            imageHtml += '<p style="color: green;">' + escapeHtml(aiccgen_google_vars.image_gen_success) + '</p>';
            imageHtml += '<img src="' + escapeHtml(data.image_url) + '" alt="' + escapeHtml(aiccgen_google_vars.generated_image_preview) + '" style="max-width: 250px; height: auto; border: 1px solid #ddd; margin-top: 5px;">';
        } else if (data.image_error) {
             imageHtml += '<p style="color: red;">' + escapeHtml(aiccgen_google_vars.image_gen_failed) + ' ' + escapeHtml(data.image_error) + '</p>';
        } else {
            imageHtml += '<p style="color: #666;">' + escapeHtml(aiccgen_google_vars.image_gen_skipped_no_prompt) + '</p>';
        }
         imageHtml += '</div>';
 
 
        // Manual Refine Part (Content Only) 
        var refineHtml = '<div id="aiccgen-google-refine-section" style="margin-top: 15px; padding: 15px; border: 1px solid #ccd0d4; background-color: #f6f7f7;">';
        refineHtml += '<h4>' + escapeHtml(aiccgen_google_vars.refine_title) + ' (Content Only)</h4>';
        refineHtml += '<p>' + escapeHtml(aiccgen_google_vars.refine_instructions) + '</p>';
        refineHtml += '<textarea id="aiccgen-google-refine-prompt" rows="4" class="large-text" placeholder="' + escapeHtml(aiccgen_google_vars.refine_placeholder) + '"></textarea>';
        refineHtml += '<p><button type="button" id="aiccgen-google-refine-button" class="button button-secondary">' + escapeHtml(aiccgen_google_vars.refine_button_text) + '</button>';
        refineHtml += '<span id="aiccgen-google-refine-loader" style="display: none; margin-left: 10px; vertical-align: middle;"><img src="'+pluginData.plugin_base_url+'img/loading.gif" style="width: 16px; height: 16px;" alt="Loading..."></span></p>';
        refineHtml += '<div id="aiccgen-google-refine-status" class="aiccgen-refine-draft-status"></div>';
        refineHtml += '</div>';
 
        // Draft Manul Post
        var createPostHtml = '<div id="aiccgen-google-create-post-section" style="margin-top: 15px; padding: 15px; border: 1px solid #ccd0d4; background-color: #eaf2fa;">';
        createPostHtml += '<h4 style="margin-top:0;">' + escapeHtml(aiccgen_google_vars.create_post_title) + '</h4>';
        createPostHtml += '<p><label for="aiccgen-google-post-title">' + escapeHtml(aiccgen_google_vars.create_post_label_title) + '</label><br>';
        createPostHtml += '<input type="text" id="aiccgen-google-post-title" class="regular-text" placeholder="' + escapeHtml(aiccgen_google_vars.create_post_placeholder_title) + '"></p>';
        
        createPostHtml += '<p><button type="button" id="aiccgen-google-create-post-button" class="button button-primary">' + escapeHtml(aiccgen_google_vars.create_post_button_text) + '</button>';
        createPostHtml += '<span id="aiccgen-google-create-post-loader" style="display: none; margin-left: 10px; vertical-align: middle;"><img src="'+pluginData.plugin_base_url+'img/loading.gif" style="width: 16px; height: 16px;" alt="Loading..."></span></p>';
        createPostHtml += '<div id="aiccgen-google-create-post-status" class="aiccgen-refine-draft-status"></div>';
        createPostHtml += '</div>';
 
        return contentHtml + imageHtml + refineHtml + createPostHtml;
     }

    function updateCreatePostButtonText() {
        var buttonText = currentManualImageId
            ? aiccgen_google_vars.create_post_button_text_with_image
            : aiccgen_google_vars.create_post_button_text_no_image;
        $('#aiccgen-google-create-post-button').text(buttonText);
    }
    function escapeHtml(unsafe) {
         if (typeof unsafe !== 'string') return '';
         return unsafe
              .replace(/&/g, "&")
              .replace(/</g, "<")
              .replace(/>/g, ">")
              .replace(/"/g, "'")
              .replace(/'/g, "'");
    }

    function handleAjaxError(jqXHR, textStatus, errorThrown, context) {
        console.error("AJAX Error (" + context + "):", {
            Status: textStatus,
            Error: errorThrown,
            Code: jqXHR.status,
            Response: jqXHR.responseText
        });
    }

    $settingsPageWrap.on('click', '.notice-dismiss', function (e) {
         e.preventDefault();
         $(this).closest('.notice.is-dismissible').fadeOut('slow', function() { $(this).remove(); });
     });

    // Disabled category options
    $('#aiccgen_google_category_to_generate option:disabled').css({'color': '#aaa', 'font-style': 'italic'});





    $settingsPageWrap.on('click', '.aiccgen-refine-image-button', function(e) {
        e.preventDefault();
        var $button = $(this);
        var categoryId = $button.data('category-id');
        var imagePromptTextareaId = $button.data('image-prompt-id'); // We don't send this, PHP gets it from settings
        var statusId = $button.data('status-id');
        var loaderId = $button.data('loader-id');
        var optionsAreaId = $button.data('options-area-id');

        var $status = $('#' + statusId);
        var $loader = $('#' + loaderId);
        var $optionsArea = $('#' + optionsAreaId);

        // Reset
        $status.html('').removeClass('success error');
        $optionsArea.html('').hide();
        currentRefineImageDraftId = null;
        currentRefineImageAllGeneratedIds = [];

        // Validation (basic, server does more)
        var imagePromptForCategory = $('#' + imagePromptTextareaId).val().trim();
        if (!imagePromptForCategory) {
            $status.text(aiccgen_google_vars.refine_image_no_prompt_settings).addClass('error');
            return;
        }


        $button.prop('disabled', true);
        $loader.show();
        $status.text(aiccgen_google_vars.refine_image_generating_text).removeClass('error success');

        var data = {
            action: aiccgen_google_vars.ajax_refine_image_action,
            _ajax_nonce: adminNonce,
            category_id: categoryId
        };

        $.post(adminUrl, data, function(response) {
            $loader.hide();
            $button.prop('disabled', false);

            if (response.success) {
                $status.text(response.data.message).addClass('success');
                currentRefineImageDraftId = response.data.draft_post_id;
                currentRefineImageAllGeneratedIds = response.data.generated_images.map(img => img.attachment_id);
                
                if (response.data.generated_images && response.data.generated_images.length > 0) {
                    var optionsHtml = '<p><strong>' + escapeHtml(aiccgen_google_vars.refine_image_select_prompt) + '</strong></p>';
                    optionsHtml += '<div class="aiccgen-image-options-container" style="display: flex; flex-wrap: wrap; gap: 10px;">';
                    
                    response.data.generated_images.forEach(function(img, index) {
                        optionsHtml += '<div class="aiccgen-image-option" style="border: 2px solid transparent; padding: 5px; cursor:pointer;">';
                        optionsHtml += '<input type="radio" name="aiccgen_refined_image_choice" value="' + img.attachment_id + '" id="refined_img_' + img.attachment_id + '" style="margin-right: 5px;">';
                        optionsHtml += '<label for="refined_img_' + img.attachment_id + '"><img src="' + escapeHtml(img.image_url) + '" alt="Option ' + (index + 1) + '" style="max-width: 150px; max-height: 150px; display: block;"></label>';
                        optionsHtml += '</div>';
                    });
                    optionsHtml += '</div>'; // close container
                    optionsHtml += '<p style="margin-top:15px;"><button type="button" class="button button-primary aiccgen-apply-refined-image-button">' + escapeHtml(aiccgen_google_vars.refine_image_apply_button_text) + '</button></p>';
                    $optionsArea.html(optionsHtml).slideDown();
                } else {
                    $status.text(aiccgen_google_vars.refine_image_all_failed).addClass('error');
                }
            } else {
                var errorMsg = response.data && response.data.message ? response.data.message : aiccgen_google_vars.error_ajax;
                $status.text(errorMsg).addClass('error');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            handleAjaxError(jqXHR, textStatus, errorThrown, 'Refine Featured Image');
            $loader.hide();
            $button.prop('disabled', false);

            // Try to extract error message from server response
            var errorMsg = aiccgen_google_vars.error_ajax;
            if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                errorMsg = jqXHR.responseJSON.data.message;
            } else if (jqXHR.responseText) {
                try {
                    var resp = JSON.parse(jqXHR.responseText);
                    if (resp.data && resp.data.message) {
                        errorMsg = resp.data.message;
                    }
                } catch (e) {
                    // Not JSON, keep default error
                }
            }
            $status.text(errorMsg).addClass('error');
        });
    });

    // Handle click on image option for visual selection
    $settingsPageWrap.on('click', '.aiccgen-image-option', function() {
        $(this).closest('.aiccgen-image-options-container').find('.aiccgen-image-option').css('border-color', 'transparent');
        $(this).css('border-color', '#0073aa'); // WordPress blue
        $(this).find('input[type="radio"]').prop('checked', true);
    });


    // Event handler for "Apply Selected Image" button
    $settingsPageWrap.on('click', '.aiccgen-apply-refined-image-button', function(e) {
        e.preventDefault();
        var $applyButton = $(this);
        var $optionsArea = $applyButton.closest('.aiccgen-refine-image-options-area');
        var $status = $optionsArea.siblings('.aiccgen-refine-image-status'); // The status area outside optionsArea
        
        var selectedImageId = $optionsArea.find('input[name="aiccgen_refined_image_choice"]:checked').val();

        if (!selectedImageId) {
            $status.text(aiccgen_google_vars.refine_image_select_one).addClass('error').removeClass('success');
            // We can also show this message closer to the radio buttons if preferred
            // $applyButton.siblings('.apply-status-local').text(...);
            return;
        }
        
        if (!currentRefineImageDraftId) {
            $status.text('Error: Draft Post ID not found. Please try refining again.').addClass('error').removeClass('success');
            return;
        }

        if (!confirm(aiccgen_google_vars.refine_image_confirm_apply)) {
            return;
        }

        $applyButton.prop('disabled', true);
        // You might want a small loader next to the apply button too
        $status.text('Applying image...').removeClass('error success');


        var data = {
            action: aiccgen_google_vars.ajax_apply_refined_image_action,
            _ajax_nonce: adminNonce,
            draft_post_id: currentRefineImageDraftId,
            selected_image_id: selectedImageId,
            all_new_image_ids: currentRefineImageAllGeneratedIds
        };

        $.post(adminUrl, data, function(response) {
            $applyButton.prop('disabled', false);
            if (response.success) {
                $status.html(response.data.message).addClass('success').removeClass('error'); // message includes HTML
                $optionsArea.slideUp(function() { $(this).empty(); }); // Clear and hide options
            } else {
                var errorMsg = response.data && response.data.message ? response.data.message : aiccgen_google_vars.refine_image_apply_failed;
                $status.text(errorMsg).addClass('error').removeClass('success');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            handleAjaxError(jqXHR, textStatus, errorThrown, 'Apply Refined Image');
            $applyButton.prop('disabled', false);
            $status.text(aiccgen_google_vars.refine_image_apply_failed).addClass('error').removeClass('success');
        });
    });

});

// Inactive category Toggle
jQuery('.category-slgname').each(function(){
    jQuery(this).click(function(){
        jQuery(this).parents('th').next('td').find('.category-settings-group.plgcollapse').toggle();
    });
});