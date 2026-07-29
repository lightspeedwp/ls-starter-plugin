<?php
/**
 * Redundant-plugin admin notice.
 *
 * Warns admins when a plugin is active whose function this codebase already
 * provides natively, so they can decide to deactivate it themselves.
 * Deliberately notice-only: never auto-deactivates, never filters the plugin
 * list, never blocks activation — a client should never be surprised by a
 * plugin disappearing.
 *
 * @package {{NAMESPACE}}
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of plugins this codebase already covers natively.
 *
 * Keyed by wp.org slug. Each entry also carries the plugin's `Name:` header
 * so a renamed/forked directory still matches via get_plugins(). Add entries
 * here as more native replacements ship — no other plumbing required.
 *
 * @return array<string, array{name: string, reason: string}>
 */
function ls_starter_redundant_plugins_registry() {
	return array(
		'cachebuster'        => array(
			'name'   => 'Cachebuster',
			'reason' => __( 'Asset cache-busting is handled natively by this plugin (filemtime-based versioning on script/style URLs).', '{{TEXT_DOMAIN}}' ),
		),
		// Safe SVG: native SVG-upload handling is not implemented yet. Wording
		// below reflects that; update it (and stop pointing at the tracking
		// issue) once a native replacement ships.
		'safe-svg'           => array(
			'name'   => 'Safe SVG',
			'reason' => __( 'This codebase does not yet include a native replacement for Safe SVG. Keep this plugin active until native SVG upload support ships; this notice is a placeholder for that tracking issue.', '{{TEXT_DOMAIN}}' ),
		),
		'change-mail-sender' => array(
			'name'   => 'Change Mail Sender',
			'reason' => __( 'The outgoing mail from-name/from-address is handled natively by this plugin.', '{{TEXT_DOMAIN}}' ),
		),
	);
}

/**
 * Determine whether a registry entry's plugin is active, matching on slug
 * (directory/file) first and falling back to the plugin Name header so a
 * renamed directory is still caught. Covers network-active multisite plugins.
 *
 * @param string $slug  wp.org slug, e.g. 'safe-svg'.
 * @param string $name  Plugin Name header to match as a fallback.
 * @return bool
 */
function ls_starter_redundant_plugin_is_active( $slug, $name ) {
	if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();

	foreach ( $all_plugins as $plugin_file => $plugin_data ) {
		$matches_slug = ( 0 === strpos( $plugin_file, $slug . '/' ) ) || ( $slug . '.php' === $plugin_file );
		$matches_name = isset( $plugin_data['Name'] ) && $name === $plugin_data['Name'];

		if ( ! $matches_slug && ! $matches_name ) {
			continue;
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return true;
		}

		if ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Render the dismissible admin notice for any active redundant plugins.
 */
function ls_starter_redundant_plugins_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$registry = ls_starter_redundant_plugins_registry();
	$found    = array();

	foreach ( $registry as $slug => $entry ) {
		if ( ls_starter_redundant_plugin_is_active( $slug, $entry['name'] ) ) {
			$found[ $slug ] = $entry;
		}
	}

	if ( empty( $found ) ) {
		return;
	}

	$dismissed = (array) get_user_meta( get_current_user_id(), 'ls_starter_dismissed_redundant_notices', true );

	// Re-show if a different redundant plugin appears later, even if an
	// earlier one was already dismissed.
	$to_show = array_diff_key( $found, array_flip( $dismissed ) );

	if ( empty( $to_show ) ) {
		return;
	}

	foreach ( $to_show as $slug => $entry ) {
		printf(
			'<div class="notice notice-warning is-dismissible ls-starter-redundant-plugin-notice" data-ls-starter-slug="%1$s"><p>%2$s</p></div>',
			esc_attr( $slug ),
			wp_kses_post(
				sprintf(
					/* translators: 1: plugin name, 2: reason this plugin's function is already covered. */
					__( '<strong>%1$s</strong> is active, but this site already handles that function natively: %2$s Consider deactivating %1$s once you have confirmed the native behaviour meets your needs.', '{{TEXT_DOMAIN}}' ),
					esc_html( $entry['name'] ),
					esc_html( $entry['reason'] )
				)
			)
		);
	}
	?>
	<script>
	( function() {
		var notices = document.querySelectorAll( '.ls-starter-redundant-plugin-notice' );
		notices.forEach( function( notice ) {
			notice.addEventListener( 'click', function( event ) {
				if ( ! event.target.classList.contains( 'notice-dismiss' ) ) {
					return;
				}
				var slug = notice.getAttribute( 'data-ls-starter-slug' );
				var data = new FormData();
				data.append( 'action', 'ls_starter_dismiss_redundant_notice' );
				data.append( 'slug', slug );
				data.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'ls_starter_dismiss_redundant_notice' ) ); ?>' );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } );
			} );
		} );
	} )();
	</script>
	<?php
}
add_action( 'admin_notices', 'ls_starter_redundant_plugins_notice' );

/**
 * AJAX handler: persist per-user dismissal of a redundant-plugin notice.
 */
function ls_starter_dismiss_redundant_notice() {
	check_ajax_referer( 'ls_starter_dismiss_redundant_notice', 'nonce' );

	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( null, 403 );
	}

	$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
	$registry = ls_starter_redundant_plugins_registry();

	if ( '' === $slug || ! isset( $registry[ $slug ] ) ) {
		wp_send_json_error( null, 400 );
	}

	$user_id   = get_current_user_id();
	$dismissed = (array) get_user_meta( $user_id, 'ls_starter_dismissed_redundant_notices', true );

	if ( ! in_array( $slug, $dismissed, true ) ) {
		$dismissed[] = $slug;
		update_user_meta( $user_id, 'ls_starter_dismissed_redundant_notices', $dismissed );
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_ls_starter_dismiss_redundant_notice', 'ls_starter_dismiss_redundant_notice' );
