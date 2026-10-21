# Marketing SEO

The public marketing site (home, pricing, about, contact, services, privacy, terms, 7 product pages, 4 solution pages, in English and Arabic: 36 indexable URLs) is server-rendered Blade, so search engines get full HTML without running JavaScript. Private areas (dashboard, admin, auth, API) are `noindex` and disallowed in robots.txt.

## How it works

| Piece | Where |
|---|---|
| Titles, descriptions, canonical, hreflang (en, ar, x-default), robots meta, Open Graph / Twitter cards, JSON-LD | `app/Support/Seo/MarketingSeo.php` + `resources/views/layouts/partials/marketing-seo.blade.php` |
| Search-intent titles for product / solution pages (the visible H1 is unchanged) | `MarketingSeo::PAGE_TITLES` |
| Structured data: Organization, WebSite, WebPage / AboutPage / ContactPage, BreadcrumbList, SoftwareApplication (home, pricing, product pages) | `MarketingSeo::schema()` |
| SoftwareApplication `offers`: an AggregateOffer built from the **active plans in the database** (cached 1 h); left out if prices are unavailable or currencies differ. No ratings, reviews or invented numbers | `MarketingSeo::planOffers()` |
| `sitemap.xml` with `xhtml:link` hreflang alternates for every URL | `SeoController::sitemap()` |
| `robots.txt` (allows the marketing site on the production host only; everything blocked on other hosts) | `SeoController::robots()` |
| HSTS (HTTPS only, 1 year, no includeSubDomains) + Referrer-Policy; never overrides headers set by the server / Cloudflare | `app/Http/Middleware/SecurityHeaders.php` |
| Optimized WebP images for the marketing and auth pages (originals kept; `og:image` keeps the originals for social-network compatibility) | `public/assets/images/home-theme/*.webp` |

New product or solution pages added to `config/marketing-products.php` / `config/marketing-solutions.php` appear in the sitemap automatically. Add their search title to `PAGE_TITLES`; the tests fail if a title is missing, duplicated or too long.

## Audit (2026-10-22, live site)

| # | Finding | Impact | Status |
|---|---|---|---|
| 1 | **`/robots.txt` returns 404 in production** (nginx error page, so the request never reaches Laravel). `sitemap.xml` from the same controller works. Crawlers get no robots rules and no sitemap pointer | High | **Needs a server change**, see Deployment step 1 |
| 2 | Marketing images 1–1.8 MB each (hero, the LCP image, was 1.78 MB at 546×309; the logo was 746 KB on every page). About 22 MB of PNG/JPG across the pages | High (LCP, mobile) | **Fixed**: WebP at 2× display size, 0.75 MB total |
| 3 | Product / solution titles were the bare product name ("Engagement \| Socialeaz"), with no search intent | Medium–High | **Fixed**: keyword titles in EN/AR, all ≤ 62 characters, unique |
| 4 | Server time about 0.6 s per HTML page (TTFB 1.5–1.9 s from Europe). The app renders these pages in 5–11 ms with 2–4 queries locally, so the time is server / network | Medium | **Needs server checks**, see Performance |
| 5 | No `SoftwareApplication` schema; breadcrumb names repeated "\| Socialeaz" | Medium | **Fixed** |
| 6 | No HSTS / Referrer-Policy header | Low–Medium | **Fixed** (app middleware) |
| 7 | Sitemap had no hreflang alternates (they were only in the HTML) | Low | **Fixed** |
| 8 | 7 separate small render-blocking CSS files + the Vite bundle on the home page | Low–Medium | Recommendation |
| 9 | The home page shows named testimonials (e.g. "Sarah Kim, Content Creator"). If these aren't real customers, they hurt trust and can breach consumer-protection rules | Trust / compliance | **Confirm with the business**; replace with real quotes or remove |
| 10 | `/` → `/en` is a 302 | Info | Fine: language selection, with x-default = `/en` |

Already correct: HTTPS + HTTP→HTTPS and www→apex 301s, HTTP/2 + HTTP/3 and Brotli via Cloudflare, unique titles and descriptions, self-referencing canonicals, hreflang, `noindex` on auth pages, real 404 status, alt text on all images, lazy loading, explicit width/height (no layout shift), and `fetchpriority="high"` on the hero.

## Deployment

1. **Fix robots.txt (server).** On the production nginx site (Forge-style configs have this block), back up the config file, then change

   ```nginx
   location = /robots.txt  { access_log off; log_not_found off; }
   ```

   to

   ```nginx
   location = /robots.txt  { access_log off; log_not_found off; try_files $uri /index.php?$query_string; }
   ```

   Run `nginx -t && systemctl reload nginx`. Check that `curl -s https://socialeaz.com/robots.txt` shows `Allow: /` and the `Sitemap:` line. Rollback: restore the backed-up file and reload nginx. Don't add a static `public/robots.txt`: labs and other hosts would then lose their `Disallow: /`.
