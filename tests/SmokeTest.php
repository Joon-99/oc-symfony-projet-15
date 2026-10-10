<?php

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SmokeTest extends WebTestCase
{
    /** Smoke test: catches regressions that would crash these pages instead of rendering or redirecting. */
    #[DataProvider('provideAnonymousRoutes')]
    public function testAnonymousRouteDoesNotError(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertLessThan(500, $client->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, string[]>
     */
    public static function provideAnonymousRoutes(): iterable
    {
        yield 'home' => ['/'];
        yield 'guests' => ['/guests'];
        yield 'portfolio' => ['/portfolio'];
        yield 'about' => ['/about'];
        yield 'admin login' => ['/login'];
        // protected routes: anonymous users should be redirected, not 500
        yield 'admin album index' => ['/admin/album'];
        yield 'admin guest index' => ['/admin/guest'];
        yield 'admin media index' => ['/admin/media'];
    }
}
