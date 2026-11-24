<?php
/**
 * Offers plugin updates from the latest GitHub release.
 *
 * Publishing a GitHub release whose tag (e.g. v1.3.0) is newer than the
 * installed Version makes the update appear under Dashboard → Updates and on
 * the Plugins screen. The release must have the built zip
 * (revision-counter-x.y.z.zip) attached; the release workflow does that.
 *
 * Private repo: define RVC_GITHUB_TOKEN in wp-config.php with a fine-grained
 * token that has read-only "Contents" access to the repo.
 *
 * Relies on the "Update URI" plugin header (WordPress 5.8+).
 *
 * @package Revision_Counter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RVC_GitHub_Updater {

	const REPO      = 'improveandgrowdev/revision-counter';
	const CACHE_KEY = 'rvc_github_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** @var string Plugin basename, e.g. revision-counter/revision-counter.php */
	private $basename;

	/** @var string Installed version. */
	private $version;

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $version     Installed version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->basename = plugin_basename( $plugin_file );
		$this->version  = $version;

		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download_private_asset' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder_name' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ) );
	}

	private function slug() {
		return dirname( $this->basename );
	}

	private function token() {
		return defined( 'RVC_GITHUB_TOKEN' ) ? (string) RVC_GITHUB_TOKEN : '';
	}

	private function api_headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'revision-counter-updater',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( $this->token() ) {
			$headers['Authorization'] = 'Bearer ' . $this->token();
		}
		return $headers;
	}

	/**
	 * Latest release, normalised, or null. Cached; "Check again" on the
	 * Updates screen (force-check) bypasses the cache.
	 *
	 * @return array|null
	 */
	private function get_release() {
		$force  = is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification -- read-only cache bypass.
		$cached = get_site_transient( self::CACHE_KEY );
		if ( ! $force && is_array( $cached ) ) {
			return $cached ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => $this->api_headers(),
			)
		);

		$release = array();
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {
				$asset = null;
				foreach ( (array) ( $data['assets'] ?? array() ) as $a ) {
					if ( isset( $a['name'] ) && preg_match( '/\.zip$/i', $a['name'] ) && 0 === strpos( $a['name'], $this->slug() ) ) {
						$asset = $a;
						break;
					}
				}
				if ( $asset ) {
					$release = array(
						'version'   => ltrim( $data['tag_name'], 'vV' ),
						'url'       => $data['html_url'],
						'notes'     => (string) ( $data['body'] ?? '' ),
						'published' => (string) ( $data['published_at'] ?? '' ),
						// Public repos: direct download. Private: the API asset URL, fetched with the token below.
						'package'   => $this->token() ? $asset['url'] : $asset['browser_download_url'],
					);
				}
			}
		}

		// Cache failures too (as an empty array) so a GitHub outage doesn't slow every admin page.
		set_site_transient( self::CACHE_KEY, $release, $release ? self::CACHE_TTL : HOUR_IN_SECONDS );

		return $release ? $release : null;
	}

	/**
	 * Hook: update_plugins_github.com
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => $this->slug(),
			'plugin'       => $this->basename,
			'version'      => $release['version'],
			'new_version'  => $release['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $release['package'],
			'requires_php' => $plugin_data['RequiresPHP'] ?? '',
		);
	}

	/**
	 * Hook: plugins_api — powers the "View version x.y.z details" popup.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug() ) {
			return $result;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Revision Counter',
			'slug'          => $this->slug(),
			'version'       => $release['version'],
			'author'        => 'Ricky Angeles',
			'homepage'      => 'https://github.com/' . self::REPO,
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => '<p><a href="' . esc_url( $release['url'] ) . '">' . esc_html__( 'View release on GitHub', 'revision-counter' ) . '</a></p>'
					. wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Hook: upgrader_pre_download — private repos only.
	 *
	 * The API asset URL needs the token, then redirects to a pre-signed
	 * storage URL that must NOT receive the token. Resolve the redirect
	 * ourselves and hand WordPress the signed URL.
	 */
	public function download_private_asset( $reply, $package, $upgrader ) {
		$prefix = 'https://api.github.com/repos/' . self::REPO . '/releases/assets/';
		if ( false !== $reply || ! $this->token() || 0 !== strpos( (string) $package, $prefix ) ) {
			return $reply;
		}

		$response = wp_remote_get(
			$package,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => $this->api_headers( 'application/octet-stream' ),
			)
		);

		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( is_wp_error( $response ) || ! $location ) {
			return new WP_Error( 'rvc_download_failed', __( 'Could not download the update from GitHub. Check RVC_GITHUB_TOKEN.', 'revision-counter' ) );
		}

		return download_url( $location );
	}

	/**
	 * Hook: upgrader_source_selection — make sure the unzipped folder matches
	 * the installed folder name, so the update replaces the plugin in place.
	 */
	public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}

		$wanted = trailingslashit( $remote_source ) . $this->slug() . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return $source;
	}

	public function clear_cache() {
		delete_site_transient( self::CACHE_KEY );
	}
}
