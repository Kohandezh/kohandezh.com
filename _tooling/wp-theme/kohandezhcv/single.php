<?php
/* KohandezhCV — single blog post (dark editorial article layout).
 *
 * TRANSLATION
 * The static posts translate in place: every leaf block carries data-i18n and
 * page-i18n.js swaps the innerHTML. That cannot work here — the_content()
 * renders the body straight from the database, with none of those attributes.
 * So this template opts into the SAME dictionary under a whole-article key:
 * `__body` holds the complete translated article HTML (composed from the
 * static file by _tooling/wp-theme/build-post-bodies.py), plus `__h1` and
 * `__eyebrow` for the parts that sit outside the body. A post is translatable
 * only when a dictionary exists for its slug; otherwise the declaration is
 * omitted and the post renders in Persian, exactly as before. */
$kdcv_post_slug = 'post-' . get_post_field( 'post_name', get_queried_object_id() );
$kdcv_has_i18n  = file_exists( get_template_directory() . '/assets/data/i18n/' . $kdcv_post_slug . '.json' );
?>
<!doctype html>
<html<?php if ( $kdcv_has_i18n ) : ?> data-kdcv-i18n="in-place" data-kdcv-i18n-page="<?php echo esc_attr( $kdcv_post_slug ); ?>" data-kdcv-i18n-source="fa"<?php endif; ?> lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#080b0d">
  <title><?php echo esc_html( wp_get_document_title() ); ?></title>
  <link rel="preload" href="<?php echo KDCV; ?>/assets/fonts/estedad/Estedad-VF.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/fonts/estedad/estedad.css">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/fonts/inter/inter.css">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/icon/icomoon/style.css">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/css/blog.css?v=3">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/css/page-chrome.min.css?v=1">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo KDCV; ?>/assets/images/logo/favicon-32.png">
  <?php wp_head(); ?>
</head>
<body class="blog-page">
<?php wp_body_open(); ?>
  <div class="blog-shell">
    <header class="blog-header">
      <a class="blog-brand" href="<?php echo esc_url( home_url('/') ); ?>" data-i18n-aria="a_home" aria-label="بازگشت به صفحه اصلی محمدعلی کهن‌دژ">
        <img src="<?php echo KDCV; ?>/assets/images/logo/logo-2.svg" width="40" height="40" alt="MK">
        <span>MOHAMMAD ALI KOHANDEZH</span>
      </a>
      <nav class="blog-nav" data-i18n-aria="a_nav" aria-label="ناوبری وبلاگ">
        <a href="<?php echo esc_url( home_url('/blog/') ); ?>" data-i18n="__nav_all">همه نوشته‌ها</a>
      </nav>
      <div class="blog-header-actions">
        <a href="<?php echo esc_url( home_url('/#contact') ); ?>" data-i18n-aria="a_chat" aria-label="شروع گفتگو"><i class="icon icon-send"></i><span data-i18n="__chat">شروع گفتگو</span></a>
      </div>
    </header>

    <main>
      <?php while ( have_posts() ) : the_post(); ?>
      <article class="blog-article">
        <header class="blog-article-header">
          <?php $cats = get_the_category(); ?>
          <span class="blog-eyebrow" data-i18n="__eyebrow"><?php echo $cats ? esc_html( $cats[0]->name ) : 'یادداشت'; ?></span>
          <h1 data-i18n="__h1"><?php the_title(); ?></h1>
          <div class="blog-meta">
            <span><?php echo esc_html( get_the_modified_date() ); ?></span>
            <span><?php echo esc_html( kdcv_reading_minutes() ); ?> دقیقه مطالعه</span>
            <span><?php echo esc_html( number_format_i18n( kdcv_get_views() ) ); ?> بازدید</span>
          </div>
        </header>

        <?php if ( has_post_thumbnail() ) : ?>
        <figure class="blog-article-hero">
          <?php the_post_thumbnail( 'large', array( 'fetchpriority' => 'high' ) ); ?>
        </figure>
        <?php endif; ?>

        <div class="blog-article-body" data-i18n="__body">
          <?php the_content(); ?>
        </div>

        <footer class="blog-article-footer">
          <a class="blog-read" href="<?php echo esc_url( home_url('/blog/') ); ?>" data-i18n="__all_posts_cta">همه نوشته‌ها <i class="icon icon-arrow-right-top"></i></a>
          <a class="blog-read" href="<?php echo esc_url( home_url('/#contact') ); ?>" data-i18n="__chat_cta">شروع گفتگو <i class="icon icon-arrow-right-top"></i></a>
        </footer>
      </article>
      <?php endwhile; ?>
    </main>

    <footer class="blog-footer">
      <span>© <?php echo esc_html( date_i18n( 'Y' ) ); ?> Mohammad Ali Kohandezh</span>
      <a href="<?php echo esc_url( home_url('/') ); ?>">بازگشت به صفحه اصلی</a>
    </footer>
  </div>
  <?php if ( $kdcv_has_i18n ) : ?>
  <!-- page-i18n reads the dictionary for this post's slug and swaps the whole
       article body, heading and chrome into the reader's language. It fetches
       nothing when the wanted locale is the source (fa), so a Persian reader
       costs no extra request. blog-post-enhance rebuilds its share controls on
       the kdcv:page-i18n event. -->
  <script src="<?php echo KDCV; ?>/assets/js/page-i18n.min.js?v=1" defer></script>
  <?php endif; ?>
  <script src="<?php echo KDCV; ?>/assets/js/blog-post-enhance.min.js?v=1" defer></script>
  <script src="<?php echo KDCV; ?>/assets/js/clock.min.js?v=1" defer></script>
  <script src="<?php echo KDCV; ?>/assets/js/page-chrome.min.js?v=1" defer></script>
  <?php wp_footer(); ?>
</body>
</html>
