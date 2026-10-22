<?php

namespace App\Support\Seo;

use Illuminate\Support\Str;

class MarketingSeo
{
    public function pages(): array
    {
        $pages = [
            'home' => '', 'pricing' => 'pricing', 'about' => 'about',
            'contact' => 'contact', 'services' => 'services',
            'privacy' => 'privacy', 'terms' => 'terms',
        ];
        foreach (array_keys(config('marketing-products', [])) as $slug) {
            $pages['product.'.$slug] = 'product/'.$slug;
        }
        foreach (array_keys(config('marketing-solutions', [])) as $slug) {
            $pages['solutions.'.$slug] = 'solutions/'.$slug;
        }

        return $pages;
    }

    /**
     * Sitemap <lastmod>: the newest mtime of the files a page renders its
     * content from. Deploys only rewrite changed files, so this moves when
     * the page actually changes - not on every request.
     */
    public function lastModified(string $route): ?string
    {
        $sources = match (true) {
            $route === 'home' => ['views/front/pages/home_modern.blade.php'],
            str_starts_with($route, 'product.') => ['views/front/pages/products/show.blade.php', '../config/marketing-products.php'],
            str_starts_with($route, 'solutions.') => ['views/front/pages/solutions/show.blade.php', '../config/marketing-solutions.php'],
            $route === 'services' => ['views/front/pages/services.blade.php', '../config/marketing-services.php'],
            in_array($route, ['privacy', 'terms'], true) => ['views/front/pages/'.$route.'.blade.php', 'views/front/pages/legal/'.$route.'-en.blade.php', 'views/front/pages/legal/'.$route.'-ar.blade.php'],
            default => ['views/front/pages/'.$route.'.blade.php'],
        };
        $times = array_filter(array_map(fn ($file) => @filemtime(resource_path($file)), $sources));

        return $times ? date('Y-m-d', max($times)) : null;
    }

