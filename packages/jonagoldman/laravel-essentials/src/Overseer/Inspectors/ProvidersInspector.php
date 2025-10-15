<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;

final class ProvidersInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        $registered = array_keys($app->getLoadedProviders());

        $unresolved = [...array_diff(array_keys(array_flip($app->getDeferredServices())), $registered)];

        $services = array_filter($app->getDeferredServices(), fn ($provider) => in_array($provider, $unresolved));

        $unresolved = array_fill_keys($unresolved, ['loaded' => false, 'deferred' => true, 'provides' => []]);

        foreach ($services as $binding => $provider) {
            $unresolved[$provider]['provides'][] = $binding;
        }

        $registered = array_fill_keys($registered, ['loaded' => true, 'deferred' => false, 'provides' => []]);

        foreach ($registered as $key => $value) {
            $provider = $app->getProvider($key);

            $registered[$key]['deferred'] = $provider->isDeferred();
            $registered[$key]['provides'] = $provider->provides();
        }

        $data = array_merge($registered, $unresolved);

        return $data;
    }
}
