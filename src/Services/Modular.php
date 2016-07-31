<?php

namespace Mnabialek\LaravelModular\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Mnabialek\LaravelModular\Models\Module;
use Mnabialek\LaravelModular\Traits\Normalizer;
use Mnabialek\LaravelModular\Traits\Replacer;

class Modular
{
    use Replacer;
    use Normalizer;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Collection|null
     */
    protected $modules = null;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Modular constructor.
     *
     * @param Application $app
     * @param Config $config
     */
    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Runs main seeders for all active modules
     *
     * @param Seeder $seeder
     */
    public function seed(Seeder $seeder)
    {
        $this->withSeeders()->each(function ($module) use ($seeder) {
            /** @var Module $module */
            $seeder->call($module->seederClass());
        });
    }

    /**
     * Load routes for active modules
     *
     * @param Registrar $router
     */
    public function loadRoutes(Registrar $router)
    {
        $this->withRoutes()->each(function ($module) use ($router) {
            /** @var Module $module */
            $router->group(['namespace' => $module->routeControllerNamespace()],
                function ($router) use ($module) {
                    $this->app['files']->requireOnce($this->app->basePath() .
                        DIRECTORY_SEPARATOR . $module->routesFilePath());
                });
        });
    }

    /**
     * Load factories for active modules
     */
    public function loadFactories()
    {
        $this->withFactories()->each(function ($module) {
            /** @var Module $module */
            $this->app['files']->requireOnce($module->factoryFilePath());
        });
    }

    /**
     * Load service providers for active modules
     */
    public function loadServiceProviders()
    {
        $this->withServiceProviders()->each(function ($module) {
            /** @var Module $module */
            $this->app->register($module->serviceProviderClass());
        });
    }

    /**
     * Get all routable modules (active and having routes file)
     *
     * @return array
     */
    public function withRoutes()
    {
        return $this->filterActiveByMethod('hasRoutes');
    }

    /**
     * Get all routable modules (active and having routes file)
     *
     * @return array
     */
    public function withFactories()
    {
        return $this->filterActiveByMethod('hasFactory');
    }

    /**
     * Get all modules that have service providers (active and having service
     * provider file)
     *
     * @return array
     */
    public function withServiceProviders()
    {
        return $this->filterActiveByMethod('hasServiceProvider');
    }

    /**
     * Get all modules that have seeders (active and having seeder file)
     *
     * @return array
     */
    public function withSeeders()
    {
        return $this->filterActiveByMethod('hasSeeder');
    }

    /**
     * Get active modules that also pass given requirement
     *
     * @param string $requirement
     *
     * @return Collection
     */
    protected function filterActiveByMethod($requirement)
    {
        return $this->modules()->filter(function ($module) use ($requirement) {
            return $module->active() && $module->$requirement();
        })->values();
    }

    /**
     * Get all modules
     *
     * @return Collection
     */
    public function all()
    {
        return $this->modules();
    }

    /**
     * Get active modules
     *
     * @return Collection
     */
    public function active()
    {
        return $this->modules()->filter(function ($module) {
            return $module->active();
        })->values();
    }

    /**
     * Load modules (if not loaded) and get modules
     *
     * @return Collection
     */
    protected function modules()
    {
        if ($this->modules === null) {
            $this->loadModules();
        }

        return $this->modules;
    }

    /**
     * Load modules from config
     */
    protected function loadModules()
    {
        $this->modules = collect();

        collect($this->config->modules())->each(function ($options, $name) {
            $this->modules->push(new Module($name, $this->config, $options));
        });
    }

    /**
     * Find given module by name
     *
     * @param string $name
     *
     * @return Module
     */
    public function find($name)
    {
        return $this->modules()->first(function ($module) use ($name) {
            /** @var Module $module */
            return $module->name() == $name;
        });
    }

    /**
     * Verify whether module with given name already exists
     *
     * @param $name
     *
     * @return bool
     */
    public function exists($name)
    {
        return $this->find($name) !== null;
    }
}