2. **Deploy the code** (WebP files in `public/assets/images/home-theme/`, views, `MarketingSeo`, middleware). No migration and no JS build. Then `php artisan config:cache && php artisan view:cache && php artisan event:cache`. Don't run `route:cache`: it currently fails in this repo because LaravelLocalization creates duplicate route names per locale.
3. **Production `.env`**

   ```dotenv
   APP_URL=https://socialeaz.com
   SEO_BASE_URL=https://socialeaz.com
   SEO_INDEXABLE=true
   GOOGLE_SITE_VERIFICATION=   # token only, not the whole <meta> tag
   BING_SITE_VERIFICATION=
   ```

   Keep `SEO_INDEXABLE=false` on staging and labs.
4. **Cloudflare:** purge the cache for `/assets/images/home-theme/*` and the marketing pages after deploying.

Rollback for the code: `git revert` the commit. The original PNG/JPG files were not removed, so the views work again immediately.

## Manual setup (needs account access; not done or verifiable from the code)

- **Google Search Console:** add a *Domain* property for `socialeaz.com` (DNS TXT record in Cloudflare). Submit `https://socialeaz.com/sitemap.xml`, then use URL Inspection on `/en`, `/ar`, `/en/pricing` and two product pages. Check *Pages* (indexing) and *Core Web Vitals* after 2–4 weeks.
- **Bing Webmaster Tools:** import from Search Console or verify with `BING_SITE_VERIFICATION`; submit the sitemap.
- **Google Analytics 4:** create the property and add its tag only after the cookie-consent question is settled. Mark `sign_up` and `purchase` (subscription checkout) as key events and segment by *Session default channel group = Organic Search*.
- **Validate:** Rich Results Test and validator.schema.org on `/en` and a product page; PageSpeed Insights (mobile) on `/en` and `/en/pricing` before and after deploying.
- **Social profiles:** set the `SOCIAL_*_URL` values to the real official profiles; they then appear in the Organization `sameAs`.

## Performance (server, needs access)

The app itself renders marketing pages in ~10 ms, so the ~0.6 s server time comes from the stack:

1. Confirm OPcache is enabled with `opcache.validate_timestamps=0` in production (and reset on deploy), and that `config:cache` / `view:cache` run on every deploy.
2. Check the origin's region and PHP-FPM pool saturation. For GCC users, an origin or Cloudflare tiered cache close to Riyadh/Dubai/Bahrain lowers latency.
3. Static assets: set a long `Cache-Control` (e.g. `public, max-age=31536000, immutable` for `/build/*`, `max-age=2592000` for `/assets/images/*`). They currently get `max-age=14400`.
4. Optional, larger change: guest marketing pages start a session on every request (`Cache-Control: no-cache, private`), so Cloudflare can't cache the HTML. Serving them without a session for anonymous visitors, plus a Cloudflare cache rule, would make TTFB near-instant. That requires reviewing forms (CSRF) and the language switcher first.
5. Combine the 7 small marketing CSS files into the Vite bundle to cut render-blocking requests.

## Growth roadmap (content, needs real product copy)

Priority landing pages that match products Socialeaz actually offers and aren't covered yet. Each needs genuine EN + AR copy (no thin or auto-generated pages):

1. **Social media advertising management** (Facebook/Instagram, Google/YouTube, TikTok, Snapchat, X campaign builders): e.g. "manage Snapchat and TikTok ads in one place" for KSA and the GCC.
2. **Unified inbox / customer messaging** (WhatsApp, Instagram, Messenger): WhatsApp-heavy in Saudi Arabia.
3. **Email marketing & automation.**
4. **AI customer support / knowledge base.**
5. **Arabic-first pages:** Arabic keyword research (e.g. "إدارة حسابات التواصل الاجتماعي", "جدولة منشورات انستقرام"), not just translations of the English pages.
6. Comparison pages (vs. tools buyers actually evaluate) and a blog or guides section, only with factual, maintained content.

Each new page should go into the config so it gets metadata, hreflang, sitemap entry and schema automatically, then into `PAGE_TITLES`.

## Checks

```sh
php artisan test tests/Feature/Seo
```

These cover unique titles and descriptions within SERP limits (both languages), canonical and hreflang, sitemap contents and alternates, robots rules, SoftwareApplication offers without invented ratings, security headers, and that marketing views only reference existing optimized images (< 150 KB).
