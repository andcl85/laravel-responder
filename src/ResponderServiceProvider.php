<?php

namespace Flugg\Responder;

use Flugg\Responder\Console\MakeTransformer;
use Flugg\Responder\Contracts\Manager as ManagerContract;
use Flugg\Responder\Http\ErrorResponseBuilder;
use Flugg\Responder\Http\SuccessResponseBuilder;
use Illuminate\Foundation\Application as Laravel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Lumen\Application as Lumen;
use League\Fractal\Manager;
use League\Fractal\Serializer\SerializerAbstract;

/**
 * The Laravel Responder service provider. This is where the package is bootstrapped.
 *
 * @package flugger/laravel-responder
 * @author  Alexander Tømmerås <flugged@gmail.com>
 * @license The MIT License
 */
class ResponderServiceProvider extends BaseServiceProvider
{
    /**
     * Keeps a quick reference to the Responder config.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app instanceof Laravel && $this->app->runningInConsole()) {
            $this->bootLaravelApplication();

        } elseif ($this->app instanceof Lumen) {
            $this->bootLumenApplication();
        }

        $this->mergeConfigFrom(__DIR__ . '/../resources/config/responder.php', 'responder');

        $this->commands([
            MakeTransformer::class
        ]);

        include __DIR__ . '/helpers.php';
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFractal();
        $this->registerResponseBuilders();

        $this->app->bind(Responder::class, function ($app) {
            return new Responder($app[SuccessResponseBuilder::class], $app[ErrorResponseBuilder::class]);
        });

        $this->registerAliases();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['responder', 'responder.success', 'responder.error', 'responder.manager', 'responder.serializer'];
    }

    /**
     * Register Fractal serializer, manager and a factory to generate Fractal
     * resource instances.
     *
     * @return void
     */
    protected function registerFractal()
    {
        $this->app->bind(SerializerAbstract::class, function ($app) {
            $serializer = $app->config->get('responder.serializer');

            return new $serializer;
        });

        $this->app->bind(Manager::class, function ($app) {
            return (new Manager())->setSerializer($app[SerializerAbstract::class]);
        });

        $this->app->bind(ResourceFactory::class, function () {
            return new ResourceFactory();
        });
    }

    /**
     * Register success and error response builders.
     *
     * @return void
     */
    protected function registerResponseBuilders()
    {
        $this->app->bind(SuccessResponseBuilder::class, function ($app) {
            $builder = new SuccessResponseBuilder(response(), $app[ResourceFactory::class], $app[Manager::class]);

            if ($parameter = $app->config->get('responder.load_relations_from_parameter')) {
                $builder->include($this->app[Request::class]->input($parameter, []));
            }

            return $builder->setIncludeStatusCode($app->config->get('responder.include_status_code'));
        });

        $this->app->bind(ErrorResponseBuilder::class, function ($app) {
            $builder = new ErrorResponseBuilder(response(), $app['translator']);

            return $builder->setIncludeStatusCode($app->config->get('responder.include_status_code'));
        });
    }

    /**
     * Set aliases for the provided services.
     *
     * @return void
     */
    protected function registerAliases()
    {
        $this->app->alias(Responder::class, 'responder');
        $this->app->alias(SuccessResponseBuilder::class, 'responder.success');
        $this->app->alias(ErrorResponseBuilder::class, 'responder.error');
        $this->app->alias(Manager::class, 'responder.manager');
        $this->app->alias(Manager::class, 'responder.serializer');
    }

    /**
     * Bootstrap the Laravel application.
     *
     * @return void
     */
    protected function bootLaravelApplication()
    {
        $this->publishes([
            __DIR__ . '/../resources/config/responder.php' => config_path('responder.php')
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/lang/en/errors.php' => base_path('resources/lang/en/errors.php')
        ], 'lang');
    }

    /**
     * Bootstrap the Lumen application.
     *
     * @return void
     */
    protected function bootLumenApplication()
    {
        $this->app->configure('responder');
    }
}
