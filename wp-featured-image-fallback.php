<?php
/**
 * Plugin Name:       Featured Image Fallback
 * Plugin URI:        https://github.com/Azanumer/wp-featured-image-fallback
 * Description:       Never show a post without a thumbnail again — a default featured image per post type, plus a wfi_fallback_id filter for custom logic.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wp-featured-image-fallback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFI_VERSION', '1.0.0' );
define( 'WFI_OPTION', 'wfi_fallbacks' );

/**
 * Fallback attachment IDs per post type, e.g. ['post' => 123, 'page' => 0].
 *
 * @return array
 */
function wfi_get_fallbacks() {
	$saved = get_option( WFI_OPTION, array() );
	return is_array( $saved ) ? $saved : array();
}

/**
 * Resolve the fallback attachment ID for a post.
 *
 * Order: filter override → per-post-type setting → global default.
 *
 * @param int $post_id Post ID.
 * @return int Attachment ID, or 0 when no fallback applies.
 */
function wfi_fallback_id( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return 0;
	}
	$fallbacks = wfi_get_fallbacks();
	$fallback  = isset( $fallbacks[ $post->post_type ] ) ? absint( $fallbacks[ $post->post_type ] ) : 0;
	if ( 0 === $fallback && isset( $fallbacks['_default'] ) ) {
		$fallback = absint( $fallbacks['_default'] );
	}
	/**
	 * Filter the fallback image for a post. Return 0 to disable it.
	 *
	 * @param int     $fallback Fallback attachment ID.
	 * @param int     $post_id  Post ID.
	 * @param WP_Post $post     Post object.
	 */
	$fallback = apply_filters( 'wfi_fallback_id', $fallback, $post_id, $post );
	// Only accept an attachment that actually exists and is an image.
	if ( $fallback > 0 && ! wp_attachment_is_image( $fallback ) ) {
		return 0;
	}
	return $fallback;
}

/**
 * Make has_post_thumbnail() true for posts that have a fallback, so
 * themes that gate thumbnails on it still render the fallback image.
 *
 * @param bool   $has_thumbnail Whether the post has a thumbnail.
 * @param int    $post_id       Post ID.
 * @return bool
 */
function wfi_has_thumbnail( $has_thumbnail, $post_id ) {
	if ( $has_thumbnail ) {
		return true;
	}
	return wfi_fallback_id( $post_id ) > 0;
}
add_filter( 'has_post_thumbnail', 'wfi_has_thumbnail', 10, 2 );

/**
 * Return the fallback image HTML when the post has no real thumbnail.
 *
 * @param string $html    Thumbnail HTML.
 * @param int    $post_id Post ID.
 * @param int    $post_thumbnail_id Thumbnail attachment ID (0 when none).
 * @return string
 */
function wfi_post_thumbnail_html( $html, $post_id, $post_thumbnail_id ) {
	if ( '' !== $html || $post_thumbnail_id > 0 ) {
		return $html;
	}
	$fallback = wfi_fallback_id( $post_id );
	if ( 0 === $fallback ) {
		return $html;
	}
	return wp_get_attachment_image( $fallback, 'post-thumbnail' );
}
add_filter( 'post_thumbnail_html', 'wfi_post_thumbnail_html', 10, 3 );

/**
 * Settings page: one media-picker row per public post type + a global default.
 */
function wfi_register_settings() {
	register_setting( 'wfi_settings_group', WFI_OPTION );
	add_settings_section( 'wfi_main', '', '__return_false', 'wfi-settings' );
}
add_action( 'admin_init', 'wfi_register_settings' );

/**
 * Add the settings page (menu registration only).
 */
function wfi_add_menu() {
	add_options_page(
		__( 'Featured Image Fallback', 'wp-featured-image-fallback' ),
		__( 'Featured Image Fallback', 'wp-featured-image-fallback' ),
		'manage_options',
		'wfi-settings',
		'wfi_render_settings'
	);
}
add_action( 'admin_menu', 'wfi_add_menu' );

/**
 * Render the settings page with a media-library picker per row.
 */
function wfi_render_settings() {
	wp_enqueue_media(); // Media-library picker used by the Choose buttons.
	$fallbacks  = wfi_get_fallbacks();
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Featured Image Fallback', 'wp-featured-image-fallback' ) . '</h1>';
	echo '<p>' . esc_html__( 'Pick a default image for each post type. Posts without their own featured image will show it instead.', 'wp-featured-image-fallback' ) . '</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'wfi_settings_group' );
	// Global default first.
	wfi_render_row( '_default', __( 'All post types (default)', 'wp-featured-image-fallback' ), isset( $fallbacks['_default'] ) ? $fallbacks['_default'] : 0 );
	foreach ( $post_types as $slug => $type ) {
		wfi_render_row( $slug, $type->labels->singular_name, isset( $fallbacks[ $slug ] ) ? $fallbacks[ $slug ] : 0 );
	}
	submit_button();
	echo '</form></div>';
	// Media picker: one shared frame, reused for every row.
	echo '<script>
	jQuery(function($){
		var frame;
		$(".wfi-pick").on("click", function(e){
			e.preventDefault();
			var btn = $(this), input = $("#" + btn.data("target"));
			frame = wp.media({ title: "Choose fallback image", library: { type: "image" }, multiple: false });
			frame.on("select", function(){
				var att = frame.state().get("selection").first().toJSON();
				input.val(att.id);
				btn.siblings(".wfi-preview").html("<img src=\"" + att.sizes.thumbnail.url + "\" style=\"max-width:60px;height:auto;vertical-align:middle;\" />");
			});
			frame.open();
		});
		$(".wfi-clear").on("click", function(e){
			e.preventDefault();
			var btn = $(this);
			$("#" + btn.data("target")).val(0);
			btn.siblings(".wfi-preview").empty();
		});
	});
	</script>';
}

/**
 * Render one settings row: hidden ID field + preview + pick/clear buttons.
 *
 * @param string $key   Option array key.
 * @param string $label Row label.
 * @param int    $value Current attachment ID.
 */
function wfi_render_row( $key, $label, $value ) {
	$value = absint( $value );
	$field = 'wfi_field_' . sanitize_key( $key );
	echo '<p><strong>' . esc_html( $label ) . '</strong><br />';
	echo '<input type="hidden" id="' . esc_attr( $field ) . '" name="' . esc_attr( WFI_OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
	echo '<span class="wfi-preview">';
	if ( $value > 0 ) {
		echo wp_get_attachment_image( $value, 'thumbnail' );
	}
	echo '</span> ';
	echo '<button type="button" class="button wfi-pick" data-target="' . esc_attr( $field ) . '">' . esc_html__( 'Choose image', 'wp-featured-image-fallback' ) . '</button> ';
	echo '<button type="button" class="button wfi-clear" data-target="' . esc_attr( $field ) . '">' . esc_html__( 'Clear', 'wp-featured-image-fallback' ) . '</button></p>';
}
