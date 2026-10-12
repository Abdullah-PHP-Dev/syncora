# Marketing SEO deployment

The shared marketing layout uses `config/seo.php` for canonical URLs, English/Arabic alternate-language links, localized titles and descriptions, Open Graph/Twitter cards, and Organization/WebSite/WebPage/BreadcrumbList JSON-LD.

Production settings:

```dotenv
APP_URL=https://socialeaz.com
SEO_BASE_URL=https://socialeaz.com
SEO_INDEXABLE=true
GOOGLE_SITE_VERIFICATION=
BING_SITE_VERIFICATION=
```

Use the verification token provided by each search engine; leave it empty until the site property is configured. Never paste an entire HTML verification tag into the setting.

After deploying the code and production settings, rebuild the application's configuration and route caches using the normal deployment workflow. Confirm that `https://socialeaz.com/robots.txt` allows crawling and includes `https://socialeaz.com/sitemap.xml`. The sitemap lists the 18 public marketing pages in both languages, automatically including configured product and solution pages. Authentication and password-recovery pages are excluded and emit `noindex`.

Local and staging hosts are intentionally blocked from indexing unless they match `SEO_BASE_URL`. Keep `SEO_INDEXABLE=false` for a staging deployment that shares the production hostname through an internal proxy.

Add a domain property in Google Search Console and complete domain ownership verification. Submit `sitemap.xml` and use URL Inspection to check the homepage and key product pages. Bing Webmaster Tools can also use the sitemap. Submission helps discovery; indexing and ranking are not guaranteed.

Set the `SOCIAL_*_URL` values to the real official profiles. Valid HTTPS links will also appear in Organization `sameAs` structured data. No ratings, review counts, or unverified business details are added.

Checks:

```sh
vendor/bin/phpunit tests/Feature/Seo/MarketingSeoTest.php
```
