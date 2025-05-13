<?php
// Activation / Deactivation Hooks
function aiccgen_google_activate() {
    add_filter('cron_schedules', 'aiccgen_google_add_cron_schedules');
    aiccgen_google_reschedule_all_tasks();
}
register_activation_hook(__FILE__, 'aiccgen_google_activate');

function aiccgen_google_deactivate() {
    // Clear all scheduled hooks with the specific name, regardless of arguments initially
    $timestamp = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK);
    while($timestamp) {
        // Get the exact arguments for this scheduled event
        $scheduled_event = get_scheduled_event(AICCG_GOOGLE_CRON_HOOK, [], $timestamp);
        if ($scheduled_event) {
            wp_unschedule_event($timestamp, AICCG_GOOGLE_CRON_HOOK, $scheduled_event->args);
        } else {
            // Fallback if getting args fails (shouldn't happen often)
            wp_unschedule_event($timestamp, AICCG_GOOGLE_CRON_HOOK);
        }
        $timestamp = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK); // Find the next one
    }

    // Double-check based on options just in case (might be redundant but safe)
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $prompts = isset($options['prompts']) ? $options['prompts'] : [];
    if (is_array($prompts)) {
        foreach (array_keys($prompts) as $cat_id) {
            wp_clear_scheduled_hook(AICCG_GOOGLE_CRON_HOOK, ['category_id' => absint($cat_id)]);
        }
    }
    remove_filter('cron_schedules', 'aiccgen_google_add_cron_schedules'); // Remove custom schedule definitions
}
register_deactivation_hook(__FILE__, 'aiccgen_google_deactivate');


// Cron Schedules
function aiccgen_google_add_cron_schedules($schedules) {
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = [
            'interval' => MONTH_IN_SECONDS,
            'display' => __('Once Monthly', 'ai-cat-content-gen-google')
        ];
    }
    if (!isset($schedules['weekly'])) {
         $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Once Weekly', 'ai-cat-content-gen-google')
        ];
    }
    return $schedules;
}
// Add filter only when needed (moved from global scope)
add_filter('cron_schedules', 'aiccgen_google_add_cron_schedules');


// Enqueue Scripts and localize scripts and Style
function aiccgen_google_enqueue_admin_scripts($hook_suffix)
{
    if (strpos($hook_suffix, AICCG_GOOGLE_SETTINGS_SLUG) === false) {
        return;
    }

    wp_enqueue_script(
        'aiccgen-google-admin-js',
        plugin_dir_url(__FILE__) . 'js/aiccgen-google-admin.js',
        ['jquery'],
        '2.0.0',
        true
    );

    wp_enqueue_style(
        'aiccgen-google-admin-css',
        plugin_dir_url(__FILE__) . 'css/aiccgen-google-admin.css',
        [],
        '2.0.0'
    );

    // WP localize Scripts
    wp_localize_script('aiccgen-google-admin-js', 'aiccgen_google_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce(AICCG_GOOGLE_NONCE_ACTION),
        'plugin_base_url'        => plugin_dir_url(__FILE__),

        'ajax_generate_action' => AICCG_GOOGLE_AJAX_ACTION,
        'ajax_refine_action' => AICCG_GOOGLE_AJAX_REFINE_ACTION,
        'ajax_create_post_action' => AICCG_GOOGLE_AJAX_CREATE_POST_ACTION,
        'ajax_refine_draft_action' => AICCG_GOOGLE_AJAX_REFINE_DRAFT_ACTION,

        'error_no_category' => __('Please select a category first.', 'ai-cat-content-gen-google'),
        'error_ajax' => __('An error occurred during the request. Please try again. Check browser console for details.', 'ai-cat-content-gen-google'),
        'error_title' => __('Error', 'ai-cat-content-gen-google'),
        'success_title' => __('Generated Content Suggestion', 'ai-cat-content-gen-google'),
        'for_category' => __('Result for category: %s', 'ai-cat-content-gen-google'),
        'copy_notice' => __('Please review, edit, and copy the content below to create a new post manually.', 'ai-cat-content-gen-google'),
        'label_formatting_instructions' => __('Formatting & Content Rules:', 'ai-cat-content-gen-google'),
        'placeholder_formatting_instructions' => __('e.g., Use H2 for headings, wrap paragraphs in <p>, avoid words like "example", "another example", ensure professional tone.', 'ai-cat-content-gen-google'),
        'refine_title' => __('Refine Content', 'ai-cat-content-gen-google'),
        'refine_instructions' => __('Enter instructions below to modify the text above (e.g., "make it shorter", "focus more on local business news", "rewrite the first paragraph").', 'ai-cat-content-gen-google'),
        'refine_placeholder' => __('Manual refinement instructions...', 'ai-cat-content-gen-google'),
        'refine_button_text' => __('Refine Now', 'ai-cat-content-gen-google'),
        'refine_success' => __('Content refined successfully.', 'ai-cat-content-gen-google'),
        'error_refine_no_original' => __('Could not find original content to refine.', 'ai-cat-content-gen-google'),
        'error_refine_no_prompt' => __('Please enter refinement instructions.', 'ai-cat-content-gen-google'),
        'error_ajax_refine' => __('An error occurred during the refinement request. Please try again.', 'ai-cat-content-gen-google'),
        'create_post_title' => __('Create Post', 'ai-cat-content-gen-google'),
        'create_post_label_title' => __('Post Title:', 'ai-cat-content-gen-google'),
        'create_post_placeholder_title' => __('Enter a title for the new post...', 'ai-cat-content-gen-google'),
        'create_post_button_text' => __('Create Post (Draft)', 'ai-cat-content-gen-google'),
        'error_create_post_no_category' => __('Cannot create post: Category information is missing.', 'ai-cat-content-gen-google'),
        'error_create_post_no_title' => __('Please enter a post title.', 'ai-cat-content-gen-google'),
        'error_create_post_no_content' => __('Cannot create post: Content is missing.', 'ai-cat-content-gen-google'),
        'error_ajax_create_post' => __('An error occurred while trying to create the post. Please try again.', 'ai-cat-content-gen-google'),
        'saving_generating_notice' => __('Settings saved. Updating schedules...', 'ai-cat-content-gen-google'),
        'refine_draft_button_text' => __('Refine Latest Draft Now', 'ai-cat-content-gen-google'),
        'refine_draft_confirm' => __('This will attempt to find the latest draft post for this category, refine its content using the instructions above, and update the draft. Are you sure?', 'ai-cat-content-gen-google'),
        'refine_draft_no_draft' => __('Could not find a draft post for this category.', 'ai-cat-content-gen-google'),
        'refine_draft_no_instructions' => __('Please enter refinement instructions in the textarea above first.', 'ai-cat-content-gen-google'),
        'refine_draft_success' => __('Latest draft post successfully refined. %s', 'ai-cat-content-gen-google'),
        'refine_draft_api_error' => __('Error refining content via API. Please check logs.', 'ai-cat-content-gen-google'),
        'refine_draft_update_error' => __('Error updating the draft post.', 'ai-cat-content-gen-google'),
        'refine_draft_ajax_error' => __('An AJAX error occurred while trying to refine the draft.', 'ai-cat-content-gen-google'),
        'image_gen_success' => __('Image generated successfully (Landscape 3:2).', 'ai-cat-content-gen-google'),
        'image_gen_failed' => __('Image generation failed:', 'ai-cat-content-gen-google'),
        'image_gen_skipped_no_prompt' => __('Image generation skipped (no prompt provided).', 'ai-cat-content-gen-google'),
        'image_gen_skipped_no_key' => __('Image generation skipped (Venice API key missing in settings).', 'ai-cat-content-gen-google'),
        'generating_image' => __('Generating image...', 'ai-cat-content-gen-google'),
        'generated_image_preview' => __('Generated Image Preview:', 'ai-cat-content-gen-google'),
        'create_post_button_text_with_image' => __('Create Draft (with Image)', 'ai-cat-content-gen-google'),
        'create_post_button_text_no_image' => __('Create Draft (Content Only)', 'ai-cat-content-gen-google'),
        'ajax_refine_image_action' => AICCG_GOOGLE_AJAX_REFINE_IMAGE_ACTION,
        'ajax_apply_refined_image_action' => AICCG_GOOGLE_AJAX_APPLY_REFINED_IMAGE_ACTION,
        'refine_image_button_text' => __('Refine Featured Image', 'ai-cat-content-gen-google'),
        'refine_image_generating_text' => __('Generating image options...', 'ai-cat-content-gen-google'),
        'refine_image_select_prompt' => __('Select any one image to apply to the latest draft as featured image:', 'ai-cat-content-gen-google'),
        'refine_image_apply_button_text' => __('Apply Selected as Featured Image', 'ai-cat-content-gen-google'),
        'refine_image_no_draft_found' => __('Could not find a draft post for this category to refine its image.', 'ai-cat-content-gen-google'),
        'refine_image_no_prompt_settings' => __('No featured image prompt is set for this category in settings. Please enter prompt and save the settings before refine the featured image.', 'ai-cat-content-gen-google'),
        'refine_image_all_failed' => __('Failed to generate any image options. Please check API key or try again.', 'ai-cat-content-gen-google'),
        'refine_image_applied_success' => __('New featured image applied to the draft post successfully. %s', 'ai-cat-content-gen-google'),
        'refine_image_apply_failed' => __('Failed to apply the selected image.', 'ai-cat-content-gen-google'),
        'refine_image_select_one' => __('Please select one of the generated images.', 'ai-cat-content-gen-google'),
        'refine_image_confirm_apply' => __('This will set the selected image as the featured image for the latest draft post in this category and delete the other two options. Are you sure?', 'ai-cat-content-gen-google'),
    ]);

}
add_action('admin_enqueue_scripts', 'aiccgen_google_enqueue_admin_scripts');


// Settings Page, Sections, Fields
function aiccgen_google_add_admin_menu()
{
    add_submenu_page(
        'edit.php', // Parent slug for "Posts"
        __('AI Post Content & Image Settings', 'ai-cat-content-gen-google'),
        __('AI Content/Image', 'ai-cat-content-gen-google'),
        'manage_options',
        AICCG_GOOGLE_SETTINGS_SLUG,
        'aiccgen_google_render_settings_page'
    );
}
add_action('admin_menu', 'aiccgen_google_add_admin_menu');




