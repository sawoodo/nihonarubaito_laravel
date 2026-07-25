<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate cached sitemap.xml in storage directory';

    public function handle()
    {
        $this->info('Generating sitemap...');

        $request = Request::create('/sitemap.xml', 'GET');
        $request->headers->set('HOST', 'nihonarubaito.com');

        URL::forceScheme('https');
        URL::forceRootUrl('https://nihonarubaito.com');

        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $xml = $response->getContent();
        $kernel->terminate($request, $response);

        $path = storage_path('app/sitemap.xml');
        file_put_contents($path, $xml);

        $locCount = substr_count($xml, '<loc>');
        $size = strlen($xml);

        $this->info("Written to: {$path}");
        $this->info("URLs: {$locCount}");
        $this->info("Size: " . number_format($size) . " bytes");

        return Command::SUCCESS;
    }
}