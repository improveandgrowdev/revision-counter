<?php
/**
 * Shows whether WP_POST_REVISIONS is set in wp-config.php, and offers a
 * one-click button to add a limit when it isn't.
 *
 * @package Revision_Counter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RVC_Config_Limit {

	const LIMIT  = 10;
	const ACTION = 'rvc_add_revision_limit';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_add_limit' ) );
	}

	/**
	 * wp-config.php is shared by the whole network on multisite, so only a
	 * super admin may change it there.
	 *
	 * @return bool
	 */
	private function user_can_manage_config() {
		return is_multisite() ? current_user_can( 'manage_network_options' ) : current_user_can( 'manage_options' );
	}

	/**
	 * Locate wp-config.php the same way wp-load.php does.
	 *
	 * @return string|false
	 */
	private function get_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}

		$parent = dirname( ABSPATH );
		if ( @file_exists( $parent . '/wp-config.php' ) && ! @file_exists( $parent . '/wp-settings.php' ) ) {
			return $parent . '/wp-config.php';
		}

		return false;
	}

	/**
	 * Does the wp-config.php source contain an uncommented WP_POST_REVISIONS define?
	 * Core defines the constant as `true` when wp-config doesn't, so defined()
	 * alone can't tell whether it was set in the config file.
	 *
	 * @param string $contents File contents.
	 * @return bool
	 */
	private function config_has_define( $contents ) {
		return (bool) preg_match( '/^\s*define\s*\(\s*[\'"]WP_POST_REVISIONS[\'"]\s*,/mi', $contents );
	}

	/**
	 * Human-readable description of the current effective value.
	 *
	 * @return string
	 */
	private function describe_current_limit() {
		$value = defined( 'WP_POST_REVISIONS' ) ? WP_POST_REVISIONS : true;

		if ( true === $value || ( is_numeric( $value ) && (int) $value < 0 ) ) {
			return __( 'unlimited', 'revision-counter' );
		}
		if ( false === $value || 0 === (int) $value ) {
			return __( 'disabled (0)', 'revision-counter' );
		}
		return number_format_i18n( (int) $value );
	}

	/**
	 * Only show on list screens for post types that get the revisions column.
	 *
	 * @return bool
	 */
	private function is_relevant_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && 'edit' === $screen->base && in_array( $screen->post_type, rvc_supported_post_types(), true );
	}

	public function render_notice() {
		if ( ! $this->user_can_manage_config() || ! $this->is_relevant_screen() ) {
			return;
		}

		$this->render_result_message();

		$path = $this->get_config_path();
		if ( ! $path ) {
			return;
		}

		$contents = @file_get_contents( $path );
		if ( false === $contents ) {
			return;
		}

		if ( $this->config_has_define( $contents ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: revision limit */
						__( 'Revision limit is set in wp-config.php (WP_POST_REVISIONS): %s', 'revision-counter' ),
						$this->describe_current_limit()
					)
				)
			);
			return;
		}

		$snippet  = sprintf( "define( 'WP_POST_REVISIONS', %d );", self::LIMIT );
		$can_edit = is_writable( $path ) && ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: current revision limit */
				__( 'No revision limit (WP_POST_REVISIONS) is set in wp-config.php. Current limit: %s.', 'revision-counter' ),
				$this->describe_current_limit()
			)
		);
		echo '</p>';

		if ( $can_edit ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 .5em;">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
			wp_nonce_field( self::ACTION );
			submit_button(
				/* translators: %d: revision limit */
				sprintf( __( 'Add revision limit (%d) to wp-config.php', 'revision-counter' ), self::LIMIT ),
				'secondary',
				'submit',
				false
			);
			echo '</form>';
		} else {
			echo '<p>' . esc_html__( 'wp-config.php can\'t be edited from here. Add this line above "That\'s all, stop editing!":', 'revision-counter' ) . '</p>';
			echo '<p><code>' . esc_html( $snippet ) . '</code></p>';
		}

		echo '</div>';
	}

	/**
	 * Result of a previous "Add limit" click.
	 */
	private function render_result_message() {
		if ( ! isset( $_GET['rvc_msg'] ) ) {
			return;
		}

		$msg      = sanitize_key( wp_unslash( $_GET['rvc_msg'] ) );
		$messages = array(
			'added'        => array( 'success', __( 'Added WP_POST_REVISIONS to wp-config.php. A backup of the previous file was saved next to it. Older revisions are trimmed the next time each post is saved.', 'revision-counter' ) ),
			'exists'       => array( 'info', __( 'WP_POST_REVISIONS is already defined in wp-config.php. No changes made.', 'revision-counter' ) ),
			'no_config'    => array( 'error', __( 'Could not locate wp-config.php.', 'revision-counter' ) ),
			'not_writable' => array( 'error', __( 'wp-config.php is not writable. Add the line manually.', 'revision-counter' ) ),
			'backup_fail'  => array( 'error', __( 'Could not create a backup of wp-config.php, so it was not changed.', 'revision-counter' ) ),
			'no_anchor'    => array( 'error', __( 'Could not find a safe place in wp-config.php to insert the constant. Add it manually above the "That\'s all, stop editing!" line.', 'revision-counter' ) ),
			'write_fail'   => array( 'error', __( 'Writing wp-config.php failed. The file was not changed.', 'revision-counter' ) ),
		);

		if ( isset( $messages[ $msg ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $msg ][0] ),
				esc_html( $messages[ $msg ][1] )
			);
		}
	}

	public function handle_add_limit() {
		if ( ! $this->user_can_manage_config() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'revision-counter' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php' );
		}
		wp_safe_redirect( add_query_arg( 'rvc_msg', $this->add_limit_to_config(), remove_query_arg( 'rvc_msg', $back ) ) );
		exit;
	}

	/**
	 * @return string Result code used for the admin notice.
	 */
	public function add_limit_to_config() {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return 'not_writable';
		}

		$path = $this->get_config_path();
		if ( ! $path ) {
			return 'no_config';
		}
		if ( ! is_writable( $path ) ) {
			return 'not_writable';
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return 'no_config';
		}
		if ( $this->config_has_define( $contents ) ) {
			return 'exists';
		}

		$eol  = ( false !== strpos( $contents, "\r\n" ) ) ? "\r\n" : "\n";
		$line = sprintf( "define( 'WP_POST_REVISIONS', %d );", self::LIMIT ) . $eol;

		// Insert before "That's all, stop editing!" or, failing that, before wp-settings.php is required.
		$anchors = array(
			'/^[ \t]*\/\*\s*That\'s all, stop editing!/mi',
			'/^[ \t]*require(_once)?\s*\(?\s*ABSPATH\s*\.\s*[\'"]wp-settings\.php[\'"]/mi',
		);
		$new = null;
		foreach ( $anchors as $anchor ) {
			if ( preg_match( $anchor, $contents, $m, PREG_OFFSET_CAPTURE ) ) {
				$pos = $m[0][1];
				$new = substr( $contents, 0, $pos ) . $line . $eol . substr( $contents, $pos );
				break;
			}
		}
		if ( null === $new ) {
			return 'no_anchor';
		}

		// Backup with a .php extension so the web server never serves it as plain text.
		// Only the most recent backup is kept.
		$dir = dirname( $path );
		foreach ( (array) glob( $dir . '/wp-config.rvc-backup-*.php' ) as $old ) {
			@unlink( $old );
		}
		$backup = $dir . '/wp-config.rvc-backup-' . gmdate( 'Ymd-His' ) . '.php';
		if ( ! @copy( $path, $backup ) ) {
			return 'backup_fail';
		}

		if ( false === @file_put_contents( $path, $new, LOCK_EX ) ) {
			return 'write_fail';
		}

		clearstatcache( true, $path );
		$check = @file_get_contents( $path );
		if ( false === $check || ! $this->config_has_define( $check ) ) {
			@file_put_contents( $path, $contents, LOCK_EX ); // Restore original.
			return 'write_fail';
		}

		return 'added';
	}
}
