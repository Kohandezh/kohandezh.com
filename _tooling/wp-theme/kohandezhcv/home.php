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
<!-- WAITING:BEGIN generated from _tooling/waiting/waiting.partial.html by _tooling/waiting/build.py -- do not edit by hand -->
<style>
#kdcv-waiting{--kw-bg:#ebebeb;--kw-ink:#0a7233;--kw-trk:rgba(10,114,51,.14);position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:var(--kw-bg);animation:kdcvw-h .4s 6s forwards}
#kdcv-waiting.kdcv-waiting-dk,html[data-kdcv-theme=dark] #kdcv-waiting{--kw-bg:#0f0f0f;--kw-ink:#a8ff46;--kw-trk:rgba(240,255,222,.12)}
html[data-kdcv-theme=light] #kdcv-waiting{--kw-bg:#ebebeb;--kw-ink:#0a7233;--kw-trk:rgba(10,114,51,.14)}
.kdcv-waiting-out{opacity:0;visibility:hidden;pointer-events:none;transition:opacity .36s .26s,visibility 0s .62s}
.kdcv-waiting-r.kdcv-waiting-out{transition:opacity .2s,visibility 0s .2s}
#kdcv-waiting svg{width:clamp(96px,24vmin,132px);height:auto;overflow:visible}
.kdcvw-t{stroke:var(--kw-trk)}
.kdcvw-c,.kdcvw-f{stroke:var(--kw-ink)}
.kdcvw-c{stroke-dasharray:487 1542}
.kdcvw-f{stroke-dasharray:2029;stroke-dashoffset:2029;opacity:0}
.kdcv-waiting-out .kdcvw-c{opacity:0;transition:opacity .15s}
.kdcv-waiting-out .kdcvw-f{opacity:1;stroke-dashoffset:0;transition:stroke-dashoffset .36s cubic-bezier(.65,0,.35,1)}
@media (prefers-reduced-motion:no-preference){
#kdcv-waiting svg{animation:kdcvw-in .5s cubic-bezier(.2,.8,.2,1) both}
.kdcvw-c{animation:kdcvw-run 1.4s linear infinite}
.kdcvw-a{animation:kdcvw-p 2.8s ease-in-out infinite}
.kdcv-waiting-r svg{animation:none}
}
@media (prefers-reduced-motion:reduce){.kdcvw-c,.kdcvw-f{display:none}}
@keyframes kdcvw-h{from{pointer-events:none}to{opacity:0;visibility:hidden;pointer-events:none}}
@keyframes kdcvw-in{from{opacity:0;transform:scale(.9)}}
@keyframes kdcvw-run{to{stroke-dashoffset:-2029}}
@keyframes kdcvw-p{50%{opacity:.6}}
</style>
<noscript><style>#kdcv-waiting{display:none}</style></noscript>
<div id="kdcv-waiting" role="progressbar" aria-label="Loading"><svg viewBox="-40 -40 592 592" aria-hidden="true"><defs><linearGradient id="kdcvw-tile" x1="0" y1="0" x2="512" y2="512" gradientUnits="userSpaceOnUse"><stop stop-color="#171D1B"/><stop offset="1" stop-color="#050807"/></linearGradient><linearGradient id="kdcvw-accent" x1="200" y1="270" x2="255" y2="380" gradientUnits="userSpaceOnUse"><stop stop-color="#D9FF8A"/><stop offset="1" stop-color="#4FD35F"/></linearGradient></defs><g fill="none" stroke-width="12" stroke-linecap="round"><rect class="kdcvw-t" x="-28" y="-28" width="568" height="568" rx="142"/><rect class="kdcvw-c" x="-28" y="-28" width="568" height="568" rx="142"/><rect class="kdcvw-f" x="-28" y="-28" width="568" height="568" rx="142"/></g><rect width="512" height="512" rx="114" fill="url(#kdcvw-tile)"/><path class="kdcvw-a" d="M262 268 205 380 258 380 258 268Z" fill="url(#kdcvw-accent)"/><g fill="none" stroke="#F0FFDE" stroke-width="46" stroke-linecap="round" stroke-linejoin="round"><path d="M126 372V174l79 100 79-100v198"/><path d="M284 268 396 156M284 268l112 112"/></g></svg></div>
<script>(function(){var d=document,w=window,h=d.documentElement,e=d.getElementById("kdcv-waiting"),T=setTimeout,D=Date.now,t0=D(),ti,n,f,t,m,M,C;if(!e)return;
function k(){n=1;e.remove()}
function o(){n||(n=1,e.className+=" kdcv-waiting-out",T(k,f?680:260))}
function q(s){try{return matchMedia(s).matches}catch(x){}}
function a(y,z,p){w.addEventListener(y,z,p)}
if(/bot|crawl|spider|slurp|facebookexternalhit/i.test(navigator.userAgent)||d.readyState=="complete")return k();
try{t=sessionStorage;f=!t.getItem("kdcvWaitingSeen");t.setItem("kdcvWaitingSeen",1)}catch(x){}
try{t=h.getAttribute("data-kdcv-theme");if(!t){try{m=localStorage.getItem("darkMode")}catch(x){}t=m!=null?m=="enabled"?"dark":"light":d.body.dataset.defaultMode||(q("(prefers-color-scheme:dark)")?"dark":"light")}
e.className+=(t=="dark"?" kdcv-waiting-dk":"")+(f?"":" kdcv-waiting-r");
t={fa:"در حال بارگذاری",ar:"جارٍ التحميل",de:"Wird geladen",es:"Cargando",fr:"Chargement",tr:"Yükleniyor",zh:"加载中",ja:"読み込み中",ru:"Загрузка"}[h.lang.slice(0,2)];t&&e.setAttribute("aria-label",t)}catch(x){}
M=f&&!q("(prefers-reduced-motion:reduce)")?940:0;C=f?1200:0;
function g(){var z=D();z-t0<M||!(d.readyState=="complete"||ti&&z-ti>=C||z-t0>=3e3)||o()}
function i(){ti=ti||D();g();T(g,C)}
a("keydown",o,!0);a("pointerdown",o,!0);a("pageshow",function(v){v.persisted&&k()});
d.addEventListener("load",g,!0);d.readyState=="loading"?d.addEventListener("readystatechange",i):i();T(g,M);T(g,3e3)})()</script>
<!-- WAITING:END -->
  <div class="blog-shell">
    <header class="blog-header">
      <a class="blog-brand" href="<?php echo esc_url( home_url('/') ); ?>" aria-label="بازگشت به صفحه اصلی محمدعلی کهن‌دژ">
        <img src="<?php echo KDCV; ?>/assets/images/logo/logo.svg" width="40" height="40" alt="MK">
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
