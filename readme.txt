=== Revision Counter ===
Contributors: rickyangeles
Tags: revisions, admin, columns, editor
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows how many revisions each page/post has — as an admin list column and in the editor sidebar.

== Description ==

Revision Counter adds a sortable **Revisions** column to the admin list screens of every post type that supports revisions, plus a **Revision Count** box in the editor sidebar. Autosaves are not counted.

* Sortable "Revisions" column on Posts, Pages and custom post types
* Count links straight to the revisions browser
* "Revision Count" sidebar box with a "Browse revisions" link
* `rvc_revision_count` field on the REST API (edit context)
* `rvc_supported_post_types` filter to choose which post types get the column
* Notice on list screens showing whether a revision limit (`WP_POST_REVISIONS`) is set in wp-config.php, with a button to add one (10) if it isn't
* Updates straight from GitHub releases

== Installation ==

1. Download `revision-counter-x.y.z.zip`.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip, click **Install Now**, then **Activate**.

== Frequently Asked Questions ==

= How do I limit it to certain post types? =

    add_filter( 'rvc_supported_post_types', function ( $types ) {
        return array( 'post', 'page' );
    } );

= Why does a post show a dash? =

It has no stored revisions yet, or revisions are disabled (`WP_POST_REVISIONS`).

== Changelog ==

= 1.2.0 =
* New: wp-config.php revision limit check, with a one-click "Add revision limit (10)" button for admins (super admins on multisite). Backs up wp-config.php first.
* New: updates are offered in WordPress from GitHub releases.

= 1.1.1 =
* Sortable revisions column, editor sidebar count, REST field.
