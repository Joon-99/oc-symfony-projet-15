<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Boots the kernel against real routes so container compilation and request-time
 * deprecations are both surfaced by symfony/phpunit-bridge, not just logged silently.
 */
class SmokeTest extends WebTestCase
{
    /**
     * @dataProvider provideAnonymousRoutes
     */
    public function testAnonymousRouteDoesNotError(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertLessThan(500, $client->getResponse()->getStatusCode());
    }

    public static function provideAnonymousRoutes(): iterable
    {
        yield 'home' => ['/'];
        yield 'guests' => ['/guests'];
        yield 'portfolio' => ['/portfolio'];
        yield 'about' => ['/about'];
        yield 'admin login' => ['/login'];
        // protected routes: anonymous users should be redirected, not 500
        yield 'admin album index' => ['/admin/album'];
        yield 'admin media index' => ['/admin/media'];
    }
}
