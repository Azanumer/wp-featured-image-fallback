<?php
/**
 * Remove fallback settings on uninstall (images themselves are kept).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wfi_fallbacks' );
