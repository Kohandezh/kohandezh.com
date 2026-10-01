<?php
/** Explicit local-only WP-CLI seed. Never run against production. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8890' !== get_option( 'home' ) ) {
    throw new RuntimeException( 'This seed is restricted to localhost:8890.' );
}
$slug = 'ai-governance-nist-guide';
$existing = get_page_by_path( $slug, OBJECT, 'post' );
if ( $existing ) {
    WP_CLI::success( 'Existing article preserved: ' . get_permalink( $existing ) );
    return;
}
$authors = get_users( array( 'include' => array( 1 ), 'number' => 1 ) );
if ( 1 !== count( $authors ) ) {
    WP_CLI::error( 'Select a verified local author before seeding.' );
}
$content = file_get_contents( __DIR__ . '/ai-governance-nist-guide.html' );
if ( false === $content ) WP_CLI::error( 'Article source missing.' );
$id = wp_insert_post( array(
    'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug,
    'post_author' => $authors[0]->ID,
    'post_title' => 'AI Governance چیست؟ راهنمای کتاب فارسی و NIST',
    'post_excerpt' => 'حاکمیت هوش مصنوعی چیست و کتاب فارسی کهن‌دژ چگونه به مطالعهٔ NIST AI RMF کمک می‌کند؟ راهنمای شروع، منابع و مسیر مطالعهٔ سازمانی.',
    'post_content' => $content,
), true );
if ( is_wp_error( $id ) ) WP_CLI::error( $id->get_error_message() );
update_post_meta( $id, '_kbk_book_editorial', 'ai-governance' );
update_post_meta( $id, '_kbk_editorial_author', 'محمدعلی کهن‌دژ' );
WP_CLI::success( 'Local article: ' . get_permalink( $id ) );
