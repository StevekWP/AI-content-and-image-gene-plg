<?php
//  Load Text Domain 
function aiccgen_google_load_textdomain()
{
    load_plugin_textdomain('ai-cat-content-gen-google', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'aiccgen_google_load_textdomain');

//  Activation / Deactivation Hooks 