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
- Notice showing whether `WP_POST_REVISIONS` is set in wp-config.php, with a one-click button to add a limit of 10 (backs up wp-config.php first)
- Updates from GitHub releases (see Releasing)
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

## Releasing (and how sites get the update)

1. Bump the version in **three** places: the `Version:` header and `RVC_VERSION` in `revision-counter.php`, and `Stable tag:` in `readme.txt`. Add a changelog entry.
2. Commit and push to `main`.
3. Tag and push the tag:

   ```bash
   git tag v1.3.0 && git push origin v1.3.0
   ```

The **Release** GitHub Action checks the versions match the tag, builds the zip and publishes the release. Sites running the plugin show the update under **Dashboard → Updates** within about 12 hours (WordPress's normal check), or straight away after clicking **Check again** there.

Pushing to `main` without a tag does **not** trigger an update — only a new release does.

### Private repo

While this repo is private, each site needs a GitHub token to see updates. Create a fine-grained personal access token with read-only **Contents** access to this repo only, and add it to that site's `wp-config.php`:

```php
define( 'RVC_GITHUB_TOKEN', 'github_pat_...' );
```

If the repo is made public, no token is needed.

## License

GPL-2.0-or-later
