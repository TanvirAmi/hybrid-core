<?php
/**
 * Handles the display and functionality of the theme settings page. This provides the needed hooks and
 * meta box calls for developers to create any number of theme settings needed.
 *
 * Provides the ability for developers to add custom meta boxes to the theme settings page by using the 
 * add_meta_box() function.  Developers should hook their meta box registration function to 'admin_menu' 
 * and register the meta box for 'appearance_page-theme-settings'. If data needs to be saved, devs can 
 * use the '$prefix_update_settings_page' action hook to save their data.
 *
 * @package HybridCore
 * @subpackage Admin
 */

/* Hook the settings page function to 'admin_menu'. */
add_action( 'admin_menu', 'hybrid_settings_page_init' );

/**
 * Initializes all the theme settings page functions. This function is used to create the theme settings 
 * page, then use that as a launchpad for specific actions that need to be tied to the settings page.
 *
 * Users or developers can set a custom capability (default is 'edit_theme_options') for access to the
 * settings page using the "$prefix_settings_capability" filter hook.
 *
 * @since 0.7.0
 * @global string $hybrid The global theme object.
 */
function hybrid_settings_page_init() {
	global $hybrid;

	/* Get theme information. */
	$theme_data = hybrid_get_theme_data();
	$prefix = hybrid_get_prefix();
	$domain = hybrid_get_textdomain();

	/* Register theme settings. */
	register_setting( "{$prefix}_theme_settings", "{$prefix}_theme_settings", 'hybrid_save_theme_settings' );

	/* Create the theme settings page. */
	$hybrid->settings_page = add_theme_page( sprintf( __( '%1$s Theme Settings', $domain ), $theme_data['Name'] ), sprintf( __( '%1$s Settings', $domain ), $theme_data['Name'] ), apply_filters( "{$prefix}_settings_capability", 'edit_theme_options' ), 'theme-settings', 'hybrid_settings_page' );

	/* Make sure the settings are saved. */
	add_action( "load-{$hybrid->settings_page}", 'hybrid_load_settings_page' );

	/* Load the theme settings meta boxes. */
	add_action( "load-{$hybrid->settings_page}", 'hybrid_load_theme_settings_meta_boxes' );

	/* Create a hook for adding meta boxes. */
	add_action( "load-{$hybrid->settings_page}", 'hybrid_add_meta_boxes' );

	/* Load the JavaScript and stylehsheets needed for the theme settings. */
	add_action( 'admin_enqueue_scripts', 'hybrid_settings_page_enqueue_script' );
	add_action( "load-{$hybrid->settings_page}", 'hybrid_admin_enqueue_style' );
	add_action( "admin_head-{$hybrid->settings_page}", 'hybrid_settings_page_load_scripts' );
}

/**
 * Returns the theme settings page name/hook as a string.
 *
 * @since 1.2.0
 */
function hybrid_get_settings_page_name() {
	global $hybrid;

	return ( isset( $hybrid->settings_page ) ? $hybrid->settings_page : 'appearance_page_theme-settings' );
}

/**
 * Provides a hook for adding meta boxes as seen on the post screen in the WordPress admin.  This addition 
 * is needed because normal plugin/theme pages don't have this hook by default.  The other goal of this 
 * function is to provide a way for themes to load and execute meta box code only on the theme settings 
 * page in the admin.  This way, they're not needlessly loading extra files.
 *
 * @since 1.2.0
 */
function hybrid_add_meta_boxes() {

	do_action( 'add_meta_boxes', hybrid_get_settings_page_name(), hybrid_get_theme_data() );
}

/**
 * Loads the meta boxes packaged with the framework on the theme settings page.  These meta boxes are 
 * merely loaded with this function.  Meta boxes are only loaded if the feature is supported by the theme.
 *
 * @since 1.2.0
 */
function hybrid_load_theme_settings_meta_boxes() {

	/* Load the 'About' meta box. */
	require_once( trailingslashit( HYBRID_ADMIN ) . 'meta-box-theme-about.php' );

	/* Load the 'Footer' meta box if it is supported. */
	require_if_theme_supports( 'hybrid-core-meta-box-footer', trailingslashit( HYBRID_ADMIN ) . 'meta-box-theme-footer.php' );
}

/**
 * Validation/Sanitization callback function for theme settings.  This just returns the data passed to it.  Theme
 * developers should validate/sanitize their theme settings on the "sanitize_option_{$prefix}_theme_settings" 
 * hook.  This function merely exists for backwards compatibility.
 *
 * @since 0.7.0
 * @param array $settings An array of the theme settings passed by the Settings API for validation.
 */
function hybrid_save_theme_settings( $settings ) {

	/* Allow developers to futher validate/sanitize the data. */
	/* @deprecated 1.0.0. Developers should filter "sanitize_option_{$prefix}_theme_settings" instead. */
	return apply_filters( hybrid_get_prefix() . '_validate_theme_settings', $settings );
}

/**
 * Creates an empty array of the default theme settings.  If the theme adds support for the 
 * 'hybrid-core-meta-box-footer' feature, it'll automatically add that setting to the $settings array.
 *
 * @since 1.0.0
 */