// Register Settings
function aiccgen_google_register_settings()
{
    register_setting(AICCG_GOOGLE_OPTION_GROUP, AICCG_GOOGLE_OPTION_NAME, [
        'sanitize_callback' => 'aiccgen_google_sanitize_options',
    ]);

    // === API Section ===
    add_settings_section(
        'aiccgen_google_section_api',
        __('API Configuration', 'ai-cat-content-gen-google'),
         null,
        AICCG_GOOGLE_SETTINGS_SLUG
    );
    add_settings_field(
        'aiccgen_google_field_api_key',
        __('Google AI API Key (Content)', 'ai-cat-content-gen-google'),
        'aiccgen_google_field_api_key_render',
        AICCG_GOOGLE_SETTINGS_SLUG,
        'aiccgen_google_section_api'
    );
    // Add Venice API Key Field
    add_settings_field(
        'aiccgen_google_field_venice_api_key',
        __('Venice AI API Key (Image)', 'ai-cat-content-gen-google'),
        'aiccgen_google_field_venice_api_key_render',
        AICCG_GOOGLE_SETTINGS_SLUG,
        'aiccgen_google_section_api'
    );

    // === Plugin Usage Instructions ===
    add_settings_section(
        'aiccgen_google_section_plugin_instructions',
        __('', 'ai-cat-content-gen-google'),
        'aiccgen_google_section_plugin_instructions_callback',
        AICCG_GOOGLE_SETTINGS_SLUG
    );

    // === Global Formatting Rules Section ===
    add_settings_section(
        'aiccgen_google_section_global_formatting',
        __('Global Formatting & Content Rules', 'ai-cat-content-gen-google'),
        'aiccgen_google_section_global_formatting_callback',
        AICCG_GOOGLE_SETTINGS_SLUG
    );
    
    // === Category Navigation ===
    add_settings_section(
        'aiccgen_google_category_nav',
        __('', 'ai-cat-content-gen-google'), // Updated title
        'aiccgen_google_category_nav_callback',
        AICCG_GOOGLE_SETTINGS_SLUG
    );


    // === Active Prompts Section ===
    add_settings_section(
        'aiccgen_google_section_prompts_active',
        '<span id="active-cat">' . __('Active Categories', 'ai-cat-content-gen-google') . '</span>',
        'aiccgen_google_section_prompts_active_callback',
        AICCG_GOOGLE_SETTINGS_SLUG
    );

    
    add_settings_field(
        'aiccgen_google_field_global_formatting_instructions',
        __('Global Rules', 'ai-cat-content-gen-google'),
        'aiccgen_google_field_global_formatting_instructions_render',
        AICCG_GOOGLE_SETTINGS_SLUG,
        'aiccgen_google_section_global_formatting'
    );

    // === Inactive Prompts Section ===
    add_settings_section(
        'aiccgen_google_section_prompts_inactive',
        '<span id="inactive-cat">' . __('Inactive Categories (No Content Prompt)', 'ai-cat-content-gen-google') . '</span>',
        'aiccgen_google_section_prompts_inactive_callback',
        AICCG_GOOGLE_SETTINGS_SLUG
    );

    // --- Prepare to add fields to sections ---
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $prompts = isset($options['prompts']) ? $options['prompts'] : [];
    $categories = get_categories(['hide_empty' => 0, 'exclude' => get_option('default_category'), 'orderby' => 'date', 'order' => 'DESC']);
    
    if ($categories) {
        $has_active = false;
        $has_inactive = false;

        // First Add fields for ACTIVE categories (based on content prompt)
        foreach ($categories as $category) {
            $cat_id = $category->term_id;
            // Active means it has a non-empty content prompt
            $has_content_prompt = isset($prompts[$cat_id]) && !empty(trim($prompts[$cat_id]));

            if ($has_content_prompt) {
                $has_active = true;
                add_settings_field(
                    'aiccgen_google_field_cat_settings_' . $cat_id,
                    '<span id="'.$category->slug.'">' . esc_html($category->name) . '</span>',
                    'aiccgen_google_field_category_settings_render',
                    AICCG_GOOGLE_SETTINGS_SLUG,
                    'aiccgen_google_section_prompts_active',
                    ['category_id' => $cat_id, 'category_name' => $category->name, 'slug' => $category->slug]
                );
            }
        }

        // Second Add fields for INACTIVE categories
        foreach ($categories as $category) {
            $cat_id = $category->term_id;
            $has_content_prompt = isset($prompts[$cat_id]) && !empty(trim($prompts[$cat_id]));

            if (!$has_content_prompt) {
                $has_inactive = true;
                add_settings_field(
                    'aiccgen_google_field_cat_settings_' . $cat_id,
                    '<span class="category-slgname" id="'.$category->slug.'">' . esc_html($category->name) . '<div class="category-collapseicon"><svg fill="#000000" viewBox="-6.5 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>dropdown</title> <path d="M18.813 11.406l-7.906 9.906c-0.75 0.906-1.906 0.906-2.625 0l-7.906-9.906c-0.75-0.938-0.375-1.656 0.781-1.656h16.875c1.188 0 1.531 0.719 0.781 1.656z"></path> </g></svg></div></span>',
                    'aiccgen_google_field_category_settings_render',
                    AICCG_GOOGLE_SETTINGS_SLUG,
                    'aiccgen_google_section_prompts_inactive',
                    ['category_id' => $cat_id, 'category_name' => $category->name, 'slug' => $category->slug]
                );
            }
        }
        // Add placeholder messages if a section ends up empty
        if (!$has_active) {
            add_settings_field(
                'aiccgen_google_field_no_active_prompts',
                 '',
                'aiccgen_google_field_no_active_prompts_render',
                AICCG_GOOGLE_SETTINGS_SLUG,
                'aiccgen_google_section_prompts_active'
            );
        }
        if (!$has_inactive) {
            add_settings_field(
                'aiccgen_google_field_no_inactive_prompts',
                 '',
                'aiccgen_google_field_no_inactive_prompts_render',
                AICCG_GOOGLE_SETTINGS_SLUG,
                'aiccgen_google_section_prompts_inactive'
            );
        }

    } else {
         add_settings_field(
             'aiccgen_google_field_no_categories',
             __('No Categories Found', 'ai-cat-content-gen-google'),
             'aiccgen_google_field_no_categories_render',
             AICCG_GOOGLE_SETTINGS_SLUG,
             'aiccgen_google_section_prompts_active'
         );
    }
}
add_action('admin_init', 'aiccgen_google_register_settings');

// --- Render Callbacks ---

function aiccgen_google_section_global_formatting_callback() {
    //echo '<p>' . esc_html__('Define global formatting and content rules that will apply to all categories by default. These can be overridden by specific rules set per category below.', 'ai-cat-content-gen-google') . '</p>';
    // echo '<p class="description">' . esc_html__('Example rules: "#1 Add H2 tags for all headings", "#2 Add horizontal line after each paragraph", "#3 Bold specific words like \'important\' or \'key\'". Each rule on a new line.', 'ai-cat-content-gen-google') . '</p>';

}
function aiccgen_google_section_plugin_instructions_callback() { ?>
    <div class="glbl-pluginlistingtp">
        <h2>Plugin Usage Instructions:</h2>
        <ul class="glbl-pluginlisting">
            <li>Enter the Google AI API Key for generation post content using Gemini.</li>
            <li>Enter the Venice AI API Key for generation post featured image.</li>
        </ul>
        <h4>3. Automated Category Settings</h4>
        <ul class="glbl-pluginlisting">
            <li>Define global formatting and content rules that will apply to all categories by default. These rules apply if a category does not have its own specific formatting rules defined (Formatting & Content Rules).
                <ul>
                    <li>Example rules: "Add H2 tags for all headings".</li>
                    <li>Example rules: "Add horizontal line after each paragraph".</li>
                    <li>Example rules: "Bold specific words like human name, dates, etc.</li>
                </ul>
            </li>
            <li>Category automated content generation is based on the frequency selected (Daily, Weekly, Monthly).
                <ul>
                    <li>Active Category Prompts (Configure generation settings for categories that have a saved content prompt. Posts and Featured images will be generated based on the frequency selected.)</li>
                    <li>Inactive Categories (No Content Prompt) (Configure generation settings for categories that do not have a saved content prompt. Posts and Featured images will not be generated.)</li>
                    <li>Content Prompt: To generate content, enter a prompt for the category. This is the main instruction for the AI to generate content.</li>
                    <li>Formatting & Content Rules: Define specific formatting and content rules for the category. These rules will be applied to the generated content (This will be overridden to the Global Formatting & Content Rules).</li>
                    <li>Frequency: Select how often you want the content to be generated for this category. Options include Daily, Weekly, Monthly, or None.</li>
                    <li>Refine Content: If you want to refine the generated content, enter your refinement instructions here. This will be used to modify the generated post content. and you can do it for run time as well to hit the "Refine Latest Draft Content" button.</li>
                    <li>Featured Image Prompt: Enter a prompt for generating the featured image for the category. This is the main instruction for the AI to generate image (Aspect Ratio Landscape 3:2).</li>
                </ul>
            </li>
        </ul>
        <h4>4. Manual Category Settings</h4>
        <ul class="glbl-pluginlisting">
            <li>Generate for Category: Based on categories that has prompt (Content Prompt).</li>
            <li>Image Prompt (Optional) for Manual Process.</li>
            <li>Refine Content for the generated content suggestion with the hit of "Refine Now" button.</li>
            <li>Create the draft post to insert the post title manually.</li>
        </ul>
    </div>
<?php }


function aiccgen_google_category_nav_callback() {
    echo '<ul class="top-catlistting">';
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $prompts = isset($options['prompts']) ? $options['prompts'] : [];
    $categories = get_categories(['hide_empty' => 0, 'exclude' => get_option('default_category'), 'orderby' => 'date', 'order' => 'DESC']);
    // All Active
    foreach ($categories as $term) {
        $has_prompt = isset($prompts[$term->term_id]) && !empty(trim($prompts[$term->term_id]));
        if ($has_prompt) {
            echo '<li><a href="#' . esc_html($term->slug) . '">' . esc_html($term->name) . '</a></li>';
        }
    }
    // Inactive
    echo '<li><a href="#inactive-cat">' . esc_html__('INACTIVE', 'ai-cat-content-gen-google') . '</a></li>';
    echo '</ul>';
}


function aiccgen_google_section_prompts_active_callback() {
     echo '<p>' . esc_html__('Configure generation settings for categories that have a saved content prompt. Posts and Featured images will be generated based on the frequency selected.', 'ai-cat-content-gen-google') . '</p>';
}

function aiccgen_google_section_prompts_inactive_callback() {
     echo '<hr>';
}

function aiccgen_google_field_no_active_prompts_render() {
    echo '<em>' . esc_html__('No categories currently have active content prompts. Add a content prompt to a category in the section below to activate it.', 'ai-cat-content-gen-google') . '</em>';
}

function aiccgen_google_field_no_inactive_prompts_render() {
    echo '<em>' . esc_html__('All categories have active content prompts configured above.', 'ai-cat-content-gen-google') . '</em>';
}

function aiccgen_google_field_no_categories_render() {
    echo '<em>' . esc_html__('No categories found. Please create some post categories.', 'ai-cat-content-gen-google') . '</em>';
}

function aiccgen_google_field_global_formatting_instructions_render() {
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $global_formatting_instructions = isset($options['global_formatting_instructions']) ? $options['global_formatting_instructions'] : '';
    ?>
    <textarea name="<?php echo esc_attr(AICCG_GOOGLE_OPTION_NAME); ?>[global_formatting_instructions]"
              id="aiccgen_google_global_formatting_instructions"
              rows="5"
              class="large-text"
              placeholder="<?php esc_attr_e('e.g., Use H2 for headings, wrap paragraphs in <p>, avoid words like "example". Each rule on a new line.', 'ai-cat-content-gen-google'); ?>"><?php echo esc_textarea($global_formatting_instructions); ?></textarea>
    <p class="description">
        <?php esc_html_e('These rules apply if a category does not have its own specific formatting rules defined.', 'ai-cat-content-gen-google'); ?>
    </p>
    <?php
}
function aiccgen_google_field_api_key_render() {
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $model_used = 'gemini-2.5-pro-exp-03-25'; // Hardcoded model ?>
    <input type="password" name="<?php echo esc_attr(AICCG_GOOGLE_OPTION_NAME); ?>[api_key]"
        value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('Enter your Gemini API Key', 'ai-cat-content-gen-google'); ?>">
    <p class="description"><?php printf(esc_html__('For content generation using model: %s.', 'ai-cat-content-gen-google'), '<strong><a target="_blank" href="https://aistudio.google.com/apikey">Gemini 2.5 pro (Experimental)</a></strong>'); ?></p>
    <?php
}

// Render callback for Venice API Key
function aiccgen_google_field_venice_api_key_render() {
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $venice_api_key = isset($options['venice_api_key']) ? $options['venice_api_key'] : '';
    ?>
    <input type="password" name="<?php echo esc_attr(AICCG_GOOGLE_OPTION_NAME); ?>[venice_api_key]"
        value="<?php echo esc_attr($venice_api_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('Enter your Venice AI API Key', 'ai-cat-content-gen-google'); ?>">
    <p class="description">For optional image generation <strong><a href="https://venice.ai/settings/api" target="_blank">(Venice AI API key)</a></strong></p>
    <?php
}


