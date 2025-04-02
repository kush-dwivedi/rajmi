<?php
/**
 * Admin Base HTML.
 *
 * @package uael
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="uae-menu-page-wrapper">
	<div id="uae-menu-page">
		<div class="uae-menu-page-content uae-clear">
			<?php
				do_action( 'uael_render_admin_page_content' );
			?>
		</div>
	</div>
</div>
