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

    public function indexableHost(string $host): bool
    {
        return (bool) config('seo.indexable') && strcasecmp($host, (string) parse_url($this->url(), PHP_URL_HOST)) === 0;
    }

    public function metadata(string $route, string $locale): array
    {
        $arabic = $locale === 'ar';
        $titles = [
            'home' => ['Socialeaz | AI Social Media Management & Scheduling', 'سوشيال إيز | إدارة وجدولة التواصل الاجتماعي بالذكاء الاصطناعي'],
            'pricing' => ['Social Media Management Plans & Pricing | Socialeaz', 'خطط وأسعار إدارة التواصل الاجتماعي | سوشيال إيز'],
            'about' => ['About Socialeaz | Your AI Social Media Workspace', 'عن سوشيال إيز | مساحة عمل التواصل الاجتماعي بالذكاء الاصطناعي'],
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
                    $title = ($page['title'][$locale] ?? $page['title']['en']).' | '.($arabic ? 'سوشيال إيز' : 'Socialeaz');
                    $description = $page['description'][$locale] ?? $page['description']['en'];
                    if ($prefix === 'solutions.') {
                        $image = 'solution-'.$slug.'.jpg';
                    }
                }
            }
        }

        return [
            'title' => $title,
            'description' => $description ? Str::limit($description, 160, '…') : null,
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
            'logo' => $this->url('assets/images/home-theme/apple-touch-icon.png'),
            'email' => 'support@socialeaz.com',
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
                ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $canonical],
            ]];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