// Renders ALL settings for a single category
function aiccgen_google_field_category_settings_render($args) {
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $category_id = $args['category_id'];
    $category_name = $args['category_name'];
    $category_slug = $args['slug'];

    $prompt = isset($options['prompts'][$category_id]) ? $options['prompts'][$category_id] : '';
    $frequency = isset($options['frequency'][$category_id]) ? $options['frequency'][$category_id] : 'none';
    $refinement = isset($options['refinement'][$category_id]) ? $options['refinement'][$category_id] : '';
    $image_prompt = isset($options['image_prompts'][$category_id]) ? $options['image_prompts'][$category_id] : '';
    $formatting_instructions = isset($options['formatting_instructions'][$category_id]) ? $options['formatting_instructions'][$category_id] : '';
    $has_content_prompt = !empty(trim($prompt));

    $has_image_prompt_in_settings = !empty(trim($image_prompt));

    $option_name_base = AICCG_GOOGLE_OPTION_NAME;

    // Frequency options
    $frequencies = [
        'none' => __('None', 'ai-cat-content-gen-google'),
        'daily' => __('Daily', 'ai-cat-content-gen-google'),
        'weekly' => __('Weekly', 'ai-cat-content-gen-google'),
        'monthly' => __('Monthly', 'ai-cat-content-gen-google'),
    ];

    // Get schedule info 
    $schedule_info = '';
    $args_for_cron = ['category_id' => $category_id];
    $next_run = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK, $args_for_cron);

    $refine_image_button_id = 'aiccgen-refine-image-btn-' . intval($category_id);
    $refine_image_loader_id = 'aiccgen-refine-image-loader-' . intval($category_id);
    $refine_image_status_id = 'aiccgen-refine-image-status-' . intval($category_id);
    $refine_image_options_area_id = 'aiccgen-refine-image-options-area-' . intval($category_id);

    if ($frequency !== 'none' && $has_content_prompt) {
        if ($next_run) {
            $schedule_info = sprintf(
                __('Next scheduled run: %s (UTC)', 'ai-cat-content-gen-google'),
                '<code>' . date('Y-m-d H:i:s', $next_run) . '</code>'
            );
        } else {
             $schedule_info = __('Scheduling pending or check WP Cron.', 'ai-cat-content-gen-google');
        }
    } elseif (!$has_content_prompt) {
         $schedule_info = __('Enter a content prompt and select frequency to activate generation.', 'ai-cat-content-gen-google');
    } else {
         $schedule_info = __('Select Daily/Weekly/Monthly frequency to schedule content generation.', 'ai-cat-content-gen-google');
    }

    $refinement_textarea_id = esc_attr($option_name_base) . '_refinement_' . intval($category_id);
    $refine_draft_button_id = 'aiccgen-refine-draft-btn-' . intval($category_id);
    $refine_draft_status_id = 'aiccgen-refine-draft-status-' . intval($category_id);
    $refine_draft_loader_id = 'aiccgen-refine-draft-loader-' . intval($category_id);
    $formatting_textarea_id = esc_attr($option_name_base) . '_formatting_instructions_' . intval($category_id);
    $image_prompt_textarea_id = esc_attr($option_name_base) . '_image_prompt_' . intval($category_id); ?>

    <div class="category-settings-group <?php echo empty(trim($prompt)) ? 'plgcollapse' : 'plgexpand'; ?>" id="<?php echo esc_attr($category_slug); ?>">
        <!-- Content Prompt Textarea -->
        <div class="aiccgen-field-group">
            <label for="<?php echo esc_attr($option_name_base); ?>_prompts_<?php echo intval($category_id); ?>"><strong><?php esc_html_e('Content Prompt:', 'ai-cat-content-gen-google'); ?></strong></label>
            <textarea id="<?php echo esc_attr($option_name_base); ?>_prompts_<?php echo intval($category_id); ?>" name="<?php echo esc_attr($option_name_base); ?>[prompts][<?php echo intval($category_id); ?>]" rows="4" class="large-text" placeholder="<?php esc_attr_e('Enter prompt for content generation...', 'ai-cat-content-gen-google'); ?>"><?php echo esc_textarea($prompt); ?></textarea>
        </div>

        <!-- Formatting & Content Rules Textarea -->
        <div class="aiccgen-field-group">
            <label for="<?php echo $formatting_textarea_id; ?>"><strong><?php esc_html_e('Formatting & Content Rules:', 'ai-cat-content-gen-google'); ?></strong></label>
            <textarea id="<?php echo $formatting_textarea_id; ?>" name="<?php echo esc_attr($option_name_base); ?>[formatting_instructions][<?php echo intval($category_id); ?>]" rows="4" class="large-text" placeholder="<?php esc_attr_e('e.g., Use H2 for headings, wrap paragraphs in <p>, avoid words like "Jeff", "Abusive words", ensure professional tone.', 'ai-cat-content-gen-google'); ?>"><?php echo esc_textarea($formatting_instructions); ?></textarea>
            <p class="description"><em>These formatting are applied during automated posting.</em></p>
        </div>

        <!-- Frequency Dropdown for schedules-->
         <div class="aiccgen-field-group">
            <label for="<?php echo esc_attr($option_name_base); ?>_frequency_<?php echo intval($category_id); ?>"><strong><?php esc_html_e('Frequency:', 'ai-cat-content-gen-google'); ?></strong></label>
            <select id="<?php echo esc_attr($option_name_base); ?>_frequency_<?php echo intval($category_id); ?>" name="<?php echo esc_attr($option_name_base); ?>[frequency][<?php echo intval($category_id); ?>]" <?php echo empty(trim($prompt)) ? 'disabled' : ''; ?>>
                <?php foreach ($frequencies as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($frequency, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const promptField = document.getElementById('<?php echo esc_attr($option_name_base); ?>_prompts_<?php echo intval($category_id); ?>');
                    const frequencyField = document.getElementById('<?php echo esc_attr($option_name_base); ?>_frequency_<?php echo intval($category_id); ?>');

                    promptField.addEventListener('blur', function () {
                        if (promptField.value.trim() === '') {
                            frequencyField.disabled = true;
                        } else {
                            frequencyField.disabled = false;
                        }
                    });
                });
            </script>

            <p class="description">
                <?php if ($schedule_info) : ?>
                     <span style="color:#666; font-style: italic;font-size:13px;"><?php echo wp_kses($schedule_info, ['code' => []]); ?></span>
                <?php endif; ?>
            </p>
         </div>

        <!-- Content Refinement Textarea and Button -->
         <div class="aiccgen-field-group">
            <label for="<?php echo $refinement_textarea_id; ?>"><strong><?php esc_html_e('Refine Content:', 'ai-cat-content-gen-google'); ?></strong></label>
            <textarea <?php echo empty(trim($prompt)) ? 'disabled' : ''; ?> id="<?php echo $refinement_textarea_id; ?>" name="<?php echo esc_attr($option_name_base); ?>[refinement][<?php echo intval($category_id); ?>]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Make generated content more casual and refine...', 'ai-cat-content-gen-google'); ?>"><?php echo esc_textarea($refinement); ?></textarea>
         </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const promptField = document.getElementById('<?php echo esc_attr($option_name_base); ?>_prompts_<?php echo intval($category_id); ?>');
                const RefineField = document.getElementById('<?php echo $refinement_textarea_id; ?>');

                promptField.addEventListener('blur', function () {
                    if (promptField.value.trim() === '') {
                        RefineField.disabled = true;
                    } else {
                        RefineField.disabled = false;
                    }
                });
            });
        </script>

        <!-- Refine Latest Draft Content -->
        <div style="margin-bottom: 15px;">
             <button type="button"
                     id="<?php echo esc_attr($refine_draft_button_id); ?>"
                     class="button button-secondary aiccgen-refine-draft-button"
                     data-category-id="<?php echo intval($category_id); ?>"
                     data-textarea-id="<?php echo esc_attr($refinement_textarea_id); ?>"
                     data-status-id="<?php echo esc_attr($refine_draft_status_id); ?>"
                     data-loader-id="<?php echo esc_attr($refine_draft_loader_id); ?>"
                     <?php disabled(!$has_content_prompt); // Disable if no prompt exists for the category ?>
                     >
                 <?php esc_html_e('Refine Latest Draft Content', 'ai-cat-content-gen-google'); ?>
             </button>
             <span class="aiccgen-refine-draft-loader" id="<?php echo esc_attr($refine_draft_loader_id); ?>" style="display: none; margin-left: 5px;">
                 <img src="<?php echo plugin_dir_url(__FILE__) ?>img/loading.gif" alt="Loading...">
             </span>
             <div class="aiccgen-refine-draft-status" id="<?php echo esc_attr($refine_draft_status_id); ?>"></div>
        </div>

        <!-- Featured Image Prompt Textarea -->
        <div class="aiccgen-field-group">
            <label for="<?php echo $image_prompt_textarea_id; ?>"><strong><?php esc_html_e('Featured Image Prompt:', 'ai-cat-content-gen-google'); ?></strong></label>
            <textarea <?php echo empty(trim($prompt)) ? 'disabled' : ''; ?> id="<?php echo $image_prompt_textarea_id; ?>" name="<?php echo esc_attr($option_name_base); ?>[image_prompts][<?php echo intval($category_id); ?>]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Enter prompt for image generation...', 'ai-cat-content-gen-google'); ?>"><?php echo esc_textarea($image_prompt); ?></textarea>
            <p style="color: #666;font-style: italic;font-size: 13px;">Aspect Ratio (Landscape 3:2)</p>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const promptField = document.getElementById('<?php echo esc_attr($option_name_base); ?>_prompts_<?php echo intval($category_id); ?>');
                    const FeaturedField = document.getElementById('<?php echo $image_prompt_textarea_id; ?>');

                    promptField.addEventListener('blur', function () {
                        if (promptField.value.trim() === '') {
                            FeaturedField.disabled = true;
                        } else {
                            FeaturedField.disabled = false;
                        }
                    });
                });
            </script>
        </div>

        <!-- Refine Featured Image Button Section -->
        <div class="aiccgen-field-group">
            <button type="button"
                    id="<?php echo esc_attr($refine_image_button_id); ?>"
                    class="button button-secondary aiccgen-refine-image-button"
                    data-category-id="<?php echo intval($category_id); ?>"
                    data-image-prompt-id="<?php echo esc_attr($image_prompt_textarea_id); ?>"
                    data-status-id="<?php echo esc_attr($refine_image_status_id); ?>"
                    data-loader-id="<?php echo esc_attr($refine_image_loader_id); ?>"
                    data-options-area-id="<?php echo esc_attr($refine_image_options_area_id); ?>"
                    <?php disabled(!$has_content_prompt || !$has_image_prompt_in_settings); // Disabled if no main content prompt OR no image prompt in settings ?>
                    title="<?php echo (!$has_image_prompt_in_settings && $has_content_prompt) ? esc_attr__('Please enter and save a "Featured Image Prompt" above to enable this.', 'ai-cat-content-gen-google') : ''; ?>"
                    >
                <?php esc_html_e('Refine Featured Image', 'ai-cat-content-gen-google'); ?>
            </button>
            <span class="aiccgen-refine-image-loader" id="<?php echo esc_attr($refine_image_loader_id); ?>" style="display: none; margin-left: 5px;">
                <img src="<?php echo plugin_dir_url(__FILE__) ?>img/loading.gif" alt="Loading...">
            </span>
            <p class="description" style="color: #666;font-style: italic;font-size: 13px;">
                <?php esc_html_e('Generates 3 new image options based on the "Featured Image Prompt" above. You can then replace one to the latest draft post for this category.', 'ai-cat-content-gen-google'); ?>
            </p>
            <div class="aiccgen-refine-image-status" id="<?php echo esc_attr($refine_image_status_id); ?>" style="margin-top:5px;"></div>
            <div class="aiccgen-refine-image-options-area" id="<?php echo esc_attr($refine_image_options_area_id); ?>" style="margin-top:10px; display:none; border:1px solid #ccc; padding:10px; background-color:#f9f9f9;">
                <!-- Image options will be loaded here by JS -->
            </div>
        </div>
        <script> // Small script to enable/disable Refine Image button based on image prompt field
            document.addEventListener('DOMContentLoaded', function () {
                const contentPromptField_<?php echo intval($category_id); ?> = document.getElementById('<?php echo esc_js(esc_attr($option_name_base) . '_prompts_' . intval($category_id)); ?>');
                const imagePromptField_<?php echo intval($category_id); ?> = document.getElementById('<?php echo esc_js($image_prompt_textarea_id); ?>');
                const refineImageButton_<?php echo intval($category_id); ?> = document.getElementById('<?php echo esc_js($refine_image_button_id); ?>');

                function toggleRefineImageButton_<?php echo intval($category_id); ?>() {
                    if (contentPromptField_<?php echo intval($category_id); ?>.value.trim() === '' || imagePromptField_<?php echo intval($category_id); ?>.value.trim() === '') {
                        refineImageButton_<?php echo intval($category_id); ?>.disabled = true;
                        refineImageButton_<?php echo intval($category_id); ?>.title = '<?php echo esc_js(isset($options['prompts'][$category_id]) && !empty(trim($options['prompts'][$category_id])) ? __('Please enter a "Featured Image Prompt" above and save settings to enable this.', 'ai-cat-content-gen-google') : __('Please enter a "Content Prompt" and "Featured Image Prompt" above and save settings to enable this.', 'ai-cat-content-gen-google')); ?>';

                    } else {
                        refineImageButton_<?php echo intval($category_id); ?>.disabled = false;
                        refineImageButton_<?php echo intval($category_id); ?>.title = '';
                    }
                }

                if (imagePromptField_<?php echo intval($category_id); ?> && refineImageButton_<?php echo intval($category_id); ?> && contentPromptField_<?php echo intval($category_id); ?>) {
                    imagePromptField_<?php echo intval($category_id); ?>.addEventListener('input', toggleRefineImageButton_<?php echo intval($category_id); ?>);
                    contentPromptField_<?php echo intval($category_id); ?>.addEventListener('input', toggleRefineImageButton_<?php echo intval($category_id); ?>);
                    // Initial check
                    toggleRefineImageButton_<?php echo intval($category_id); ?>();
                }
            });
        </script>
    </div>
    <?php
}


