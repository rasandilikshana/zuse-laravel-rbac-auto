<?php

namespace Zuse\LaravelRbacAuto\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CodeGenerator
{
    /**
     * Generate file from template with replacements
     */
    public function generateFromTemplate(string $templateName, array $variables = []): string
    {
        $templatePath = $this->getTemplatePath($templateName);
        
        if (!File::exists($templatePath)) {
            throw new \Exception("Template {$templateName} not found");
        }

        $content = File::get($templatePath);

        // Replace variables in template
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . strtoupper($key) . '}}';
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    /**
     * Create authentication controller
     */
    public function createAuthController(array $config): bool
    {
        $controllerContent = $this->generateFromTemplate('KeycloakController.stub', [
            'namespace' => 'App\\Http\\Controllers\\Auth',
            'base_url' => $config['base_url'],
            'realm' => $config['realm'],
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret']
        ]);

        $controllerPath = app_path('Http/Controllers/Auth/KeycloakController.php');
        
        // Create directory if it doesn't exist
        $this->ensureDirectoryExists(dirname($controllerPath));

        return File::put($controllerPath, $controllerContent) !== false;
    }

    /**
     * Create role middleware
     */
    public function createRoleMiddleware(): bool
    {
        $middlewareContent = $this->generateFromTemplate('RoleMiddleware.stub');
        $middlewarePath = app_path('Http/Middleware/RoleMiddleware.php');
        
        $this->ensureDirectoryExists(dirname($middlewarePath));

        return File::put($middlewarePath, $middlewareContent) !== false;
    }

    /**
     * Update User model with RBAC methods
     */
    public function updateUserModel(): bool
    {
        $userModelPath = app_path('Models/User.php');
        
        if (!File::exists($userModelPath)) {
            return false;
        }

        $userContent = File::get($userModelPath);

        // Check if already updated
        if (str_contains($userContent, 'keycloak_id')) {
            return true; // Already updated
        }

        // Add fillable fields
        $fillablePattern = "/protected \\\$fillable = \[(.*?)\];/s";
        if (preg_match($fillablePattern, $userContent, $matches)) {
            $existingFillable = $matches[1];
            $newFillable = $existingFillable . ",\n        'keycloak_id',\n        'roles',\n        'groups'";
            
            $userContent = preg_replace(
                $fillablePattern, 
                "protected \$fillable = [{$newFillable}];", 
                $userContent
            );
        }

        // Add casts
        $castsPattern = "/protected \\\$casts = \[(.*?)\];/s";
        if (preg_match($castsPattern, $userContent, $matches)) {
            $existingCasts = $matches[1];
            $newCasts = $existingCasts . ",\n        'roles' => 'array',\n        'groups' => 'array'";
            
            $userContent = preg_replace(
                $castsPattern,
                "protected \$casts = [{$newCasts}];",
                $userContent
            );
        }

        // Add RBAC methods before the closing class bracket
        $rbacMethods = "
    /**
     * Check if user has specific role
     */
    public function hasRole(\$role): bool
    {
        return in_array(\$role, \$this->roles ?? []);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array \$roles): bool
    {
        return !empty(array_intersect(\$roles, \$this->roles ?? []));
    }

    /**
     * Check if user belongs to specific group
     */
    public function belongsToGroup(\$group): bool
    {
        return in_array(\$group, \$this->groups ?? []);
    }
";

        $userContent = str_replace(
            "\n}",
            $rbacMethods . "\n}",
            $userContent
        );

        return File::put($userModelPath, $userContent) !== false;
    }

    /**
     * Add authentication routes
     */
    public function addAuthRoutes(): bool
    {
        $webRoutesPath = base_path('routes/web.php');
        
        if (!File::exists($webRoutesPath)) {
            return false;
        }

        $routesContent = File::get($webRoutesPath);

        // Check if routes already exist
        if (str_contains($routesContent, 'keycloak')) {
            return true; // Already added
        }

        $authRoutes = "
// Zuse RBAC Authentication Routes
use App\Http\Controllers\Auth\KeycloakController;

Route::get('/auth/keycloak', [KeycloakController::class, 'login'])->name('keycloak.login');
Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('keycloak.callback');
Route::post('/auth/keycloak/logout', [KeycloakController::class, 'logout'])->name('keycloak.logout');
Route::post('/auth/keycloak/refresh', [KeycloakController::class, 'refreshToken'])->name('keycloak.refresh');
";

        $routesContent .= $authRoutes;

        return File::put($webRoutesPath, $routesContent) !== false;
    }

    /**
     * Create database migration
     */
    public function createMigration(): bool
    {
        $timestamp = date('Y_m_d_His');
        $migrationName = "add_keycloak_fields_to_users_table";
        $migrationPath = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $migrationContent = $this->generateFromTemplate('UserMigration.stub', [
            'class_name' => Str::studly($migrationName),
            'timestamp' => $timestamp
        ]);

        return File::put($migrationPath, $migrationContent) !== false;
    }

    /**
     * Create dashboard view
     */
    public function createDashboardView(): bool
    {
        $dashboardContent = $this->generateFromTemplate('dashboard.stub');
        $dashboardPath = resource_path('views/dashboard.blade.php');
        
        $this->ensureDirectoryExists(dirname($dashboardPath));

        return File::put($dashboardPath, $dashboardContent) !== false;
    }

    /**
     * Get template path
     */
    protected function getTemplatePath(string $templateName): string
    {
        return __DIR__ . '/../../stubs/' . $templateName;
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }
}