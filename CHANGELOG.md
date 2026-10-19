# Changelog

All notable changes to the LoopDues WordPress plugin. Versions follow [Semantic Versioning](https://semver.org/). When you release, bump the version in `loopdues.php` (both the `Version:` header and `LOOPDUES_VERSION`) and the `Stable tag` in `readme.txt`, and add the entry to the changelog in `readme.txt` too.

## [Unreleased]

## [1.0.3] - 2026-10-19
### Added
- `loopdues_member_updated` action, run whenever a linked member's record is stored (at sign-in and from webhooks), with the record before and after, the full member record from LoopDues, and where it came from. See "Reacting to changes" in the README.
- `loopdues_member_record()`, which returns the stored record for a user (status, whether current, tier, paid-through date) as one array.
- `README.md` with features, installation and a developer guide (functions, user meta, filters and examples for themes and other plugins).
- Download link and the Allowed websites step in the installation steps in `readme.txt`.

## [1.0.2] - 2026-10-19
### Changed
- Settings page and installation steps now point to **Admin → Website sign-in** in LoopDues, the menu's current name (it was Admin → WordPress).
- Sign-in and the connection check now use `/connect/sso` and `/connect/ping`, the addresses in the LoopDues developer docs. The `/wp/sso` and `/wp/ping` aliases the plugin used before are permanent, so older copies keep working.

## [1.0.1] - 2026-10-19
### Changed
- Now maintained in-house, since LoopDues no longer provides the plugin.
- Plugin header: author is now Tentuso and the plugin URI points to this repo.

### Added
- `Update URI: false` header, so WordPress never offers to replace this plugin with a same-named one from WordPress.org.
- This changelog.

## [1.0.0] - 2026-10-14
### Added
- Original release from LoopDues: join form, member login (SSO), members-only pages and sections, and webhooks for membership status.
- Adapted for oao.on.ca: members get the `oao_member` role.
