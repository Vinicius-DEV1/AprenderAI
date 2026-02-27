<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SitemapController extends Controller
{
    /**
     * Generate a dynamic XML sitemap.
     */
    public function index()
    {
        $baseUrl = config('app.url');

        // Define public routes
        $routes = [
            '/',
            '/login',
            '/register',
            '/privacidade',
            '/uso-justo',
            '/plans',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($routes as $route) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . $route . '</loc>';
            $xml .= '<lastmod>' . now()->toAtomString() . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>' . ($route === '/' ? '1.0' : '0.8') . '</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return Response::make($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'max-age=86400',
        ]);
    }
}
