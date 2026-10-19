<?php
/**
 * The connection to one LoopDues organization: the key pasted in Settings → LoopDues, and signed calls to LoopDues.
 *
 * Key format: ldwp_{subdomain}_{site id}_{secret}. The secret signs everything in both directions: sign-in tokens
 * and webhooks from LoopDues, and the plugin's own calls to LoopDues.
 */

defined('ABSPATH') || exit;

/** @return array{subdomain: string, site: int, secret: string}|null */
function loopdues_connection()
{
    $key = trim((string) get_option('loopdues_key', ''));
    $parts = explode('_', $key, 4);
    if (count($parts) !== 4 || $parts[0] !== 'ldwp' || ! preg_match('/^[a-z0-9-]+$/', $parts[1]) || ! ctype_digit($parts[2]) || strlen($parts[3]) < 20) {
        return null;
    }

    return ['subdomain' => $parts[1], 'site' => (int) $parts[2], 'secret' => $parts[3]];
}

/** https://{subdomain}.loopdues.com/{path} */
function loopdues_url($path = '')
{
    $c = loopdues_connection();
    if (! $c) {
        return '';
    }
    $domain = apply_filters('loopdues_domain', 'loopdues.com');

    return 'https://'.$c['subdomain'].'.'.$domain.'/'.ltrim($path, '/');
}

function loopdues_sign($data, $secret)
{
    return hash_hmac('sha256', $data, $secret);
}

function loopdues_b64url_decode($s)
{
    return base64_decode(strtr($s, '-_', '+/'), true);
}

/**
 * Ask LoopDues who we are connected to: organization name and its tiers (used for "members in these tiers" choices).
 * Saved in the loopdues_org option. Returns the organization array, or a WP_Error.
 */
function loopdues_refresh_org()
{
    $c = loopdues_connection();
    if (! $c) {
        return new WP_Error('loopdues_key', __('Paste the connection key from LoopDues first.', 'loopdues'));
    }

    $t = time();
    $response = wp_remote_get(add_query_arg('site', $c['site'], loopdues_url('wp/ping')), [
        'timeout' => 10,
        'headers' => ['LoopDues-Signature' => 't='.$t.',v1='.loopdues_sign($t.'.ping.'.$c['site'], $c['secret'])],
    ]);
    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code !== 200 || ! is_array($body) || empty($body['organization'])) {
        $message = is_array($body) && ! empty($body['message']) ? $body['message'] : sprintf(__('LoopDues answered with HTTP %d.', 'loopdues'), $code);

        return new WP_Error('loopdues_ping', $message);
    }

    $org = [
        'name' => sanitize_text_field($body['organization']['name'] ?? ''),
        'portal_url' => esc_url_raw($body['organization']['portal_url'] ?? loopdues_url('portal')),
        'tiers' => array_map(fn ($t) => ['id' => (int) $t['id'], 'name' => sanitize_text_field($t['name'])], $body['tiers'] ?? []),
        'checked_at' => time(),
    ];
    update_option('loopdues_org', $org, false);

    return $org;
}

function loopdues_org()
{
    $org = get_option('loopdues_org');

    return is_array($org) ? $org : ['name' => '', 'portal_url' => loopdues_url('portal'), 'tiers' => [], 'checked_at' => 0];
}

// Keep the tier list fresh (tiers renamed or added in LoopDues).
add_action('loopdues_daily', 'loopdues_refresh_org');
add_action('init', function () {
    if (! wp_next_scheduled('loopdues_daily')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'loopdues_daily');
    }
});
register_deactivation_hook(LOOPDUES_DIR.'loopdues.php', function () {
    wp_clear_scheduled_hook('loopdues_daily');
});
