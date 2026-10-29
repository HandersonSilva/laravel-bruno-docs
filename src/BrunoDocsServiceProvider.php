<?php

namespace Dotlib\BrunoDocs;

use Dotlib\BrunoDocs\Http\Controllers\DocsController;
use Dotlib\BrunoDocs\Http\Middleware\EnsureBrunoDocsAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Swagger UI routes and the bruno-docs config.
 */
final class BrunoDocsServiceProvider extends ServiceProvider
{
    /**
     * Absolute path of the package root.
     */
    public static function packagePath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Merge the package configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(self::packagePath().'/config/bruno-docs.php', 'bruno-docs');
    }

    /**
     * Load the view and register the documentation routes.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(self::packagePath().'/resources/views', 'bruno-docs');
        $this->loadJsonTranslationsFrom(self::packagePath().'/lang');

        $this->publishes([
            self::packagePath().'/config/bruno-docs.php' => config_path('bruno-docs.php'),
        ], 'bruno-docs-config');

        $swaggerUi = self::packagePath().'/resources/swagger-ui';
        $this->publishes([
            $swaggerUi.'/swagger-ui.css' => public_path('vendor/bruno-docs/swagger-ui.css'),
            $swaggerUi.'/swagger-ui-bundle.js' => public_path('vendor/bruno-docs/swagger-ui-bundle.js'),
        ], 'bruno-docs-assets');

        $this->registerRoutes();
    }

    /**
     * Register the page and the spec outside the API middleware group.
     */
    private function registerRoutes(): void
    {
        if (! BrunoDocsSettings::enabled()) {
            return;
        }

        $path = BrunoDocsSettings::path();

        Route::middleware(['web', EnsureBrunoDocsAccess::class])->group(function () use ($path): void {
            Route::get($path.'/openapi.json', [DocsController::class, 'spec'])->name('bruno-docs.spec');
            Route::get($path, [DocsController::class, 'show'])->name('bruno-docs.show');
        });
    }
}
