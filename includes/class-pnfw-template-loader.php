<?php
/**
 * Template Loader
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template loader class.
 */
class PNFW_Template_Loader {

	/**
	 * Hook in methods.
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_loader' ) );
		add_action( 'wp_head', array( __CLASS__, 'noindex' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array(__CLASS__, 'exclude_from_sitemap'), 10, 2 );
	}

	/**
	 * Load a template.
	 *
	 * Handles template usage so that we can use our own templates instead of the theme's.
	 *
	 * Templates are in the 'templates' folder. Plugin looks for theme
	 * overrides in /theme/pnfw/ by default.
	 *
	 *
	 * @param string $template Template to load.
	 * @return string
	 */
	public static function template_loader( $template ) {
		if ( is_embed() ) {
			return $template;
		}

		$default_file = self::get_template_loader_default_file();

		if ( $default_file ) {
			/**
			 * Filter hook to choose which files to find before plugin does it's own logic.
			 *
			 * @since 3.0.0
			 * @var array
			 */
			$search_files = self::get_template_loader_files( $default_file );
			$template = locate_template( $search_files );

			if ( ! $template || PNFW_TEMPLATE_DEBUG_MODE ) {
				$template = PNFW_Push_Notifications_for_WordPress_Lite::instance()->plugin_path() . '/templates/' . $default_file;
			}
		}

		return $template;
	}

	/**
	 * Get the default filename for a template.
	 *
	 * @since  3.0.0
	 * @return string
	 */
	private static function get_template_loader_default_file() {
		if ( pnfw_is_activate_page() ) {
			$default_file = 'pnfw-activate.php';
		} else {
			$default_file = '';
		}
		return $default_file;
	}

	/**
	 * Get an array of filenames to search for a given template.
	 *
	 * @since  3.0.0
	 * @param  string $default_file The default file name.
	 * @return string[]
	 */
	private static function get_template_loader_files( $default_file ) {
		$templates = apply_filters( 'pnfw_template_loader_files', array(), $default_file );

		if ( is_page_template() ) {
			$templates[] = get_page_template_slug();
		}

		$templates[] = $default_file;
		$templates[] = PNFW_Push_Notifications_for_WordPress_Lite::instance()->template_path() . $default_file;

		return array_unique( $templates );
	}

	public static function noindex() {
		// See https://support.google.com/webmasters/answer/93710?hl=it
		if (pnfw_is_activate_page()) { ?>
			<meta name="robots" content="noindex,follow" />
		<?php }
	}

	public static function exclude_from_sitemap( $args, $post_type ) {
		if ( 'page' !== $post_type ) {
			return $args;
		}

		$args['post__not_in'] = isset( $args['post__not_in'] ) ? $args['post__not_in'] : array();
		$args['post__not_in'][] = pnfw_get_page_id('confirm');
		return $args;
	}
}

add_action( 'init', array( 'PNFW_Template_Loader', 'init' ) );
