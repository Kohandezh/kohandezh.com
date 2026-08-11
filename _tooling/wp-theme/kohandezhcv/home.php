<?php /* KohandezhCV — blog index (posts page), dynamic version of the static blog design */ ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="نوشته‌های منتخب محمدعلی کهن‌دژ درباره هوش مصنوعی، زیرساخت، امنیت و تجربه‌های ساخت محصول.">
  <meta name="theme-color" content="#080b0d">
  <title><?php echo esc_html( wp_get_document_title() ); ?></title>
  <link rel="preload" href="<?php echo KDCV; ?>/assets/fonts/estedad/Estedad-VF.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/fonts/estedad/estedad.css">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/fonts/inter/inter.css">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/icon/icomoon/style.css">
  <link rel="stylesheet" href="<?php echo KDCV; ?>/assets/css/blog.css?v=3">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo KDCV; ?>/assets/images/logo/favicon-32.png">
  <?php wp_head(); ?>
</head>
<body class="blog-page">
<?php wp_body_open(); ?>
  <div class="blog-shell">
    <header class="blog-header">
      <a class="blog-brand" href="<?php echo esc_url( home_url('/') ); ?>" aria-label="بازگشت به صفحه اصلی محمدعلی کهن‌دژ">
        <img src="<?php echo KDCV; ?>/assets/images/logo/logo-2.svg" width="40" height="40" alt="MK">
        <span>MOHAMMAD ALI KOHANDEZH</span>
      </a>
      <nav class="blog-nav" aria-label="ناوبری وبلاگ">
        <a href="#latest" aria-current="page">تازه‌ها</a>
      </nav>
      <div class="blog-header-actions">
      </div>
    </header>

    <main>
      <section class="blog-hero" aria-labelledby="blog-title">
        <div>
          <span class="blog-eyebrow">KOHANDEZH.COM / FIELD NOTES</span>
          <h1 id="blog-title">نوشته‌های منتخب<br>از <span>kohandezh.com</span></h1>
          <p>یادداشت‌هایی درباره ساخت محصولات هوشمند، زیرساخت قابل اتکا و تصمیم‌های فنی که باید در دنیای واقعی جواب بدهند.</p>
        </div>
        <aside class="blog-hero-note" aria-label="معرفی وبلاگ">
          <span class="blog-note-number"><?php printf( '%02d', (int) wp_count_posts()->publish ); ?></span>
          <p>ایده‌های روشن، تجربه‌های اجرایی و مسیرهایی برای ساختن سیستم‌هایی که می‌شود به آن‌ها اعتماد کرد.</p>
        </aside>
      </section>

      <?php if ( have_posts() ) : ?>
      <?php
        the_post(); // first (newest) post becomes the featured card
      ?>
      <section aria-labelledby="featured-heading">
        <div class="blog-section-heading">
          <div>
            <span class="blog-eyebrow">FEATURED NOTE</span>
            <h2 id="featured-heading">تازه‌ترین نوشته</h2>
          </div>
        </div>
        <article class="blog-featured">
          <div class="blog-featured-image">
            <a href="<?php the_permalink(); ?>">
              <img src="<?php echo esc_url( kdcv_card_image( get_post(), 0 ) ); ?>" width="1600" height="900" fetchpriority="high" alt="<?php the_title_attribute(); ?>">
            </a>
            <span class="blog-featured-number"><?php echo esc_html( strtoupper( get_the_modified_date( 'd M Y' ) ) ); ?></span>
          </div>
          <div class="blog-featured-body">
            <?php $cats = get_the_category(); ?>
            <span class="blog-card-kicker"><?php echo $cats ? esc_html( $cats[0]->name ) : 'یادداشت'; ?></span>
            <h3><?php the_title(); ?></h3>
            <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></p>
            <div class="blog-featured-footer">
              <div class="blog-meta"><span><?php echo esc_html( kdcv_reading_minutes() ); ?> دقیقه مطالعه</span></div>
              <a class="blog-read" href="<?php the_permalink(); ?>">خواندن نوشته <i class="icon icon-arrow-right-top"></i></a>
            </div>
          </div>
        </article>
      </section>

      <section id="latest" class="blog-list-section" aria-labelledby="latest-heading">
        <div class="blog-section-heading">
          <div>
            <span class="blog-eyebrow">SELECTED WRITING</span>
            <h2 id="latest-heading">یادداشت‌های بیشتر</h2>
          </div>
          <p>ترکیبی از نگاه آینده‌محور، تجربه اجرایی و روایت‌هایی از مسیر ساختن.</p>
        </div>
        <div class="blog-grid">
          <?php $i = 1; while ( have_posts() ) : the_post(); ?>
          <article class="blog-card">
            <div class="blog-card-image">
              <a href="<?php the_permalink(); ?>">
                <img loading="lazy" decoding="async" src="<?php echo esc_url( kdcv_card_image( get_post(), $i ) ); ?>" width="1600" height="900" alt="<?php the_title_attribute(); ?>">
              </a>
              <span class="blog-featured-number"><?php echo esc_html( strtoupper( get_the_modified_date( 'd M Y' ) ) ); ?></span>
            </div>
            <div class="blog-card-body">
              <?php $cats = get_the_category(); ?>
              <span class="blog-card-kicker"><?php echo $cats ? esc_html( $cats[0]->name ) : 'یادداشت'; ?></span>
              <h3><?php the_title(); ?></h3>
              <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
              <div class="blog-card-footer">
                <span class="blog-meta"><span><?php echo esc_html( kdcv_reading_minutes() ); ?> دقیقه مطالعه</span></span>
                <a class="blog-read" href="<?php the_permalink(); ?>" aria-label="خواندن نوشته <?php the_title_attribute(); ?>"><i class="icon icon-arrow-right-top"></i></a>
              </div>
            </div>
          </article>
          <?php $i++; endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
      </section>
      <?php endif; ?>
    </main>

    <footer class="blog-footer">
      <span>© <?php echo esc_html( date_i18n( 'Y' ) ); ?> Mohammad Ali Kohandezh</span>
      <a href="<?php echo esc_url( home_url('/') ); ?>">بازگشت به صفحه اصلی</a>
    </footer>
  </div>
  <?php wp_footer(); ?>
</body>
</html>
