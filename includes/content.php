<?php
/**
 * What visitors see: the join form, the member login button, and members-only content (whole pages or parts of one).
 *
 * Shortcodes                                      Blocks
 *   [loopdues_join tiers="3,7" width="full" …]      LoopDues join form
 *   [loopdues_login]                                LoopDues member login
 *   [loopdues_members_only tiers="3"]…[/…]          LoopDues members only (wraps other blocks)
 *
 * Pages and posts also get a "Who can see this" box. Staff who can edit a post always see it.
 */

defined('ABSPATH') || exit;

// ------------------------------------------------------------------ join form (the LoopDues widget)

/** Shortcode/block attributes → the widget's data attributes (same options as Settings → Website signup widget in LoopDues). */
function loopdues_join_html($atts)
{
    if (! loopdues_connection()) {
        return current_user_can('manage_options') ? '<p><em>'.esc_html__('LoopDues: paste the connection key in Settings → LoopDues to show the join form.', 'loopdues').'</em></p>' : '';
    }

    $atts = shortcode_atts(['tier' => '', 'tiers' => '', 'width' => '', 'logo' => '', 'font' => '', 'frame' => '', 'color' => '', 'signin' => ''], $atts, 'loopdues_join');
    $data = ['data-loopdues-widget' => ''];
    if (ctype_digit((string) $atts['tier'])) {
        $data['data-tier'] = $atts['tier'];
    }
    $tiers = implode(',', array_filter(array_map('intval', explode(',', (string) $atts['tiers']))));
    if ($tiers !== '') {
        $data['data-tiers'] = $tiers;
    }
    foreach (['width' => ['full'], 'logo' => ['hide'], 'font' => ['inherit'], 'frame' => ['none'], 'signin' => ['hide']] as $key => $allowed) {
        if (in_array($atts[$key], $allowed, true)) {
            $data['data-'.$key] = $atts[$key];
        }
    }
    if (preg_match('/^#[0-9a-f]{6}$/i', (string) $atts['color'])) {
        $data['data-color'] = strtolower($atts['color']);
    }

    wp_enqueue_script('loopdues-widget', loopdues_url('widget.js'), [], null, ['strategy' => 'async', 'in_footer' => true]);

    $html = '';
    foreach ($data as $name => $value) {
        $html .= $value === '' ? ' '.$name : ' '.$name.'="'.esc_attr($value).'"';
    }

    return '<div'.$html.'></div>';
}
add_shortcode('loopdues_join', 'loopdues_join_html');

// ------------------------------------------------------------------ member login / my account

function loopdues_login_html($atts = [])
{
    if (! loopdues_connection()) {
        return '';
    }
    $atts = shortcode_atts(['label' => ''], $atts, 'loopdues_login');

    if (is_user_logged_in() && loopdues_is_linked_member()) {
        $here = is_singular() ? get_permalink() : home_url('/');

        return sprintf(
            '<span class="loopdues-account"><a class="loopdues-button wp-element-button" href="%s">%s</a> <a class="loopdues-logout" href="%s">%s</a></span>',
            esc_url(loopdues_org()['portal_url'] ?: loopdues_url('portal')),
            esc_html__('My account', 'loopdues'),
            esc_url(wp_logout_url($here)),
            esc_html__('Log out', 'loopdues')
        );
    }

    $label = $atts['label'] ?: (get_option('loopdues_login_label') ?: __('Member login', 'loopdues'));

    return sprintf('<a class="loopdues-button wp-element-button" href="%s">%s</a>', esc_url(loopdues_login_url()), esc_html($label));
}
add_shortcode('loopdues_login', 'loopdues_login_html');

// ------------------------------------------------------------------ members-only content

function loopdues_parse_tiers($value)
{
    $list = is_array($value) ? $value : explode(',', (string) $value);

    return array_values(array_filter(array_map('intval', $list)));
}

