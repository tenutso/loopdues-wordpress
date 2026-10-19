<?php
/**
 * Plugin Name:       LoopDues
 * Plugin URI:        https://github.com/tenutso/loopdues-wordpress
 * Description:       Join form, member login and members-only content for organizations that run their memberships on LoopDues.
 * Version:           1.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Tentuso
 * Author URI:        https://github.com/tenutso
 * License:           GPL-2.0-or-later
 * Text Domain:       loopdues
 * Update URI:        false
 */

defined('ABSPATH') || exit;

define('LOOPDUES_VERSION', '1.0.1');
define('LOOPDUES_DIR', plugin_dir_path(__FILE__));
define('LOOPDUES_URL', plugin_dir_url(__FILE__));

/** Role given to WordPress users who sign in with LoopDues. What they can see is decided by their membership status, not the role. */
const LOOPDUES_ROLE = 'oao_member';

/** LoopDues statuses that count as a current member (grace = renewal payment overdue but not lapsed yet). */
const LOOPDUES_CURRENT_STATUSES = ['active', 'grace_period'];

require_once LOOPDUES_DIR.'includes/connection.php';
require_once LOOPDUES_DIR.'includes/members.php';
require_once LOOPDUES_DIR.'includes/sso.php';
require_once LOOPDUES_DIR.'includes/webhook.php';
require_once LOOPDUES_DIR.'includes/content.php';
require_once LOOPDUES_DIR.'includes/settings.php';

register_activation_hook(__FILE__, function () {
    add_role(LOOPDUES_ROLE, __('Member (LoopDues)', 'loopdues'), ['read' => true]);
});

// Also on load, in case the plugin was updated in place without being reactivated.
add_action('init', function () {
    if (! get_role(LOOPDUES_ROLE)) {
        add_role(LOOPDUES_ROLE, __('Member (LoopDues)', 'loopdues'), ['read' => true]);
    }
});
