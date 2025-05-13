<?php
//  Constants 
define('AICCG_GOOGLE_OPTION_GROUP', 'aiccgen_google_settings_group');
define('AICCG_GOOGLE_OPTION_NAME', 'aiccgen_google_options');
define('AICCG_GOOGLE_SETTINGS_SLUG', 'ai-content-generator');
define('AICCG_GOOGLE_AJAX_ACTION', 'aiccgen_google_generate_content_ajax'); // For Manual Post Creation
define('AICCG_GOOGLE_AJAX_REFINE_ACTION', 'aiccgen_google_refine_content_ajax'); // For Manual Post Creation
define('AICCG_GOOGLE_NONCE_ACTION', 'aiccgen_google_generate_nonce');
define('AICCG_GOOGLE_AJAX_CREATE_POST_ACTION', 'aiccgen_google_create_post_ajax'); // For Manual Post Creation
define('AICCG_GOOGLE_SETTINGS_NOTICE_TRANSIENT', 'aiccgen_google_save_notice');
define('AICCG_GOOGLE_AJAX_REFINE_DRAFT_ACTION', 'aiccgen_google_refine_latest_draft_ajax');
define('AICCG_GOOGLE_AJAX_REFINE_IMAGE_ACTION', 'aiccgen_google_refine_featured_image');
define('AICCG_GOOGLE_AJAX_APPLY_REFINED_IMAGE_ACTION', 'aiccgen_google_apply_refined_image');

//  Cron Hook Name 
define('AICCG_GOOGLE_CRON_HOOK', 'aiccgen_google_scheduled_generation_event');

//  Load Text Domain 