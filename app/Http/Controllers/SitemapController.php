<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * The public pages, for search engines.
     */
    public function sitemap(): Response
    {
        $urls = collect(['home', 'legal.terms', 'legal.risk', 'legal.privacy', 'register', 'login'])
            ->map(fn (string $name): string => route($name));

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
