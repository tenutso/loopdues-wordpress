<?php
// Removes the plugin's settings. Member users, their LoopDues details and page access rules are left in place.
defined('WP_UNINSTALL_PLUGIN') || exit;

foreach (['loopdues_key', 'loopdues_org', 'loopdues_login_label', 'loopdues_teaser', 'loopdues_join_url', 'loopdues_hide_admin_bar'] as $option) {
    delete_option($option);
}
wp_clear_scheduled_hook('loopdues_daily');