// Sanitize Options based on Frequency
function aiccgen_google_sanitize_options($input) {
    $sanitized_input = [];
    $options = get_option(AICCG_GOOGLE_OPTION_NAME); // Get old options

    // Sanitize Google API Key
    $sanitized_input['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : (isset($options['api_key']) ? $options['api_key'] : '');
    // Sanitize Venice API Key
    $sanitized_input['venice_api_key'] = isset($input['venice_api_key']) ? sanitize_text_field($input['venice_api_key']) : (isset($options['venice_api_key']) ? $options['venice_api_key'] : '');

    // Sanitize Model (using default)
    $sanitized_input['model'] = 'gemini-2.5-pro-exp-03-25'; // Ensure model is always set

    // Sanitize Global Formatting Instructions
    if (isset($input['global_formatting_instructions'])) {
        $sanitized_input['global_formatting_instructions'] = sanitize_textarea_field(wp_unslash($input['global_formatting_instructions']));
    } else {
        $sanitized_input['global_formatting_instructions'] = isset($options['global_formatting_instructions']) ? $options['global_formatting_instructions'] : '';
    }

    // Allowed frequencies
    $allowed_frequencies = ['none', 'daily', 'weekly', 'monthly']; // Removed 'manual'

    // Sanitize Prompts, Frequency, Refinement, and Image Prompts per category
    $sanitized_input['prompts'] = [];
    $sanitized_input['frequency'] = [];
    $sanitized_input['refinement'] = [];
    $sanitized_input['image_prompts'] = []; // Initialize image prompts array
    $sanitized_input['formatting_instructions'] = [];

    // Get all possible category IDs to ensure we process deletions correctly
    $all_possible_cat_ids = get_terms(['taxonomy' => 'category', 'fields' => 'ids', 'hide_empty' => false]);
    if (is_wp_error($all_possible_cat_ids) || !is_array($all_possible_cat_ids)) {
        $all_possible_cat_ids = [];
    }
    // Determine which categories were submitted (based on content prompts array presence)
    $submitted_cat_ids = isset($input['prompts']) && is_array($input['prompts']) ? array_keys($input['prompts']) : [];

    if (isset($input['formatting_instructions']) && is_array($input['formatting_instructions'])) { $submitted_cat_ids = array_merge($submitted_cat_ids, array_keys($input['formatting_instructions'])); }

    // Process all categories that exist or were submitted
    $process_cat_ids = array_unique(array_merge($submitted_cat_ids, $all_possible_cat_ids));


    foreach ($process_cat_ids as $cat_id) {
        $cat_id_int = absint($cat_id);
        if ($cat_id_int === 0) continue;

        $has_content_prompt = false;
        // Sanitize Content Prompt
        if (isset($input['prompts'][$cat_id_int])) {
            $sanitized_prompt = sanitize_textarea_field(wp_unslash($input['prompts'][$cat_id_int]));
             if (!empty(trim($sanitized_prompt))) {
                $sanitized_input['prompts'][$cat_id_int] = $sanitized_prompt;
                $has_content_prompt = true;
            }
        }

        // Sanitize Frequency - Only relevant if content prompt exists
        if ($has_content_prompt) {
            if (isset($input['frequency'][$cat_id_int])) {
                $submitted_frequency = sanitize_text_field($input['frequency'][$cat_id_int]);
                $sanitized_input['frequency'][$cat_id_int] = in_array($submitted_frequency, $allowed_frequencies) ? $submitted_frequency : 'none';
            } else {
                 $sanitized_input['frequency'][$cat_id_int] = 'none'; // Default to none if not submitted
            }
        } else {
             // Force frequency to 'none' if no content prompt
             $sanitized_input['frequency'][$cat_id_int] = 'none';
        }


        // Sanitize Refinement - Only save if content prompt exists
        if ($has_content_prompt) {
            if (isset($input['refinement'][$cat_id_int])) {
                $sanitized_refinement = sanitize_textarea_field(wp_unslash($input['refinement'][$cat_id_int]));
                $sanitized_input['refinement'][$cat_id_int] = $sanitized_refinement; // Save even if empty
           } else {
                $sanitized_input['refinement'][$cat_id_int] = ''; // Default to empty if not submitted
           }
       } // else: refinement is irrelevant if no content prompt

        // Sanitize Image Prompt - Only save if content prompt exists
        if ($has_content_prompt) {
             if (isset($input['image_prompts'][$cat_id_int])) {
                $sanitized_image_prompt = sanitize_textarea_field(wp_unslash($input['image_prompts'][$cat_id_int]));
                if (!empty(trim($sanitized_image_prompt))) {
                     $sanitized_input['image_prompts'][$cat_id_int] = $sanitized_image_prompt;
                }
                 // If submitted image prompt is empty/whitespace, ensure it's *not* saved
             }
             // If image prompt wasn't submitted for an active category, it just won't be in the array
        } // else: image prompt is irrelevant if no content prompt

        if (isset($input['formatting_instructions'][$cat_id_int])) {
            $sanitized_formatting_instructions = sanitize_textarea_field(wp_unslash($input['formatting_instructions'][$cat_id_int]));
            // Save it even if empty, signifies no specific rules set by user
            $sanitized_input['formatting_instructions'][$cat_id_int] = $sanitized_formatting_instructions;
        } else {
             // If not submitted at all for this category, store empty string
             $sanitized_input['formatting_instructions'][$cat_id_int] = '';
        }

        // Clean up - If no content prompt, ensure related settings are cleared
        if (!$has_content_prompt) {
            unset($sanitized_input['frequency'][$cat_id_int]);
            unset($sanitized_input['refinement'][$cat_id_int]);
            unset($sanitized_input['image_prompts'][$cat_id_int]);
        }

    } // end foreach category

    return $sanitized_input;
}


// Render Settings Page HTML Manual Process
function aiccgen_google_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'ai-cat-content-gen-google'));
    }
    // Get options once for checks
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $venice_api_key_exists = !empty($options['venice_api_key']);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php aiccgen_google_show_save_notices(); // Display feedback notices ?>

        <form action="options.php" method="post">
            <?php
            settings_fields(AICCG_GOOGLE_OPTION_GROUP);
            do_settings_sections(AICCG_GOOGLE_SETTINGS_SLUG);
            submit_button(__('Save Settings', 'ai-cat-content-gen-google'));
            ?>
        </form>

        <hr>
        <h2><?php esc_html_e('Manual Generation (Content & Image)', 'ai-cat-content-gen-google'); ?></h2>
        <p><?php esc_html_e('Use this section to manual generation for a each category. Select a category with a saved content prompt and enter an image prompt to featured image generation.', 'ai-cat-content-gen-google'); ?></p>
         <form id="aiccgen-google-generate-form">
             <table class="form-table" role="presentation">
                 <tbody>
                     <tr>
                         <th scope="row"><label for="aiccgen_google_category_to_generate"><?php esc_html_e('Generate for Category', 'ai-cat-content-gen-google'); ?></label></th>
                         <td>
                             <?php
                             // Options already fetched above
                             $categories = get_categories(['hide_empty' => 0, 'exclude' => get_option('default_category'), 'orderby' => 'date', 'order' => 'DESC']);
                             $prompts = isset($options['prompts']) ? $options['prompts'] : [];
                             ?>
                             <select name="aiccgen_google_category_to_generate" id="aiccgen_google_category_to_generate" required>
                                 <option value=""><?php esc_html_e('-- Select Category --', 'ai-cat-content-gen-google'); ?></option>
                                 <?php if ($categories):
                                     usort($categories, function($a, $b) use ($prompts) { /* ... sort logic same as before ... */
                                         $a_has_prompt = isset($prompts[$a->term_id]) && !empty(trim($prompts[$a->term_id]));
                                         $b_has_prompt = isset($prompts[$b->term_id]) && !empty(trim($prompts[$b->term_id]));
                                         if ($a_has_prompt == $b_has_prompt) {
                                             return strcmp($a->name, $b->name);
                                         }
                                         return $a_has_prompt ? -1 : 1;
                                     });
                                     foreach ($categories as $category):
                                         $has_prompt = isset($prompts[$category->term_id]) && !empty(trim($prompts[$category->term_id]));
                                         $prompt_indicator = $has_prompt ? '' : __(' (No content prompt)', 'ai-cat-content-gen-google');
                                         ?>
                                             <option value="<?php echo intval($category->term_id); ?>" <?php disabled(!$has_prompt); ?>>
                                                 <?php echo esc_html($category->name . $prompt_indicator); ?>
                                             </option>
                                         <?php
                                     endforeach;
                                 endif; ?>
                             </select>
                             <p class="description"><?php esc_html_e('Only categories with saved content prompts are enabled.', 'ai-cat-content-gen-google'); ?></p>
                         </td>
                     </tr>
                     <?php // --- Manual Image Prompt Textarea --- ?>
                     <tr>
                         <th scope="row"><label for="aiccgen_google_image_prompt_manual"><?php esc_html_e('Image Prompt (Optional)', 'ai-cat-content-gen-google'); ?></label></th>
                         <td>
                             <textarea id="aiccgen_google_image_prompt_manual" name="aiccgen_google_image_prompt_manual" rows="3" class="large-text" placeholder="<?php esc_attr_e('Enter prompt for AI image generation...', 'ai-cat-content-gen-google'); ?>" <?php disabled(!$venice_api_key_exists); ?>></textarea>
                         </td>
                     </tr>
                      <tr>
                         <th scope="row"></th>
                         <td>
                              <span class="wrploader-wrap">
                                 <input type="submit" name="aiccgen_google_submit_generate" class="button button-primary" value="<?php esc_attr_e('Generate Now', 'ai-cat-content-gen-google'); ?>"> <?php // Changed button text & style ?>
                                 <span id="aiccgen-google-loader" style="display: none;"><img src="<?php echo plugin_dir_url(__FILE__) ?>img/loading.gif" alt="Loading..."></span>
                             </span>
                         </td>
                     </tr>
                 </tbody>
             </table>
         </form>
         <div id="aiccgen-google-result-area" style="margin-top: 20px; display: none;">
             <!-- AJAX results loaded here -->
         </div>

    </div><!-- /.wrap -->
    <?php
}

// Ajax for Image Refine
function aiccgen_google_ajax_refine_featured_image() {
    check_ajax_referer(AICCG_GOOGLE_NONCE_ACTION, '_ajax_nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'ai-cat-content-gen-google')], 403);
    }

    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;

    if ($category_id <= 0 || !term_exists($category_id, 'category')) {
        wp_send_json_error(['message' => __('Invalid category specified.', 'ai-cat-content-gen-google')], 400);
    }

    // Find the latest draft post for this category
    $latest_draft_args = [
        'post_type'      => 'post',
        'post_status'    => 'draft',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => [[ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $category_id ]],
        'fields'         => 'ids',
    ];
    $latest_draft_query = new WP_Query($latest_draft_args);
    $draft_post_id = $latest_draft_query->have_posts() ? $latest_draft_query->posts[0] : 0;

    if ($draft_post_id === 0) {
        wp_send_json_error(['message' => __('Could not find a draft post in this category to refine its image.', 'ai-cat-content-gen-google')], 404);
    }

    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $venice_api_key = isset($options['venice_api_key']) ? trim($options['venice_api_key']) : '';
    $image_prompts_settings = isset($options['image_prompts']) ? $options['image_prompts'] : [];
    $category_image_prompt = isset($image_prompts_settings[$category_id]) ? trim($image_prompts_settings[$category_id]) : '';

    if (empty($venice_api_key)) {
        wp_send_json_error(['message' => __('Venice AI API Key missing in settings.', 'ai-cat-content-gen-google')], 400);
    }
    if (empty($category_image_prompt)) {
        wp_send_json_error(['message' => __('No featured image prompt is set for this category in settings. Please enter prompt and save the settings before refine the featured image.', 'ai-cat-content-gen-google')], 400);
    }

    $generated_images = [];
    $generation_attempts = 3;

    for ($i = 0; $i < $generation_attempts; $i++) {
        $image_result = aiccgen_google_generate_venice_image($venice_api_key, $category_image_prompt);
        if ($image_result['success'] && isset($image_result['attachment_id'])) {
            $image_url = wp_get_attachment_image_url($image_result['attachment_id'], 'medium'); // Or 'thumbnail'
            if ($image_url) {
                $generated_images[] = [
                    'attachment_id' => $image_result['attachment_id'],
                    'image_url'     => $image_url,
                ];
            } else {
                // If URL fetch fails, still log but don't send to user maybe? Or delete attachment?
                // For now, let's assume it mostly works. If it fails, the image won't show.
                error_log("[AI Cat Gen Refine Image] Failed to get URL for attachment ID: " . $image_result['attachment_id']);
            }
        } else {
            // Log individual image generation failure
            error_log("[AI Cat Gen Refine Image] Attempt " . ($i+1) . " failed: " . ($image_result['error'] ?? 'Unknown error'));
        }
    }

    if (empty($generated_images)) {
        wp_send_json_error(['message' => __('Failed to generate any image options. Check API key or prompt. See server logs for details.', 'ai-cat-content-gen-google')], 500);
    }

    wp_send_json_success([
        'draft_post_id'   => $draft_post_id,
        'generated_images' => $generated_images,
        'message'         => sprintf(_n('%d image option generated.', '%d image options generated.', count($generated_images), 'ai-cat-content-gen-google'), count($generated_images))
    ]);
}
add_action('wp_ajax_' . AICCG_GOOGLE_AJAX_REFINE_IMAGE_ACTION, 'aiccgen_google_ajax_refine_featured_image');


