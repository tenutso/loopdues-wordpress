<?php
/**
 * WordPress users linked to LoopDues members. The member record (status, tier, paid-through date) is copied into
 * user meta at every sign-in and whenever LoopDues sends a webhook, and access is decided from that copy.
 */

defined('ABSPATH') || exit;

/** Roles a sign-in may log into. Anything more (author, editor, admin…) keeps using its WordPress password. */
function loopdues_linkable_roles()
{
    return apply_filters('loopdues_linkable_roles', ['subscriber', LOOPDUES_ROLE]);
}

function loopdues_user_is_linkable(WP_User $user)
{
    return ! array_diff((array) $user->roles, loopdues_linkable_roles());
}

function loopdues_user_by_member_id($memberId)
{
    $users = get_users(['meta_key' => 'loopdues_member_id', 'meta_value' => (int) $memberId, 'number' => 1, 'count_total' => false]);

    return $users[0] ?? null;
}

/**
 * Store a LoopDues member record on a user. $at is when LoopDues produced the record: older records (a webhook
 * retried after a newer sign-in, say) are ignored so a stale copy never overwrites a fresh one.
 */
function loopdues_store_member($userId, array $member, $at)
{
    $previous = (int) get_user_meta($userId, 'loopdues_synced_at', true);
    if ($previous && $at < $previous) {
        return false;
    }

    update_user_meta($userId, 'loopdues_member_id', (int) $member['id']);
    update_user_meta($userId, 'loopdues_status', sanitize_key($member['status'] ?? ''));
    update_user_meta($userId, 'loopdues_tier_id', (int) ($member['tier']['id'] ?? 0));
    update_user_meta($userId, 'loopdues_tier_name', sanitize_text_field($member['tier']['name'] ?? ''));
    update_user_meta($userId, 'loopdues_paid_through', sanitize_text_field($member['paid_through'] ?? ''));
    update_user_meta($userId, 'loopdues_synced_at', (int) $at);

    $names = array_filter([
        'first_name' => sanitize_text_field($member['first_name'] ?? ''),
        'last_name' => sanitize_text_field($member['last_name'] ?? ''),
    ]);
    if ($names) {
        $display = trim(($names['first_name'] ?? '').' '.($names['last_name'] ?? ''));
        wp_update_user(['ID' => $userId] + $names + ($display ? ['display_name' => $display] : []));
    }

    return true;
}

/** The member was erased or merged in LoopDues: the WordPress user stays (with their comments etc.), unlinked. */
function loopdues_unlink_user($userId)
{
    foreach (['loopdues_member_id', 'loopdues_status', 'loopdues_tier_id', 'loopdues_tier_name', 'loopdues_paid_through', 'loopdues_synced_at'] as $key) {
        delete_user_meta($userId, $key);
    }
    $user = get_userdata($userId);
    if ($user && in_array(LOOPDUES_ROLE, (array) $user->roles, true)) {
        $user->set_role('subscriber');
    }
}

/**
 * Whether a user is a current member, optionally limited to some tiers (array of LoopDues tier ids).
 * Defaults to the current user.
 */
function loopdues_is_current_member($userId = null, array $tiers = [])
{
    $userId = $userId ?? get_current_user_id();
    if (! $userId || ! get_user_meta($userId, 'loopdues_member_id', true)) {
        return false;
    }

    $current = in_array(get_user_meta($userId, 'loopdues_status', true), LOOPDUES_CURRENT_STATUSES, true);
    if ($current && $tiers) {
        $current = in_array((int) get_user_meta($userId, 'loopdues_tier_id', true), array_map('intval', $tiers), true);
    }

    return (bool) apply_filters('loopdues_is_current_member', $current, $userId, $tiers);
}

function loopdues_is_linked_member($userId = null)
{
    $userId = $userId ?? get_current_user_id();

    return $userId && get_user_meta($userId, 'loopdues_member_id', true);
}

// Members don't need the WordPress dashboard: no admin bar, and wp-admin sends them back to the site.
function loopdues_member_only_user($user = null)
{
    $user = $user ?: wp_get_current_user();

    return $user && $user->exists() && (array) $user->roles === [LOOPDUES_ROLE];
}

add_filter('show_admin_bar', function ($show) {
    return loopdues_member_only_user() && get_option('loopdues_hide_admin_bar', '1') === '1' ? false : $show;
});

add_action('admin_init', function () {
    if (loopdues_member_only_user() && get_option('loopdues_hide_admin_bar', '1') === '1' && ! wp_doing_ajax()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
});

// Profile column so staff can see who is linked and their standing.
add_filter('manage_users_columns', function ($columns) {
    $columns['loopdues'] = 'LoopDues';

    return $columns;
});
add_filter('manage_users_custom_column', function ($value, $column, $userId) {
    if ($column !== 'loopdues' || ! loopdues_is_linked_member($userId)) {
        return $value;
    }
    $status = get_user_meta($userId, 'loopdues_status', true);
    $tier = get_user_meta($userId, 'loopdues_tier_name', true);
    $through = get_user_meta($userId, 'loopdues_paid_through', true);
    $label = in_array($status, LOOPDUES_CURRENT_STATUSES, true) ? __('Current', 'loopdues') : ucwords(str_replace('_', ' ', $status));

    return esc_html(trim($label.($tier ? ' · '.$tier : '').($through ? ' · '.sprintf(__('to %s', 'loopdues'), $through) : '')));
}, 10, 3);
