<?php
/**
 * UAEL Admin.
 *
 * @package UAEL
 */

namespace UltimateElementor\Classes;

use UltimateElementor\Classes\UAEL_Helper;
use UltimateElementor\Classes\UAEL_Maxmind_Database;
use \BSF_License_Manager;

if ( ! class_exists( 'UAEL_Admin' ) ) {

	/**
	 * Class UAEL_Admin.
	 */
	final class UAEL_Admin {

		/**
		 * Instance
		 * z
		 *
		 * @access private
		 * @var string Class object.
		 * @since 1.0.0
		 */
		private static $menu_slug = 'uaepro';

		/**
		 * Widget List
		 *
		 * @var widget_list
		 */
		private static $widget_list = null;

		/**
		 * Widget List
		 *
		 * @var free_widget_list
		 */
		private static $free_widget_list = null;

		/**
		 * Errors
		 *
		 * @access private
		 * @var array Errors strings.
		 * @since 1.0.0
		 */
		private static $errors = array();

		/**
		 * Product ID
		 *
		 * @access private
		 * @var string Product ID.
		 * @since 1.37.0
		 */
		private static $product_id = 'uael';

		/**
		 * Calls on initialization
		 *
		 * @since 0.0.1
		 */
		public static function init() {
			self::initialize_ajax();
			self::initialise_plugin();
			add_action( 'after_setup_theme', __CLASS__ . '::init_hooks' );
			add_action( 'elementor/init', __CLASS__ . '::load_admin', 0 );

			if ( is_admin() ) {
				global $pagenow;

				add_filter( 'bsf_product_name_uael', __CLASS__ . '::uael_whitelabel_name' );
				add_filter( 'bsf_product_description_uael', __CLASS__ . '::uael_whitelabel_description' );
				add_filter( 'bsf_product_author_uael', __CLASS__ . '::uael_whitelabel_author_name' );
				add_filter( 'bsf_product_homepage_uael', __CLASS__ . '::uael_whitelabel_author_url' );

				add_filter( 'all_plugins', __CLASS__ . '::change_plugin_details' );

				if ( 'Ultimate Addons for Elementor' !== self::uael_whitelabel_name() && 'update-core.php' === $pagenow ) {
					add_filter( 'gettext', __CLASS__ . '::get_plugin_branding_name' );
				}

				$branding = UAEL_Helper::get_white_labels();

				if ( 'disable' === $branding['enable_knowledgebase'] ) {
					add_filter( 'bsf_product_changelog_uael', '__return_empty_string' );
				}

				$integration_options = UAEL_Helper::get_integrations_options();
				$login_form_active   = UAEL_Helper::is_widget_active( 'LoginForm' );

				if ( $login_form_active && ( ! isset( $integration_options['facebook_app_secret'] ) || '' === $integration_options['facebook_app_secret'] ) && ( isset( $integration_options['facebook_app_id'] ) && '' !== $integration_options['facebook_app_id'] ) ) {
					add_action( 'admin_init', __CLASS__ . '::uael_login_form_notice' );
				}
			}

			self::$errors = array(
				'permission' => __( 'Sorry, you are not allowed to do this operation.', 'uael' ),
				'nonce'      => __( 'Nonce validation failed', 'uael' ),
				'default'    => __( 'Sorry, something went wrong.', 'uael' ),
			);

		}

		/**
		 * Add UAE Lite Branding support.
		 *
		 * Updates the plugin details with branding information based on white-labeling settings.
		 *
		 * @param array $plugins An array containing data for each plugin.
		 * @return array Filtered plugin data with branding changes applied.
		 */
		public static function change_plugin_details( $plugins ) {
			// Define the plugin file path relative to the plugins directory.
			$plugin_file = 'header-footer-elementor/header-footer-elementor.php';

			$branding = UAEL_Helper::get_white_labels();

			// Check if the plugin exists in the list.
			if ( isset( $plugins[ $plugin_file ] ) ) {
				// Change the plugin name.
				$branding_name = $branding['plugin']['name'];
				if ( ! empty( $branding_name ) ) {
					$plugins[ $plugin_file ]['Name'] = $branding_name . ' Lite';
				}

				// Change the plugin description.
				$branding_desc = $branding['plugin']['description'];
				if ( ! empty( $branding_desc ) ) {
					$plugins[ $plugin_file ]['Description'] = $branding_desc;
				}

				// Change the plugin author.
				$branding_author_name = $branding['agency']['author'];
				if ( ! empty( $branding_author_name ) ) {
					$plugins[ $plugin_file ]['Author']     = $branding_author_name;
					$plugins[ $plugin_file ]['AuthorName'] = $branding_author_name;
				}

				// Change the plugin author URL.
				$branding_url = $branding['agency']['author_url'];
				if ( ! empty( $branding_url ) ) {
					$plugins[ $plugin_file ]['AuthorURI'] = $branding_url;
				}
			}

			return $plugins;
		}

		/**
		 * Fires admin notice when Login Form facebook app secret key is not added.
		 *
		 * @since 1.21.0
		 *
		 * @return void
		 */
		public static function uael_login_form_notice() {

			// Check the user capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$uae_name = self::get_plugin_branding_name( 'Ultimate Addons for Elementor' );

			if ( ! isset( self::$widget_list ) ) {
				self::$widget_list = UAEL_Helper::get_widget_list();
			}

			$admin_link = self::$widget_list['LoginForm']['setting_url'];

			\Astra_Notices::add_notice(
				array(
					'id'                         => 'uael-login-facebook-notice',
					'type'                       => 'error',
					'message'                    => '<div class="notice-content">' . sprintf(
						/* translators: %s: html tags */

						__( 'With the new %1$s %3$s %2$s version 1.21.0 it is mandatory to add a Facebook App Secret Key for the Login Form widget.  You can add it from %1$s%4$shere%5$s%2$s. </br></br>This is to ensure extra security for the widget. In case your existing login form is not displaying Facebook login option, adding the App Secret Key will fix it.', 'uael' ),
						'<strong>',
						'</strong>',
						$uae_name,
						'<a href="' . $admin_link . '">',
						'</a>'
					) . '</div>',
					'display-with-other-notices' => true,
				)
			);
		}

		/**
		 *  Function that renders UAEL's branding Plugin Name on Updates page
		 *
		 *  @since 1.10.1
		 *  @param string $text gets an string for is plugin name.
		 *  @return string
		 */
		public static function get_plugin_branding_name( $text ) {

			if ( is_admin() && 'Ultimate Addons for Elementor' === $text ) {

				$branding      = UAEL_Helper::get_white_labels();
				$branding_name = $branding['plugin']['name'];

				if ( ! empty( $branding_name ) ) {
					$text = $branding_name;
				}
			}

			return $text;
		}

		/**
		 * Function that renders UAEL's branding Plugin name
		 *
		 * @since 1.10.1
		 */
		public static function uael_whitelabel_name() {
			$branding      = UAEL_Helper::get_white_labels();
			$branding_name = $branding['plugin']['name'];

			if ( empty( $branding_name ) ) {
				$branding_name = __( 'Ultimate Addons for Elementor', 'uael' );
			}
			return $branding_name;

		}

		/**
		 * Function that renders UAEL's branding Plugin Description
		 *
		 * @since 1.10.1
		 */
		public static function uael_whitelabel_description() {
			$branding      = UAEL_Helper::get_white_labels();
			$branding_desc = $branding['plugin']['description'];

			if ( empty( $branding_desc ) ) {
				$branding_desc = __( 'Ultimate Addons is a premium extension for Elementor that adds 35+ widgets and works on top of any Elementor Package (Free, Pro). You can use it with any WordPress theme.', 'uael' );
			}

			return $branding_desc;
		}

		/**
		 * Function that renders UAEL's branding Plugin Author name
		 *
		 * @since 1.10.1
		 */
		public static function uael_whitelabel_author_name() {
			$branding             = UAEL_Helper::get_white_labels();
			$branding_author_name = $branding['agency']['author'];

			if ( empty( $branding_author_name ) ) {
				$branding_author_name = __( 'Brainstorm Force', 'uael' );
			}

			return $branding_author_name;
		}

		/**
		 * Function that renders UAEL's branding Plugin Author URL
		 *
		 * @since 1.10.1
		 */
		public static function uael_whitelabel_author_url() {
			$branding     = UAEL_Helper::get_white_labels();
			$branding_url = $branding['agency']['author_url'];

			if ( empty( $branding_url ) ) {
				$branding_url = UAEL_DOMAIN;
			}
			return $branding_url;

		}

		/**
		 * Defines all constants
		 *
		 * @since 0.0.1
		 */
		public static function load_admin() {
			add_action( 'elementor/editor/after_enqueue_styles', __CLASS__ . '::uael_admin_enqueue_scripts' );
		}

		/**
		 * Enqueue admin scripts
		 *
		 * @since 0.0.1
		 * @param string $hook Current page hook.
		 * @access public
		 */
		public static function uael_admin_enqueue_scripts( $hook ) {

			// Register styles.
			wp_register_style(
				'uael-style',
				UAEL_URL . 'editor-assets/css/style.css',
				array(),
				UAEL_VER
			);

			wp_enqueue_style( 'uael-style' );

			$branding       = UAEL_Helper::get_white_labels();
			$is_lite_active = UAEL_Helper::is_lite_active();

			if ( isset( $branding['plugin']['short_name'] ) && '' !== $branding['plugin']['short_name'] ) {
				$short_name = $branding['plugin']['short_name'];
				$custom_css = '.elementor-element [class*="uael-icon-"]:after {';
				if ( $is_lite_active ) {
					$custom_css = '.elementor-element [class*="uael-icon-"]:after, .elementor-element [class*="hfe-icon-"]:after {';
				}
				$custom_css .= 'content: "' . $short_name . '"; }';
				wp_add_inline_style( 'uael-style', $custom_css );
			}
		}

		/**
		 * Adds the admin menu and enqueues CSS/JS if we are on
		 * the builder admin settings page.
		 *
		 * @since 0.0.1
		 * @return void
		 */
		public static function init_hooks() {
			if ( ! is_admin() ) {
				return;
			}

			// Add UAEL menu option to admin.
			add_action( 'network_admin_menu', __CLASS__ . '::menu' );
			add_action( 'admin_menu', __CLASS__ . '::menu' );
			add_action( 'admin_init', __CLASS__ . '::render_styles' );

			// Filter to White labled options.
			add_filter( 'all_plugins', __CLASS__ . '::plugins_page' );

			add_action(
				'current_screen',
				function () {
					$current_screen = get_current_screen();
					if ( $current_screen && ( 'edit-elementor-hf' === $current_screen->id || 'elementor-hf' === $current_screen->id ) ) {
						add_action(
							'in_admin_header',
							function () {
								self::render_admin_top_bar();
							} 
						);
					}
				} 
			);

			/* Flow content view */
			add_action( 'uael_render_admin_page_content', __CLASS__ . '::react_content', 10, 2 );
			add_action( 'admin_enqueue_scripts', __CLASS__ . '::update_uae_page', 10, 2 );

		}

		/**
		 * Update strings on the update-core.php page.
		 *
		 * @since 1.37.3
		 * @return void
		 */
		public static function render_admin_top_bar() {
			?>
			<div id="hfe-admin-top-bar-root">
			</div>
			<?php
		}

		/**
		 * Update strings on the update-core.php page.
		 *
		 * @since 1.37.0
		 * @return void
		 */
		public static function update_uae_page() {

			$replaced_logo = UAEL_Helper::replaced_logo_url();
			$hide_logo     = UAEL_Helper::is_replace_logo();
			$uae_logo      = $hide_logo ? '' : UAEL_URL . 'assets/images/settings/dashboard-logo.svg';
			$white_logo    = $hide_logo ? '' : UAEL_URL . 'assets/images/settings/white-logo.svg';

			if ( '' !== $replaced_logo ) {
				$uae_logo   = $replaced_logo;
				$white_logo = $replaced_logo;
			}

			if ( '' !== $uae_logo && '' !== $white_logo ) {

				// Add inline styles.
				$custom_css = '
					#toplevel_page_uaepro .wp-menu-image {
						background-image: url(' . esc_url( $uae_logo ) . ') !important;
						background-size: 23px 34px !important;
						background-repeat: no-repeat;
						background-position: center;
					}
					#toplevel_page_uaepro.wp-menu-open .wp-menu-image,
					#toplevel_page_uaepro .wp-has-current-submenu .wp-menu-image {
						background-image: url(' . esc_url( $white_logo ) . ') !important;
					}
				';
				
				wp_add_inline_style( 'wp-admin', $custom_css );
			}

		}

		/**
		 * Renders the admin settings content.
		 *
		 * @since 1.37.0
		 *
		 * @return void
		 */
		public static function react_content() {

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( self::is_current_page( 'uaepro' ) ) {
				include_once UAEL_DIR . 'includes/admin/uael-settings-app.php';
			}
		}

		
		/**
		 * CHeck if it is current page by parameters
		 *
		 * @param string $page_slug Menu name.
		 * @param string $action Menu name.
		 *
		 * @return  string page url
		 */
		public static function is_current_page( $page_slug = '', $action = '' ) {

			$page_matched = false;

			if ( empty( $page_slug ) ) {
				return false;
			}

			$current_page_slug = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_action    = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! is_array( $action ) ) {
				$action = explode( ' ', $action );
			}

			if ( $page_slug === $current_page_slug && in_array( $current_action, $action, true ) ) {
				$page_matched = true;
			}

			return $page_matched;
		}

		/**
		 * Initialises the Plugin Name.
		 *
		 * @since 0.0.1
		 * @return void
		 */
		public static function initialise_plugin() {

			$branding_settings = UAEL_Helper::get_white_labels();

			if (
				isset( $branding_settings['plugin']['name'] ) &&
				'' !== $branding_settings['plugin']['name']
			) {
				$name = $branding_settings['plugin']['name'];
			} else {
				$name = 'Ultimate Addons for Elementor';
			}

			if (
				isset( $branding_settings['plugin']['short_name'] ) &&
				'' !== $branding_settings['plugin']['short_name']
			) {
				$short_name = $branding_settings['plugin']['short_name'];
			} else {
				$short_name = 'UAE';
			}

			define( 'UAEL_PLUGIN_NAME', $name );
			define( 'UAEL_PLUGIN_SHORT_NAME', $short_name );
		}

		/**
		 * Register Menu pages.
		 */
		public static function menu() {

			$menu_slug = self::$menu_slug;
		
			// Check the user capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
		
			$_REQUEST['uael_admin_nonce'] = wp_create_nonce( 'uael_admin_nonce' );

			// Add main menu page.
			add_menu_page(
				UAEL_PLUGIN_SHORT_NAME,
				UAEL_PLUGIN_SHORT_NAME,
				'manage_options',
				'uaepro',
				__CLASS__ . '::render',
				'none',
				'59'
			);
		
			// Add sub-menu pages with different sections (linked to render with page actions).
			add_submenu_page(
				'uaepro',                                       // Parent slug.
				UAEL_PLUGIN_SHORT_NAME,                      // Page title.
				__( 'Dashboard', 'uael' ),                      // Menu title.
				'manage_options',                               // Capability.
				$menu_slug,                      // Menu slug with page hash.
				__CLASS__ . '::render',                              // Callback method.
			);
		
			add_submenu_page(
				'uaepro',                                       // Parent slug.
				__( 'Widgets & Features', 'uael' ),             // Page title.
				__( 'Widgets', 'uael' ),                        // Menu title.
				'manage_options',                               // Capability.
				$menu_slug . '#widgets',                        // Menu slug with page hash.
				__CLASS__ . '::render',                             // Callback method.
			);
		
			add_submenu_page(
				'uaepro',                                       // Parent slug.
				__( 'Templates', 'uael' ),                      // Page title.
				__( 'Templates', 'uael' ),                      // Menu title.
				'manage_options',                               // Capability.
				$menu_slug . '#templates',                      // Menu slug with page hash.
				__CLASS__ . '::render',                            // Callback method.
			);
		
			add_submenu_page(
				'uaepro',                                       // Parent slug.
				__( 'Settings', 'uael' ),                       // Page title.
				__( 'Settings', 'uael' ),                       // Menu title.
				'manage_options',                               // Capability.
				$menu_slug . '#settings',                       // Menu slug with page hash.
				__CLASS__ . '::render',                             // Callback method.
			);
		
		}

		/**
		 * Enqueues CSS/JS if we are on the builder admin settings page.
		 *
		 * @since 1.22.1
		 * @return void
		 */
		public static function render_styles() {
			if ( isset( $_REQUEST['uael_admin_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_REQUEST['uael_admin_nonce'] ), 'uael_admin_nonce' ) ) {
				if ( isset( $_REQUEST['page'] ) ) {

					if ( 'uaepro' === $_REQUEST['page'] ) {
						add_action( 'admin_enqueue_scripts', __CLASS__ . '::enqueue_react_scripts' );
					}
				}
			}

			if ( ( isset( $_GET['post_type'] ) && 'elementor-hf' === sanitize_text_field( $_GET['post_type'] ) && 
					( 'edit.php' === $GLOBALS['pagenow'] || 'post.php' === $GLOBALS['pagenow'] || 'post-new.php' === $GLOBALS['pagenow'] ) ) ||
					( isset( $_GET['post'] ) && 'post.php' === $GLOBALS['pagenow'] && isset( $_GET['action'] ) && 'edit' === sanitize_text_field( $_GET['action'] ) && 'elementor-hf' === get_post_type( sanitize_text_field( $_GET['post'] ) ) )
				) {
				add_action( 'admin_enqueue_scripts', __CLASS__ . '::enqueue_react_scripts' );
			}
			
		}

		
		/**
		 * Check if the license is active.
		 *
		 * @return bool
		 * @since 1.37.0
		 */
		public static function is_license_active() {

			if ( ! class_exists( 'BSF_License_Manager' ) ) {
				return false;
			}

			return BSF_License_Manager::bsf_is_active_license( self::$product_id );
		}

		/**
		 * Load admin styles on UAEL settings screen.
		 *
		 * @return void
		 */
		public static function enqueue_react_scripts() {

			global $pagenow, $post_type;

			$replaced_logo      = UAEL_Helper::replaced_logo_url();
			$hide_logo          = UAEL_Helper::is_replace_logo();
			$hide_whitelabel    = UAEL_Helper::is_hide_branding();
			$is_lite_active     = UAEL_Helper::is_lite_active(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			$st_status          = UAEL_Helper::free_starter_templates_status();
			$stpro_status       = UAEL_Helper::premium_starter_templates_status();
			$st_link            = UAEL_Helper::starter_templates_link();
			$show_theme_support = 'no';
			$hfe_theme_status   = get_option( 'hfe_is_theme_supported', false );
			$rollback_version   = isset( self::uael_get_rollback_versions( 'uael' )[0] ) ? self::uael_get_rollback_versions( 'uael' )[0] : '';
			$hfe_post_url       = $is_lite_active ? admin_url( 'post-new.php?post_type=elementor-hf' ) : '';
			$is_hfe_post        = ( 'elementor-hf' === $post_type && ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) ) ? 'yes' : 'no';

			// UAE Lite rollback versions.
			$free_versions = self::uaelite_get_rollback_versions();
			if ( $is_lite_active &&
				( ! current_theme_supports( 'header-footer-elementor' ) ) &&
				! $hfe_theme_status
			) {
				$show_theme_support = 'yes';
			}
			$theme_option = get_option( 'hfe_compatibility_option', '1' );

			
			$beta_enabled = UAEL_Helper::get_admin_settings_option( '_uael_beta', 'disable' );
			$uae_logo     = $hide_logo ? '' : UAEL_URL . 'assets/images/settings/logo.svg';

			if ( '' !== $replaced_logo ) {
				$uae_logo = $replaced_logo;
			}

			wp_enqueue_script(
				'uael-react-app',
				UAEL_URL . 'build/main.js',
				array( 'wp-element', 'wp-dom-ready', 'wp-api-fetch' ),
				UAEL_VER,
				true
			);

			wp_enqueue_style(
				'uael-react-styles',
				UAEL_URL . 'build/main.css',
				array(),
				UAEL_VER
			);

			wp_register_style(
				'uael-style',
				UAEL_URL . 'editor-assets/css/style.css',
				array(),
				UAEL_VER
			);

			wp_enqueue_style( 'uael-style' );

			wp_localize_script(
				'uael-react-app',
				'uaelSettingsData',
				array(
					'license_activation_nonce'            => wp_create_nonce( 'uael_license_activation' ),
					'license_deactivation_nonce'          => wp_create_nonce( 'uael_license_deactivation' ),
					'license_status'                      => self::is_license_active(),
					'bsf_graupi_nonce'                    => wp_create_nonce( 'bsf_license_activation_deactivation_nonce' ),
					'uael_nonce_action'                   => wp_create_nonce( 'wp_rest' ),
					'installer_nonce'                     => wp_create_nonce( 'updates' ),
					'ajax_url'                            => admin_url( 'admin-ajax.php' ),
					'ajax_nonce'                          => wp_create_nonce( 'uael-widget-nonce' ),
					'templates_url'                       => UAEL_URL . 'assets/images/settings/starter-templates.png',
					'column_url'                          => UAEL_URL . 'assets/images/settings/column.png',
					'template_url'                        => UAEL_URL . 'assets/images/settings/template.png',
					'icon_url'                            => $uae_logo,
					'theme_url'                           => UAEL_URL . 'assets/images/settings/editor.svg',
					'theme__selected_url'                 => UAEL_URL . 'assets/images/settings/theme-selected.svg',
					'user__selected_url'                  => UAEL_URL . 'assets/images/settings/user-selected.svg',
					'version__selected_url'               => UAEL_URL . 'assets/images/settings/git-compare.svg',
					'integrations__selected_url'          => UAEL_URL . 'assets/images/settings/integrations-selected.svg',
					'version_url'                         => UAEL_URL . 'assets/images/settings/version.svg',
					'integrations_url'                    => UAEL_URL . 'assets/images/settings/integrations.svg',
					'postskins_url'                       => UAEL_URL . 'assets/images/settings/Post-Skin.svg',
					'postskins_selected_url'              => UAEL_URL . 'assets/images/settings/Post-Skin-Selected.svg',
					'user_url'                            => UAEL_URL . 'assets/images/settings/user.svg',
					'info_url'                            => UAEL_URL . 'assets/images/settings/info.svg',
					'branding_url'                        => UAEL_URL . 'assets/images/settings/branding.svg',
					'branding__selected_url'              => UAEL_URL . 'assets/images/settings/branding-selected.svg',
					'video_control'                       => UAEL_URL . 'assets/images/settings/video-control.svg',
					'business_skin'                       => UAEL_URL . 'assets/images/settings/uae-post-skin-business.png',
					'pro_badge'                           => UAEL_URL . 'assets/images/settings/badge.svg',
					'core_badge'                          => UAEL_URL . 'assets/images/settings/core-badge.svg',
					'beta_enabled'                        => $beta_enabled,
					'elementor_page_url'                  => self::get_elementor_new_page_url(),
					'hide_settings'                       => $hide_whitelabel,
					'plugin_name'                         => UAEL_PLUGIN_NAME,
					'plugin_short_name'                   => UAEL_PLUGIN_SHORT_NAME,
					'is_lite_active'                      => $is_lite_active, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
					'st_link'                             => $st_link,
					'theme_option'                        => $theme_option,
					'show_theme_support'                  => $show_theme_support,
					'uael_domain'                         => UAEL_DOMAIN,
					'st_status'                           => $st_status,
					'enable_internal_links'               => UAEL_Helper::is_internal_links(),
					'is_bsf_package'                      => UAEL_BSF_PACKAGE,
					'license_status'                      => UAEL_BSF_PACKAGE ? BSF_License_Manager::bsf_is_active_license( 'uael' ) : false,
					'uael_versions'                       => self::uael_get_rollback_versions( 'uael' ),
					'uael_rollback_nonce_url'             => esc_url( add_query_arg( 'version_no', $rollback_version, wp_nonce_url( admin_url( 'index.php?action=bsf_rollback&product_id=' . self::$product_id ), 'bsf_rollback' ) ) ),
					'uael_rollback_nonce_placeholder_url' => esc_url( wp_nonce_url( admin_url( 'index.php?action=bsf_rollback&version_no=VERSION&product_id=' . self::$product_id ), 'bsf_rollback' ) ),
					'uael_rollback_url'                   => esc_url( admin_url() . 'index.php?action=bsf_rollback&version_no=VERSION&product_id=uael&_wpnonce=' . wp_create_nonce( 'bsf_rollback' ) ),
					'maxmind_db_path'                     => UAEL_Helper::get_maxmind_database_path(),
					'uaelite_previous_version'            => isset( $free_versions[0]['value'] ) ? $free_versions[0]['value'] : '',
					'uaelite_versions'                    => $free_versions,
					'uaelite_rollback_url'                => esc_url( add_query_arg( 'version', 'VERSION', wp_nonce_url( admin_url( 'admin-post.php?action=uaelite_rollback' ), 'uaelite_rollback' ) ) ),
					'uael_current_version'                => UAEL_VER,
					'uaelite_current_version'             => $is_lite_active && defined( 'HFE_VER' ) ? HFE_VER : '',
					'uaepro_settings_url'                 => admin_url( 'admin.php?page=uaepro' ),
					'header_footer_builder'               => $is_lite_active ? admin_url( 'edit.php?post_type=elementor-hf' ) : '',
					'st_pro_status'                       => $stpro_status,
					'uael_hfe_post_url'                   => $hfe_post_url,
					'is_hfe_post'                         => $is_hfe_post,
				)
			);
		}

		/**
		 * Get UAE Lite Rollback versions.
		 *
		 * @return array
		 * @since 1.37.0
		 */
		public static function uaelite_get_rollback_versions() {
			// Activate all free widgets.
			$is_lite_active = UAEL_Helper::is_lite_active(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			
			if ( $is_lite_active && class_exists( '\HFE\WidgetsManager\Base\HFE_Helper' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

				$hfe_helper = new \HFE\WidgetsManager\Base\HFE_Helper();
				return $hfe_helper::get_rollback_versions_options();
			}

			return '';
		}

		/**
		 * Get UAE Pro Rollback versions.
		 *
		 * @param string $product UAE.
		 * @return array
		 * @since 1.37.0
		 */
		public static function uael_get_rollback_versions( $product = 'uael' ) {
			$rollback_versions_options = array();

			if ( UAEL_BSF_PACKAGE && 'uael' === $product ) {
				$product_id        = self::$product_id;
				$product_details   = get_brainstorm_product( $product_id );
				$installed_version = isset( $product_details['version'] ) ? $product_details['version'] : '';
				$product_versions  = \BSF_Rollback_Version::bsf_get_product_versions( $product_id ); // Get Remote versions
				// Show versions above than latest install version of the product.
				$rollback_versions = \BSF_Rollback_Version::sort_product_versions( $product_versions, $installed_version );

				foreach ( $rollback_versions as $version ) {

					$version = array(
						'label' => $version,
						'value' => $version,
					);

					$rollback_versions_options[] = $version;
				}
			}

			return $rollback_versions_options;
		}
		

		/**
		 * Get Elementor edit page link
		 */
		public static function get_elementor_new_page_url() {

			if ( class_exists( '\Elementor\Plugin' ) && current_user_can( 'edit_pages' ) ) {
				// Ensure Elementor is loaded.
				$query_args = array(
					'action'    => 'elementor_new_post',
					'post_type' => 'page',
				);
		
				$new_post_url = add_query_arg( $query_args, admin_url( 'edit.php' ) );
		
				$new_post_url = add_query_arg( '_wpnonce', wp_create_nonce( 'elementor_action_new_post' ), $new_post_url );
		
				return $new_post_url;
			}
			return '';
		}

		/**
		 * Renders the admin settings.
		 *
		 * @since 0.0.1
		 * @return void
		 */
		public static function render() {

			include_once UAEL_DIR . 'includes/admin/uael-admin-base.php';

		}

		/**
		 * Branding addon on the plugins page.
		 *
		 * @since 0.0.1
		 * @param array $plugins An array data for each plugin.
		 * @return array
		 */
		public static function plugins_page( $plugins ) {

			$branding = UAEL_Helper::get_white_labels();
			$basename = plugin_basename( UAEL_DIR . 'ultimate-elementor.php' );

			if ( isset( $plugins[ $basename ] ) && is_array( $branding ) ) {

				$plugin_name = ( isset( $branding['plugin']['name'] ) && '' !== $branding['plugin']['name'] ) ? $branding['plugin']['name'] : '';
				$plugin_desc = ( isset( $branding['plugin']['description'] ) && '' !== $branding['plugin']['description'] ) ? $branding['plugin']['description'] : '';
				$author_name = ( isset( $branding['agency']['author'] ) && '' !== $branding['agency']['author'] ) ? $branding['agency']['author'] : '';
				$author_url  = ( isset( $branding['agency']['author_url'] ) && '' !== $branding['agency']['author_url'] ) ? $branding['agency']['author_url'] : '';

				if ( '' !== $plugin_name ) {
					$plugins[ $basename ]['Name']  = $plugin_name;
					$plugins[ $basename ]['Title'] = $plugin_name;
				}

				if ( '' !== $plugin_desc ) {
					$plugins[ $basename ]['Description'] = $plugin_desc;
				}

				if ( '' !== $author_name ) {
					$plugins[ $basename ]['Author']     = $author_name;
					$plugins[ $basename ]['AuthorName'] = $author_name;
				}

				if ( '' !== $author_url ) {
					$plugins[ $basename ]['AuthorURI'] = $author_url;
					$plugins[ $basename ]['PluginURI'] = $author_url;
				}
			}
			return $plugins;
		}

		/**
		 * Initialize Ajax
		 */
		public static function initialize_ajax() {

			// Check the user capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Ajax requests.

			add_action( 'wp_ajax_uael_license_activation', __CLASS__ . '::license_activation' );
			add_action( 'wp_ajax_uael_license_deactivation', __CLASS__ . '::license_deactivation' );

			add_action( 'wp_ajax_uael_activate_widget', __CLASS__ . '::activate_widget' );
			add_action( 'wp_ajax_uael_deactivate_widget', __CLASS__ . '::deactivate_widget' );

			add_action( 'wp_ajax_uael_bulk_activate_widgets', __CLASS__ . '::bulk_activate_widgets' );
			add_action( 'wp_ajax_uael_bulk_deactivate_widgets', __CLASS__ . '::bulk_deactivate_widgets' );

			add_action( 'wp_ajax_uael_bulk_activate_skins', __CLASS__ . '::bulk_activate_skins' );
			add_action( 'wp_ajax_uael_bulk_deactivate_skins', __CLASS__ . '::bulk_deactivate_skins' );

			add_action( 'wp_ajax_uael_allow_beta_updates', __CLASS__ . '::allow_beta_updates' );

			add_action( 'wp_ajax_uael_recommended_plugin_activate', __CLASS__ . '::activate_addon' );
			add_action( 'wp_ajax_uael_recommended_plugin_install', 'wp_ajax_install_plugin' );
			add_action( 'wp_ajax_uael_recommended_theme_install', 'wp_ajax_install_theme' );

			add_action( 'wp_ajax_save_hfe_compatibility_option', __CLASS__ . '::save_hfe_compatibility_option_callback' );
		}

		/**
		 * Save HFE compatibility option via AJAX.
		 *
		 * @since 1.37.0
		 * @return void
		 */
		public static function save_hfe_compatibility_option_callback() {
			// Check nonce for security.
			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			if ( isset( $_POST['hfe_compatibility_option'] ) ) {
				// Sanitize and update option.
				$option = sanitize_text_field( $_POST['hfe_compatibility_option'] );
				update_option( 'hfe_compatibility_option', $option );
		
				// Return a success response.
				wp_send_json_success( 'Option saved successfully!' );
			} else {
				// Return an error response if the option is not set.
				wp_send_json_error( 'Option not set.' );
			}
		}

		/**
		 * License Deactivation AJAX
		 *
		 * @Hooked - wp_ajax_uael_license_activation
		 *
		 * @return void
		 * @since 1.37.0
		 */
		public static function license_activation() {

			if ( ! class_exists( 'BSF_License_Manager' ) ) {
				wp_send_json_error( array( 'message' => self::$errors['default'] ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => self::$errors['permission'] ) );
			}
			if ( ! check_ajax_referer( 'uael_license_activation', 'security', false ) ) {
				wp_send_json_error( array( 'message' => self::$errors['nonce'] ) );
			}
		
			if ( ! isset( $_POST['key'] ) ) {
				wp_send_json_error( array( 'message' => __( 'License key not found.', 'uael' ) ) );
			}
		
			$license_key = sanitize_text_field( $_POST['key'] );
			$data        = array(
				'privacy_consent'          => true,
				'terms_conditions_consent' => true,
				'product_id'               => self::$product_id,
				'license_key'              => $license_key,
			);

			if ( method_exists( BSF_License_Manager::instance(), 'bsf_process_license_activation' ) ) {
				$result = BSF_License_Manager::instance()->bsf_process_license_activation( $data );

				if ( ! is_bool( $result ) && ! $result['success'] ) {
					wp_send_json_error(
						array(
							'success' => false,
							'message' => $result['message'],
						)
					);
				}
			
				wp_send_json_success(
					array(
						'success' => true,
						'message' => __( 'License Successfully Activated', 'uael' ),
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'License activation failed!', 'uael' ) ) );
			}
		
		}

		/**
		 * License Deactivation AJAX
		 *
		 * @Hooked - wp_ajax_uag_license_deactivation
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public static function license_deactivation() {

			if ( ! class_exists( 'BSF_License_Manager' ) ) {
				wp_send_json_error( array( 'message' => self::$errors['default'] ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => self::$errors['permission'] ) );
			}
			if ( ! check_ajax_referer( 'uael_license_deactivation', 'security', false ) ) {
				wp_send_json_error( array( 'message' => self::$errors['nonce'] ) );
			}
		
			if ( method_exists( BSF_License_Manager::instance(), 'process_license_deactivation' ) ) {
				$result = BSF_License_Manager::instance()->process_license_deactivation( self::$product_id );

				if ( isset( $result['success'] ) && ! $result['success'] ) {
					wp_send_json_error(
						array(
							'success' => false,
							'message' => $result['message'],
						)
					);
				}
			
				wp_send_json_success(
					array(
						'success' => true,
						'message' => __( 'License Successfully Deactivated', 'uael' ),
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'License deactivation failed!', 'uael' ) ) );
			}
		
		}

		/**
		 * Activate addon.
		 *
		 * @since 1.6.0
		 * @return void
		 */
		public static function activate_addon() {

			// Run a security check.
			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			if ( isset( $_POST['plugin'] ) ) {

				$type = '';
				if ( ! empty( $_POST['type'] ) ) {
					$type = sanitize_key( wp_unslash( $_POST['type'] ) );
				}

				$plugin = sanitize_text_field( wp_unslash( $_POST['plugin'] ) );

				if ( 'plugin' === $type ) {

					// Check for permissions.
					if ( ! current_user_can( 'activate_plugins' ) ) {
						wp_send_json_error( esc_html__( 'Plugin activation is disabled for you on this site.', 'uael' ) );
					}

					$activate = activate_plugins( $plugin );

					if ( ! is_wp_error( $activate ) ) {

						do_action( 'uael_plugin_activated', $plugin );

						wp_send_json_success( esc_html__( 'Plugin Activated.', 'uael' ) );
					}
				}

				if ( 'theme' === $type ) {

					if ( isset( $_POST['slug'] ) ) {
						$slug = sanitize_key( wp_unslash( $_POST['slug'] ) );

						// Check for permissions.
						if ( ! ( current_user_can( 'switch_themes' ) ) ) {
							wp_send_json_error( esc_html__( 'Theme activation is disabled for you on this site.', 'uael' ) );
						}

						$activate = switch_theme( $slug );

						if ( ! is_wp_error( $activate ) ) {

							do_action( 'uael_theme_activated', $plugin );

							wp_send_json_success( esc_html__( 'Theme Activated.', 'uael' ) );
						}
					}
				}
			}

			if ( 'plugin' === $type ) {
				wp_send_json_error( esc_html__( 'Could not activate plugin. Please activate from the Plugins page.', 'uael' ) );
			} elseif ( 'theme' === $type ) {
				wp_send_json_error( esc_html__( 'Could not activate theme. Please activate from the Themes page.', 'uael' ) );
			}
		}

		/**
		 * Activate module
		 */
		public static function activate_widget() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			$module_id      = isset( $_POST['module_id'] ) ? sanitize_text_field( $_POST['module_id'] ) : '';
			$is_pro         = isset( $_POST['is_pro'] ) ? sanitize_text_field( $_POST['is_pro'] ) : '';
			$is_lite_active = UAEL_Helper::is_lite_active(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			
			if ( 'false' === $is_pro ) {
				if ( $is_lite_active ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
					
					$widgets               = UAEL_Helper::get_admin_settings_option( '_hfe_widgets', array() );
					$widgets[ $module_id ] = $module_id;
					$widgets               = array_map( 'esc_attr', $widgets );

					// Update widgets.
					UAEL_Helper::update_admin_settings_option( '_hfe_widgets', $widgets );
				}
			} else {
				$widgets               = UAEL_Helper::get_admin_settings_option( '_uael_widgets', array() );
				$widgets[ $module_id ] = $module_id;
				$widgets               = array_map( 'esc_attr', $widgets );

				// Update widgets.
				UAEL_Helper::update_admin_settings_option( '_uael_widgets', $widgets );
				UAEL_Helper::create_specific_stylesheet();
			}

			wp_send_json_success( $module_id );
		}

		/**
		 * Deactivate module
		 */
		public static function deactivate_widget() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			$module_id      = isset( $_POST['module_id'] ) ? sanitize_text_field( $_POST['module_id'] ) : '';
			$is_pro         = isset( $_POST['is_pro'] ) ? sanitize_text_field( $_POST['is_pro'] ) : '';
			$is_lite_active = UAEL_Helper::is_lite_active(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

			if ( 'false' === $is_pro ) {
				if ( $is_lite_active ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
					$widgets               = UAEL_Helper::get_admin_settings_option( '_hfe_widgets', array() );
					$widgets[ $module_id ] = 'disabled';
					$widgets               = array_map( 'esc_attr', $widgets );

					// Update widgets.
					UAEL_Helper::update_admin_settings_option( '_hfe_widgets', $widgets );
				}
			} else {
				$widgets               = UAEL_Helper::get_admin_settings_option( '_uael_widgets', array() );
				$widgets[ $module_id ] = 'disabled';
				$widgets               = array_map( 'esc_attr', $widgets );

				// Update widgets.
				UAEL_Helper::update_admin_settings_option( '_uael_widgets', $widgets );
				UAEL_Helper::create_specific_stylesheet();
			}

			wp_send_json_success( $module_id );
		}

		/**
		 * Activate all module
		 */
		public static function bulk_activate_widgets() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			if ( ! isset( self::$widget_list ) ) {
				self::$widget_list = UAEL_Helper::get_widget_list();
			}

			$new_widgets = array();

			// Set all extension to enabled.
			foreach ( self::$widget_list  as $slug => $value ) {
				$new_widgets[ $slug ] = $slug;
			}

			// Escape attrs.
			$new_widgets = array_map( 'esc_attr', $new_widgets );

			// Update new_extensions.
			UAEL_Helper::update_admin_settings_option( '_uael_widgets', $new_widgets );
			UAEL_Helper::create_specific_stylesheet();

			// Activate all free widgets.
			$is_lite_active = UAEL_Helper::is_lite_active(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			
			if ( $is_lite_active && class_exists( '\HFE\WidgetsManager\Base\HFE_Helper' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

				if ( ( ! isset( self::$free_widget_list ) ) ) {
					$hfe_helper             = new \HFE\WidgetsManager\Base\HFE_Helper();
					self::$free_widget_list = $hfe_helper::get_widget_list();
				}
				$new_free_widgets = array();
				// Set all extension to enabled.
				foreach ( self::$free_widget_list  as $slug => $value ) {
					$new_free_widgets[ $slug ] = $slug;
				}
				// Escape attrs.
				$new_free_widgets = array_map( 'esc_attr', $new_free_widgets );
				// Update new_extensions.
				UAEL_Helper::update_admin_settings_option( '_hfe_widgets', $new_free_widgets );
			}

			// Send a JSON response.
			wp_send_json_success( 'Widgets activated successfully.' );
		}

		/**
		 * Deactivate all module
		 */
		public static function bulk_deactivate_widgets() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			if ( ! isset( self::$widget_list ) ) {
				self::$widget_list = UAEL_Helper::get_widget_list();
			}

			$new_widgets = array();

			// Set all extension to enabled.
			foreach ( self::$widget_list as $slug => $value ) {
				$new_widgets[ $slug ] = 'disabled';
			}

			// Escape attrs.
			$new_widgets = array_map( 'esc_attr', $new_widgets );

			// Update new_extensions.
			UAEL_Helper::update_admin_settings_option( '_uael_widgets', $new_widgets );
			UAEL_Helper::create_specific_stylesheet();

			// Activate all free widgets.
			$is_lite_active = UAEL_Helper::is_lite_active(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			
			if ( $is_lite_active && class_exists( '\HFE\WidgetsManager\Base\HFE_Helper' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

				if ( ( ! isset( self::$free_widget_list ) ) ) {
					$hfe_helper             = new \HFE\WidgetsManager\Base\HFE_Helper();
					self::$free_widget_list = $hfe_helper::get_widget_list();
				}
				$new_free_widgets = array();
				// Set all extension to enabled.
				foreach ( self::$free_widget_list  as $slug => $value ) {
					$new_free_widgets[ $slug ] = 'disabled';
				}
				// Escape attrs.
				$new_free_widgets = array_map( 'esc_attr', $new_free_widgets );
				// Update new_extensions.
				UAEL_Helper::update_admin_settings_option( '_hfe_widgets', $new_free_widgets );
			}

			// Send a JSON response.
			wp_send_json_success( 'Widgets deactivated successfully.' );
		}

		/**
		 * Activate all module
		 */
		public static function bulk_activate_skins() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			// Get all skins.
			$post_skins = UAEL_Helper::get_post_skin_list();

			$new_widgets = array();

			// Set all extension to enabled.
			foreach ( $post_skins  as $slug => $value ) {
				$new_widgets[ $slug ] = $slug;
			}

			// Escape attrs.
			$new_widgets = array_map( 'esc_attr', $new_widgets );

			// Update new_extensions.
			UAEL_Helper::update_admin_settings_option( '_uael_widgets', $new_widgets );
			UAEL_Helper::create_specific_stylesheet();

			wp_send_json_success( 'Skins activated successfully.' );
		}

		/**
		 * Deactivate all module
		 */
		public static function bulk_deactivate_skins() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			// Get all skins.
			$post_skins = UAEL_Helper::get_post_skin_list();

			$new_widgets = array();

			// Set all extension to enabled.
			foreach ( $post_skins as $slug => $value ) {
				$new_widgets[ $slug ] = 'disabled';
			}

			// Escape attrs.
			$new_widgets = array_map( 'esc_attr', $new_widgets );

			// Update new_extensions.
			UAEL_Helper::update_admin_settings_option( '_uael_widgets', $new_widgets );
			UAEL_Helper::create_specific_stylesheet();

			wp_send_json_success( 'Skins deactivated successfully.' );
		}

		/**
		 * Allow beta updates
		 */
		public static function allow_beta_updates() {

			check_ajax_referer( 'uael-widget-nonce', 'nonce' );

			$beta_update = isset( $_POST['allow_beta'] ) ? sanitize_text_field( $_POST['allow_beta'] ) : '';

			// Update new_extensions.
			UAEL_Helper::update_admin_settings_option( '_uael_beta', $beta_update );

			wp_send_json_success( 'success' );
		}

	}

	UAEL_Admin::init();

}