// Refine Image Ajax
function aiccgen_google_ajax_apply_refined_image() {
    check_ajax_referer(AICCG_GOOGLE_NONCE_ACTION, '_ajax_nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'ai-cat-content-gen-google')], 403);
    }

    $draft_post_id = isset($_POST['draft_post_id']) ? absint($_POST['draft_post_id']) : 0;
    $selected_image_id = isset($_POST['selected_image_id']) ? absint($_POST['selected_image_id']) : 0;
    $all_new_image_ids = isset($_POST['all_new_image_ids']) && is_array($_POST['all_new_image_ids'])
                        ? array_map('absint', $_POST['all_new_image_ids'])
                        : [];

    if ($draft_post_id <= 0 || !get_post($draft_post_id)) {
        wp_send_json_error(['message' => __('Invalid draft post specified.', 'ai-cat-content-gen-google')], 400);
    }
    if ($selected_image_id <= 0 || !wp_get_attachment_url($selected_image_id)) {
        wp_send_json_error(['message' => __('Invalid image selected to apply.', 'ai-cat-content-gen-google')], 400);
    }
    if (empty($all_new_image_ids) || !in_array($selected_image_id, $all_new_image_ids)) {
        wp_send_json_error(['message' => __('Mismatch in image selection data.', 'ai-cat-content-gen-google')], 400);
    }

    // Set the new featured image
    $set_thumb_result = set_post_thumbnail($draft_post_id, $selected_image_id);

    if (!$set_thumb_result && !has_post_thumbnail($draft_post_id)) { // set_post_thumbnail returns false on failure, or meta_id on success. Check if it has a thumb after.
         wp_send_json_error(['message' => __('Failed to set the new featured image for the draft.', 'ai-cat-content-gen-google')], 500);
    }

    // Delete unselected newly generated images
    $deleted_count = 0;
    foreach ($all_new_image_ids as $img_id) {
        if ($img_id !== $selected_image_id) {
            if (wp_delete_attachment($img_id, true)) { // true for force delete
                $deleted_count++;
            } else {
                error_log("[AI Cat Gen Apply Image] Failed to delete unselected image ID: " . $img_id);
            }
        }
    }

    $edit_link = get_edit_post_link($draft_post_id, 'raw');
    $edit_link_html = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($edit_link), __('Edit Draft', 'ai-cat-content-gen-google'));

    wp_send_json_success([
        'message' => sprintf(
            __('New featured image applied successfully. %d unused image option(s) deleted. %s', 'ai-cat-content-gen-google'),
            $deleted_count,
            $edit_link_html
        )
    ]);
}
add_action('wp_ajax_' . AICCG_GOOGLE_AJAX_APPLY_REFINED_IMAGE_ACTION, 'aiccgen_google_ajax_apply_refined_image');




// Manual Generation
function aiccgen_google_ajax_manual_generate() {
    check_ajax_referer(AICCG_GOOGLE_NONCE_ACTION, '_ajax_nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'ai-cat-content-gen-google')], 403);
    }

    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    // Get the manual image prompt
    $manual_image_prompt = isset($_POST['image_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['image_prompt'])) : '';

    if (!$category_id) {
        wp_send_json_error(['message' => __('No category selected.', 'ai-cat-content-gen-google')], 400);
    }

    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $google_api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
    $venice_api_key = isset($options['venice_api_key']) ? trim($options['venice_api_key']) : ''; // Get Venice key
    $model = isset($options['model']) ? $options['model'] : 'gemini-2.5-pro-exp-03-25';
    $prompts = isset($options['prompts']) ? $options['prompts'] : [];

    // Get global and category-specific formatting instructions for manual generation
    $global_formatting_instructions = isset($options['global_formatting_instructions']) ? trim($options['global_formatting_instructions']) : '';
    $category_specific_formatting_instructions_map = isset($options['formatting_instructions']) ? $options['formatting_instructions'] : [];

    if (empty($google_api_key)) {
        wp_send_json_error(['message' => __('Google AI API Key missing in settings.', 'ai-cat-content-gen-google')], 400);
    }

    $user_entered_prompt = isset($prompts[$category_id]) ? trim($prompts[$category_id]) : '';
    if (empty($user_entered_prompt)) {
         wp_send_json_error(['message' => __('No content prompt saved for this category in settings. Cannot manual generation.', 'ai-cat-content-gen-google')], 400);
    }

    $category = get_category($category_id);
    if (!$category) {
        wp_send_json_error(['message' => __('Category not found.', 'ai-cat-content-gen-google')], 404);
    }

    // Determine effective formatting instructions for manual generation
    $category_formatting_text = isset($category_specific_formatting_instructions_map[$category_id]) ? trim($category_specific_formatting_instructions_map[$category_id]) : '';
    $effective_formatting_instructions = !empty($category_formatting_text) ? $category_formatting_text : $global_formatting_instructions;

    // --- Generate Content ---
    $final_prompt = aiccgen_google_build_api_prompt($category->name, $user_entered_prompt);
    $content_response = aiccgen_google_call_gemini_api($google_api_key, $model, $final_prompt);

    // Initialize response data structure
    $response_data = [
        'category_name' => $category->name,
        'category_id'   => $category_id,
        'content'       => null,
        'content_error' => null,
        'image_attachment_id' => null,
        'image_url'     => null,
        'image_error'   => null,
    ];

    if (!$content_response['success']) {
        // If content fails, we don't proceed to image, send error immediately
        wp_send_json_error(['message' => $content_response['error']], $content_response['code']);
        return; // Exit
    }

    $response_data['content'] = $content_response['content'];

    // --- Generate Image (Optional) ---
    $image_prompt_trimmed = trim($manual_image_prompt);
    if (!empty($image_prompt_trimmed)) {
        if (!empty($venice_api_key)) {
            $image_result = aiccgen_google_generate_venice_image($venice_api_key, $image_prompt_trimmed);
            if ($image_result['success'] && isset($image_result['attachment_id'])) {
                $response_data['image_attachment_id'] = $image_result['attachment_id'];
                $response_data['image_url'] = wp_get_attachment_url($image_result['attachment_id']); // Get URL for preview
                 if (!$response_data['image_url']) { // Fallback if URL fetch fails
                     $response_data['image_error'] = __('Image generated but failed to retrieve URL.', 'ai-cat-content-gen-google');
                     wp_delete_attachment($response_data['image_attachment_id'], true); // Clean up if URL fails
                     $response_data['image_attachment_id'] = null;
                 }
            } else {
                $response_data['image_error'] = $image_result['error'] ?? __('Unknown image generation error.', 'ai-cat-content-gen-google');
            }
        } else {
            // Venice key is missing, set specific error message
            $response_data['image_error'] = __('Venice AI API key missing in settings.', 'ai-cat-content-gen-google');
        }
    } // No 'else' needed, if prompt is empty, image fields remain null

    // Send combined success response
    wp_send_json_success($response_data);

}

remove_action('wp_ajax_' . AICCG_GOOGLE_AJAX_ACTION, 'aiccgen_google_ajax_generate_content');
add_action('wp_ajax_' . AICCG_GOOGLE_AJAX_ACTION, 'aiccgen_google_ajax_manual_generate'); 

// AJAX Refine Content (Manual Test - Content Only)
function aiccgen_google_ajax_refine_content() {
     check_ajax_referer(AICCG_GOOGLE_NONCE_ACTION, '_ajax_nonce');
     if (!current_user_can('edit_posts')) {
         wp_send_json_error(['message' => __('Permission denied.', 'ai-cat-content-gen-google')], 403);
     }

     $original_content = isset($_POST['original_content']) ? wp_kses_post(wp_unslash($_POST['original_content'])) : '';
     $refinement_prompt_text = isset($_POST['refinement_prompt']) ? sanitize_textarea_field($_POST['refinement_prompt']) : '';
     // Retrieve category ID from POST, maybe needed if create post uses it? Let's pass it back.
     $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;

     if (empty($original_content) || empty($refinement_prompt_text)) {
         wp_send_json_error(['message' => __('Missing original content or refinement instruction.', 'ai-cat-content-gen-google')], 400);
     }

     $options = get_option(AICCG_GOOGLE_OPTION_NAME);
     $google_api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
     $model = isset($options['model']) ? $options['model'] : 'gemini-2.5-pro-exp-03-25';

     if (empty($google_api_key)) {
         wp_send_json_error(['message' => __('Google AI API Key missing.', 'ai-cat-content-gen-google')], 400);
     }

     $final_refinement_prompt = aiccgen_google_build_refinement_prompt($original_content, $refinement_prompt_text);
     $response_data = aiccgen_google_call_gemini_api($google_api_key, $model, $final_refinement_prompt);

     if ($response_data['success']) {
          wp_send_json_success([
              'content' => $response_data['content'],
              'category_id' => $category_id // Pass category ID back for consistency
              ]);
     } else {
         wp_send_json_error(['message' => $response_data['error']], $response_data['code']);
     }
}
add_action('wp_ajax_' . AICCG_GOOGLE_AJAX_REFINE_ACTION, 'aiccgen_google_ajax_refine_content');

// AJAX Create Post (Manual Test - NO IMAGE) Manual Process
// AJAX Create Post (Manual Test - WITH Optional Image) Manual Process
function aiccgen_google_ajax_create_post() {
    check_ajax_referer(AICCG_GOOGLE_NONCE_ACTION, '_ajax_nonce');
    if (!current_user_can('publish_posts')) {
        wp_send_json_error(['message' => __('You do not have permission to create posts.', 'ai-cat-content-gen-google')], 403);
    }

    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $post_title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
    $post_content = isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '';
    // Get the image attachment ID (sanitize as int or null)
    $image_attachment_id = isset($_POST['image_attachment_id']) ? absint($_POST['image_attachment_id']) : null;
    if ($image_attachment_id === 0) { $image_attachment_id = null; } // Ensure 0 becomes null


    if (empty($post_title) || empty($post_content) || $category_id <= 0 || !term_exists($category_id, 'category')) {
         wp_send_json_error(['message' => __('Invalid input for post creation.', 'ai-cat-content-gen-google')], 400);
         return;
    }

    // Create post WITH optional featured image ID
    $result = aiccgen_google_create_draft_post($category_id, $post_title, $post_content, $image_attachment_id); // Pass the image ID

    if ($result['success']) {
         $edit_link = get_edit_post_link($result['post_id'], 'raw');
         $edit_link_html = sprintf(
             '<a href="%s" target="_blank">%s</a>',
             esc_url($edit_link),
             __('Edit Draft Post', 'ai-cat-content-gen-google')
         );
         $message = $image_attachment_id
            ? __('Draft post with featured image created successfully (Manual).', 'ai-cat-content-gen-google')
            : __('Draft post created successfully (Manual - Content Only).', 'ai-cat-content-gen-google');

         wp_send_json_success([
             'message' => $message,
             'post_id' => $result['post_id'],
             'edit_link_html' => $edit_link_html
         ]);
    } else {
        wp_send_json_error(['message' => $result['error']], 500);
    }
}
add_action('wp_ajax_' . AICCG_GOOGLE_AJAX_CREATE_POST_ACTION, 'aiccgen_google_ajax_create_post');

// AJAX Refine Draft (Content Only)
function aiccgen_google_ajax_refine_latest_draft() {
    check_ajax_referer(AICCG_GOOGLE_NONCE_ACTION, '_ajax_nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'ai-cat-content-gen-google')], 403);
    }

    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $refinement_instructions = isset($_POST['instructions']) ? sanitize_textarea_field(wp_unslash($_POST['instructions'])) : '';

    if ($category_id <= 0 || !term_exists($category_id, 'category')) {
        wp_send_json_error(['message' => __('Invalid category specified.', 'ai-cat-content-gen-google')], 400);
    }
    if (empty($refinement_instructions)) {
        wp_send_json_error(['message' => __('Refinement instructions cannot be empty.', 'ai-cat-content-gen-google')], 400);
    }
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $google_api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
    $model = isset($options['model']) ? $options['model'] : 'gemini-2.5-pro-exp-03-25';
    if (empty($google_api_key)) {
        wp_send_json_error(['message' => __('Google AI API Key missing in settings.', 'ai-cat-content-gen-google')], 400);
    }

    // Find latest draft
    $latest_draft_args = [
        'post_type'      => 'post',
        'post_status'    => 'draft',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => [
            [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $category_id,
            ],
        ],
        'fields'         => 'ids',
    ];
    $latest_draft_query = new WP_Query($latest_draft_args);
    $draft_post_id = $latest_draft_query->have_posts() ? $latest_draft_query->posts[0] : 0;

    if ($draft_post_id === 0) {
        wp_send_json_error(['message' => __('Could not find a draft post for this category.', 'ai-cat-content-gen-google')], 404);
    }

    $draft_post = get_post($draft_post_id);
    if (!$draft_post) {
         wp_send_json_error(['message' => __('Error retrieving draft post data.', 'ai-cat-content-gen-google')], 500);
    }
    $original_content = $draft_post->post_content;

    // Refine Content using Google API
    $refinement_api_prompt = aiccgen_google_build_refinement_prompt($original_content, $refinement_instructions);
    $refinement_response = aiccgen_google_call_gemini_api($google_api_key, $model, $refinement_api_prompt);

    if (!$refinement_response['success']) {
         $error_msg = $refinement_response['error'] ? $refinement_response['error'] : __('Unknown API error during refinement.', 'ai-cat-content-gen-google');
        wp_send_json_error(['message' => __('API Content Refinement Failed: ', 'ai-cat-content-gen-google') . $error_msg], $refinement_response['code']);
    }

    $refined_content = $refinement_response['content'];

    // Update ONLY the post content
    $update_args = [
        'ID'           => $draft_post_id,
        'post_content' => wp_kses_post($refined_content), // Sanitize the refined content
    ];

    // Temporarily remove kses filters for update
    $kses_filters_removed = false;
    if (has_filter('content_save_pre', 'wp_filter_post_kses')) {
        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        $kses_filters_removed = true;
    }

    $updated_post_id = wp_update_post($update_args, true); // Pass true to enable WP_Error return

    // Add kses filters back if they were removed
    if ($kses_filters_removed) {
        add_filter('content_save_pre', 'wp_filter_post_kses');
        add_filter('content_filtered_save_pre', 'wp_filter_post_kses');
    }


    if (is_wp_error($updated_post_id)) {
         $error_msg = $updated_post_id->get_error_message();
        wp_send_json_error(['message' => __('Error updating draft post content: ', 'ai-cat-content-gen-google') . $error_msg], 500);
    } elseif ($updated_post_id === 0) {
        wp_send_json_error(['message' => __('Failed to update draft post content (wp_update_post returned 0 - maybe no changes?).', 'ai-cat-content-gen-google')], 500);
    } else {
        $edit_link = get_edit_post_link($draft_post_id, 'raw');
        $edit_link_html = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($edit_link), __('Edit Draft', 'ai-cat-content-gen-google'));
        wp_send_json_success([
            'message' => sprintf(__('Latest draft post content successfully refined. %s', 'ai-cat-content-gen-google'), $edit_link_html),
            'post_id' => $draft_post_id,
            'edit_link_html' => $edit_link_html
        ]);
    }
}
add_action('wp_ajax_' . AICCG_GOOGLE_AJAX_REFINE_DRAFT_ACTION, 'aiccgen_google_ajax_refine_latest_draft');


// --- Scheduling and Settings Update Handling ---
function aiccgen_google_handle_settings_update($old_value, $new_value) {

    // Increase execution time limit for potentially long operations (like scheduling many tasks)
    @set_time_limit(300); // 5 minutes

    $results = [
        'schedule_updates' => 0,
        'schedule_cleared' => 0,
        'details' => []
        // Removed success/fail counts as manual generation on save is removed
    ];

    $old_prompts = isset($old_value['prompts']) && is_array($old_value['prompts']) ? $old_value['prompts'] : [];
    $old_freqs = isset($old_value['frequency']) && is_array($old_value['frequency']) ? $old_value['frequency'] : [];

    $new_prompts = isset($new_value['prompts']) && is_array($new_value['prompts']) ? $new_value['prompts'] : [];
    $new_freqs = isset($new_value['frequency']) && is_array($new_value['frequency']) ? $new_value['frequency'] : [];
    // No need for refinements/image prompts here, only scheduling matters

    // Get all categories that might have changed state
    $all_category_ids = get_terms(['taxonomy' => 'category', 'fields' => 'ids', 'hide_empty' => false]);
     if (is_wp_error($all_category_ids) || !is_array($all_category_ids)) {
         $all_category_ids = [];
     }
     // Include keys from old/new options just in case a category was deleted since last save
     $process_cat_ids = array_unique(array_merge(
         array_keys($old_prompts),
         array_keys($new_prompts),
         $all_category_ids
     ));


    foreach ($process_cat_ids as $cat_id) {
        $cat_id = absint($cat_id);
        if ($cat_id === 0) continue;

        $category = get_category($cat_id);
        // If category doesn't exist anymore, try to clear any lingering schedule
        if (!$category) {
            $args_for_cron = ['category_id' => $cat_id];
             $timestamp = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK, $args_for_cron);
            if ($timestamp) {
                wp_unschedule_event($timestamp, AICCG_GOOGLE_CRON_HOOK, $args_for_cron);
                $results['schedule_cleared']++;
                 $results['details'][] = ['type' => 'info', 'message' => sprintf(__('Cleared schedule for deleted/invalid category ID %d.', 'ai-cat-content-gen-google'), $cat_id)];
            }
            continue; // Skip further processing for this ID
        }
        $category_name = $category->name;

        // Check prompt existence in NEW value (determines active state)
        $new_prompt_exists = isset($new_prompts[$cat_id]) && !empty(trim($new_prompts[$cat_id]));

        // Get frequencies, default to 'none' if not set or no prompt
        $old_freq = isset($old_prompts[$cat_id]) && isset($old_freqs[$cat_id]) && !empty(trim($old_prompts[$cat_id])) ? $old_freqs[$cat_id] : 'none';
        $new_freq = $new_prompt_exists && isset($new_freqs[$cat_id]) ? $new_freqs[$cat_id] : 'none';

        // Treat 'manual' from old settings as 'none' for scheduling comparison
        if ($old_freq === 'manual') $old_freq = 'none';

        $args_for_cron = ['category_id' => $cat_id];

        // --- Schedule Management ---
        $is_currently_scheduled = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK, $args_for_cron);
        $needs_scheduling = $new_prompt_exists && $new_freq !== 'none';
        // Needs clearing if:
        // 1. It was scheduled before (old_freq !== 'none') AND (it's no longer active OR frequency changed)
        // 2. Or if it's currently scheduled but shouldn't be (e.g., prompt removed)
        $needs_clearing = ($is_currently_scheduled && !$needs_scheduling) || ($is_currently_scheduled && $needs_scheduling && wp_get_schedule(AICCG_GOOGLE_CRON_HOOK, $args_for_cron) !== $new_freq);


        // 1. Clear schedule if needed
        if ($needs_clearing) {
            $timestamp = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK, $args_for_cron); // Get timestamp again just in case
            if ($timestamp) {
                $cleared = wp_unschedule_event($timestamp, AICCG_GOOGLE_CRON_HOOK, $args_for_cron);
                if ($cleared !== false) {
                    $results['schedule_cleared']++;
                    $results['details'][] = ['type' => 'info', 'message' => sprintf(__('Cleared previous schedule for category "%s".', 'ai-cat-content-gen-google'), esc_html($category_name))];
                    $is_currently_scheduled = false; // Update state after clearing
                } else {
                     $results['details'][] = ['type' => 'warning', 'message' => sprintf(__('Could not clear previous schedule for "%s". Check WP Cron.', 'ai-cat-content-gen-google'), esc_html($category_name))];
                }
            }
        }

        // 2. Schedule if needed AND not already scheduled with the correct frequency
        if ($needs_scheduling && !$is_currently_scheduled) {
            $first_run_time = time() + 60; // Schedule slightly in the future
            wp_schedule_event($first_run_time, $new_freq, AICCG_GOOGLE_CRON_HOOK, $args_for_cron);
            $verify_schedule_time = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK, $args_for_cron); // Verify schedule
            if ($verify_schedule_time) {
                 $results['schedule_updates']++;
                 $results['details'][] = ['type' => 'success', 'message' => sprintf(__('Scheduled new task (%s) for category "%s".', 'ai-cat-content-gen-google'), esc_html($new_freq), esc_html($category_name))];
            } else {
                 $results['details'][] = ['type' => 'error', 'message' => sprintf(__('CRITICAL FAILURE: Could not schedule task (%s) for category "%s". Check WP Cron system/logs.', 'ai-cat-content-gen-google'), esc_html($new_freq), esc_html($category_name))];
            }
        } elseif ($needs_scheduling && $is_currently_scheduled) {
            // Already scheduled correctly, log for info? Maybe not necessary unless debugging.
             // $results['details'][] = ['type' => 'info', 'message' => sprintf(__('Schedule (%s) for category "%s" remains active.', 'ai-cat-content-gen-google'), esc_html($new_freq), esc_html($category_name))];
        }

    } // End foreach category loop

    // Save the results for display on the settings page
    set_transient(AICCG_GOOGLE_SETTINGS_NOTICE_TRANSIENT, $results, 60);
}
add_action('update_option_' . AICCG_GOOGLE_OPTION_NAME, 'aiccgen_google_handle_settings_update', 10, 2);


