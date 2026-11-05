# Revision Counter

A WordPress plugin that shows how many revisions each page/post has — as a sortable admin list column and in the editor sidebar. Autosaves are excluded.

## Install

1. Download the latest `revision-counter-x.y.z.zip` (from Releases, or build it yourself below).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, **Install Now**, then **Activate**.

## Features

- Sortable **Revisions** column on every post type that supports revisions
- Count links to the revisions browser
- **Revision Count** sidebar box with a "Browse revisions" link
- `rvc_revision_count` REST field (edit context)
- `rvc_supported_post_types` filter:

```php
add_filter( 'rvc_supported_post_types', fn( $types ) => array( 'post', 'page' ) );
```

## Requirements

WordPress 5.9+, PHP 7.4+.

## Building the zip

```bash
./bin/build-zip.sh
```

This writes `dist/revision-counter-<version>.zip`, with the version read from the plugin header. The zip contains a single `revision-counter/` folder, which is what WordPress expects. Dev files (`bin/`, `dist/`, `README.md`, dotfiles) are left out.

## Releasing

1. Bump `Version:` in `revision-counter.php` and `Stable tag:` in `readme.txt`, and add a changelog entry.
2. Commit, then tag: `git tag v1.2.0`.
3. Run `./bin/build-zip.sh` and attach the zip to the release.

## License

GPL-2.0-or-later
