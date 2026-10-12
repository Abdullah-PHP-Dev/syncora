<?php

namespace App\Http\Controllers;

use App\Support\Seo\MarketingSeo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(MarketingSeo $seo): Response
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach (array_keys($seo->pages()) as $route) {
            foreach (config('seo.locales') as $locale) {
                $xml->startElement('url');
                $xml->writeElement('loc', $seo->localizedUrl($route, $locale));
                $xml->endElement();
            }
        }
        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(Request $request, MarketingSeo $seo): Response
    {
        $rules = "User-agent: *\n";
        if (!$seo->indexableHost($request->getHost())) {
            $rules .= "Disallow: /\n";
        } else {
            $rules .= "Allow: /\nDisallow: /dashboard\nDisallow: /admin/\nDisallow: /api/\nDisallow: /en/dashboard\nDisallow: /ar/dashboard\nDisallow: /en/admin/\nDisallow: /ar/admin/\n";
        }
        $rules .= "\nSitemap: ".$seo->url('sitemap.xml')."\n";

        return response($rules, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
