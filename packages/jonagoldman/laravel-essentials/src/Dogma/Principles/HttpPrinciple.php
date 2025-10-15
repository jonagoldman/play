<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Dogma\Principles;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Sleep;
use JonaGoldman\Essentials\EssentialsConfig;
use ReflectionClass;

final class HttpPrinciple
{
    public static function apply(EssentialsConfig $config): void
    {
        /**
         * Configures Laravel Sleep Facade to be faked.
         * Avoid unexpected sleep during testing cases.
         */
        Sleep::fake($config->fakeSleep);

        /**
         * Forces all generated URLs to use `https://`.
         * Ensures all traffic uses secure connections by default.
         */
        URL::forceHttps($config->forceHttps);

        /**
         * Configures Laravel Http Facade to prevent stray requests.
         * Ensure every HTTP calls during tests have been explicitly faked.
         */
        Http::preventStrayRequests($config->preventStrayRequests);

        /**
         * Configures Laravel Vite to preload assets more aggressively.
         * Improves front-end load times and user experience.
         */
        if ($config->aggressivePrefetching) {
            Vite::useAggressivePrefetching();
        }
    }

    public static function status(): array
    {
        $reflectionSleep = new ReflectionClass(Sleep::class);

        return [
            'fakeSleep' => $reflectionSleep->getProperty('fake')->getValue(),
            'forceHttps' => URL::getRequest()->getScheme() === 'https',
            'aggressivePrefetching' => null, // todo: expose setting
            'preventStrayRequests' => Http::preventingStrayRequests(),
        ];
    }
}
