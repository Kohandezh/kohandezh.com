<?php
// Small WP API fixture: no DB, network or production writes.
define('ABSPATH', __DIR__);
$actions = array();
function add_action($hook, $callback, ...$args) { global $actions; $actions[$hook] = $callback; }
function add_filter(...$args) {}
function get_the_title($post) { return $post->post_title; }
function get_permalink($post) { return 'https://kohandezh.com/2025/10/26/' . $post->post_name . '/'; }
function get_the_date($format, $post) { return $post->published; }
function get_the_modified_date($format, $post) { return $post->modified; }
function get_the_author_meta($key, $id) { return $key === 'display_name' ? 'Actual author' : 'https://example.org/profile'; }
function esc_url_raw($value, $protocols) { return $value; }
function get_the_post_thumbnail_url($post, $size) { return $post->image; }
function wp_strip_all_tags($text, $breaks = false) { return strip_tags($text); }
function strip_shortcodes($text) { return preg_replace('/\[[^\]]*\]/', '', $text); }
function wp_html_excerpt($text, $length, $more) { return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . $more : $text; }
function esc_xml($text) { return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function is_singular($type) { return true; }
function is_preview() { return false; }
function get_queried_object() { global $post; return $post; }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($value, $flags) { return json_encode($value, $flags); }
require __DIR__ . '/../wp-theme/kohandezhcv/inc/publication.php';
function check($value, $label) { if (!$value) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } echo "PASS: $label\n"; }
$post = (object) array('post_type'=>'post','post_status'=>'publish','post_password'=>'', 'post_title'=>'A <b>real</b> title', 'post_excerpt'=>'<p>دربارهٔ هوش مصنوعی &amp; امنیت</p>', 'post_content'=>'Fallback', 'post_author'=>7,'post_name'=>'test','published'=>'2025-10-26T08:30:00+03:30','modified'=>'2026-08-24T10:00:00+03:30','image'=>false);
$data = kdcv_post_schema($post);
check($data['datePublished'] === $post->published && $data['dateModified'] === $post->modified, 'actual publication and modification dates, no current-time fabrication');
check($data['headline'] === 'A real title' && $data['author']['name'] === 'Actual author', 'real title/author, not assumed site owner');
check(!isset($data['image']), 'no fabricated image');
check($data['description'] === 'دربارهٔ هوش مصنوعی & امنیت', 'clean Unicode description');
$post->post_status = 'draft'; check(!kdcv_post_schema($post), 'draft hidden');
$post->post_status = 'publish'; $post->post_password = 'secret'; check(!kdcv_post_schema($post), 'password-protected post hidden');
$post->post_password = ''; $post->post_title = '</script><script>alert(1)</script>';
ob_start(); $actions['wp_head'](); $head = ob_get_clean();
check(substr_count($head, 'name="description"') === 1 && substr_count($head, 'application/ld+json') === 1, 'one description and one Article graph');
check(!str_contains($head, '</script><script>'), 'JSON-LD cannot terminate into a script');
define('WPSEO_VERSION', 'test'); ob_start(); $actions['wp_head'](); check(ob_get_clean() === '', 'external SEO provider retains ownership');
$args = kdcv_sitemap_query_args(2);
check($args['post_status'] === 'publish' && $args['has_password'] === false && $args['paged'] === 2 && $args['posts_per_page'] === 1000, 'bounded published-only sitemap query');
$xml = kdcv_sitemap_xml(array(array('loc'=>'https://kohandezh.com/?kdcv_sitemap=posts&kdcv_sitemap_page=2')), true);
check(str_contains($xml, '&amp;') && simplexml_load_string($xml) !== false, 'valid escaped XML sitemap index');
