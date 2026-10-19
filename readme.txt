=== LoopDues ===
Contributors: tenutso
Tags: membership, members only, association, dues, login
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later

Join form, member login and members-only content for organizations that run their memberships on LoopDues.

== Description ==

* **Join form**: the "LoopDues join form" block or [loopdues_join]. No code to paste.
* **Member login**: members sign in with their LoopDues account and come back to your site logged in. WordPress never stores their password.
* **Members-only pages**: choose "Current members" (or particular tiers) in the "Who can see this" box. Visitors see a message with Member login and Join buttons instead.
* **Members-only sections**: the "LoopDues members only" block or [loopdues_members_only]…[/loopdues_members_only].
* **Always up to date**: when a membership is renewed or lapses, LoopDues tells your site straight away.

== Installation ==

Download the latest version: https://github.com/tenutso/loopdues-wordpress/releases/latest/download/loopdues.zip
All releases and developer documentation: https://github.com/tenutso/loopdues-wordpress

1. In WordPress, go to Plugins → Add New → Upload Plugin, choose loopdues.zip and activate it.
2. In LoopDues, go to Admin → Website sign-in, add your site's address and copy the connection key.
3. In WordPress, go to Settings → LoopDues and paste the key.
4. For the join form, add your site's address to Allowed websites under Settings → Website signup widget in LoopDues.

== Changelog ==

= 1.0.2 =
* Settings and installation steps now point to Admin → Website sign-in in LoopDues (formerly Admin → WordPress).
* Sign-in and the connection check use the documented /connect/sso and /connect/ping addresses. The old /wp/ addresses still work.

= 1.0.1 =
* Now maintained in-house, since LoopDues no longer provides the plugin. Source: https://github.com/tenutso/loopdues-wordpress
* Plugin header: new author and plugin URI, and "Update URI: false" so WordPress never replaces it with a same-named plugin from WordPress.org.

= 1.0.0 =
* Original release from LoopDues, adapted for oao.on.ca (members get the `oao_member` role).
