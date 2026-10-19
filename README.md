# LoopDues for WordPress

Join form, member login and members-only content for organizations that run their memberships on [LoopDues](https://loopdues.com).

Members sign in with their LoopDues account and come back to your site logged in. WordPress never stores their password. Access follows each membership: when a member renews, lapses or changes tier, LoopDues tells your site straight away.

**[Download the latest version (loopdues.zip)](https://github.com/tenutso/loopdues-wordpress/releases/latest/download/loopdues.zip)** · [All releases](https://github.com/tenutso/loopdues-wordpress/releases) · [Changelog](CHANGELOG.md)

> LoopDues no longer provides a WordPress plugin. This one is maintained independently and is built only on the public LoopDues integration ("Sign in with LoopDues", the connection check and webhooks).

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Using it on your site](#using-it-on-your-site)
- [Using member data in themes and other plugins](#using-member-data-in-themes-and-other-plugins)
- [How it works](#how-it-works)
- [Releasing a new version](#releasing-a-new-version)

## Features

- **Join form.** The LoopDues signup form as a block or the `[loopdues_join]` shortcode, with the same options as the embed code in LoopDues.
- **Member login.** A login button (block, shortcode or on `wp-login.php`). Signed-in members see **My account**, which opens their LoopDues portal, and **Log out**.
- **Members-only pages and posts.** A **Who can see this (LoopDues)** box on every public post type: Everyone, Current members, or Members in these tiers. The content is also kept out of excerpts, search results, feeds and the REST API.
- **Members-only sections.** The **LoopDues members only** block, or `[loopdues_members_only]`, for part of a page.
- **Renew prompt.** Lapsed members can still log in and see a **Renew my membership** button in place of members-only content.
- **Live updates.** A signed webhook keeps each member's status, tier and paid-through date current, without the member signing in again.
- **Safe with existing accounts.** It never deletes or replaces users. It links an existing account only if the email matches and the account is a Subscriber, and never links author, editor or administrator accounts.
- **Admin overview.** A LoopDues column on the Users list (standing, tier, paid-through date), and an Access column on Pages and Posts.
- **Members stay out of the dashboard.** By default members don't see the admin bar or wp-admin (this can be changed in Settings → LoopDues).

## Requirements

- WordPress 6.2 or newer and PHP 7.4 or newer
- An HTTPS site
- A LoopDues organization, with access to **Admin → Website sign-in**

## Installation

1. [Download `loopdues.zip`](https://github.com/tenutso/loopdues-wordpress/releases/latest/download/loopdues.zip).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Activate**.
3. In LoopDues, go to **Admin → Website sign-in**, enter your site's address under **Add your site** and copy the **connection key**.
4. In WordPress, go to **Settings → LoopDues**, paste the key and save. You should see "Connected to" with your organization's name.
5. For the join form, add your site's address to **Allowed websites** under **Settings → Website signup widget** in LoopDues. The form only loads on sites listed there.

**Updating:** download the new zip and upload it the same way. WordPress offers to replace the installed version. The plugin sets `Update URI: false`, so WordPress never offers to "update" it with an unrelated plugin from WordPress.org.

**From git**, for development: clone into `wp-content/plugins/loopdues`, so the folder name matches the release zip.

```sh
git clone https://github.com/tenutso/loopdues-wordpress.git wp-content/plugins/loopdues
```

## Using it on your site

### Join form

Add the **LoopDues join form** block, or the shortcode:

```
[loopdues_join]
[loopdues_join tiers="3,7" width="full" logo="hide" font="inherit" frame="none" color="#1d4ed8" signin="hide"]
[loopdues_join tier="12"]
```

| Attribute | Values | Effect |
|---|---|---|
| `tiers` | tier ids, comma-separated | Offer only these tiers (default: every public tier) |
| `tier` | one tier id | Preselect this tier |
| `width` | `full` | Fill the containing block instead of a 640px column |
| `logo` | `hide` | Hide your logo (the heading stays) |
| `font` | `inherit` | Use the site's font |
| `frame` | `none` | No border, header bar or card |
| `color` | `#rrggbb` | Button and highlight colour for this form only |
| `signin` | `hide` | Hide the "Already a member? Sign in" link |

Tier ids appear in the block's sidebar and in Settings → LoopDues once the site is connected.

### Member login

The **LoopDues member login** block, or `[loopdues_login]` (optional `label="…"`). Put it in a header, menu area or page.

### Members-only content

- **A whole page or post:** use the **Who can see this (LoopDues)** box when editing it. Anyone who can edit the post always sees it.
- **Part of a page:** use the **LoopDues members only** block and put other blocks inside it, or the shortcode:

```
[loopdues_members_only]Only current members see this.[/loopdues_members_only]
[loopdues_members_only tiers="3,12"]Only members in tiers 3 or 12 see this.[/loopdues_members_only]
```

### Settings → LoopDues

| Setting | Purpose |
|---|---|
| Connection key | From LoopDues, Admin → Website sign-in. Treat it like a password. |
| Join page on this site | Members-only messages link to it with a **Join** button. |
| Login button | Wording of the login button. |
| Members-only message | What visitors see in place of members-only content. |
| Members and the dashboard | Hide the admin bar and wp-admin from members (on by default). |

## Using member data in themes and other plugins

The plugin stores each linked member's record on their WordPress user and exposes a few functions. Everything below is safe to use from a theme or another plugin.

Always check that the plugin is active before calling its functions, so your site keeps working if it's deactivated:

```php
if ( function_exists( 'loopdues_is_current_member' ) && loopdues_is_current_member() ) {
    // current member
}
```

### Who counts as a current member

A **current member** is a linked user whose LoopDues status is `active` or `grace_period` (a renewal payment is being retried). These are listed in the `LOOPDUES_CURRENT_STATUSES` constant.

| Status | Meaning |
|---|---|
| `active` | Paid up |
| `grace_period` | Payment failed and is being retried; still in good standing |
| `pending` | Hasn't paid yet, or waiting for application approval |
| `past_due` | Payment failed and the grace period has ended |
| `suspended` | Billing stopped after repeated failed payments |
| `expired` | Membership ended without renewal |
| `cancelled` | Cancelled by the member or staff |

### Functions

| Function | Returns |
|---|---|
| `loopdues_is_current_member( $user_id = null, array $tier_ids = [] )` | `true` if the user (default: current user) is a current member, optionally only in one of the given tiers. Passes through the `loopdues_is_current_member` filter. |
| `loopdues_is_linked_member( $user_id = null )` | Truthy if the user is linked to a LoopDues member, whatever their status. Use it to tell lapsed members from visitors. |
| `loopdues_member_record( $user_id = null )` | The stored record as an array, or `null` if the user isn't linked: `member_id`, `status`, `current` (bool), `tier_id`, `tier_name`, `paid_through`, `synced_at`. Since 1.0.3. |
| `loopdues_user_by_member_id( $member_id )` | The `WP_User` linked to a LoopDues member id, or `null`. |
| `loopdues_login_url( $redirect_to = '' )` | URL that signs a member in and brings them back to `$redirect_to` (default: the current page). |
| `loopdues_login_html( [ 'label' => '' ] )` | The login button, or **My account** / **Log out** for signed-in members. Same output as `[loopdues_login]`. |
| `loopdues_teaser_html( array $tier_ids = [] )` | The members-only message with the right buttons for the visitor (login and join, or renew). |
| `loopdues_can_see( array $tier_ids = [], $post_id = null )` | Whether the current user may see members-only content: editors of the post, or current members (in those tiers). |
| `loopdues_post_rule( $post_id )` | A post's access rule: `null` (everyone), `[]` (any current member) or an array of tier ids. |
| `loopdues_post_hidden( $post_id )` | `true` if the current user can't see that post. |
| `loopdues_org()` | The connected organization: `[ 'name', 'portal_url', 'tiers' => [ [ 'id', 'name' ], … ], 'checked_at' ]`. Refreshed daily. |
| `loopdues_url( $path = '' )` | `https://{org}.loopdues.com/{path}`, e.g. `loopdues_url( 'portal' )` or `loopdues_url( 'join?tier=12' )`. Empty if not connected. |
| `loopdues_connection()` | `null` if no valid key is saved. Use it only as an "is the site connected?" check: the array it returns contains the secret. |

### User meta

Written at every sign-in and whenever LoopDues sends an update. Read it with `get_user_meta( $user_id, $key, true )`.

| Key | Example | Notes |
|---|---|---|
| `loopdues_member_id` | `1483` | LoopDues member id. Present only on linked users. |
| `loopdues_status` | `active` | See the status table above. |
| `loopdues_tier_id` | `3` | |
| `loopdues_tier_name` | `Active Member` | |
| `loopdues_paid_through` | `2027-12-31` | Last day the membership covers (organization's time zone). Empty for lifetime memberships. |
| `loopdues_synced_at` | `1792332202` | Unix time of the record LoopDues sent. Older updates arriving late are ignored. |

The member's first and last name are copied into the WordPress user's own `first_name`, `last_name` and `display_name`. When a member is erased or merged in LoopDues, all `loopdues_*` meta is removed. If the user's role was `Member (LoopDues)`, it's changed back to Subscriber. The user account itself is kept.

### Post meta

| Key | Values |
|---|---|
| `_loopdues_access` | empty (everyone), `members` or `tiers` |
| `_loopdues_tiers` | array of tier ids, when `_loopdues_access` is `tiers` |

Prefer `loopdues_post_rule()` and `loopdues_post_hidden()` over reading these directly.

### Filters

| Filter | Use |
|---|---|
| `loopdues_is_current_member` `( bool $current, int $user_id, array $tier_ids )` | Change who counts as current, e.g. grant access to honorary members. |
| `loopdues_linkable_roles` `( array $roles )` | Roles a member sign-in may log into. Default: `subscriber` and the plugin's member role. Add a role from another plugin if your existing member accounts use it. Never add staff roles. |
| `loopdues_domain` `( string $domain )` | The LoopDues domain (default `loopdues.com`), for testing against a staging server. |

### Reacting to changes

Use the `loopdues_member_updated` action (since 1.0.3). It runs whenever a linked member's record is stored: at every sign-in, and for every membership or payment webhook from LoopDues.

```php
add_action( 'loopdues_member_updated', function ( $user_id, $current, $previous, $member, $context ) {
    $was_current = $previous && $previous['current'];

    if ( $current['current'] && ! $was_current ) {
        // joined, renewed after lapsing, or signed in for the first time as a current member
    } elseif ( ! $current['current'] && $was_current ) {
        // lapsed: grace period over, expired, cancelled…
    }

    if ( $previous && $previous['tier_id'] !== $current['tier_id'] ) {
        // changed tier
    }
}, 10, 5 );
```

| Argument | Contains |
|---|---|
| `$user_id` | WordPress user id |
| `$current` | The stored record now, as returned by `loopdues_member_record()` |
| `$previous` | The record before this update, or `null` if the user wasn't linked yet (first sign-in) |
| `$member` | The member record exactly as LoopDues sent it. Webhooks carry the full record (email, phone, address, custom attributes…); sign-in carries only the fields listed under `loopdues_member_record()`. |
| `$context` | `[ 'source' => 'sign-in' ]` or `[ 'source' => 'webhook', 'event' => 'membership.renewed', 'event_id' => '…' ]` |

Notes:

- It runs on every update, even when nothing changed (a member signing in again, a `payment.received` event), so compare `$current` with `$previous`.
- It doesn't run for an update that is older than the stored record (events can arrive late), or for members who have never signed in to the site.
- Webhook handlers must answer LoopDues within 10 seconds, so keep listeners quick. Hand slow work (a CRM or mailing-list call) to `wp_schedule_single_event()`.
- Members are signed in with `wp_set_auth_cookie()` and the standard `wp_login` action runs too, so existing login hooks keep working.
- When a member is erased or merged in LoopDues, the plugin removes their `loopdues_*` user meta. To react to that, hook `deleted_user_meta` for the key `loopdues_member_id`.

### Examples

A members' menu item or greeting in a theme:

```php
<?php if ( function_exists( 'loopdues_is_current_member' ) && loopdues_is_current_member() ) : ?>
    <p>Welcome back, <?php echo esc_html( wp_get_current_user()->first_name ); ?>.
       Your membership runs to <?php echo esc_html( get_user_meta( get_current_user_id(), 'loopdues_paid_through', true ) ); ?>.</p>
<?php elseif ( function_exists( 'loopdues_login_url' ) ) : ?>
    <a href="<?php echo esc_url( loopdues_login_url() ); ?>">Member login</a>
<?php endif; ?>
```

Protecting a custom template or endpoint the same way as a members-only page:

```php
if ( function_exists( 'loopdues_can_see' ) && ! loopdues_can_see() ) {
    echo loopdues_teaser_html(); // login / join, or renew for lapsed members
    return;
}
```

Restricting to particular tiers, with names from the connected organization:

```php
$tiers   = wp_list_pluck( loopdues_org()['tiers'], 'name', 'id' ); // [ 3 => 'Active Member', … ]
$allowed = loopdues_is_current_member( null, [ 3, 12 ] );
```

Listing current members, e.g. for a directory:

```php
$members = get_users( [
    'meta_query' => [
        [ 'key' => 'loopdues_status', 'value' => LOOPDUES_CURRENT_STATUSES, 'compare' => 'IN' ],
    ],
] );
```

Linking to the member portal ("Renew", "Update my card", "Receipts"):

```php
$portal = loopdues_org()['portal_url'] ?: loopdues_url( 'portal' );
```

Need more than the fields above, such as addresses or custom fields? Use the LoopDues REST API, or a webhook of your own in LoopDues. The plugin keeps only what it needs to decide access.

## How it works

| | |
|---|---|
| **Connection key** | `ldwp_{org}_{site id}_{secret}`, saved in the `loopdues_key` option. The secret signs everything between LoopDues and the site (HMAC-SHA256). |
| **Connection check** | `GET https://{org}.loopdues.com/connect/ping`, when the key is saved, daily, and from **Test connection** in settings. It caches the organization name, portal address and tier list. |
| **Sign-in** | `/?loopdues_login=1` sends the visitor to `https://{org}.loopdues.com/connect/sso` with a one-time state cookie. LoopDues sends them back to `/?loopdues_sso=1&ld_token=…`. The token is checked for signature, site, expiry (2 minutes), state and single use, then the user is found or created and logged in. |
| **Updates** | LoopDues `POST`s membership events to `/wp-json/loopdues/v1/webhook`. The plugin checks the `LoopDues-Signature` header (5-minute tolerance) and updates the linked user's meta. Events for members who have never signed in are acknowledged and ignored. |

| File | Contents |
|---|---|
| `loopdues.php` | Bootstrap, constants, member role |
| `includes/connection.php` | Key parsing, signing, connection check |
| `includes/sso.php` | Member sign-in |
| `includes/webhook.php` | Webhook endpoint |
| `includes/members.php` | Linking users, stored member record, access checks, Users column |
| `includes/content.php` | Join form, login button, members-only content, blocks |
| `includes/settings.php` | Settings → LoopDues |
| `assets/blocks.js` | Block editor UI (no build step) |

## Releasing a new version

1. Bump the version in `loopdues.php` (the `Version:` header and `LOOPDUES_VERSION`) and `Stable tag` in `readme.txt`.
2. Add the changes to `CHANGELOG.md` and to the changelog in `readme.txt`.
3. Commit, tag `vX.Y.Z` and push with tags.
4. Build the zip and publish the release:

```sh
git archive --format=zip --prefix=loopdues/ -o loopdues.zip vX.Y.Z && zip -qd loopdues.zip loopdues/.gitignore
gh release create vX.Y.Z loopdues.zip -t "LoopDues X.Y.Z" --notes-file notes.md
```

Always name the asset `loopdues.zip`, so the "latest" download link keeps working.

## License

GPL-2.0-or-later.
