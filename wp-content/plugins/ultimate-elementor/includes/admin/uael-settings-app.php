<?php
/**
 * Single page settings page
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UltimateElementor\Classes\UAEL_Helper;

$language_list = UAEL_Helper::get_google_map_languages();

$json_languages_data = json_encode( $language_list );
?>
<script type="text/javascript">
	window.uaeLanguagesData = <?php echo $json_languages_data; ?>;
</script>

<div id="uae-settings-app" class="uae-settings-app"></div>