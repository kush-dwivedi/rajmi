<?php

namespace ImageOptimization\Modules\Core\Components;

use ImageOptimization\Classes\Utils;
use ImageOptimization\Modules\Settings\Module;
use ImageOptimization\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Not_Connected_Modal {
	const NOT_CONNECTED_MODAL_SLUG = 'image-optimizer-not-connected-modal';

	public function render_not_connected_modal() {
		add_thickbox(); ?>

		<div
			id="<?php echo esc_html( self::NOT_CONNECTED_MODAL_SLUG ); ?>"
			style="display:none;"
		>
			<div class="image-optimizer__modal image-optimizer__modal--not-connected" data-modal-slug="<?php echo esc_attr( self::NOT_CONNECTED_MODAL_SLUG ); ?>">
				<img src="<?php echo esc_url( plugins_url( 'assets/static/connect.jpg', dirname( __FILE__, 3 ) ) ); ?>" alt="<?php esc_html_e( 'Connect now', 'image-optimization' ); ?>">
				<h2><?php esc_html_e( 'Let\'s start optimizing your images!', 'image-optimization' ); ?></h2>
				<p><?php esc_html_e( 'Connect the Image Optimizer plugin to speed up your site. We\'ll handle the rest.', 'image-optimization' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Boost site speed and compress images', 'image-optimization' ); ?></li>
					<li><?php esc_html_e( 'Bulk optimization for faster processing', 'image-optimization' ); ?></li>
					<li><?php esc_html_e( 'Convert to AVIF and WebP formats', 'image-optimization' ); ?></li>
					<li><?php esc_html_e( 'Works across unlimited websites', 'image-optimization' ); ?></li>
				</ul>
				<a href="<?php echo admin_url( 'admin.php?page=' . Module::SETTING_BASE_SLUG . '&action=connect' ); ?>">
					<?php esc_html_e( 'Connect to start', 'image-optimization' ); ?>
				</a>
			</div>
		</div>
		<script>
			jQuery( document ).ready( function( $ ) {
				setTimeout(() => {
					const TB_WIDTH = 630;
					const TB_HEIGHT = 620;
					const msInOneDay = 24 * 60 * 60 * 1000;
					const time_dismissed = localStorage.getItem('<?php echo self::NOT_CONNECTED_MODAL_SLUG; ?>');
					const show_notice = !time_dismissed || Date.now() - time_dismissed >= msInOneDay;
					const $modal = $( '[data-modal-slug="<?php echo esc_js( self::NOT_CONNECTED_MODAL_SLUG ); ?>"]' );

					if (show_notice) {
						tb_show( '', `/?TB_inline&inlineId=<?php echo esc_js( self::NOT_CONNECTED_MODAL_SLUG ); ?>&height=${TB_HEIGHT - 40}&width=${TB_WIDTH - 30}&modal=true` );
						$modal.parents('#TB_window')
							.width(TB_WIDTH)
							.height(TB_HEIGHT)
							.css('margin-left', - TB_WIDTH / 2)
							.css('margin-top', - TB_HEIGHT / 2)
							.css('top', '50%');
						const $closeButton = $modal.find( 'a' );
						$closeButton.on( 'click', function (e) {
							e.preventDefault();
							localStorage.setItem('<?php echo esc_js( self::NOT_CONNECTED_MODAL_SLUG ); ?>', Date.now().toString());
							tb_remove();
							window.open( $( this ).attr( 'href' ), '_blank' ).focus();
						} );
					} else {
						$modal.remove();
					}
				}, 0);
			} );
		</script>
		<?php
	}



	public function __construct() {
		add_action('current_screen', function () {
			// @var ImageOptimizer/Modules/ConnectManager/Module
			$module = Plugin::instance()->modules_manager->get_modules( 'connect-manager' );

			if ( $module->connect_instance->is_connected() || ! Utils::user_is_admin() ) {
				return;
			}

			if (
				Utils::is_plugin_settings_page() ||
				Utils::is_bulk_optimization_page()
			) {
				add_action( 'admin_notices', [ $this, 'render_not_connected_modal' ] );
			}
		});
	}
}
