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
| 1 | **`/robots.txt` returns 404 in production** (nginx error page, so the request never reaches Laravel). `sitemap.xml` from the same controller works. Crawlers get no robots rules and no sitemap pointer | High | **Fixed**: a static `public/robots.txt` with the production rules is live (`MarketingSeoTest` keeps it in sync with `SeoController::robots()`) |
| 2 | Marketing images 1–1.8 MB each (hero, the LCP image, was 1.78 MB at 546×309; the logo was 746 KB on every page). About 22 MB of PNG/JPG across the pages | High (LCP, mobile) | **Fixed**: WebP at 2× display size, 0.75 MB total |
| 3 | Product / solution titles were the bare product name ("Engagement \| Socialeaz"), with no search intent | Medium–High | **Fixed**: keyword titles in EN/AR, all ≤ 62 characters, unique |
| 4 | Server time about 0.6 s per HTML page (TTFB 1.5–1.9 s from Europe). The app renders these pages in 5–11 ms with 2–4 queries locally, so the time is server / network | Medium | **Needs server checks**, see Performance |
| 5 | No `SoftwareApplication` schema; breadcrumb names repeated "\| Socialeaz" | Medium | **Fixed** |
| 6 | No HSTS / Referrer-Policy header | Low–Medium | **Fixed** (app middleware) |
| 7 | Sitemap had no hreflang alternates (they were only in the HTML) | Low | **Fixed** |
| 8 | 7 separate small render-blocking CSS files + the Vite bundle on the home page | Low–Medium | **Fixed** (Oct 23): one `marketing.css` bundle, plus `marketing-home.css` on the homepage |
| 9 | The home page shows named testimonials (e.g. "Sarah Kim, Content Creator"). If these aren't real customers, they hurt trust and can breach consumer-protection rules | Trust / compliance | **Confirm with the business**; replace with real quotes or remove |
| 10 | `/` → `/en` is a 302 | Info | Fine: language selection, with x-default = `/en` |

Already correct: HTTPS + HTTP→HTTPS and www→apex 301s, HTTP/2 + HTTP/3 and Brotli via Cloudflare, unique titles and descriptions, self-referencing canonicals, hreflang, `noindex` on auth pages, real 404 status, alt text on all images, lazy loading, explicit width/height (no layout shift), and `fetchpriority="high"` on the hero.

## Deployment

1. **robots.txt (done; kept for reference).** On the production nginx site (Forge-style configs have this block), back up the config file, then change

   ```nginx
   location = /robots.txt  { access_log off; log_not_found off; }
   ```

   to

   ```nginx
   location = /robots.txt  { access_log off; log_not_found off; try_files $uri /index.php?$query_string; }
   ```

   Run `nginx -t && systemctl reload nginx`. Check that `curl -s https://socialeaz.com/robots.txt` shows `Allow: /` and the `Sitemap:` line. Rollback: restore the backed-up file and reload nginx. Production now serves the static `public/robots.txt` instead; staging and labs still get `noindex` from the robots meta tag (`seo.indexable` + host check).
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
3. Static assets: proposed nginx config under *Oct 23 fixes*. They currently get `max-age=14400`.
4. Optional, larger change: guest marketing pages start a session on every request (`Cache-Control: no-cache, private`), so Cloudflare can't cache the HTML. Serving them without a session for anonymous visitors, plus a Cloudflare cache rule, would make TTFB near-instant. That requires reviewing forms (CSRF) and the language switcher first.
5. ~~Combine the marketing CSS files into the Vite bundle~~ (done Oct 23).

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

## Search Console fixes (Oct 2026)