// --- Cron Setup Callback for posts---
function aiccgen_google_run_scheduled_generation($category_id) {
    $category_id = absint($category_id);
    if ($category_id === 0) {
        // Log error: Invalid category ID passed to cron.
        error_log("[AI Cat Gen Cron] Error: Received invalid category ID 0.");
        return;
    }

    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $google_api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
    $venice_api_key = isset($options['venice_api_key']) ? trim($options['venice_api_key']) : ''; // Get Venice key
    $model = isset($options['model']) ? $options['model'] : 'gemini-2.5-pro-exp-03-25';
    $prompts = isset($options['prompts']) ? $options['prompts'] : [];
    $refinements = isset($options['refinement']) ? $options['refinement'] : [];
    $image_prompts = isset($options['image_prompts']) ? $options['image_prompts'] : []; // Get image prompts
    $frequencies = isset($options['frequency']) ? $options['frequency'] : [];
    $formatting_instructions_map = isset($options['formatting_instructions']) ? $options['formatting_instructions'] : [];

    $global_formatting_instructions = isset($options['global_formatting_instructions']) ? trim($options['global_formatting_instructions']) : '';
    $category_specific_formatting_instructions_map = isset($options['formatting_instructions']) ? $options['formatting_instructions'] : [];

    $is_valid = true;
    $validation_error_reason = '';

    // Basic validation
    if (empty($google_api_key)) {$is_valid = false; $validation_error_reason = 'Missing Google API Key.'; }
    if (!isset($prompts[$category_id]) || empty(trim($prompts[$category_id]))) { $is_valid = false; $validation_error_reason = 'Missing Content Prompt.'; }
    if (!isset($frequencies[$category_id]) || $frequencies[$category_id] === 'none') { $is_valid = false; $validation_error_reason = 'Frequency set to None.'; } // Ensure frequency is not 'none' for cron

    $category = get_category($category_id);
    if (!$category) { $is_valid = false; $validation_error_reason = 'Category not found.'; }

    if (!$is_valid) {
        // Log the reason for failure and clear the invalid schedule
        error_log("[AI Cat Gen Cron] Error for Cat ID {$category_id}: {$validation_error_reason} - Unscheduling task.");
        $args_for_cron = ['category_id' => $category_id];
        wp_clear_scheduled_hook(AICCG_GOOGLE_CRON_HOOK, $args_for_cron);
        return;
    }

    $category_name = $category->name;
    $prompt_text = $prompts[$category_id];
    $refinement_text = isset($refinements[$category_id]) ? trim($refinements[$category_id]) : '';
    $image_prompt_text = isset($image_prompts[$category_id]) ? trim($image_prompts[$category_id]) : ''; // Get specific image prompt
    $formatting_instructions_text = isset($formatting_instructions_map[$category_id]) ? trim($formatting_instructions_map[$category_id]) : '';

    // Determine effective formatting instructions
    $category_formatting_text = isset($category_specific_formatting_instructions_map[$category_id]) ? trim($category_specific_formatting_instructions_map[$category_id]) : '';
    $effective_formatting_instructions = !empty($category_formatting_text) ? $category_formatting_text : $global_formatting_instructions;

    // Call the helper function with all necessary data
    $cron_result = aiccgen_google_generate_and_create_post(
        $category_id,
        $category_name,
        $prompt_text,
        $refinement_text,
        $image_prompt_text,
        $effective_formatting_instructions, // Pass the determined instructions
        $google_api_key,
        $venice_api_key,
        $model,
        'Scheduled'
    );

    // Log the result (optional but helpful for debugging)
    if ($cron_result && isset($cron_result['notice'])) {
        $log_level = ($cron_result['success']) ? 'Info' : 'Error';
        // Strip HTML before logging
        $log_message = wp_strip_all_tags($cron_result['notice']['message']);
        error_log("[AI Cat Gen Cron/{$log_level}] Cat ID {$category_id}: {$log_message}");
    } else {
         error_log("[AI Cat Gen Cron/Error] Cat ID {$category_id}: Failed to get result from aiccgen_google_generate_and_create_post.");
    }
}
add_action(AICCG_GOOGLE_CRON_HOOK, 'aiccgen_google_run_scheduled_generation', 10, 1);


