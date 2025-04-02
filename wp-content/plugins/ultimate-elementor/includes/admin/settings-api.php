<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UltimateElementor\Classes\UAEL_Helper;
use UltimateElementor\Classes\UAEL_Maxmind_Database;

/**
 * Class Settings_Api.
 */
class Settings_Api {

	/**
	 * Instance.
	 *
	 * @access private
	 * @var object Class object.
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Settings_Api
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {

		$routes = array(
            '/widgets' => 'get_uael_widgets',
            '/postskins' => 'get_uael_post_skins',
            '/settings' => array('GET' => 'get_uael_settings', 'POST' => 'save_uael_settings'),
            '/branding' => array('GET' => 'get_uael_branding', 'POST' => 'save_uael_branding'),
            '/plugins' => 'get_plugins_list',
            '/validateApiKey' => 'validate_google_places_api_key',
            '/templates' => 'get_templates_status',
        );

		foreach ( $routes as $route => $callback ) {
            if ( is_array( $callback ) ) {
                foreach ( $callback as $method => $cb ) {
                    register_rest_route(
                        'uael/v1',
                        $route,
                        array(
                            'methods'             => $method,
                            'callback'            => array( $this, $cb ),
                            'permission_callback' => array( $this, 'get_items_permissions_check' ),
                        )
                    );
                }
            } else {
				$call_method = ( '/validateApiKey' === $route ) ? 'POST' : 'GET';
                register_rest_route(
                    'uael/v1',
                    $route,
                    array(
                        'methods'             => $call_method,
                        'callback'            => array( $this, $callback ),
                        'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    )
                );
            }
        }
	}

	/**
	 * Get Starter Templates Status.
	 */
	public function get_templates_status( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}

		$templates_status = UAEL_Helper::starter_templates_status();

		$response_data = array(
			'templates_status' => $templates_status,
		);
	
		if ( 'Activated' === $templates_status ) {
			$response_data['redirect_url'] = UAEL_Helper::starter_templates_link();
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Validate Google Places API key.
	 */
	public function validate_google_places_api_key( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
	
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}
	
		$api_key = $request->get_param( 'apiKey' );
		$api_type = $request->get_param( 'source' );

		if( 'google' === $api_type || 'yelp' === $api_type ) {
	
			// Perform your API key validation logic here.
			UAEL_Helper::get_api_authentication( $api_type, $api_key );

			if( 'google' === $api_type ) {
				$google_status = get_option( 'uael_google_api_status' );

				if ( 'yes' === $google_status ) {
                    return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Your API key authenticated successfully!', 'uael' ) ), 200 );
                } elseif ( 'no' === $google_status ) {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Entered API key is invalid', 'uael' ) ), 400 );
                } elseif ( 'exceeded' === $google_status ) {
                    $error_message = sprintf(
                        '%1$s%s%2$s %3$s%s%4$s',
                        '<b>' . __( 'Google Error Message:', 'uael' ) . '</b>',
                        __( 'You have exceeded your daily request quota for this API. If you did not set a custom daily request quota, verify your project has an active billing account.', 'uael' ),
                        '<a href="http://g.co/dev/maps-no-account" target="_blank" rel="noopener">',
                        __( 'Click here to enable billing.', 'uael' ),
                        '</a>'
                    );
                    return new WP_REST_Response( array( 'success' => false, 'message' => $error_message ), 400 );
                } else {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown error', 'uael' ) ), 400 );
                }
			}

			if( 'yelp' === $api_type ) {
				$yelp_status = get_option( 'uael_yelp_api_status' );

				if ( 'yes' === $yelp_status ) {
                    return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Your API key authenticated successfully!', 'uael' ) ), 200 );
                } elseif ( 'no' === $yelp_status ) {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Entered API key is invalid', 'uael' ) ), 400 );
                } else {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown error', 'uael' ) ), 400 );
                }
			}
		} else if( 'facebook' === $api_type ) {
			$response = UAEL_Helper::facebook_token_authentication( $api_key );
			if ( 200 === $response ) {
                return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Access Token authenticated successfully!', 'uael' ) ), 200 );
            } else {
                return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid Access Token', 'uael' ) ), 400 );
            }
		}
		
		return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown error', 'uael' ) ), 400 );
        
	}

