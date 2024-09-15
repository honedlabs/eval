<?php

namespace Conquest\Evaluate\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Conquest\Evaluate\EvaluateServiceProvider;

if (!defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Conquest\\Evaluate\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EvaluateServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_evaluate_table.php.stub';
        $migration->up();
        */
    }
}
