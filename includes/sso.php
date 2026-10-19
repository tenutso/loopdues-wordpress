<?php
/**
 * "Sign in with LoopDues".
 *
 *   1. /?loopdues_login=1&redirect_to=… sets a one-time state cookie and sends the visitor to
 *      https://{org}.loopdues.com/wp/sso?site=…&state=…&return=…  (they sign in there if they aren't already).
 *   2. LoopDues sends them back to /?loopdues_sso=1&ld_token={payload}.{signature}: base64url JSON signed with
 *      HMAC-SHA256 and the connection secret, valid for two minutes, carrying the state from step 1.
 *   3. We check it, find or create the WordPress user, copy the member record and log them in.
 */

defined('ABSPATH') || exit;

const LOOPDUES_STATE_COOKIE = 'loopdues_sso_state';

function loopdues_login_url($redirectTo = '')
{
    $redirectTo = $redirectTo ?: (is_singular() ? get_permalink() : home_url(add_query_arg([])));

    return add_query_arg(['loopdues_login' => '1', 'redirect_to' => rawurlencode($redirectTo)], home_url('/'));
}

function loopdues_sso_fail($message)
{
    wp_die(
        '<p>'.esc_html($message).'</p><p><a href="'.esc_url(home_url('/')).'">'.esc_html__('Back to the site', 'loopdues').'</a></p>',
        esc_html__('Sign-in did not work', 'loopdues'),
        ['response' => 400]
    );
}

function loopdues_set_cookie($name, $value, $expires)
{
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => COOKIEPATH ?: '/',
        'domain' => COOKIE_DOMAIN ?: '',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax', // LoopDues sends the visitor back with a top-level GET, which Lax cookies survive
    ]);
}

add_action('init', function () {
    if (isset($_GET['loopdues_login'])) {
        loopdues_start_sso();
    } elseif (isset($_GET['loopdues_sso'])) {
        loopdues_finish_sso();
    }
}, 1);

function loopdues_start_sso()
{
    $c = loopdues_connection();
    if (! $c) {
        loopdues_sso_fail(__('Member sign-in is not set up on this site yet.', 'loopdues'));
    }

    $redirect = wp_validate_redirect(isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : '', home_url('/'));
    if (is_user_logged_in() && loopdues_is_linked_member()) {
        wp_safe_redirect($redirect);
        exit;
    }

    $state = wp_generate_password(32, false);
    // The cookie carries the state and where to go afterwards; it's checked (and cleared) when they come back.
    loopdues_set_cookie(LOOPDUES_STATE_COOKIE, $state.'|'.base64_encode($redirect), time() + 15 * MINUTE_IN_SECONDS);

    $url = add_query_arg([
        'site' => $c['site'],
        'state' => $state,
        'return' => rawurlencode(add_query_arg('loopdues_sso', '1', home_url('/'))),
    ], loopdues_url('wp/sso'));

    wp_redirect($url); // LoopDues is another host, so not wp_safe_redirect
    exit;
}

function loopdues_finish_sso()
{
    $c = loopdues_connection();
    if (! $c) {
        loopdues_sso_fail(__('Member sign-in is not set up on this site yet.', 'loopdues'));
    }

    [$state, $redirect] = array_pad(explode('|', (string) ($_COOKIE[LOOPDUES_STATE_COOKIE] ?? ''), 2), 2, '');
    $redirect = wp_validate_redirect((string) base64_decode($redirect), home_url('/'));
    loopdues_set_cookie(LOOPDUES_STATE_COOKIE, '', time() - YEAR_IN_SECONDS);

    $token = isset($_GET['ld_token']) ? sanitize_text_field(wp_unslash($_GET['ld_token'])) : '';
    $claims = loopdues_verify_token($token, $c);
    if (is_wp_error($claims)) {
        loopdues_sso_fail($claims->get_error_message());
    }
    if ($state === '' || ! hash_equals($state, (string) ($claims['state'] ?? ''))) {
        loopdues_sso_fail(__('This sign-in link has expired or was opened in another browser. Please try again.', 'loopdues'));
    }
    // Each token works once (it lives two minutes; remembered for five).
    $used = 'loopdues_used_'.substr(strrchr($token, '.'), 1, 40);
    if (get_transient($used)) {
        loopdues_sso_fail(__('This sign-in link has already been used. Please try again.', 'loopdues'));
    }
    set_transient($used, 1, 5 * MINUTE_IN_SECONDS);

    $user = loopdues_user_for_member($claims['member']);
    if (is_wp_error($user)) {
        loopdues_sso_fail($user->get_error_message());
    }

    loopdues_store_member($user->ID, $claims['member'], (int) $claims['iat']);
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    wp_safe_redirect($redirect);
    exit;
}