function hybrid_get_default_theme_settings() {

	/* Set up some default variables. */
	$settings = array();
	$domain = hybrid_get_textdomain();
	$prefix = hybrid_get_prefix();

	/* If the current theme supports the footer meta box and shortcodes, add default footer settings. */
	if ( current_theme_supports( 'hybrid-core-meta-box-footer' ) && current_theme_supports( 'hybrid-core-shortcodes' ) ) {

		/* If there is a child theme active, add the [child-link] shortcode to the $footer_insert. */
		if ( is_child_theme() )
			$settings['footer_insert'] = '<p class="copyright">' . __( 'Copyright &#169; [the-year] [site-link].', $domain ) . '</p>' . "\n\n" . '<p class="credit">' . __( 'Powered by [wp-link], [theme-link], and [child-link].', $domain ) . '</p>';

		/* If no child theme is active, leave out the [child-link] shortcode. */
		else
			$settings['footer_insert'] = '<p class="copyright">' . __( 'Copyright &#169; [the-year] [site-link].', $domain ) . '</p>' . "\n\n" . '<p class="credit">' . __( 'Powered by [wp-link] and [theme-link].', $domain ) . '</p>';
	}

	/* Backwards compatibility hook. @deprecated 1.0.0. */
	$settings = apply_filters( "{$prefix}_settings_args", $settings );

	/* Return the $settings array and provide a hook for overwriting the default settings. */
	return apply_filters( "{$prefix}_default_theme_settings", $settings );
}

/**
 * Saves the default theme settings in the {$wpdb->prefix}_options if none have been added.  The 
 * settings are given a unique name depending on the theme directory.  They are always saved as 
 * {$prefix}_theme_settings in the database. 
 *
 * @since 0.7.0
 */
function hybrid_load_settings_page() {
	$prefix = hybrid_get_prefix();

	/* Get theme settings from the database. */
	$settings = get_option( "{$prefix}_theme_settings" );

	/* If no settings are available, add the default settings to the database. */
	if ( false === $settings ) {
		$settings = hybrid_get_default_theme_settings();
		add_option( "{$prefix}_theme_settings", $settings, '', 'yes' );

		/* Redirect the page so that the settings are reflected on the settings page. */
		wp_redirect( admin_url( 'themes.php?page=theme-settings' ) );
		exit;
	}
}

/**
 * Displays the theme settings page and calls do_meta_boxes() to allow additional settings
 * meta boxes to be added to the page.
 *
 * @since 0.7.0
 * @global string $hybrid The global theme object.
 */
function hybrid_settings_page() {

	/* Get the theme information. */
	$prefix = hybrid_get_prefix();
	$domain = hybrid_get_textdomain();
	$theme_data = hybrid_get_theme_data(); ?>

	<div class="wrap">

		<?php screen_icon(); ?>

		<h2><?php printf( __( '%1$s Theme Settings', $domain ), $theme_data['Name'] ); ?></h2>

		<?php if ( isset( $_GET['settings-updated'] ) && 'true' == esc_attr( $_GET['settings-updated'] ) ) echo '<div class="updated"><p><strong>' . __( 'Settings saved.', $domain ) . '</strong></p></div>'; ?>

		<div id="poststuff">

			<form method="post" action="options.php">

				<?php settings_fields( "{$prefix}_theme_settings" ); ?>
				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>

				<div class="metabox-holder">
					<div class="post-box-container column-1 normal"><?php do_meta_boxes( hybrid_get_settings_page_name(), 'normal', null ); ?></div>
					<div class="post-box-container column-2 advanced"><?php do_meta_boxes( hybrid_get_settings_page_name(), 'advanced', null ); ?></div>
					<div class="post-box-container column-3 side"><?php do_meta_boxes( hybrid_get_settings_page_name(), 'side', null ); ?></div>
				</div>

				<p class="submit" style="clear: both;">
					<input type="submit" name="Submit"  class="button-primary" value="<?php esc_attr_e( 'Update Settings', $domain ); ?>" />
				</p><!-- .submit -->

			</form>

		</div><!-- #poststuff -->

	</div><!-- .wrap --><?php
}

/**
 * Creates a settings field id attribute for use on the theme settings page.  This is a helper function for use
 * with the WordPress settings API.
 *
 * @since 1.0.0
 */
function hybrid_settings_field_id( $setting ) {
	return hybrid_get_prefix() . "_theme_settings-{$setting}";
}

/**
 * Creates a settings field name attribute for use on the theme settings page.  This is a helper function for 
 * use with the WordPress settings API.
 *
 * @since 1.0.0
 */
function hybrid_settings_field_name( $setting ) {
	return hybrid_get_prefix() . "_theme_settings[{$setting}]";
}

/**
 * Loads the JavaScript files required for managing the meta boxes on the theme settings
 * page, which allows users to arrange the boxes to their liking.
 *
 * @since 0.7.0
 * @param string $hook_suffix The current page being viewed.
 */
function hybrid_settings_page_enqueue_script( $hook_suffix ) {

	if ( $hook_suffix == hybrid_get_settings_page_name() ) {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
	}
}

/**
 * Loads the JavaScript required for toggling the meta boxes on the theme settings page.
 *
 * @since 0.7.0
 * @global string $hybrid The global theme object.
 */
function hybrid_settings_page_load_scripts() { ?>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			postboxes.add_postbox_toggles( '<?php echo hybrid_get_settings_page_name(); ?>' );
		});
		//]]>
	</script><?php
}

?>