/** Message shown in place of members-only content, with login / join buttons. */
function loopdues_teaser_html(array $tiers = [])
{
    $loggedInNotCurrent = is_user_logged_in() && loopdues_is_linked_member();
    if ($loggedInNotCurrent && $tiers && loopdues_is_current_member(null, [])) {
        $message = __('This content is for members in a different membership tier.', 'loopdues');
    } elseif ($loggedInNotCurrent) {
        $message = __('Your membership is not current. Renew to see this content.', 'loopdues');
    } else {
        $message = get_option('loopdues_teaser') ?: __('This content is for members. Please log in, or join to get access.', 'loopdues');
    }

    $buttons = $loggedInNotCurrent
        ? sprintf('<a class="loopdues-button wp-element-button" href="%s">%s</a>', esc_url(loopdues_org()['portal_url'] ?: loopdues_url('portal')), esc_html__('Renew my membership', 'loopdues'))
        : loopdues_login_html().(get_option('loopdues_join_url') ? sprintf(' <a class="loopdues-join-link" href="%s">%s</a>', esc_url(get_option('loopdues_join_url')), esc_html__('Join', 'loopdues')) : '');

    return '<div class="loopdues-members-only"><p>'.esc_html($message).'</p><p>'.$buttons.'</p></div>';
}

function loopdues_can_see(array $tiers = [], $postId = null)
{
    if ($postId && current_user_can('edit_post', $postId)) {
        return true;
    }
    if (! $postId && current_user_can('edit_posts')) {
        return true;
    }

    return loopdues_is_current_member(null, $tiers);
}

add_shortcode('loopdues_members_only', function ($atts, $content = '') {
    $atts = shortcode_atts(['tiers' => ''], $atts, 'loopdues_members_only');
    $tiers = loopdues_parse_tiers($atts['tiers']);

    return loopdues_can_see($tiers, get_the_ID() ?: null) ? do_shortcode($content) : loopdues_teaser_html($tiers);
});

// Whole pages and posts: _loopdues_access = '' (everyone) | 'members' | 'tiers' (with _loopdues_tiers).

function loopdues_post_rule($postId)
{
    $access = get_post_meta($postId, '_loopdues_access', true);
    if ($access === 'members') {
        return [];
    }
    if ($access === 'tiers') {
        $tiers = loopdues_parse_tiers(get_post_meta($postId, '_loopdues_tiers', true));

        return $tiers ?: []; // "tiers" with none ticked = any current member
    }

    return null; // everyone
}

function loopdues_post_hidden($postId)
{
    $rule = loopdues_post_rule($postId);

    return $rule !== null && ! loopdues_can_see($rule, $postId);
}

// Early priority: the real content (and anything shortcodes in it would output) is never built for visitors who can't see it.
// the_content also covers the REST API's content.rendered and feeds.
add_filter('the_content', function ($content) {
    $post = get_post();
    if (! $post || ! loopdues_post_hidden($post->ID)) {
        return $content;
    }

    return loopdues_teaser_html((array) loopdues_post_rule($post->ID));
}, 1);

add_filter('get_the_excerpt', function ($excerpt, $post = null) {
    $post = get_post($post);

    return $post && loopdues_post_hidden($post->ID) ? '' : $excerpt;
}, 1, 2);

// Search and feeds would otherwise show the start of the text when there is no hand-written excerpt.
add_filter('wp_trim_excerpt', function ($text, $raw) {
    $post = get_post();

    return $post && loopdues_post_hidden($post->ID) ? '' : $text;
}, 1, 2);

// "Who can see this" box (shows in the block editor's sidebar panel area and in the classic editor).
add_action('add_meta_boxes', function () {
    foreach (get_post_types(['public' => true]) as $type) {
        if ($type !== 'attachment') {
            add_meta_box('loopdues_access', __('Who can see this (LoopDues)', 'loopdues'), 'loopdues_access_box', $type, 'side');
        }
    }
});

function loopdues_access_box(WP_Post $post)
{
    wp_nonce_field('loopdues_access', 'loopdues_access_nonce');
    $access = get_post_meta($post->ID, '_loopdues_access', true);
    $chosen = loopdues_parse_tiers(get_post_meta($post->ID, '_loopdues_tiers', true));
    $tiers = loopdues_org()['tiers'];

    $choices = ['' => __('Everyone', 'loopdues'), 'members' => __('Current members', 'loopdues')];
    if ($tiers) {
        $choices['tiers'] = __('Members in these tiers:', 'loopdues');
    }
    foreach ($choices as $value => $label) {
        printf('<p style="margin:4px 0"><label><input type="radio" name="loopdues_access" value="%s" %s> %s</label></p>', esc_attr($value), checked($access, $value, false), esc_html($label));
    }
    if ($tiers) {
        echo '<div style="margin-left:22px">';
        foreach ($tiers as $tier) {
            printf('<label style="display:block"><input type="checkbox" name="loopdues_tiers[]" value="%d" %s> %s</label>', (int) $tier['id'], checked(in_array((int) $tier['id'], $chosen, true), true, false), esc_html($tier['name']));
        }
        echo '</div>';
    }
    echo '<p class="description">'.esc_html__('Visitors who can\'t see it get a message with Member login and Join buttons. Editors always see it.', 'loopdues').'</p>';
}