/** @return array|WP_Error the token's claims */
function loopdues_verify_token($token, array $c)
{
    $parts = explode('.', $token);
    if (count($parts) !== 2 || ! hash_equals(loopdues_sign($parts[0], $c['secret']), $parts[1])) {
        return new WP_Error('loopdues_token', __('The sign-in response could not be verified. Check the connection key in Settings → LoopDues.', 'loopdues'));
    }

    $claims = json_decode((string) loopdues_b64url_decode($parts[0]), true);
    if (! is_array($claims) || (int) ($claims['site'] ?? 0) !== $c['site'] || empty($claims['member']['id'])) {
        return new WP_Error('loopdues_token', __('The sign-in response was not for this site.', 'loopdues'));
    }
    if ((int) ($claims['exp'] ?? 0) < time()) {
        return new WP_Error('loopdues_token', __('This sign-in link has expired. Please try again.', 'loopdues'));
    }

    return $claims;
}

/** The WordPress user for a member: already linked, else an unlinked member-level account with the same email, else a new one. */
function loopdues_user_for_member(array $member)
{
    $user = loopdues_user_by_member_id($member['id']);
    $email = sanitize_email($member['email'] ?? '');

    if (! $user && $email && ($existing = get_user_by('email', $email))) {
        if (loopdues_is_linked_member($existing->ID)) {
            // Linked to a different member (e.g. two LoopDues members once shared this email): don't take it over.
            return new WP_Error('loopdues_taken', __('Another member is already linked to this email address on this site. Please contact the site administrator.', 'loopdues'));
        }
        $user = $existing;
    }

    if ($user) {
        if (! loopdues_user_is_linkable($user)) {
            return new WP_Error('loopdues_staff', __('This email address belongs to a staff account on this site. Please sign in with your WordPress username and password instead.', 'loopdues'));
        }
        if (in_array('subscriber', (array) $user->roles, true) || ! $user->roles) {
            $user->set_role(LOOPDUES_ROLE);
        }

        return $user;
    }

    if (! $email) {
        return new WP_Error('loopdues_email', __('Your membership record has no email address. Please contact the organization.', 'loopdues'));
    }

    $id = wp_insert_user([
        'user_login' => loopdues_unique_login($email),
        'user_email' => $email,
        'user_pass' => wp_generate_password(32, true, true), // never used: they always sign in through LoopDues
        'first_name' => sanitize_text_field($member['first_name'] ?? ''),
        'last_name' => sanitize_text_field($member['last_name'] ?? ''),
        'display_name' => trim(($member['first_name'] ?? '').' '.($member['last_name'] ?? '')) ?: $email,
        'role' => LOOPDUES_ROLE,
    ]);

    return is_wp_error($id) ? $id : get_userdata($id);
}

function loopdues_unique_login($email)
{
    $base = sanitize_user(strtok($email, '@'), true) ?: 'member';
    $login = $base;
    for ($i = 2; username_exists($login); $i++) {
        $login = $base.$i;
    }

    return $login;
}

// "Sign in with LoopDues" on wp-login.php too, for members who end up there.
add_action('login_form', function () {
    if (! loopdues_connection()) {
        return;
    }
    $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : home_url('/');
    printf(
        '<p style="margin:0 0 16px"><a class="button button-large" style="width:100%%;text-align:center" href="%s">%s</a></p>',
        esc_url(loopdues_login_url($redirect)),
        esc_html(get_option('loopdues_login_label') ?: __('Member login', 'loopdues'))
    );
});
