<?php
add_action('aiccgen_google_cron_hook', 'aiccgen_google_run_cron');

function aiccgen_google_run_cron() {
    aiccgen_google_generate_content();
}