// --- Generate_and_create_post (Handles Content, Refine, Image, Post Create) ---
function aiccgen_google_generate_and_create_post($cat_id, $category_name, $prompt_text, $refinement_text, $image_prompt_text, $formatting_instructions_text, $google_api_key, $venice_api_key, $model, $context = 'Process') {
    $notice_prefix = sprintf(__('%s generation for "%s": ', 'ai-cat-content-gen-google'), $context, esc_html($category_name));
    $generated_image_id = null; // Initialize image ID
    $image_generation_status_msg = ''; // For logging/notices

    // Step 1: Generation (Google AI Content)
    $final_prompt = aiccgen_google_build_api_prompt($category_name, $prompt_text);
    $final_prompt = aiccgen_google_build_api_prompt($category_name, $prompt_text, $formatting_instructions_text);
    $generation_response = aiccgen_google_call_gemini_api($google_api_key, $model, $final_prompt);

    if (!$generation_response['success']) {
        $error_msg = esc_html($generation_response['error']);
        return [
            'success' => false,
            'notice' => ['type' => 'error', 'message' => $notice_prefix . sprintf(__('Content generation FAILED - %s', 'ai-cat-content-gen-google'), $error_msg)]
        ];
    }
    $content = $generation_response['content'];

    // Step 2: Refinement (Google AI Content - Optional)
    $refinement_text_trimmed = trim($refinement_text);
    if (!empty($refinement_text_trimmed)) {
        $refinement_api_prompt = aiccgen_google_build_refinement_prompt($content, $refinement_text_trimmed);
        $refinement_response = aiccgen_google_call_gemini_api($google_api_key, $model, $refinement_api_prompt);

        if ($refinement_response['success']) {
            $content = $refinement_response['content']; // Update content with refined version
        } else {
            // Log refinement failure but proceed with unrefined content
            $refinement_error = esc_html($refinement_response['error'] ?? __('Unknown error', 'ai-cat-content-gen-google'));
            $image_generation_status_msg .= ' ' . sprintf(__('(Content auto-refinement failed: %s)', 'ai-cat-content-gen-google'), $refinement_error);
            error_log("[AI Cat Gen Helper/$context] Cat ID {$cat_id}: Content refinement failed - {$refinement_error}");
        }
    }
    

    // Step 3: Image Generation (Venice AI - Optional)
    $image_prompt_text_trimmed = trim($image_prompt_text);
    if (!empty($image_prompt_text_trimmed) && !empty($venice_api_key)) {
        $image_result = aiccgen_google_generate_venice_image($venice_api_key, $image_prompt_text_trimmed);
        if ($image_result['success'] && isset($image_result['attachment_id'])) {
            $generated_image_id = $image_result['attachment_id'];
            $image_generation_status_msg .= ' ' . __('Image generated successfully (Landscape 3:2).', 'ai-cat-content-gen-google');
        } else {
            // Log image generation failure but proceed without image
            $image_error = esc_html($image_result['error'] ?? __('Unknown error', 'ai-cat-content-gen-google'));
            $image_generation_status_msg .= ' ' . sprintf(__('(Image generation failed: %s)', 'ai-cat-content-gen-google'), $image_error);
            error_log("[AI Cat Gen Helper/$context] Cat ID {$cat_id}: Image generation failed - {$image_error}");
        }
    } elseif (!empty($image_prompt_text_trimmed) && empty($venice_api_key)) {
        $image_generation_status_msg .= ' ' . __('(Image generation skipped: Venice AI API key missing)', 'ai-cat-content-gen-google');
         error_log("[AI Cat Gen Helper/$context] Cat ID {$cat_id}: Image generation skipped - Venice API key missing.");
    }
    // No message if image prompt was empty

    // Step 4: Create Draft Post (with optional Featured Image)
    $post_title_prefix = ($context === 'Scheduled') ? __('Scheduled Draft:', 'ai-cat-content-gen-google') : __('Draft:', 'ai-cat-content-gen-google');
    $post_title = sprintf('%s %s - %s', $post_title_prefix, $category_name, date_i18n(get_option('date_format')));
    // Pass the generated image ID (null if failed or not requested)
    $create_result = aiccgen_google_create_draft_post($cat_id, $post_title, $content, $generated_image_id);

     if ($create_result['success']) {
        $edit_link = get_edit_post_link($create_result['post_id'], 'raw');
        $edit_link_html = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($edit_link), __('Edit Draft', 'ai-cat-content-gen-google'));
        // Append image status message to the main success message
        $success_message = $notice_prefix . sprintf(__('Draft created successfully. %s', 'ai-cat-content-gen-google'), $edit_link_html) . esc_html($image_generation_status_msg);

        return [
            'success' => true,
            'notice' => ['type' => 'success', 'message' => $success_message]
        ];
    } else {
        $error_msg = esc_html($create_result['error']);
        // If post creation failed, we should maybe delete the generated image?
        if ($generated_image_id) {
            wp_delete_attachment($generated_image_id, true); // Force delete image if post creation fails
            error_log("[AI Cat Gen Helper/$context] Cat ID {$cat_id}: Deleted generated image (ID: {$generated_image_id}) because post creation failed.");
        }
        return [
            'success' => false,
            'notice' => ['type' => 'error', 'message' => $notice_prefix . sprintf(__('Post creation FAILED - %s', 'ai-cat-content-gen-google'), $error_msg)]
        ];
    }
}

// --- Reschedule all tasks based on current settings ---
function aiccgen_google_reschedule_all_tasks() {
    $options = get_option(AICCG_GOOGLE_OPTION_NAME);
    $prompts = isset($options['prompts']) ? $options['prompts'] : [];
    $frequencies = isset($options['frequency']) ? $options['frequency'] : [];

    // Clear ALL existing hooks first more reliably
    $timestamp = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK);
    while($timestamp) {
        $hook_args = get_scheduled_event(AICCG_GOOGLE_CRON_HOOK, [], $timestamp);
        if ($hook_args) {
            wp_unschedule_event($timestamp, AICCG_GOOGLE_CRON_HOOK, $hook_args->args);
        } else {
             // Fallback, try unscheduling without args if getting the event failed
            wp_unschedule_event($timestamp, AICCG_GOOGLE_CRON_HOOK);
        }
        // Crucially, check for the *next* scheduled event after potentially unscheduling one
        $timestamp = wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK);
    }

    if (empty($prompts) || !is_array($prompts)) {
        return; // No prompts configured, nothing to schedule
    }

    foreach ($prompts as $cat_id => $prompt_text) {
        if (empty(trim($prompt_text))) continue; // Skip if content prompt is empty

        $cat_id = absint($cat_id);
        $frequency = isset($frequencies[$cat_id]) ? $frequencies[$cat_id] : 'none';
        $args = ['category_id' => $cat_id];

        // Only schedule if frequency is valid for scheduling (not 'none')
        if ($frequency !== 'none') {
             if (!wp_next_scheduled(AICCG_GOOGLE_CRON_HOOK, $args)) {
                 $first_run_time = time() + 60; // Add a small delay
                 wp_schedule_event($first_run_time, $frequency, AICCG_GOOGLE_CRON_HOOK, $args);
                 // Optional: Log successful scheduling
                 // error_log("[AI Cat Gen Activation/Reschedule] Scheduled task ({$frequency}) for Cat ID {$cat_id}.");
             }
        }
    }
}


// --- Instruction for the AI generation---
function aiccgen_google_build_api_prompt($category_name, $user_prompt, $formatting_instructions = '') {
    $current_date = date_i18n(get_option('date_format'));

    // Prepare the formatting instructions part conditionally
    $instructions_part = '';
    $formatting_instructions_trimmed = trim($formatting_instructions);
    if (!empty($formatting_instructions_trimmed)) {
        // No complex pre-processing needed if we instruct the AI to interpret rules as HTML directly.
        // We just pass the user's instructions as they are.
        // The key is in how we frame these instructions to the AI.

        $instructions_part = sprintf(
            "**IMPORTANT Formatting & Content Rules (Apply STRICTLY to the generated content using HTML tags as described in these rules):**\n" .
            "The following rules describe how the content should be structured and formatted using HTML. Interpret these rules literally to generate the correct HTML output.\n\n" .
            "---BEGIN USER-DEFINED HTML RULES---\n" .
            "%s\n" . // User's raw formatting instructions
            "---END USER-DEFINED HTML RULES---\n\n" .
            "For example, if a rule says 'Wrap all human names in <strong> tags', you MUST output '<strong>John Doe</strong>' and NOT '**John Doe**' or 'John Doe'.\n" .
            "If a rule says 'Create an unordered list for key points using <ul> and <li> tags', you MUST generate that HTML structure.\n\n",
            $formatting_instructions_trimmed // Pass the user's instructions directly
        );
    }

    // Construct the final prompt including the rules
    return sprintf(
        "You are an expert HTML content generator. Generate a structured news/content summary for a WordPress blog category named '%s'.\n" .
        "Base the content on the user's request below, enhanced with relevant, factual information from recent web searches (information current up to %s).\n\n" .
        // --- START: Insert the Formatting Rules ---
        "%s" . // This is where $instructions_part (with the user's rules) will go.
        // --- END: Insert the Formatting Rules ---
        "**General Output Requirements (Adhere to these IN ADDITION to the User-Defined HTML Rules above):**\n" .
        "1.  **Valid HTML:** The entire output MUST be valid HTML. All text content should be appropriately wrapped in HTML tags (e.g., `<p>` for paragraphs, heading tags like `<h2>`, `<h3>` for sections, `<ul>/<li>` for lists, `<strong>/<em>` for emphasis, etc., as per the User-Defined HTML Rules or standard web content practices if not specified).\n" .
        "2.  **Paragraphs:** Wrap all standard text paragraphs in `<p>` and `</p>` tags.\n" .
        "3.  **Headings:** Use HTML heading tags (e.g., `<h2>`, `<h3>`) for section titles as indicated by the User-Defined HTML Rules. If no specific heading level is mentioned for a section in the rules, use `<h2>` for main sections and `<h3>` for sub-sections.\n" .
        "4.  **Lists:** If the User-Defined HTML Rules specify creating lists (e.g., 'use `<ul>` and `<li>` for bullet points'), generate the correct HTML list structure.\n" .
        "5.  **Emphasis/Styling:** Strictly follow any User-Defined HTML Rules for bolding, italics, underlining, or other styling. For instance, if a rule specifies using `<strong>` for bold, you must use `<strong>text</strong>` and NOT Markdown `**text**` or other equivalents.\n" .
        "6.  **Avoid Markdown:** Do NOT use Markdown syntax (like `## Heading`, `**bold**`, `*italic*`, `- list item`) in the final HTML output unless a User-Defined HTML Rule explicitly asks for Markdown to be embedded as text (which is rare).\n" .
        "7.  **General Recommendations Section:** After the main content, include a 'General Recommendations' section. This section should also be valid HTML (e.g., `<h3>General Recommendations</h3><p>For more information, visit...</p>`). Recommend 1-3 reputable websites.\n\n" .
        "**User Request (for content topic):**\n---\n%s\n---",

        esc_html($category_name),
        esc_html($current_date),
        $instructions_part, // User-defined HTML rules and instructions on how to interpret them
        $user_prompt // Assumed already sanitized
    );
}

function aiccgen_google_build_refinement_prompt($original_content, $refinement_instructions) {
    // Same as before
    return sprintf(
        "You are assisting a user in refining text content for a blog.\n" .
        "Below is the original text, which may have been generated using web search grounding. Please refine it based *only* on the user's refinement request.\n" .
        "Try to maintain the original structure (headings, citations/links provided by grounding, etc.) unless the request specifically asks to change it.\n\n" .
        "**Original Text:**\n---\n%s\n---\n\n" .
        "**User's Refinement Request:**\n---\n%s\n---",
        $original_content, // Assumed already sanitized/appropriate
        $refinement_instructions // Assumed already sanitized
    );
}

// Gemini API Function
function aiccgen_google_call_gemini_api($api_key, $model, $prompt) {
    // Same as before
    if (empty($api_key) || empty($model) || empty($prompt)) {
        return ['success' => false, 'content' => null, 'error' => __('Missing Google API key, model, or prompt.', 'ai-cat-content-gen-google'), 'code' => 400];
    }

    $api_url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-exp-03-25:generateContent?key=%s', esc_attr($api_key));

    $request_body_array = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.6,
            // 'maxOutputTokens' => 2048, // Consider if needed
        ],
         'safetySettings' => [
             ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
             ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
             ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
             ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
         ],
        //  'tools' => [ 
        //      [
        //          // Use googleSearchRetrieval for grounding with Google Search
        //          'googleSearchRetrieval' => new \stdClass()
        //      ]
        //  ]
    ];

    $request_body = wp_json_encode($request_body_array); // Use wp_json_encode
    if ($request_body === false) {
         return ['success' => false, 'content' => null, 'error' => __('Internal error preparing API request (JSON).', 'ai-cat-content-gen-google') . ' Error: ' . json_last_error_msg(), 'code' => 500];
    }

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $request_body,
        'method' => 'POST',
        'data_format' => 'body',
        'timeout' => 180, // Consider increasing if needed
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return ['success' => false, 'content' => null, 'error' => __('Error communicating with Google AI service: ', 'ai-cat-content-gen-google') . $error_message, 'code' => 500];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    // Check for API-level errors first
    if (isset($data['error'])) {
        $api_error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown Google API error structure.', 'ai-cat-content-gen-google');
        return ['success' => false, 'content' => null, 'error' => 'Google API Error: ' . $api_error_message, 'code' => $response_code];
    }

    // Check for specific content blocking reasons before assuming success
     if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
         $finish_reason = $data['candidates'][0]['finishReason'];
         $error_msg = __('AI response did not complete successfully.', 'ai-cat-content-gen-google');
         if ($finish_reason === 'SAFETY') {
             $error_msg = __('Google AI response blocked due to safety settings.', 'ai-cat-content-gen-google');
         } elseif ($finish_reason === 'RECITATION') {
             $error_msg = __('Google AI response blocked due to potential recitation issues.', 'ai-cat-content-gen-google');
         } elseif ($finish_reason === 'MAX_TOKENS') {
              $error_msg = __('Google AI response incomplete: Maximum output length reached.', 'ai-cat-content-gen-google');
         } else {
             $error_msg = sprintf(__('Google AI response ended unexpectedly (Reason: %s).', 'ai-cat-content-gen-google'), $finish_reason);
         }
        return ['success' => false, 'content' => null, 'error' => $error_msg, 'code' => ($finish_reason === 'SAFETY' || $finish_reason === 'RECITATION') ? 400 : 500];
    }

    // Check if the expected text part exists
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
         $error_msg = __('Google AI response structure invalid or missing content.', 'ai-cat-content-gen-google');
         // Log the raw response body for debugging if this happens
         error_log("[AI Cat Gen API Error] Invalid Gemini response structure: " . $response_body);
         return ['success' => false, 'content' => null, 'error' => $error_msg, 'code' => 500];
    }

    // Check response code *after* checking for specific error structures
    if ($response_code < 200 || $response_code >= 300) {
        return ['success' => false, 'content' => null, 'error' => __('Unexpected response status from Google AI service.', 'ai-cat-content-gen-google') . ' (Code: ' . $response_code . ')', 'code' => $response_code];
    }


    $generated_content = $data['candidates'][0]['content']['parts'][0]['text'];
    return ['success' => true, 'content' => $generated_content, 'error' => null, 'code' => $response_code];
}