    public function url(string $path = ''): string
    {
        return rtrim(config('seo.base_url'), '/').($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    public function localizedUrl(string $route, string $locale): ?string
    {
        $pages = $this->pages();
        if (!array_key_exists($route, $pages)) {
            return null;
        }

        return $this->url($locale.($pages[$route] === '' ? '' : '/'.$pages[$route]));
    }

    /** The live site's host (seo.base_url) - analytics only runs there. */
    public function isProductionHost(string $host): bool
    {
        return strcasecmp($host, (string) parse_url($this->url(), PHP_URL_HOST)) === 0;
    }

    public function indexableHost(string $host): bool
    {
        return (bool) config('seo.indexable') && strcasecmp($host, (string) parse_url($this->url(), PHP_URL_HOST)) === 0;
    }

    /**
     * Search-intent titles for product / solution pages (the page's own H1
     * stays the short product name). Each describes what that page covers -
     * no claims beyond the page content. [en, ar]
     */
    public const PAGE_TITLES = [
        'product.content-creation' => ['AI Social Media Post & Caption Creator', 'صانع محتوى التواصل الاجتماعي بالذكاء الاصطناعي'],
        'product.ai-assistant' => ['AI Social Assistant: Captions, Hashtags & Ideas', 'مساعد ذكي لأفكار المنشورات والتعليقات والوسوم'],
        'product.engagement' => ['Social Media Inbox for Comments & Messages', 'صندوق وارد موحد للتعليقات والرسائل الاجتماعية'],
        'product.analytics' => ['Social Media Analytics & Content Performance', 'تحليلات التواصل الاجتماعي وأداء المحتوى'],
        'product.scheduling-publishing' => ['Social Media Scheduler & Content Calendar', 'جدولة منشورات التواصل الاجتماعي وتقويم المحتوى'],
        'product.integrations' => ['Social Media Integrations & Connected Accounts', 'تكاملات التواصل الاجتماعي والحسابات المتصلة'],
        'product.collaboration' => ['Social Media Team Collaboration Workspace', 'مساحة عمل لتعاون فرق التواصل الاجتماعي'],
        'solutions.creators' => ['Social Media Tools for Content Creators', 'أدوات التواصل الاجتماعي لصنّاع المحتوى'],
        'solutions.teams' => ['Social Media Management for Marketing Teams', 'إدارة التواصل الاجتماعي لفرق التسويق'],
        'solutions.agencies' => ['Social Media Management Platform for Agencies', 'منصة إدارة التواصل الاجتماعي للوكالات'],
        'solutions.brands' => ['Social Media Management for Brands & Businesses', 'إدارة التواصل الاجتماعي للعلامات التجارية'],
    ];

    public function metadata(string $route, string $locale): array
    {
        $arabic = $locale === 'ar';
        $titles = [
            'home' => ['Socialeaz | AI Social Media Management & Scheduling', 'سوشيال إيز: إدارة وجدولة التواصل الاجتماعي بالذكاء الاصطناعي'],
            'pricing' => ['Social Media Management Plans & Pricing | Socialeaz', 'خطط وأسعار إدارة التواصل الاجتماعي | سوشيال إيز'],
            'about' => ['About Socialeaz | Your AI Social Media Workspace', 'عن سوشيال إيز: مساحة عمل التواصل الاجتماعي بالذكاء الاصطناعي'],
            'contact' => ['Contact & Support | Socialeaz', 'التواصل والدعم | سوشيال إيز'],
            'services' => ['Social Media & Marketing Services | Socialeaz', 'خدمات التواصل الاجتماعي والتسويق | سوشيال إيز'],
            'privacy' => ['Privacy Policy | Socialeaz', 'سياسة الخصوصية | سوشيال إيز'],
            'terms' => ['Terms of Service | Socialeaz', 'شروط الخدمة | سوشيال إيز'],
        ];
        $descriptions = [
            'home' => ['Create content with AI, schedule social posts, manage conversations and track results. Bring your channels and team together with Socialeaz.', 'أنشئ المحتوى بالذكاء الاصطناعي وجدول المنشورات وأدر المحادثات وتابع النتائج. اجمع قنواتك وفريقك في مساحة عمل واحدة مع سوشيال إيز.'],
            'pricing' => ['Compare Socialeaz plans, features and usage limits. Choose monthly or yearly billing for your social media workspace, team and connected accounts.', 'قارن خطط سوشيال إيز ومزاياها وحدود الاستخدام. اختر الفوترة الشهرية أو السنوية لمساحة عملك وفريقك وحساباتك الاجتماعية المتصلة.'],
            'about' => ['Meet Socialeaz, a connected workspace for creators, teams, agencies and brands. Explore our approach to social content, collaboration and growth.', 'تعرف على سوشيال إيز، مساحة عمل متصلة لصنّاع المحتوى والفرق والوكالات والعلامات التجارية. اكتشف نهجنا للمحتوى الاجتماعي والتعاون والنمو.'],
            'contact' => ['Contact Socialeaz for workspace support, subscription questions and team enquiries. Reach our team at support@socialeaz.com or open a support ticket.', 'تواصل مع سوشيال إيز لدعم مساحة العمل واستفسارات الاشتراك والفريق. راسل support@socialeaz.com أو افتح تذكرة دعم من حسابك.'],
            'services' => ['Explore social content, community engagement, copywriting, articles, campaigns and website services. Discuss your goals and project scope with Socialeaz.', 'استكشف خدمات المحتوى الاجتماعي والتفاعل وكتابة النصوص والمقالات والحملات والمواقع. ناقش أهدافك ونطاق مشروعك مع سوشيال إيز.'],
            'privacy' => ['Learn how Socialeaz collects, uses and protects account information and connected-platform data, and how to contact us about your privacy choices.', 'تعرف على كيفية جمع سوشيال إيز لمعلومات الحساب وبيانات المنصات المتصلة واستخدامها وحمايتها، وكيفية التواصل معنا بشأن خيارات الخصوصية.'],
            'terms' => ['Read the terms for using Socialeaz, including accounts, connected platforms, subscription billing, acceptable use, content ownership and support.', 'اقرأ شروط استخدام سوشيال إيز، بما فيها الحسابات والمنصات المتصلة وفوترة الاشتراك والاستخدام المقبول وملكية المحتوى والدعم.'],
        ];
        $title = $titles[$route][$arabic ? 1 : 0] ?? null;
        $description = $descriptions[$route][$arabic ? 1 : 0] ?? null;
        $image = match ($route) {
            'about' => 'about-creators.jpg', 'services' => 'services-hero.jpg',
            'pricing' => 'pricing-hero.jpg', default => 'hero.png',
        };
        foreach (['product.' => 'marketing-products', 'solutions.' => 'marketing-solutions'] as $prefix => $config) {
            if (str_starts_with($route, $prefix)) {
                $slug = substr($route, strlen($prefix));
                $page = config($config.'.'.$slug);
                if ($page) {
                    $title = (self::PAGE_TITLES[$route][$arabic ? 1 : 0] ?? ($page['title'][$locale] ?? $page['title']['en'])).' | '.($arabic ? 'سوشيال إيز' : 'Socialeaz');
                    $description = $page['description'][$locale] ?? $page['description']['en'];
                    if ($prefix === 'solutions.') {
                        $image = 'solution-'.$slug.'.jpg';
                    }
                }
            }
        }

        return [
            'title' => $title,
            'description' => $description ? Str::limit($description, 155, '…') : null,
            'image' => $this->url('assets/images/home-theme/'.$image),
        ];
    }

    public function schema(string $route, string $locale, string $title, string $description): array
    {
        $home = $this->localizedUrl('home', $locale);
        $canonical = $this->localizedUrl($route, $locale);
        $organizationId = $this->url().'#organization';
        $websiteId = $this->url().'#website';
        $organization = [
            '@type' => 'Organization', '@id' => $organizationId,
            'name' => 'Socialeaz', 'url' => $this->url(),
            // Own spellings only ("SocialEZ" is an unrelated company).
            'alternateName' => ['SocialEaz', 'Social Eaz', 'سوشيال إيز'],
            'logo' => ['@type' => 'ImageObject', 'url' => $this->url('assets/images/home-theme/logo-square-1024.png'), 'width' => 1024, 'height' => 1024],
            'email' => 'support@socialeaz.com',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Riyadh', 'addressCountry' => 'SA'],
            'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'support@socialeaz.com', 'availableLanguage' => ['en', 'ar']],
        ];
        $social = array_values(array_filter(config('social', []), fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')));
        if ($social) {
            $organization['sameAs'] = $social;
        }
        $graph = [
            $organization,
            ['@type' => 'WebSite', '@id' => $websiteId, 'name' => 'Socialeaz', 'url' => $this->url(), 'inLanguage' => config('seo.locales'), 'publisher' => ['@id' => $organizationId]],
            ['@type' => match ($route) { 'about' => 'AboutPage', 'contact' => 'ContactPage', default => 'WebPage' }, '@id' => $canonical.'#webpage', 'url' => $canonical, 'name' => $title, 'description' => $description, 'inLanguage' => $locale, 'isPartOf' => ['@id' => $websiteId]],
        ];
        if ($route !== 'home') {
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'ar' ? 'الرئيسية' : 'Home', 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => trim(preg_replace('/\s*\|\s*(Socialeaz|سوشيال إيز)$/u', '', $title)), 'item' => $canonical],
            ]];
        }
        if ($faqs = $this->faqs($route, $locale)) {
            $graph[] = ['@type' => 'FAQPage', '@id' => $canonical.'#faq', 'url' => $canonical, 'inLanguage' => $locale, 'isPartOf' => ['@id' => $websiteId], 'mainEntity' => array_map(
                fn ($faq) => ['@type' => 'Question', 'name' => $faq['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']]],
                $faqs,
            )];
        }
        if ($route === 'home' || $route === 'pricing' || str_starts_with($route, 'product.')) {
            $graph[] = $this->softwareApplication($locale, $organizationId);
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * The FAQ items a page shows visibly, as plain [question, answer] text
     * in $locale - product/solution FAQs live in their page config, other
     * pages' in config/marketing-faqs.php.
     */
    public function faqs(string $route, string $locale): array
    {
        foreach (['product.' => 'marketing-products', 'solutions.' => 'marketing-solutions'] as $prefix => $config) {
            if (str_starts_with($route, $prefix)) {
                return array_map(fn ($faq) => [
                    'question' => $faq['question'][$locale] ?? $faq['question']['en'],
                    'answer' => $faq['answer'][$locale] ?? $faq['answer']['en'],
                ], config($config.'.'.substr($route, strlen($prefix)).'.faq', []));
            }
        }

        return array_map(fn ($faq) => [
            'question' => __($faq['question'], [], $locale),
            'answer' => __($faq['answer'], [], $locale),
        ], config('marketing-faqs.'.$route, []));
    }

    /**
     * The product itself. offers come from the real active plans (no ratings,
     * reviews or invented numbers); left out when prices aren't available.
     */
    public function softwareApplication(string $locale, string $organizationId): array
    {
        $app = [
            '@type' => 'SoftwareApplication', '@id' => $this->url().'#software',
            'name' => 'Socialeaz', 'url' => $this->localizedUrl('home', $locale),
            'applicationCategory' => 'BusinessApplication', 'applicationSubCategory' => 'Social media management',
            'operatingSystem' => 'Web', 'inLanguage' => config('seo.locales'), 'publisher' => ['@id' => $organizationId],
        ];
        if ($offers = $this->planOffers()) {
            $app['offers'] = $offers + ['url' => $this->localizedUrl('pricing', $locale)];
        }

        return $app;
    }

    /** AggregateOffer from the active plans' monthly prices (cached; null on any failure). */
    public function planOffers(): ?array
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember('seo:plan-offers', 3600, function () {
                $plans = \App\Models\Bundle::where('is_active', true)->get(['price', 'currency', 'is_free']);
                $currencies = $plans->pluck('currency')->filter()->unique();
                if ($plans->isEmpty() || $currencies->count() !== 1) {
                    return null;
                }
                $prices = $plans->map(fn ($p) => $p->is_free ? 0.0 : (float) $p->price);

                return ['@type' => 'AggregateOffer', 'priceCurrency' => $currencies->first(), 'lowPrice' => round($prices->min(), 2), 'highPrice' => round($prices->max(), 2), 'offerCount' => $plans->count()];
            });
        } catch (\Throwable) {
            return null;
        }
    }
}