add_action('save_post', function ($postId) {
    if (! isset($_POST['loopdues_access_nonce']) || ! wp_verify_nonce(sanitize_key($_POST['loopdues_access_nonce']), 'loopdues_access')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || ! current_user_can('edit_post', $postId)) {
        return;
    }

    $access = sanitize_key($_POST['loopdues_access'] ?? '');
    if (! in_array($access, ['members', 'tiers'], true)) {
        delete_post_meta($postId, '_loopdues_access');
        delete_post_meta($postId, '_loopdues_tiers');

        return;
    }
    update_post_meta($postId, '_loopdues_access', $access);
    update_post_meta($postId, '_loopdues_tiers', $access === 'tiers' ? loopdues_parse_tiers((array) ($_POST['loopdues_tiers'] ?? [])) : []);
});

// A lock icon in the Pages/Posts list so staff can see what's members-only.
foreach (['page', 'post'] as $type) {
    add_filter("manage_{$type}_posts_columns", function ($columns) {
        $columns['loopdues'] = __('Access', 'loopdues');

        return $columns;
    });
    add_action("manage_{$type}_posts_custom_column", function ($column, $postId) {
        if ($column === 'loopdues') {
            $rule = loopdues_post_rule($postId);
            echo $rule === null ? '—' : esc_html($rule ? __('Some tiers', 'loopdues') : __('Members', 'loopdues'));
        }
    }, 10, 2);
}

// ------------------------------------------------------------------ blocks (plain JS in the editor, rendered here)

add_action('init', function () {
    wp_register_script('loopdues-blocks', LOOPDUES_URL.'assets/blocks.js', ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'], LOOPDUES_VERSION, true);
    wp_localize_script('loopdues-blocks', 'LoopDuesBlocks', ['tiers' => loopdues_org()['tiers'], 'connected' => (bool) loopdues_connection()]);

    register_block_type('loopdues/join', [
        'editor_script' => 'loopdues-blocks',
        'attributes' => [
            'tiers' => ['type' => 'array', 'default' => [], 'items' => ['type' => 'integer']],
            'fullWidth' => ['type' => 'boolean', 'default' => false],
            'hideLogo' => ['type' => 'boolean', 'default' => false],
            'inheritFont' => ['type' => 'boolean', 'default' => false],
            'frameless' => ['type' => 'boolean', 'default' => false],
            'hideSignin' => ['type' => 'boolean', 'default' => false],
        ],
        'render_callback' => fn ($a) => loopdues_join_html([
            'tiers' => implode(',', (array) ($a['tiers'] ?? [])),
            'width' => ! empty($a['fullWidth']) ? 'full' : '',
            'logo' => ! empty($a['hideLogo']) ? 'hide' : '',
            'font' => ! empty($a['inheritFont']) ? 'inherit' : '',
            'frame' => ! empty($a['frameless']) ? 'none' : '',
            'signin' => ! empty($a['hideSignin']) ? 'hide' : '',
        ]),
    ]);

    register_block_type('loopdues/login', [
        'editor_script' => 'loopdues-blocks',
        'attributes' => ['label' => ['type' => 'string', 'default' => '']],
        'render_callback' => fn ($a) => loopdues_login_html(['label' => $a['label'] ?? '']),
    ]);

    register_block_type('loopdues/members-only', [
        'editor_script' => 'loopdues-blocks',
        'attributes' => ['tiers' => ['type' => 'array', 'default' => [], 'items' => ['type' => 'integer']]],
        'render_callback' => function ($a, $content) {
            $tiers = loopdues_parse_tiers($a['tiers'] ?? []);

            return loopdues_can_see($tiers, get_the_ID() ?: null) ? $content : loopdues_teaser_html($tiers);
        },
    ]);
});

add_action('wp_head', function () {
    echo "<style>.loopdues-members-only{padding:1.25em 1.5em;border:1px solid rgba(0,0,0,.12);border-radius:8px}.loopdues-members-only p:last-child{margin-bottom:0}.loopdues-logout,.loopdues-join-link{margin-left:.75em}</style>\n";
});