What the code now does: `/{locale}/product` 301s to `/{locale}/services`; legacy aliases (`/help`, `/channels`, `/tools`, `/ai-copilot`) are 301s and nothing links to them; any GET path with a trailing slash 301s to the slashless URL (`RemoveTrailingSlash`); sitemap URLs carry `<lastmod>` (newest mtime of the page's view/config files); FAQ items a page shows are also emitted as FAQPage JSON-LD; `public/ads.txt` exists.

### Server-side checks (not fixable in code)

Googlebot got 21 4XX responses on Oct 19 for pages that return 200 now. Nothing in the app throttles or blocks public pages (`MarketingRoutesTest` locks that in), so check the edge:

1. **Cloudflare > Security > Events**, filter Oct 19 and user agent contains `Googlebot`: look for Managed Challenge / Block / Rate limit actions and note the rule ID.
2. **Cloudflare > Security > Bots**: Bot Fight Mode (free plan) challenges some verified bots; turn it off or use Super Bot Fight Mode with "Verified bots: Allow".
3. **Cloudflare > Security > WAF**: custom and rate-limiting rules should skip `cf.client.bot` (verified bots) and must not cover `GET /en/*`, `/ar/*`.
4. **Forge / Nginx**: `grep ' 4[0-9][0-9] ' /var/log/nginx/socialeaz.com-access.log* | grep -i googlebot` for Oct 19. 429 = `limit_req` in the Nginx site config; 403 = `deny` rules or fail2ban. Also check `storage/logs/laravel-2026-10-19.log` for exceptions.
5. If the origin logs show no 4XX for those hits, the edge answered them: it's Cloudflare.

### Search Console housekeeping

- Remove the stale `/en/sitemap.xml` submission (Sitemaps report) and keep only `https://socialeaz.com/sitemap.xml`. Nothing in the code references locale sitemaps.
- `login.socialeaz.com` no longer resolves, but Google still retries `http://login.socialeaz.com/login`. Either add a DNS record that 301s to `https://socialeaz.com/en/login`, or leave it to drop out (DNS failures on a dead subdomain don't hurt the main site).

### Thin pages: content to write (EN + AR, no invented claims)

Add answered questions to the page's existing FAQ list (`faq` in `config/marketing-products.php` / `marketing-solutions.php`, or `config/marketing-faqs.php` for contact). They render visibly and as FAQPage schema automatically. Arabic word counts are below 300 for:

- **/ar/contact** (290): response times and support hours; what billing questions support can help with; how to request a demo or team onboarding; where to report a connected-account problem.
- **/ar/product/scheduling-publishing** (294): which platforms support scheduling; how time zones work; whether you can edit or cancel a scheduled post; what happens when publishing fails; approvals before scheduling (if available on the plan).
- **/ar/solutions/teams** (293): roles and permissions; approvals workflow; how many members per plan (link pricing); handing work between members; a short real customer scenario if one is available.

## Oct 23 fixes

In the code:

- `/{locale}/yellow`, `/studio` and `/amber` (retired homepage designs, served as indexable 200 duplicates) 301 to `/{locale}` in one hop.
- `/index.php/...` and `/index.php?...` 301 to the clean URL for GET/HEAD (`RemoveIndexPhp` middleware), query string kept.
- `/{locale}/share/posts/{id}` only shows public posts that are live or being published now (`Post::scopeShareable`); drafts, failed, private and future-scheduled posts and missing IDs are 404. The page sends `X-Robots-Tag: noindex` and a robots meta tag; share-button crawlers ignore both. The dashboard no longer offers the Snap share button after *scheduling* a post.
- Marketing layout CSS is one Vite bundle (`resources/css/marketing.css`, homepage adds `marketing-home.css`). The sources stay in `public/assets/css` because other layouts link them; **run `npm run build` and commit `public/build` after editing one**.
- Google Fonts: Noto Sans Arabic only on Arabic pages, and a `wght@400..800` range (both are variable fonts, so the files downloaded are the same; the font CSS drops from 37 KB to 2.5 KB on English pages and 7.5 KB on Arabic ones).

### Proposed nginx config (not deployed)

Tested on nginx 1.27 with a Forge-style server block: GET/HEAD redirect in one hop, POST untouched, no loop through `try_files`' internal `/index.php`, 404s never get the immutable header. The app's `RemoveIndexPhp` does the same, so (1) is optional and only saves a PHP round trip.

```nginx
# Top of the site file, outside server { }:
map "$request_method $request_uri" $socialeaz_clean_index_php {
    default "";
    "~^(?:GET|HEAD) /index\.php/+(?<ixp_path>[^?]*?)/*(?<ixp_query>\?.*)?$" "/$ixp_path$ixp_query";
    "~^(?:GET|HEAD) /index\.php(?<ixp_query_only>\?.*)?$"                   "/$ixp_query_only";
}

# Inside server { }, above the location blocks:
if ($socialeaz_clean_index_php) { return 301 $socialeaz_clean_index_php; }

# Content-hashed Vite output. add_header here replaces the server-level
# headers, so the Forge security headers are repeated.
location ^~ /build/assets/ {
    try_files $uri =404;
    access_log off;
    add_header Cache-Control "public, max-age=31536000, immutable";
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";
}

# Images keep their names when replaced: 7 days. `expires` keeps the inherited headers.
location ~* ^/assets/images/.+\.(?:png|jpe?g|webp|gif|svg|ico|avif)$ {
    try_files $uri =404;
    access_log off;
    expires 7d;
}
```

### Guest pages, sessions and HTML caching (findings, nothing changed)

- From Zurich, a Cloudflare cache HIT answers in ~0.10–0.15 s, while any origin fetch, even a static `.txt` that never reaches PHP, takes ~1.1 s. PHP pages take 0.45–1.6 s. So TTFB is mostly the edge-to-origin round trip; edge caching of guest HTML is the lever, PHP tuning is secondary.
- Every cookieless guest request starts a session (one `sessions` row per hit with the database driver locally), and sets `locale`, `XSRF-TOKEN` and the session cookie with `Cache-Control: no-cache, private`. Cloudflare won't cache responses that set cookies.
- Session/user-dependent output on guest-reachable pages: the CSRF meta tag in `layouts/apps.blade.php`; pricing CTAs and checkout URLs (`pricing.blade.php`, `sections/billing-workflow-pricing.blade.php`) and the contact page ticket button depend on `auth()`. No marketing page has a form. Locale comes from the URL prefix; `/` uses Accept-Language plus the `locale` cookie/session.
- A safe design, if wanted: a `stateless-marketing` middleware stack for GET marketing routes when the request has no session cookie (no StartSession, no CSRF cookie, no locale cookie; `Cache-Control: public, s-maxage=600`), guests always see the guest CTAs, and a Cloudflare Cache Rule that caches `/en*` and `/ar*` HTML only when the request has no `socialeaz-session` / `remember_web_*` cookie. `/`, `/login`, `/register`, `/dashboard*`, `/admin*`, `/share/*` and anything non-GET stay uncached. Needs its own approval and tests.
