<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/includes
 */
class Emoji_Reaction {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @access   protected
	 * @var      Emoji_Reaction_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected Emoji_Reaction_Loader $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected string $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected string $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 */
	public function __construct() {
		if ( defined( 'EMOJI_REACTION_VERSION' ) ) {
			$this->version = EMOJI_REACTION_VERSION;
		} else {
			$this->version = '0.0.1';
		}
		$this->plugin_name = 'emoji-reaction';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Emoji_Reaction_Loader. Orchestrates the hooks of the plugin.
	 * - Emoji_Reaction_i18n. Defines internationalization functionality.
	 * - Emoji_Reaction_Admin. Defines all hooks for the admin area.
	 * - Emoji_Reaction_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @access   private
	 */
	private function load_dependencies(): void {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-emoji-reaction-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-emoji-reaction-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-emoji-reaction-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-emoji-reaction-public.php';

		/**
		 * The class responsible for chart functionality.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-emoji-reaction-chart.php';

		$this->loader = new Emoji_Reaction_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Emoji_Reaction_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @access   private
	 */
	private function set_locale(): void {

		$plugin_i18n = new Emoji_Reaction_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @access   private
	 */
	private function define_admin_hooks(): void {

		$plugin_admin = new Emoji_Reaction_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @access   private
	 */
	private function define_public_hooks(): void {

		$plugin_public = new Emoji_Reaction_Public( $this->get_plugin_name(), $this->get_version() );
		$plugin_chart  = new Emoji_Reaction_Chart( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		/**
		 * Ajax actions
		 */
		$this->loader->add_action( 'wp_ajax_emoji_reaction_ajax_save_action', $plugin_public, 'emoji_reaction_ajax_save_action' );
		$this->loader->add_action( 'wp_ajax_nopriv_emoji_reaction_ajax_save_action', $plugin_public, 'emoji_reaction_ajax_save_action' );

		/**
		 * Custom actions/filters, which can be inserted into a theme
		 * some infos on this: https://github.com/DevinVinson/WordPress-Plugin-Boilerplate/issues/218
		 */
		$this->loader->add_action( 'emoji_reaction_display_buttons', $plugin_public, 'display_buttons' );
		$this->loader->add_filter( 'emoji_reaction_emojis', $this, 'get_default_emojis' );
		$this->loader->add_filter( 'emoji_reaction_max_reactions_per_user', $this, 'get_default_max_reactions_per_user' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return   string    The name of the plugin.
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return   Emoji_Reaction_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader(): Emoji_Reaction_Loader {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return   string    The version number of the plugin.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Provide the list of emojis.
	 *
	 * @return   array    Default emojis used in the plugin.
	 */
	public static function get_default_emojis(): array {
		return array(
			array( '👍', __( 'thumbs up', 'emoji-reaction' ) ),
			array( '👎', __( 'thumbs down', 'emoji-reaction' ) ),
			array( '❤️', __( 'heart', 'emoji-reaction' ) ),
			array( '🔥', __( 'fire', 'emoji-reaction' ) ),
			array( '😂', __( 'laugh', 'emoji-reaction' ) ),
			array( '😮', __( 'surprised', 'emoji-reaction' ) ),
			array( '🍎', __( 'apple', 'emoji-reaction' ) ),
			array( '🍐', __( 'pear', 'emoji-reaction' ) ),
			array( '🍓', __( 'strawberry', 'emoji-reaction' ) ),
			array( '🍊', __( 'orange', 'emoji-reaction' ) ),
			array( '🍉', __( 'watermelon', 'emoji-reaction' ) ),
			array( '🍇', __( 'grapes', 'emoji-reaction' ) ),
		);
	}

	/**
	 * Provide the default maximum reactions per user.
	 *
	 * @return int    Default maximum reactions per user (1 means users can only add one reaction per post/comment).
	 */
	public static function get_default_max_reactions_per_user(): int {
		return 1;
	}
}
