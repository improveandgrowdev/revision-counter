<?php
/**
 * Plugin Name:       Revision Counter
 * Description:       Shows how many revisions each page/post has — as an admin list column and in the editor sidebar.
 * Version:           1.1.1
 * Author:            Ricky Angeles
 * License:           GPL-2.0-or-later
 * Requires at least: 5.9
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types the revision column should appear on.
 *
 * @return string[]
 */
function rvc_supported_post_types() {
	$types = array();

	foreach ( get_post_types( array( 'show_ui' => true ), 'names' ) as $type ) {
		if ( post_type_supports( $type, 'revisions' ) ) {
			$types[] = $type;
		}
	}

	/**
	 * Filter which post types get the revision count column.
	 *
	 * @param string[] $types Post type slugs.
	 */
	return apply_filters( 'rvc_supported_post_types', $types );
}

/**
 * Count the revisions stored for a post (excludes autosaves).
 *
 * @param int $post_id Post ID.
 * @return int
 */
function rvc_get_revision_count( $post_id ) {
	$revisions = wp_get_post_revisions(
		$post_id,
		array(
			'fields'      => 'ids',
			'post_status' => 'inherit',
		)
	);

	if ( empty( $revisions ) ) {
		return 0;
	}

	// Drop autosaves — they are revisions whose name ends in "-autosave-v1".
	$count = 0;
	foreach ( $revisions as $revision_id ) {
		if ( false === strpos( get_post_field( 'post_name', $revision_id ), 'autosave' ) ) {
			$count++;
		}
	}

	return $count;
}

/* -------------------------------------------------------------------------
 * Admin list table column
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	foreach ( rvc_supported_post_types() as $type ) {
		add_filter( "manage_{$type}_posts_columns", 'rvc_add_column' );
		add_action( "manage_{$type}_posts_custom_column", 'rvc_render_column', 10, 2 );
		add_filter( "manage_edit-{$type}_sortable_columns", 'rvc_sortable_column' );
	}
} );

/**
 * Mark the revisions column as sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function rvc_sortable_column( $columns ) {
	$columns['rvc_revisions'] = 'rvc_revisions';
	return $columns;
}

/**
 * Order the list table by revision count when the column header is clicked.
 *
 * @param array    $clauses Query clauses.
 * @param WP_Query $query   Query object.
 * @return array
 */
add_filter( 'posts_clauses', function ( $clauses, $query ) {
	global $wpdb;

	if ( ! is_admin() || ! $query->is_main_query() || 'rvc_revisions' !== $query->get( 'orderby' ) ) {
		return $clauses;
	}

	$order = ( 'ASC' === strtoupper( $query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

	$clauses['join']    .= " LEFT JOIN {$wpdb->posts} rvc_rev ON rvc_rev.post_parent = {$wpdb->posts}.ID"
		. " AND rvc_rev.post_type = 'revision' AND rvc_rev.post_name NOT LIKE '%autosave%'";
	$clauses['groupby']  = "{$wpdb->posts}.ID";
	$clauses['orderby']  = "COUNT(rvc_rev.ID) {$order}";

	return $clauses;
}, 10, 2 );

/**
 * Add the "Revisions" column header.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function rvc_add_column( $columns ) {
	$columns['rvc_revisions'] = __( 'Revisions', 'revision-counter' );
	return $columns;
}

/**
 * Render the revision count for a row.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function rvc_render_column( $column, $post_id ) {
	if ( 'rvc_revisions' !== $column ) {
		return;
	}

	$count = rvc_get_revision_count( $post_id );

	if ( ! $count ) {
		echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No revisions', 'revision-counter' ) . '</span>';
		return;
	}

	$link = wp_get_post_revisions_url( $post_id );

	if ( ! $link ) {
		echo esc_html( number_format_i18n( $count ) );
		return;
	}

	printf(
		'<a href="%s">%s</a>',
		esc_url( $link ),
		esc_html( number_format_i18n( $count ) )
	);
}

/* -------------------------------------------------------------------------
 * Classic editor meta box
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function ( $post_type ) {
	if ( ! in_array( $post_type, rvc_supported_post_types(), true ) ) {
		return;
	}

	add_meta_box(
		'rvc_revision_count',
		__( 'Revision Count', 'revision-counter' ),
		'rvc_render_meta_box',
		null,
		'side',
		'default'
	);
} );

/**
 * Meta box body.
 *
 * @param WP_Post $post Current post.
 */
function rvc_render_meta_box( $post ) {
	$count = rvc_get_revision_count( $post->ID );

	printf(
		/* translators: %s: number of revisions */
		'<p>%s</p>',
		esc_html( sprintf( _n( '%s revision', '%s revisions', $count, 'revision-counter' ), number_format_i18n( $count ) ) )
	);

	$link = $count ? wp_get_post_revisions_url( $post->ID ) : null;

	if ( $link ) {
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( $link ),
			esc_html__( 'Browse revisions', 'revision-counter' )
		);
	}
}

/* -------------------------------------------------------------------------
 * Block editor (Gutenberg) — expose count via REST so it shows in the sidebar panel
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	foreach ( rvc_supported_post_types() as $type ) {
		register_rest_field(
			$type,
			'rvc_revision_count',
			array(
				'get_callback' => function ( $arr ) {
					return rvc_get_revision_count( $arr['id'] );
				},
				'schema'       => array(
					'description' => __( 'Number of stored revisions (autosaves excluded).', 'revision-counter' ),
					'type'        => 'integer',
					'context'     => array( 'edit' ),
				),
			)
		);
	}
} );
