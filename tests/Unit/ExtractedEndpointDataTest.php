<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Matching\RouteMatcher;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;

class ExtractedEndpointDataTest extends BaseLaravelTest
{
    /** @test */
    public function normalizes_resource_url_params()
    {
        Route::apiResource('things', TestController::class)->only('show');
        $route = $this->getRoute(['prefixes' => '*']);

        $this->assertEquals('things/{thing}', $this->originalUri($route));
        $this->assertEquals('things/{id}', $this->expectedUri($route));


        Route::apiResource('things.otherthings', TestController::class)->only('destroy');
        $route = $this->getRoute(['prefixes' => '*/otherthings/*']);

        $this->assertEquals('things/{thing}/otherthings/{otherthing}', $this->originalUri($route));
        $this->assertEquals('things/{thing_id}/otherthings/{id}', $this->expectedUri($route));
    }

    /** @test */
    public function allows_user_specified_normalization()
    {
        Scribe::normalizeEndpointUrlUsing(function (string $url, LaravelRoute $route, \ReflectionFunctionAbstract $method, ?\ReflectionClass $controller) {
            if ($url == 'things/{thing}') return 'things/{the_id_of_the_thing}';

            if ($route->named('things.otherthings.destroy')) return 'things/{thing-id}/otherthings/{other_thing-id}';
        });

        Route::apiResource('things', TestController::class)->only('show');
        $route = $this->getRoute(['prefixes' => '*']);
        $this->assertEquals('things/{thing}', $this->originalUri($route));
        $this->assertEquals('things/{the_id_of_the_thing}', $this->expectedUri($route));

        Route::apiResource('things.otherthings', TestController::class)->only('destroy');
        $route = $this->getRoute(['prefixes' => '*/otherthings/*']);
        $this->assertEquals('things/{thing}/otherthings/{otherthing}', $this->originalUri($route));
        $this->assertEquals('things/{thing-id}/otherthings/{other_thing-id}', $this->expectedUri($route));

        Scribe::normalizeEndpointUrlUsing(null);
    }

    /** @test */
    public function normalizes_resource_url_params_from_underscores_to_hyphens()
    {
        Route::apiResource('audio-things', TestController::class)->only('show');
        $route = $this->getRoute(['prefixes' => '*']);

        $this->assertEquals('audio-things/{audio_thing}', $this->originalUri($route));
        $this->assertEquals('audio-things/{id}', $this->expectedUri($route));

        Route::apiResource('big-users.audio-things.things', TestController::class)->only('store');
        $route = $this->getRoute(['prefixes' => '*big-users*']);

        $this->assertEquals('big-users/{big_user}/audio-things/{audio_thing}/things', $this->originalUri($route));
        $this->assertEquals('big-users/{big_user_id}/audio-things/{audio_thing_id}/things', $this->expectedUri($route));
    }

    /** @test */
    public function normalizes_nonresource_url_params_with_inline_bindings()
    {
        Route::get('things/{thing:slug}', [TestController::class, 'show']);
        $route = $this->getRoute(['prefixes' => '*']);

        $this->assertEquals('things/{thing}', $this->originalUri($route));
        $this->assertEquals('things/{thing_slug}', $this->expectedUri($route));
    }

    protected function expectedUri(LaravelRoute $route): string
    {
        return $this->endpoint($route)->uri;
    }

    protected function originalUri(LaravelRoute $route): string
    {
        return $route->uri;
    }

    protected function endpoint(LaravelRoute $route): ExtractedEndpointData
    {
        return new ExtractedEndpointData([
            'route' => $route,
            'uri' => $route->uri,
            'httpMethods' => $route->methods,
            'method' => new \ReflectionFunction('dump'), // Just so we don't have null
        ]);
    }

    protected function getRoute(array $matchRules): LaravelRoute
    {
        $routeRules[0]['match'] = array_merge($matchRules, ['domains' => '*']);
        $matchedRoutes = (new RouteMatcher)->getRoutes($routeRules);
        $this->assertCount(1, $matchedRoutes);
        return $matchedRoutes[0]->getRoute();
    }
}
