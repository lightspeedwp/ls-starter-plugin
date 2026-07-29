<?php
/**
 * Native asset cachebusting.
 *
 * Replaces the `Cachebuster` plugin: rewrites the `ver` query arg on
 * locally enqueued scripts and styles to the asset file's filemtime,
 * so browsers bust cache on every deploy without a manual version bump.
 * Doing this in the loader filters (rather than reinstalling a plugin)
 * covers theme, plugin and core assets globally with no theme-side code
 * and none of a plugin's bootstrap overhead.
 *
 * @package {{NAMESPACE}}
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve an enqueued asset URL to a filesystem path.
 *
 * Handles the three URL bases WordPress can hand back: the (possibly
 * relocated) content URL, the includes URL and the admin URL. Falls back
 * to ABSPATH for anything else under the home/site URL.
 *
 * @param string $url Asset URL, without query string.
 * @return string|false Absolute filesystem path, or false if it can't be resolved locally.
 */
function ls_starter_cachebusting_resolve_path( $url ) {
	$map = array(
		content_url()  => WP_CONTENT_DIR,
		includes_url() => ABSPATH . WPINC,
		admin_url()    => ABSPATH . 'wp-admin',
		site_url()     => ABSPATH,
	);

	foreach ( $map as $base_url => $base_dir ) {
		if ( ! $base_url ) {
			continue;
		}

		if ( 0 !== strpos( $url, $base_url ) ) {
			continue;
		}

		$relative = ltrim( substr( $url, strlen( $base_url ) ), '/' );
		$path     = trailingslashit( $base_dir ) . $relative;

		// Guard against path traversal in the resolved path.
		$real_base = wp_normalize_path( realpath( $base_dir ) );
		$real_path = wp_normalize_path( realpath( $path ) );

		if ( ! $real_base || ! $real_path ) {
			return false;
		}

		// Compare against a trailing-slashed base so a sibling directory whose
		// name merely starts with the base (wp-content-backup) is not treated
		// as inside it.
		if ( $real_path !== $real_base && 0 !== strpos( $real_path, trailingslashit( $real_base ) ) ) {
			return false;
		}

		return $real_path;
	}

	return false;
}

/**
 * Filter callback: rewrite the `ver` query arg on a local asset src to the
 * file's mtime, leaving every other query arg untouched.
 *
 * @param string $src    Asset URL as WordPress built it.
 * @param string $handle Registered handle for the script/style.
 * @return string Possibly rewritten asset URL.
 */
function ls_starter_cachebusting_filter_src( $src, $handle ) {
	if ( ! $src ) {
		return $src;
	}

	if ( ! apply_filters( 'ls_starter_cachebusting_enabled', true ) ) {
		return $src;
	}

	if ( apply_filters( 'ls_starter_cachebusting_skip_handle', false, $handle, $src ) ) {
		return $src;
	}

	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$src_host  = wp_parse_url( $src, PHP_URL_HOST );

	// Only rewrite local assets; leave external/CDN URLs untouched.
	if ( $src_host && $home_host && strtolower( $home_host ) !== strtolower( $src_host ) ) {
		return $src;
	}

	$parsed = wp_parse_url( $src );
	if ( empty( $parsed['path'] ) ) {
		return $src;
	}

	if ( empty( $parsed['host'] ) ) {
		// Relative URL (no host) — path is enough for local resolution.
		$clean_url = $parsed['path'];
	} elseif ( ! empty( $parsed['scheme'] ) ) {
		$clean_url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
	} else {
		// Protocol-relative URL.
		$clean_url = '//' . $parsed['host'] . $parsed['path'];
	}

	$mtime = ls_starter_cachebusting_get_mtime( $clean_url );
	if ( false === $mtime ) {
		// File not found on disk (or outside the resolvable roots); never emit a broken URL.
		return $src;
	}

	$existing_args = array();
	if ( ! empty( $parsed['query'] ) ) {
		wp_parse_str( $parsed['query'], $existing_args );
	}

	$existing_args['ver'] = $mtime;

	return $clean_url . '?' . build_query( $existing_args );
}
add_filter( 'script_loader_src', 'ls_starter_cachebusting_filter_src', 10, 2 );
add_filter( 'style_loader_src', 'ls_starter_cachebusting_filter_src', 10, 2 );

/**
 * Get (and request-scope cache) the mtime for a resolved local asset URL.
 *
 * Adds one filesystem stat per unique local asset per request; results are
 * cached in a static array for the lifetime of the request so repeated
 * enqueues of the same handle/URL don't re-stat the file.
 *
 * @param string $clean_url Asset URL with the query string already stripped.
 * @return int|false Unix timestamp, or false if the file can't be found/stat'd.
 */
function ls_starter_cachebusting_get_mtime( $clean_url ) {
	static $cache = array();

	if ( array_key_exists( $clean_url, $cache ) ) {
		return $cache[ $clean_url ];
	}

	$path = ls_starter_cachebusting_resolve_path( $clean_url );

	if ( ! $path || ! file_exists( $path ) ) {
		$cache[ $clean_url ] = false;
		return false;
	}

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- filemtime() can warn on a race (file removed between file_exists() and this call); the return-value check below is what actually decides the outcome.
	$mtime = @filemtime( $path );

	$cache[ $clean_url ] = false !== $mtime ? $mtime : false;

	return $cache[ $clean_url ];
}
