# Featured Image Fallback

Never show a post without a thumbnail again. Set one default image per
post type (plus a global default), and posts without their own featured
image display it instead — in archives, related-posts blocks, and anywhere
else your theme calls `the_post_thumbnail()`.

## Features

- **Per-post-type defaults** — different fallback for posts, pages, and
  any public custom post type, picked from the media library.
- **Theme-compatible** — filters both `has_post_thumbnail()` and
  `post_thumbnail_html()`, so themes that gate thumbnails on
  `has_post_thumbnail()` still render the fallback.
- **`wfi_fallback_id` filter** — override the fallback per post with
  custom logic (e.g. first image in the content, category-based image);
  return `0` to disable it for a post.
- **Invalid images rejected** — a deleted or non-image attachment never
  renders a broken thumbnail.
- **Uninstall-safe** — removing the plugin deletes only its settings;
  your images stay in the media library.

## Installation

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate it.
3. Choose fallbacks under Settings → Featured Image Fallback.

## License

MIT — see `LICENSE`.
