// Read-only public HTTP audit. Does not authenticate, submit URLs or purge caches.
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const base = 'https://kohandezh.com';
const output = process.argv[2] || '/tmp/kdcv-qa-20260907/public-audit.json';
const records = [];
async function get(path) {
  const url = new URL(path, base).href;
  const start = performance.now();
  try {
    const r = await fetch(url, { signal: AbortSignal.timeout(25000) });
    const headersMs = performance.now() - start;
    const body = await r.text();
    const record = { url, finalUrl: r.url, status: r.status, headersMs: Math.round(headersMs), totalMs: Math.round(performance.now()-start), bytes: Buffer.byteLength(body), headers: Object.fromEntries(['content-type','cache-control','last-modified','etag','x-robots-tag','x-litespeed-cache','wpo-cache-status'].map(k=>[k,r.headers.get(k)])) };
    if ((r.headers.get('content-type') || '').includes('text/html')) {
      const d = new JSDOM(body, {url:r.url}).window.document;
      record.html = {lang:d.documentElement.lang,dir:d.documentElement.dir,title:d.title,canonical:[...d.querySelectorAll('link[rel=canonical]')].map(e=>e.href),robots:[...d.querySelectorAll('meta[name=robots]')].map(e=>e.content),alternates:[...d.querySelectorAll('link[hreflang]')].map(e=>({lang:e.hreflang,url:e.href})),scripts:[...d.scripts].filter(e=>e.src).map(e=>e.src),styles:[...d.querySelectorAll('link[rel=stylesheet]')].map(e=>e.href)};
    } else if (url.includes('sitemap')) {
      record.locations = [...body.matchAll(/<loc>(.*?)<\/loc>/gs)].map(m=>m[1].replaceAll('&amp;','&'));
    } else if (url.endsWith('/robots.txt')) record.body = body;
    records.push(record); return record;
  } catch (e) { const record={url,error:e.message};records.push(record);return record; }
}
async function batch(paths) { for(let i=0;i<paths.length;i+=3) await Promise.all(paths.slice(i,i+3).map(get)); }
(async()=>{
  await batch(['/','/fa/','/ar/','/de/','/es/','/fr/','/tr/','/zh/','/ja/','/ru/','/robots.txt','/sitemap.xml','/?kdcv_sitemap=index','/wp-sitemap.xml','/llms.txt','/fa-llms.txt']);
  const maps=records.filter(r=>r.locations); const urls=[...new Set(maps.flatMap(r=>r.locations))].filter(u=>new URL(u).origin===base&&!records.some(r=>r.url===u));
  await batch(urls.slice(0,100));
  const assets=[...new Set(records.flatMap(r=>[...(r.html?.scripts||[]),...(r.html?.styles||[])]))].filter(u=>new URL(u).origin===base);
  await batch(assets.slice(0,100));
  fs.writeFileSync(output,JSON.stringify({at:new Date().toISOString(),note:'HTTP transfer timings are not Lighthouse, LCP, INP or field CWV.',records},null,2));
  console.log(JSON.stringify({output,total:records.length,failures:records.filter(r=>r.error||r.status>=400),pages:records.filter(r=>r.html).map(r=>({url:r.url,status:r.status,lang:r.html.lang,canonical:r.html.canonical,robots:r.html.robots,headersMs:r.headersMs,bytes:r.bytes})),robots:records.find(r=>r.body)?.body},null,2));
})();
