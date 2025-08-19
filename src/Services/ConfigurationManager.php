<?php

namespace Zuse\LaravelRbacAuto\Services;

use Illuminate\Support\Facades\File;

class ConfigurationManager
{
    /**
     * Update .env file with RBAC configuration
     */
    public function updateEnvFile(array $envVars): bool
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);
        $newContent = $envContent;

        // Check if RBAC section already exists
        if (!str_contains($envContent, '# Zuse RBAC Configuration')) {
            // Add new RBAC section
            $rbacSection = "\n# Zuse RBAC Configuration\n";
            
            foreach ($envVars as $key => $value) {
                // Escape quotes in values
                $escapedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;
                $rbacSection .= "{$key}={$escapedValue}\n";
            }

            $newContent .= $rbacSection;
        } else {
            // Update existing values
            foreach ($envVars as $key => $value) {
                $escapedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;
                $pattern = "/^{$key}=.*$/m";
                
                if (preg_match($pattern, $newContent)) {
                    $newContent = preg_replace($pattern, "{$key}={$escapedValue}", $newContent);
                } else {
                    // Add new variable after the RBAC section header
                    $newContent = str_replace(
                        '# Zuse RBAC Configuration',
                        "# Zuse RBAC Configuration\n{$key}={$escapedValue}",
                        $newContent
                    );
                }
            }
        }

        return File::put($envPath, $newContent) !== false;
    }

    /**
     * Add Keycloak configuration to config/services.php
     */
    public function addKeycloakServiceConfig(): bool
    {
        $servicesPath = config_path('services.php');
        
        if (!File::exists($servicesPath)) {
            return false;
        }

        $servicesContent = File::get($servicesPath);

        // Check if keycloak config already exists
        if (str_contains($servicesContent, "'keycloak'")) {
            return true; // Already configured
        }

        // Find the closing bracket and array end
        $keycloakConfig = "
    /*
    |--------------------------------------------------------------------------
    | Zuse RBAC / Keycloak Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for Keycloak authentication.
    | This configuration will be used by your KeycloakController.
    |
    */

    'keycloak' => [
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realm' => env('KEYCLOAK_REALM'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),
        'auth_url' => env('KEYCLOAK_BASE_URL') . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/auth',
        'token_url' => env('KEYCLOAK_BASE_URL') . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/token',
        'userinfo_url' => env('KEYCLOAK_BASE_URL') . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/userinfo',
        'logout_url' => env('KEYCLOAK_BASE_URL') . '/realms/' . env('KEYCLOAK_REALM') . '/protocol/openid-connect/logout',
        'role_mappings' => [
            'admin' => ['admin', 'administrator'],
            'developer' => ['developer', 'dev'],
            'manager' => ['manager', 'supervisor'],
            'user' => ['user', 'member'],
        ],
    ],

];";

        // Replace the closing array bracket and semicolon
        $updatedContent = str_replace(
            '];',
            $keycloakConfig,
            $servicesContent
        );

        return File::put($servicesPath, $updatedContent) !== false;
    }

    /**
     * Register middleware in app/Http/Kernel.php
     */
    public function registerMiddleware(): bool
    {
        $kernelPath = app_path('Http/Kernel.php');
        
        if (!File::exists($kernelPath)) {
            return false;
        }

        $kernelContent = File::get($kernelPath);

        // Check if middleware already registered
        if (str_contains($kernelContent, "'role'")) {
            return true; // Already registered
        }

        // Add to middlewareAliases array
        $middlewareEntry = "        'role' => \\App\\Http\\Middleware\\RoleMiddleware::class,\n    ];";
        
        $updatedContent = str_replace(
            '    ];',
            $middlewareEntry,
            $kernelContent,
            1 // Only replace the first occurrence (middlewareAliases)
        );

        return File::put($kernelPath, $updatedContent) !== false;
    }

    /**
     * Check if a package is installed
     */
    public function isPackageInstalled(string $package): bool
    {
        $composerPath = base_path('composer.json');
        
        if (!File::exists($composerPath)) {
            return false;
        }

        $composer = json_decode(File::get($composerPath), true);
        
        return isset($composer['require'][$package]) || isset($composer['require-dev'][$package]);
    }
}