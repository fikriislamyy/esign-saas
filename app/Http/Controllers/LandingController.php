<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class LandingController extends Controller
{
    public function __invoke()
    {
        $seo = config('seo.landing');
        $base = rtrim(config('app.url'), '/');

        $meta = [
            'title' => $seo['title'],
            'description' => $seo['description'],
            'canonical' => $base.'/',
            'image' => $base.$seo['image'],
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => config('app.name'),
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $base.'/',
                'description' => $seo['description'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'USD',
                ],
            ],
        ];

        return Inertia::render('Landing', ['meta' => $meta])
            ->withViewData(['meta' => $meta]);
    }
}