	/**
	 * Check whether a given request has permission to read notes.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check() {

		if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'uae_rest_not_allowed', __( 'Sorry, you are not authorized to perform this action.', 'uael' ), array( 'status' => 403 ) );
        }

		return true;
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_widgets( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        $all_widgets = UAEL_Helper::get_all_widgets_list();

        if ( ! is_array( $all_widgets ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Widgets not found', 'uael' ) ), 404 ); // Return not found response
        }

		return new WP_REST_Response( $all_widgets, 200 );
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_post_skins( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        $post_skins = UAEL_Helper::get_post_skin_options();

        if ( ! is_array( $post_skins ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Not found', 'uael' ) ), 404 ); // Return not found response
        }

		return new WP_REST_Response( $post_skins, 200 );
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_settings( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        // Fetch your settings here
        $settings = UAEL_Helper::get_admin_settings_option( '_uael_integration', array(), true );

        if ( ! is_array( $settings ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Settings not found', 'uael' ) ), 404 ); // Return not found response
        }

		return new WP_REST_Response( $settings, 200 );
	}

	public function save_uael_settings( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

		// Get the settings from the request
		$settings = $request->get_json_params();
		$new_settings   = array();
		$maxmind_status = 'success';

		// Verify MaxMind key and download database
		if ( isset( $settings['uael_maxmind_geolocation_license_key'] ) ) {
            $geolite_db = new UAEL_Maxmind_Database();
            $result     = $geolite_db->verify_key_and_download_database( $settings['uael_maxmind_geolocation_license_key'] );
            if ( isset( $result['error'] ) && $result['error'] ) {
                $maxmind_status = 'error';
            }
        }

		// Loop through the input and sanitize each of the values.
		foreach ( $settings as $key => $val ) {

			if ( is_array( $val ) ) {
				foreach ( $val as $k => $v ) {
					$new_settings[ $key ][ $k ] = ( isset( $val[ $k ] ) ) ? sanitize_text_field( $v ) : '';
				}
			} else {
				$new_settings[ $key ] = ( isset( $settings[ $key ] ) ) ? sanitize_text_field( $val ) : '';
			}
		}
	
		// Save the settings back to the database
		UAEL_Helper::update_admin_settings_option( '_uael_integration', $new_settings, true );
	
		return new WP_REST_Response( array(
			'message' => __( 'Settings saved successfully', 'uael' ),
			'maxmind_status' => $maxmind_status
		), 200 );
    }

	/**
	 * Callback function to return plugins list.
	 *
	 * @return WP_REST_Response
	 */
	public function get_plugins_list( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        // Fetch branding settings
        $plugins_list = UAEL_Helper::get_bsf_plugins_list();

        if ( ! is_array( $plugins_list ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Plugins list not found', 'uael' ) ), 404 );
        }

		return new WP_REST_Response( $plugins_list, 200 );
		
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_branding( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        // Fetch branding settings
        $branding_settings = UAEL_Helper::get_white_labels();

        if ( ! is_array( $branding_settings ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Branding settings not found', 'uael' ) ), 404 );
        }

		return new WP_REST_Response( $branding_settings, 200 );
		
	}

	public function save_uael_branding( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }
	
		// Get the settings from the request
		$settings        = $request->get_json_params();
		$stored_settings = UAEL_Helper::get_white_labels();
	
		// Sanitize and prepare the settings for saving
		$new_settings = array();

		if ( is_array( $settings ) ) {
			foreach ( $settings as $key => $val ) {

				if ( is_array( $val ) ) {
					foreach ( $val as $k => $v ) {
						$new_settings[ $key ][ $k ] = ( isset( $val[ $k ] ) ) ? sanitize_text_field( $v ) : '';
					}
				} else {
					$new_settings[ $key ] = ( isset( $settings[ $key ] ) ) ? sanitize_text_field( $val ) : '';
				}
			}
		}

		if ( ! isset( $new_settings['agency']['hide_branding'] ) ) {
			$new_settings['agency']['hide_branding'] = false;
		} else {
			// Add a hide branding component option
		}

		$checkbox_var = array(
			'replace_logo',
			'internal_help_links',
		);

		foreach ( $checkbox_var as $key => $value ) {
			if ( ! isset( $new_settings[ $value ] ) ) {
				$new_settings[ $value ] = 'disable';
			}
		}
		
		$new_settings = wp_parse_args( $new_settings, $stored_settings );
		
		// Save the settings back to the database
		UAEL_Helper::update_admin_settings_option( '_uael_white_label', $new_settings, true );

		return new WP_REST_Response( 'Branding settings saved successfully', 200 );

	}
}

// Initialize the Settings_Api class
Settings_Api::get_instance();