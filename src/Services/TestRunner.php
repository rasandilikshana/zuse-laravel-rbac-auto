<?php

namespace Zuse\LaravelRbacAuto\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class TestRunner
{
    protected $errors = [];

    /**
     * Run all integration tests
     */
    public function runIntegrationTests(array $config): array
    {
        $this->errors = [];

        // Test 1: File existence
        $this->testRequiredFiles();

        // Test 2: Environment configuration
        $this->testEnvironmentConfiguration($config);

        // Test 3: Routes registration
        $this->testRoutesRegistration();

        // Test 4: Middleware registration
        $this->testMiddlewareRegistration();

        // Test 5: Database migration
        $this->testDatabaseMigration();

        // Test 6: Configuration files
        $this->testConfigurationFiles();

        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
            'total_tests' => 6,
            'passed_tests' => 6 - count($this->errors)
        ];
    }

    /**
     * Test if all required files exist
     */
    protected function testRequiredFiles(): void
    {
        $requiredFiles = [
            'Controller' => app_path('Http/Controllers/Auth/KeycloakController.php'),
            'Middleware' => app_path('Http/Middleware/RoleMiddleware.php'),
            'User Model' => app_path('Models/User.php'),
            'Web Routes' => base_path('routes/web.php'),
            'Services Config' => config_path('services.php'),
        ];

        foreach ($requiredFiles as $name => $path) {
            if (!File::exists($path)) {
                $this->errors[] = "Missing required file: {$name} at {$path}";
            }
        }
    }

    /**
     * Test environment configuration
     */
    protected function testEnvironmentConfiguration(array $config): void
    {
        $requiredEnvVars = [
            'KEYCLOAK_BASE_URL',
            'KEYCLOAK_REALM',
            'KEYCLOAK_CLIENT_ID',
            'KEYCLOAK_CLIENT_SECRET',
            'KEYCLOAK_REDIRECT_URI'
        ];

        foreach ($requiredEnvVars as $var) {
            if (empty(env($var))) {
                $this->errors[] = "Missing environment variable: {$var}";
            }
        }

        // Validate URLs
        if (env('KEYCLOAK_BASE_URL') && !filter_var(env('KEYCLOAK_BASE_URL'), FILTER_VALIDATE_URL)) {
            $this->errors[] = "Invalid KEYCLOAK_BASE_URL format";
        }

        if (env('KEYCLOAK_REDIRECT_URI') && !filter_var(env('KEYCLOAK_REDIRECT_URI'), FILTER_VALIDATE_URL)) {
            $this->errors[] = "Invalid KEYCLOAK_REDIRECT_URI format";
        }
    }

    /**
     * Test if authentication routes are registered
     */
    protected function testRoutesRegistration(): void
    {
        try {
            $routes = collect(Route::getRoutes())->pluck('uri')->toArray();

            $requiredRoutes = [
                'auth/keycloak',
                'auth/keycloak/callback',
            ];

            foreach ($requiredRoutes as $route) {
                if (!in_array($route, $routes)) {
                    $this->errors[] = "Authentication route not registered: {$route}";
                }
            }
        } catch (\Exception $e) {
            $this->errors[] = "Could not verify routes registration: " . $e->getMessage();
        }
    }

    /**
     * Test middleware registration
     */
    protected function testMiddlewareRegistration(): void
    {
        $kernelPath = app_path('Http/Kernel.php');
        
        if (!File::exists($kernelPath)) {
            $this->errors[] = "Kernel.php file not found";
            return;
        }

        $kernelContent = File::get($kernelPath);
        
        if (!str_contains($kernelContent, 'RoleMiddleware')) {
            $this->errors[] = "RoleMiddleware not registered in Kernel.php";
        }
    }

    /**
     * Test database migration
     */
    protected function testDatabaseMigration(): void
    {
        $migrationFiles = glob(database_path('migrations/*_add_keycloak_fields_to_users_table.php'));
        
        if (empty($migrationFiles)) {
            $this->errors[] = "Keycloak fields migration not created";
        }
    }

    /**
     * Test configuration files
     */
    protected function testConfigurationFiles(): void
    {
        // Test services.php configuration
        try {
            $keycloakConfig = config('services.keycloak');
            if (empty($keycloakConfig)) {
                $this->errors[] = "Keycloak configuration not found in services.php";
            }
        } catch (\Exception $e) {
            $this->errors[] = "Error loading keycloak configuration: " . $e->getMessage();
        }

        // Test User model modifications
        $userModelPath = app_path('Models/User.php');
        if (File::exists($userModelPath)) {
            $userContent = File::get($userModelPath);
            
            if (!str_contains($userContent, 'keycloak_id')) {
                $this->errors[] = "User model not updated with RBAC fields";
            }
            
            if (!str_contains($userContent, 'hasRole')) {
                $this->errors[] = "User model missing RBAC helper methods";
            }
        }
    }

    /**
     * Test Keycloak connectivity (optional)
     */
    public function testKeycloakConnectivity(array $config): array
    {
        try {
            $authUrl = $config['base_url'] . '/realms/' . $config['realm'] . '/protocol/openid-connect/auth';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET'
                ]
            ]);
            
            $response = @file_get_contents($authUrl . '?client_id=' . urlencode($config['client_id']), false, $context);
            
            if ($response === false) {
                return [
                    'success' => false,
                    'error' => 'Could not connect to Keycloak server'
                ];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Keycloak connectivity test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate test report
     */
    public function generateTestReport(array $testResults, array $config): string
    {
        $report = "🧪 Zuse RBAC Integration Test Report\n";
        $report .= "=====================================\n\n";
        
        if ($testResults['success']) {
            $report .= "✅ All tests passed! ({$testResults['passed_tests']}/{$testResults['total_tests']})\n\n";
        } else {
            $report .= "❌ {$testResults['passed_tests']}/{$testResults['total_tests']} tests passed\n\n";
            $report .= "Errors found:\n";
            foreach ($testResults['errors'] as $error) {
                $report .= "  • {$error}\n";
            }
            $report .= "\n";
        }

        $report .= "🔧 Configuration Summary:\n";
        $report .= "  Base URL: {$config['base_url']}\n";
        $report .= "  Realm: {$config['realm']}\n";
        $report .= "  Client ID: {$config['client_id']}\n";
        $report .= "  Redirect URI: {$config['redirect_uri']}\n\n";

        $report .= "🚀 Next Steps:\n";
        if ($testResults['success']) {
            $report .= "  • Run 'php artisan serve' to start your application\n";
            $report .= "  • Visit /auth/keycloak to test authentication\n";
            $report .= "  • Check /dashboard for role-based content\n";
        } else {
            $report .= "  • Fix the errors listed above\n";
            $report .= "  • Re-run the integration with --test flag\n";
        }

        return $report;
    }
}