// --- function for Venice AI Image Generation ---
function aiccgen_google_generate_venice_image($api_key, $prompt) {
    if (empty($api_key) || empty($prompt)) {
        return ['success' => false, 'attachment_id' => null, 'error' => __('Missing Venice AI API key or image prompt.', 'ai-cat-content-gen-google')];
    }

    $api_url = 'https://api.venice.ai/api/v1/image/generate';
    // Consider making model, size, etc., configurable in the future
    $data = [
        'model' => 'flux-dev', // Check if this is still the desired model
        'prompt' => $prompt,
        'height' => 848,
        'width' => 1264,
        'steps' => 30,
        'cfg_scale' => 7.5,
        'return_binary' => false, // Request base64 encoded string
        'hide_watermark' => true,
        'format' => 'png'
    ];

    $request_body = wp_json_encode($data);
    if ($request_body === false) {
        return ['success' => false, 'attachment_id' => null, 'error' => __('Internal error preparing Venice API request (JSON).', 'ai-cat-content-gen-google') . ' Error: ' . json_last_error_msg()];
    }

    // Use wp_remote_post for consistency and better error handling
    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body' => $request_body,
        'method' => 'POST',
        'data_format' => 'body',
        'timeout' => 120, // Image generation can take time
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return ['success' => false, 'attachment_id' => null, 'error' => __('Error communicating with Venice AI service: ', 'ai-cat-content-gen-google') . $error_message];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);

    // Check for Venice API specific errors (structure may vary)
    if ($response_code != 200 || !$result || isset($result['error']) || !isset($result['images'][0])) {
        $api_error_message = __('Unknown Venice API error.', 'ai-cat-content-gen-google');
        if (isset($result['error']['message'])) {
             $api_error_message = $result['error']['message'];
        } elseif (isset($result['detail'])) { // Some APIs use 'detail' for errors
            $api_error_message = is_string($result['detail']) ? $result['detail'] : wp_json_encode($result['detail']);
        } elseif ($response_code != 200) {
            $api_error_message = sprintf(__('API returned status %d.', 'ai-cat-content-gen-google'), $response_code);
        }
         // Log the full response body for debugging Venice errors
         error_log("[AI Cat Gen Venice Error] Response Code: {$response_code}, Body: " . $response_body);
        return ['success' => false, 'attachment_id' => null, 'error' => 'Venice AI Error: ' . $api_error_message];
    }

    $base64_image = $result['images'][0];
    $image_data = base64_decode($base64_image);

    if ($image_data === false) {
         return ['success' => false, 'attachment_id' => null, 'error' => __('Failed to decode base64 image data from Venice AI.', 'ai-cat-content-gen-google')];
    }

    // Save image to WordPress uploads
    $upload_dir = wp_upload_dir();
    $safe_prompt_prefix = substr(sanitize_title(substr($prompt, 0, 50)), 0, 40); // Limit length
    $unique_filename = 'aiccgen-' . $safe_prompt_prefix . '-' . time() . '.png';
    $image_path = $upload_dir['path'] . '/' . $unique_filename;

    // Ensure the uploads directory is writable
    if (!wp_is_writable($upload_dir['path'])) {
        return ['success' => false, 'attachment_id' => null, 'error' => sprintf(__('Uploads directory is not writable: %s', 'ai-cat-content-gen-google'), $upload_dir['path'])];
    }

    $file_saved = file_put_contents($image_path, $image_data);
    if ($file_saved === false) {
         return ['success' => false, 'attachment_id' => null, 'error' => __('Failed to save generated image to disk.', 'ai-cat-content-gen-google')];
    }

    // Create WordPress attachment
    $filetype = wp_check_filetype(basename($image_path), null);
    $attachment = [
        'guid'           => $upload_dir['url'] . '/' . basename($image_path),
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($unique_filename), // Use the filename as title initially
        'post_content'   => '', // Can add prompt here if desired: 'Generated from prompt: ' . esc_html($prompt),
        'post_status'    => 'inherit'
    ];

    $attachment_id = wp_insert_attachment($attachment, $image_path);

    if (is_wp_error($attachment_id)) {
        aiccgen_google_delete_file($image_path); // Clean up saved file if attachment fails // <-- CORRECTED CALL
        return ['success' => false, 'attachment_id' => null, 'error' => __('Failed to create WordPress attachment: ', 'ai-cat-content-gen-google') . $attachment_id->get_error_message()];
    }

    // Generate attachment metadata (thumbnails etc.)
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $image_path);
    if (is_wp_error($attachment_metadata) || empty($attachment_metadata)) {
        // Attachment exists, but metadata failed. Not fatal, but log it.
         error_log("[AI Cat Gen Venice Warning] Failed to generate attachment metadata for ID {$attachment_id}. Error: " . ($is_wp_error($attachment_metadata) ? $attachment_metadata->get_error_message() : 'Empty metadata'));
    } else {
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
    }


    // Return success with the attachment ID
    return ['success' => true, 'attachment_id' => $attachment_id, 'error' => null];
}


// --- Updated Helper function to create draft post (accepts featured image ID) ---
function aiccgen_google_create_draft_post($category_id, $post_title, $post_content, $featured_image_id = null) {
     $admin_users = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
     $post_author_id = (!empty($admin_users)) ? $admin_users[0]->ID : get_current_user_id(); // Fallback to current user if no admin found
     if (!$post_author_id) $post_author_id = 1; // Absolute fallback

     if (empty($post_title) || empty($post_content) || $category_id <= 0) {
         return ['success' => false, 'post_id' => null, 'error' => __('Missing title, content, or category ID for post creation.', 'ai-cat-content-gen-google')];
     }
      if (!term_exists($category_id, 'category')) {
           return ['success' => false, 'post_id' => null, 'error' => sprintf(__('Category ID %d does not exist.', 'ai-cat-content-gen-google'), $category_id)];
      }

    $post_data = [
        'post_title'    => wp_strip_all_tags($post_title),
        'post_content'  => wp_kses_post($post_content), // Use KSES for safety
        'post_status'   => 'draft',
        'post_author'   => $post_author_id,
        'post_category' => [$category_id],
        'post_type'     => 'post',
    ];

    // Temporarily remove kses filters for insertion if they interfere
    $kses_filters_removed = false;
    if (has_filter('content_save_pre', 'wp_filter_post_kses')) {
        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
        $kses_filters_removed = true;
    }

    $post_id = wp_insert_post($post_data, true); // Pass true to enable WP_Error return

    // Add kses filters back if they were removed
    if ($kses_filters_removed) {
        add_filter('content_save_pre', 'wp_filter_post_kses');
        add_filter('content_filtered_save_pre', 'wp_filter_post_kses');
    }


    if (is_wp_error($post_id)) {
        $error_message = $post_id->get_error_message();
        return ['success' => false, 'post_id' => null, 'error' => __('WordPress error creating post: ', 'ai-cat-content-gen-google') . $error_message];
    } elseif ($post_id === 0) {
        return ['success' => false, 'post_id' => null, 'error' => __('Failed to create post draft (wp_insert_post returned 0).', 'ai-cat-content-gen-google')];
    } else {
        // Post created successfully, now try setting the featured image
        if ($featured_image_id && is_numeric($featured_image_id) && $featured_image_id > 0) {
            // Check if the attachment ID is valid
             if (wp_get_attachment_url($featured_image_id)) {
                set_post_thumbnail($post_id, absint($featured_image_id));
            } else {
                 
                 error_log("[AI Cat Gen Post Create] Warning: Attempted to set invalid featured image ID ({$featured_image_id}) for Post ID {$post_id}.");
            }
        }
        return ['success' => true, 'post_id' => $post_id, 'error' => null];
    }
}

//  Admin Notices for Feedback to plugin settings
function aiccgen_google_show_save_notices() {
    $screen = get_current_screen();
    // Ensure this runs only on our specific settings page
    if (!$screen || !isset($screen->id) || $screen->id !== 'settings_page_' . AICCG_GOOGLE_SETTINGS_SLUG) {
        return;
    }

    $results = get_transient(AICCG_GOOGLE_SETTINGS_NOTICE_TRANSIENT);

    if ($results && is_array($results)) {
        $notice_html = '';
        $overall_type = 'info'; // Default type

        // Determine overall status based on schedule actions
        $has_schedule_updates = isset($results['schedule_updates']) && $results['schedule_updates'] > 0;
        $has_schedule_cleared = isset($results['schedule_cleared']) && $results['schedule_cleared'] > 0;
        $has_errors = false;
        if (!empty($results['details'])) {
             foreach ($results['details'] as $detail) {
                if(isset($detail['type']) && ($detail['type'] === 'error' || $detail['type'] === 'warning')) {
                    $has_errors = true;
                    break;
                }
             }
        }

        if ($has_errors) {
            $overall_type = 'warning'; // Use warning if there were schedule errors/warnings
        } elseif ($has_schedule_updates || $has_schedule_cleared) {
            $overall_type = 'success'; // Success if actions occurred without errors
        } else {
             $overall_type = 'info'; // Info if just saved with no changes triggering actions
        }

        // Build Summary Message
        $summary_parts = [];
        if ($has_schedule_updates || $has_schedule_cleared) {
             $schedule_summary = sprintf(__('%d schedule(s) updated/set, %d schedule(s) cleared.', 'ai-cat-content-gen-google'),
                isset($results['schedule_updates']) ? $results['schedule_updates'] : 0,
                isset($results['schedule_cleared']) ? $results['schedule_cleared'] : 0
            );
             $summary_parts[] = $schedule_summary;
         }

        if (!empty($summary_parts)) {
            $notice_html .= '<p><strong>' . implode(' ', $summary_parts) . '</strong></p>';
        } else {
             $notice_html .= '<p><strong>' . __('Settings saved. No schedule changes detected.', 'ai-cat-content-gen-google') . '</strong></p>'; // More informative default
        }

        // Add Details List (Scrollable)
        if (!empty($results['details']) && is_array($results['details'])) {
             $notice_html .= '<ul style="margin-top: 5px; list-style: disc; margin-left: 20px; max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 5px;">';
             foreach ($results['details'] as $detail) {
                 if (!is_array($detail) || !isset($detail['message'])) continue;

                 $detail_type = isset($detail['type']) ? $detail['type'] : 'info';
                 $color = '#333'; // Default color
                 $icon = ' інформация;'; // Info icon

                 if ($detail_type === 'success') {$color = '#28a745'; $icon = '✔'; }
                 if ($detail_type === 'warning') {$color = '#ffc107'; $icon = '⚠'; }
                 if ($detail_type === 'error')   {$color = '#dc3545'; $icon = '✘'; }
                 if ($detail_type === 'info')    {$color = '#17a2b8'; $icon = 'ℹ'; }

                 // Use wp_kses_post carefully, ensure messages don't have harmful HTML
                 $notice_html .= '<li style="margin-bottom: 3px; color: ' . esc_attr($color) . ';"><span style="margin-right: 5px;">' . $icon . '</span>' . wp_kses_post($detail['message']) . '</li>';
             }
             $notice_html .= '</ul>';
        }

        // Output the notice div
        printf(
            '<div id="setting-error-settings_updated" class="notice notice-%s is-dismissible settings-error"><div style="padding: 10px 0;">%s</div></div>',
            esc_attr($overall_type), // Use determined overall type
            $notice_html // Contains KSESed content and escaped attributes
        );

        // Delete the transient so it doesn't show again
        delete_transient(AICCG_GOOGLE_SETTINGS_NOTICE_TRANSIENT);
    }
}
add_action('admin_notices', 'aiccgen_google_show_save_notices');

function aiccgen_google_delete_file( $file ) {
    // Sanitize the file path? Might be tricky with WP filesystem abstractions.
    // Basic check: ensure it's within the uploads dir?
    $upload_dir = wp_upload_dir();
    if ( strpos( realpath( $file ), realpath( $upload_dir['basedir'] ) ) !== 0 ) {
        // File is not inside the uploads directory, bail out for safety.
        error_log( "[AI Cat Gen Security] Attempted to delete file outside uploads directory: " . $file );
        return false;
    }

    // Use WP Filesystem API if possible for better compatibility/safety
    global $wp_filesystem;
    if ( empty( $wp_filesystem ) ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if ( $wp_filesystem ) {
        return $wp_filesystem->delete( $file );
    } else {
        // Fallback to PHP unlink if WP_Filesystem fails to initialize
        if ( file_exists( $file ) ) {
            return unlink( $file );
        }
    }
    return false;
}

?>