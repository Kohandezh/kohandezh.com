(function(e){"use strict";var f=function(){e(".infiniteSlide").length>0&&e(".infiniteSlide").each(function(){var a=e(this),t=a.data("style")||"left",i=a.data("clone")||2,n=a.data("speed")||50;a.infiniteslide({speed:n,direction:t,clone:i,pauseonhover:!0})})},p=()=>{if(window.__KDCV_CLOCK_ACTIVE__)return;function a(t=".time-local"){function i(){const n=new Date,o=n.toLocaleTimeString("en-GB",{hour:"2-digit",minute:"2-digit"}),s=n.toLocaleDateString("en-US",{weekday:"short",month:"short",day:"numeric"});document.querySelectorAll(t).forEach(l=>{const d=l.querySelector(".date"),r=l.querySelector(".clock");d&&(d.textContent=s),r&&(r.textContent=o)})}i(),setInterval(i,1e3)}a(".time-local")};const v=()=>{
/* Record the language of the page being viewed, not just the one clicked.
   `siteLang` used to be written ONLY on a .lang-item click, so a visitor who
   landed on fa.html straight from the geo/browser router had nothing stored —
   and Certificates, which falls back to `siteLang`, dropped to its own
   lang="en" markup. Their Persian session turned English mid-navigation. */
try{var pl=(document.documentElement.lang||"").toLowerCase().split("-")[0];
if(["en","fa","ar","de","es","fr","tr","zh","ja"].indexOf(pl)>-1&&localStorage.getItem("siteLang")!==pl)localStorage.setItem("siteLang",pl);}catch(e){}
const a=document.querySelectorAll(".lang-item");if(!a.length)return;const t=document.documentElement,i=s=>{a.forEach(l=>l.classList.remove("is-active")),s.classList.add("is-active"),t.setAttribute("lang",s.dataset.lang),t.setAttribute("dir",s.dataset.dir)},n=localStorage.getItem("siteLang"),o=n&&[...a].find(s=>s.dataset.lang===n);o&&o.dataset.lang===t.getAttribute("lang")&&i(o),a.forEach(s=>{s.addEventListener("click",()=>{localStorage.setItem("siteLang",s.dataset.lang);try{sessionStorage.setItem("kdcvExplicitLocale",s.dataset.lang)}catch(l){}if(s.dataset.href&&s.dataset.lang!==t.getAttribute("lang")){window.location.href=s.dataset.href;return}i(s)})})};var b=function(){if(e(document.body).hasClass("counter-scroll")){let a=function(n){var o=n.getBoundingClientRect();return o.top<window.innerHeight&&o.bottom>0},t=function(n){e().countTo&&n.find(".number").each(function(){var o=e(this).data("to"),s=e(this).data("speed");e(this).countTo({to:o,speed:s})})},i=function(){e(".counter").each(function(){var n=e(this),o=n.data("started");!o&&a(this)&&(t(n),n.data("started",!0))})};e(".counter").each(function(){e(this).data("started",!1)}),i(),e(window).on("scroll resize",i)}},y=function(){let a=20,t=20;e(".hover-cursor-img").on("mousemove",function(i){e(this).find(".hover-image").css({top:i.clientY+t+"px",left:i.clientX+a+"px"})}),e(".hover-cursor-img").on("mouseenter",function(){e(this).find(".hover-image").css({transform:"scale(1)",opacity:1})}),e(".hover-cursor-img").on("mouseleave",function(){e(this).find(".hover-image").css({transform:"scale(0)",opacity:0})})};const w=()=>{const a=document.querySelector(".tf-left-bar"),t=a&&a.querySelector(".btn-setting-color"),i=document.querySelector(".sidebar-tools"),n=i&&i.querySelector(".nav-top"),o=document.querySelector(".nav-mobile-item"),s=n&&n.querySelector(".toggle-switch-mode");if(!t||!n||!s)return;const l=(document.documentElement.lang||"en").split("-")[0],d={en:{theme:"Appearance",language:"Language"},fa:{theme:"حالت نمایش",language:"زبان"},ar:{theme:"المظهر",language:"اللغة"},de:{theme:"Darstellung",language:"Sprache"},es:{theme:"Apariencia",language:"Idioma"},fr:{theme:"Apparence",language:"Langue"},tr:{theme:"Görünüm",language:"Dil"},zh:{theme:"外观",language:"语言"},ja:{theme:"外観",language:"言語"}},r=d[l]||d.en,c=(q,B)=>{const h=document.createElement("span");h.className="tool-tip text-caption menu-utility-tooltip",h.setAttribute("aria-hidden","true"),h.textContent=B,q.appendChild(h)};if(n.classList.add("menu-utilities"),s.classList.add("menu-utility-control","menu-utility-theme"),s.setAttribute("aria-label",r.theme),s.removeAttribute("title"),c(s,r.theme),t.classList.add("menu-utility-control","menu-utility-language"),t.setAttribute("aria-label",r.language),t.removeAttribute("title"),c(t,r.language),n.appendChild(t),a.remove(),!o||o.querySelector(".mobile-menu-utilities"))return;const u=document.createElement("li");u.className="mobile-menu-utilities";const m=document.createElement("button");m.type="button",m.className="mobile-menu-utility toggle-switch-mode",m.setAttribute("aria-label",r.theme),m.innerHTML='<i class="icon icon-light" aria-hidden="true"></i><span>'+r.theme+"</span>";const g=t.cloneNode(!0);g.className="mobile-menu-utility mobile-menu-language",g.removeAttribute("title"),g.innerHTML='<span class="language-button-label" aria-hidden="true">'+l.toUpperCase()+"</span><span>"+r.language+"</span>",u.appendChild(m),u.appendChild(g),o.insertBefore(u,o.firstChild)},C=()=>{
/* "Trusted by banks & enterprises" strip.
   Three identical five-logo sets are in the markup so the CSS marquee can loop
   seamlessly. Three jobs here:
     1. alt text from the filename, for logos authored without one;
     2. each logo becomes a LINK to the enterprise portfolio — the client work
        is described there, so a reader who recognises a mark has somewhere to
        go. It deliberately does NOT link out to the banks: two of these are
        merged institutions whose sites no longer resolve to the entity named
        on the logo, and an outbound link would read as their endorsement.
     3. sets 2 and 3 are hidden from assistive tech. They are duplicates that
        exist only to make the loop seamless, and a screen reader was
        announcing all fifteen. */
const strip=document.querySelector(".infiniteSlide-brand:not(.eco-marquee)");
if(!strip)return;
const NAMES={"brand-1":"Parsian Bank","brand-2":"Sarmayeh Bank","brand-3":"Mehr Eqtesad Bank","brand-4":"Tehran Chamber of Commerce","brand-5":"Noor Hospital"};
strip.querySelectorAll("img").forEach(img=>{
const src=img.getAttribute("src")||"",key=Object.keys(NAMES).find(k=>src.includes(k));
if(key&&!img.getAttribute("alt"))img.setAttribute("alt",NAMES[key]);
img.setAttribute("decoding","async");
});
const lang=(document.documentElement.lang||"en").split("-")[0];
const COPY={fa:"مشاهده همه همکاری‌ها",ar:"عرض جميع الشراكات",de:"Alle Partnerschaften",es:"Ver todas las colaboraciones",fr:"Voir toutes les collaborations",tr:"Tüm iş birlikleri",zh:"查看全部合作",ja:"すべての実績を見る",en:"View enterprise portfolio"};
/* Absolute on WordPress (pages live at pretty slugs, so a relative path would
   resolve under the current page), relative on the static build. */
const wp=window.KDCV_WP&&window.KDCV_WP.pages&&window.KDCV_WP.pages.portfolio;
const href=wp||("portfolio/?lang="+encodeURIComponent(lang));
const label=COPY[lang]||COPY.en;
const boxes=strip.querySelectorAll(".image-brand");
boxes.forEach((box,i)=>{
if(box.tagName==="A"||box.closest("a")){return;}
const link=document.createElement("a");
link.className=box.className;
link.href=href;
const img=box.querySelector("img");
const name=img&&img.getAttribute("alt")||"";
link.setAttribute("aria-label",name?name+" — "+label:label);
while(box.firstChild)link.appendChild(box.firstChild);
box.parentNode.replaceChild(link,box);
if(i>=5){link.setAttribute("aria-hidden","true");link.setAttribute("tabindex","-1");}
});
const head=strip.previousElementSibling;
if(!head||head.querySelector(".trust-portfolio-link"))return;
const cta=document.createElement("a");
cta.className="trust-portfolio-link";
cta.href=href;
cta.textContent=label;
head.appendChild(cta);
},S=()=>{const a=e(".toggle-switch-mode"),t=e("body"),i=e(".image-switch"),n=e("#logo_mode");if(!a.length)return;i.each(function(){const c=e(this);c.data("light-original")||c.data("light-original",c.attr("src"))}),n.length&&!n.data("light-original")&&n.data("light-original",n.attr("src"));const o=c=>{if(i.each(function(){const u=e(this),m=u.data("light-original"),g=u.data("dark");u.attr("src",c&&g?g:m)}),n.length){const u=n.data("light-original"),m=n.data("dark");n.attr("src",c&&m?m:u)}},s=c=>{a.each(function(){e(this).toggleClass("active",c).attr("aria-pressed",c?"true":"false")})},l=localStorage.getItem("darkMode"),d=t.data("default-mode");let r;l!==null?r=l==="enabled":typeof d!="undefined"?r=d==="dark":r=!1,t.toggleClass("dark-mode",r),o(r),s(r),a.on("click",function(){const c=!t.hasClass("dark-mode");t.toggleClass("dark-mode",c),s(c),o(c),localStorage.setItem("darkMode",c?"enabled":"disabled")})};var k=function(){
/* Circular ring text around the KSF mark.
   WAS: 52 absolutely-positioned <span>s, each rotated by 360/52 = 6.9deg. On a
   166px ring that is ~10px of arc per glyph while the type was set at 16px, so
   wide letters (Q, M, W) collided and the ring read as noise.
   NOW: one SVG <textPath>. The browser lays the string along the arc with real
   kerning, and textLength pinned to the exact circumference (2*PI*R) makes the
   tracking self-adjusting — it can neither crowd nor leave a gap, at any ring
   size. Colour is CSS (`.kdcv-ring-text`), so both themes stay in styles.css. */
var rings=document.querySelectorAll(".text-rotate .text");
if(!rings.length)return;
/* The ring label is localized. RTL is the reason this needed care: an SVG
   <textPath> inherits `direction` from the document, and on fa/ar the inherited
   `rtl` collapsed the whole string to a ~13px bbox \u2014 the ring rendered blank.
   So direction is pinned explicitly per locale (see `.kdcv-ring-text` in
   styles.css for the LTR default) and, for RTL, the arc is drawn the other way
   round (sweep-flag 0) so right-to-left glyph order still reads clockwise. */
var RING_LABELS={
en:"SECURITY \u2022 AI \u2022 QUANTUM \u2022 ",
fa:"\u0627\u0645\u0646\u06cc\u062a \u2022 \u0647\u0648\u0634 \u0645\u0635\u0646\u0648\u0639\u06cc \u2022 \u06a9\u0648\u0627\u0646\u062a\u0648\u0645 \u2022 ",
ar:"\u0627\u0644\u0623\u0645\u0646 \u2022 \u0627\u0644\u0630\u0643\u0627\u0621 \u0627\u0644\u0627\u0635\u0637\u0646\u0627\u0639\u064a \u2022 \u0627\u0644\u0643\u0645 \u2022 ",
de:"SICHERHEIT \u2022 KI \u2022 QUANTUM \u2022 ",
es:"SEGURIDAD \u2022 IA \u2022 CU\u00c1NTICA \u2022 ",
fr:"S\u00c9CURIT\u00c9 \u2022 IA \u2022 QUANTIQUE \u2022 ",
tr:"G\u00dcVENL\u0130K \u2022 YAPAY ZEKA \u2022 KUANTUM \u2022 ",
zh:"\u5b89\u5168 \u2022 \u4eba\u5de5\u667a\u80fd \u2022 \u91cf\u5b50 \u2022 ",
ja:"\u30bb\u30ad\u30e5\u30ea\u30c6\u30a3 \u2022 AI \u2022 \u91cf\u5b50 \u2022 "};
var ringLang=(document.documentElement.getAttribute("lang")||"en").toLowerCase().split("-")[0];
var rtlRing=ringLang==="fa"||ringLang==="ar";
var R=76,C=(2*Math.PI*R).toFixed(2),label=RING_LABELS[ringLang]||RING_LABELS.en;
if(rtlRing)label=label.split(" • ").reverse().join(" • ");
/* One pass to start with; the fill loop below adds however many more the
   circumference actually needs, which differs per script. */
var repeats=1;
Array.prototype.forEach.call(rings,function(host,i){
if(host.querySelector(".kdcv-ring"))return;
var id="kdcv-ring-path-"+i;
host.textContent="";
host.insertAdjacentHTML("beforeend",
'<svg class="kdcv-ring" viewBox="0 0 200 200" aria-hidden="true" focusable="false">'+
'<defs><path id="'+id+'" fill="none" d="M100,100 m-'+R+',0 a'+R+','+R+' 0 1,1 '+(R*2)+',0 a'+R+','+R+' 0 1,1 -'+(R*2)+',0"/></defs>'+
'<text class="kdcv-ring-text" direction="ltr"><textPath href="#'+id+'" startOffset="0">'+
new Array(repeats+1).join(label)+'</textPath></text></svg>');
/* textLength is applied AFTER measuring, not up front, and only ever to CLOSE
   a gap — never to open one. Stretching a short string onto the full
   circumference letter-spaces it, and Persian/Arabic are cursive: spacing them
   apart severs the joins and the ring renders as loose disconnected letters
   (that is exactly how fa/ar looked). So: repeat the label until it naturally
   over-fills the circle, then pin textLength to the circumference, which now
   only ever squeezes slightly. */
var tp=host.querySelector("textPath");
if(tp&&tp.getComputedTextLength){
var guard=0;
while(tp.getComputedTextLength()<C&&guard++<12)tp.textContent+=label;
tp.setAttribute("textLength",C);
tp.setAttribute("lengthAdjust","spacing");
}
});
},x=()=>{function a(){document.querySelectorAll(".intro-title span").forEach(i=>{if(i.classList.contains("active"))return;const n=i.getBoundingClientRect();n.top<window.innerHeight*.8&&n.bottom>0&&setTimeout(()=>{i.classList.add("active")},300)})}window.addEventListener("scroll",a),window.addEventListener("load",a)},A=()=>{e(window).on("scroll",function(){e(".wrap-hover-award").each(function(){let a=e(this),t=a.offset().top,i=t+a.outerHeight(),n=e(window).scrollTop(),o=n+e(window).height();i>n&&t<o?a.addClass("active"):a.removeClass("active")})})},E=()=>{if(e(".text-typing").length>0){let r=function(){const c=a[i];if(o)t.textContent=c.slice(0,--n),n===0&&(o=!1,i=(i+1)%a.length);else if(t.textContent=c.slice(0,++n),n===c.length){o=!0,setTimeout(r,d);return}setTimeout(r,o?l:s)};const a=["Kohandezh","Designer","Developer"],t=document.getElementById("typed");let i=0,n=0,o=!1;const s=100,l=50,d=1200;r()}},I=function(){let a=e("a.scroll-link");e(document).on("scroll",function(){a.each(function(){let t=e(this).attr("href");if(!t||t==="#"||!e(t).length)return;let i=e(t).offset().top,n=e(t).outerHeight(),o=i+n,s=e(document).scrollTop();s<o-20&&s>=i-20?e(this).addClass("active"):e(this).removeClass("active")})})},L=()=>{e(".action-open-mobile > .tf-btn-icon, .overlay-pop").on("click",function(t){t.preventDefault();t.stopPropagation();var o=!e(".nav-mobile-list").hasClass("open");e(".nav-mobile-list, .overlay-pop").toggleClass("open",o);e("body").toggleClass("overflow-hidden",o);e(".btn-mobile-menu").toggleClass("close",o)}),e(".mobile-menu-utility").on("click",function(){e(".nav-mobile-list, .overlay-pop").removeClass("open"),e("body").removeClass("overflow-hidden"),e(".btn-mobile-menu").removeClass("close")})},T=function(){e("#contactform").each(function(){e(this).validate({submitHandler:function(a){var t=e(a),i=t.serialize(),n=e("<div />",{class:"loading"});e.ajax({type:"POST",url:t.attr("action"),data:i,dataType:"json",beforeSend:function(){t.find(".send-wrap").append(n)},success:function(o){var s,l;o&&o.success?(s="Thank you! Your message has been sent — we will contact you shortly.",l="msg-success",t.find('input[type="text"], input[type="email"], textarea').val("")):(s=o&&o.message?o.message:"Error sending message. Please try again or email us directly.",l="msg-error"),t.prepend(e("<div />",{class:"flat-alert "+l,text:s,role:o&&o.success?"status":"alert","aria-live":o&&o.success?"polite":"assertive"}).append(e('<button type="button" class="close" aria-label="Dismiss notification"><i class="icon icon-close2" aria-hidden="true"></i></button>')))},error:function(){t.prepend(e("<div />",{class:"flat-alert msg-error",text:"Error sending message. Please try again or email us directly.",role:"alert","aria-live":"assertive"}).append(e('<button type="button" class="close" aria-label="Dismiss notification"><i class="icon icon-close2" aria-hidden="true"></i></button>')))},complete:function(){t.find(".loading").remove()}})}})})};e(function(){e(document).on("click",".flat-alert .close",function(){e(this).closest(".flat-alert").remove()}),T(),I(),k(),f(),p(),b(),v(),y(),w(),C(),S(),x(),A(),E(),L()})})(jQuery);
