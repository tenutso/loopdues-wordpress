<?php
/** Settings → LoopDues: the connection key and a few wording choices. */

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_options_page('LoopDues', 'LoopDues', 'manage_options', 'loopdues', 'loopdues_settings_page');
});

add_filter('plugin_action_links_'.plugin_basename(LOOPDUES_DIR.'loopdues.php'), function ($links) {
    array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php?page=loopdues')).'">'.esc_html__('Settings', 'loopdues').'</a>');

    return $links;
});

add_action('admin_init', function () {
    $group = 'loopdues';
    register_setting($group, 'loopdues_key', ['sanitize_callback' => fn ($v) => preg_replace('/\s+/', '', (string) $v)]);
    register_setting($group, 'loopdues_login_label', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting($group, 'loopdues_teaser', ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting($group, 'loopdues_join_url', ['sanitize_callback' => 'esc_url_raw']);
    register_setting($group, 'loopdues_hide_admin_bar', ['sanitize_callback' => fn ($v) => $v ? '1' : '0', 'default' => '1']);
});

// A new key: check it straight away and fetch the organization's tiers.
add_action('update_option_loopdues_key', function () {
    delete_option('loopdues_org');
    if (loopdues_connection()) {
        $result = loopdues_refresh_org();
        set_transient('loopdues_check_'.get_current_user_id(), is_wp_error($result) ? $result->get_error_message() : 'ok', 60);
    }
});
add_action('add_option_loopdues_key', function () {
    do_action('update_option_loopdues_key');
});

add_action('admin_post_loopdues_test', function () {
    check_admin_referer('loopdues_test');
    if (! current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    $result = loopdues_refresh_org();
    set_transient('loopdues_check_'.get_current_user_id(), is_wp_error($result) ? $result->get_error_message() : 'ok', 60);
    wp_safe_redirect(admin_url('options-general.php?page=loopdues'));
    exit;
});

function loopdues_settings_page()
{
    $c = loopdues_connection();
    $org = loopdues_org();
    $check = get_transient('loopdues_check_'.get_current_user_id());
    delete_transient('loopdues_check_'.get_current_user_id());
    $key = (string) get_option('loopdues_key', '');
    ?>
    <div class="wrap">
        <h1>LoopDues</h1>

        <?php if ($check === 'ok') { ?>
            <div class="notice notice-success"><p><?php printf(esc_html__('Connected to %s.', 'loopdues'), '<strong>'.esc_html($org['name']).'</strong>'); ?></p></div>
        <?php } elseif ($check) { ?>
            <div class="notice notice-error"><p><?php echo esc_html(sprintf(__('Could not connect: %s', 'loopdues'), $check)); ?></p></div>
        <?php } elseif ($key !== '' && ! $c) { ?>
            <div class="notice notice-error"><p><?php esc_html_e('That doesn\'t look like a LoopDues connection key. Copy it again from LoopDues: Admin → Website sign-in.', 'loopdues'); ?></p></div>
        <?php } ?>

        <form method="post" action="options.php">
            <?php settings_fields('loopdues'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="loopdues_key"><?php esc_html_e('Connection key', 'loopdues'); ?></label></th>
                    <td>
                        <input type="password" id="loopdues_key" name="loopdues_key" value="<?php echo esc_attr($key); ?>" class="large-text code" autocomplete="off">
                        <p class="description"><?php esc_html_e('In LoopDues, go to Admin → Website sign-in, add this site, and paste the key it shows.', 'loopdues'); ?></p>
                        <?php if ($c && $org['name']) { ?>
                            <p><?php printf(esc_html__('Connected to %1$s (%2$s). Last checked %3$s ago.', 'loopdues'), '<strong>'.esc_html($org['name']).'</strong>', esc_html($c['subdomain'].'.loopdues.com'), esc_html(human_time_diff($org['checked_at']))); ?></p>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="loopdues_join_url"><?php esc_html_e('Join page on this site', 'loopdues'); ?></label></th>
                    <td>
                        <input type="url" id="loopdues_join_url" name="loopdues_join_url" value="<?php echo esc_attr(get_option('loopdues_join_url')); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/join/')); ?>">
                        <p class="description"><?php esc_html_e('The page with your join form. Members-only messages link to it. Leave blank to show only the login button.', 'loopdues'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="loopdues_login_label"><?php esc_html_e('Login button', 'loopdues'); ?></label></th>
                    <td><input type="text" id="loopdues_login_label" name="loopdues_login_label" value="<?php echo esc_attr(get_option('loopdues_login_label')); ?>" class="regular-text" placeholder="<?php esc_attr_e('Member login', 'loopdues'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="loopdues_teaser"><?php esc_html_e('Members-only message', 'loopdues'); ?></label></th>
                    <td><textarea id="loopdues_teaser" name="loopdues_teaser" rows="3" class="large-text" placeholder="<?php esc_attr_e('This content is for members. Please log in, or join to get access.', 'loopdues'); ?>"><?php echo esc_textarea(get_option('loopdues_teaser')); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Members and the dashboard', 'loopdues'); ?></th>
                    <td><label><input type="checkbox" name="loopdues_hide_admin_bar" value="1" <?php checked(get_option('loopdues_hide_admin_bar', '1'), '1'); ?>> <?php esc_html_e('Hide the admin bar and the WordPress dashboard from members', 'loopdues'); ?></label></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <?php if ($c) { ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="loopdues_test">
                <?php wp_nonce_field('loopdues_test'); ?>
                <?php submit_button(__('Test connection and refresh tiers', 'loopdues'), 'secondary', 'submit', false); ?>
            </form>
        <?php } ?>

        <h2><?php esc_html_e('Using it', 'loopdues'); ?></h2>
        <ul style="list-style:disc;margin-left:20px">
            <li><?php esc_html_e('Join form: add the "LoopDues join form" block, or the shortcode', 'loopdues'); ?> <code>[loopdues_join]</code> <?php esc_html_e('(options: tiers="3,7" width="full" logo="hide" font="inherit" frame="none" color="#1d4ed8" signin="hide").', 'loopdues'); ?></li>
            <li><?php esc_html_e('Member login / My account button: the "LoopDues member login" block, or', 'loopdues'); ?> <code>[loopdues_login]</code>.</li>
            <li><?php esc_html_e('Members-only pages: use the "Who can see this" box when editing a page or post.', 'loopdues'); ?></li>
            <li><?php esc_html_e('Members-only parts of a page: the "LoopDues members only" block, or', 'loopdues'); ?> <code>[loopdues_members_only]…[/loopdues_members_only]</code>.</li>
        </ul>
        <p><?php esc_html_e('Members sign in with their LoopDues account. The first time, a WordPress user is created for them with the "Member (LoopDues)" role. Renewals and lapses update their access automatically.', 'loopdues'); ?></p>
    </div>
    <?php
}
