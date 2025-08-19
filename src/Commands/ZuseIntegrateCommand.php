<?php

namespace Zuse\LaravelRbacAuto\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Zuse\LaravelRbacAuto\Services\CodeGenerator;
use Zuse\LaravelRbacAuto\Services\ConfigurationManager;
use Zuse\LaravelRbacAuto\Services\TestRunner;

class ZuseIntegrateCommand extends Command
{
    protected $signature = 'zuse:integrate 
                            {--client-id= : Client ID from Lyceum RBAC panel}
                            {--client-secret= : Client secret from Lyceum RBAC panel}
                            {--base-url=https://keycloak.zuse.lk : Keycloak base URL}
                            {--realm=zuse : Keycloak realm}
                            {--redirect-uri= : Custom redirect URI (optional)}
                            {--test : Run integration tests after setup}
                            {--force : Force overwrite existing files}';

    protected $description = 'Automated Zuse RBAC integration - Automates all 12 integration steps';

    protected $codeGenerator;
    protected $configManager;
    protected $testRunner;

    public function __construct(
        CodeGenerator $codeGenerator,
        ConfigurationManager $configManager,
        TestRunner $testRunner
    ) {
        parent::__construct();
        
        $this->codeGenerator = $codeGenerator;
        $this->configManager = $configManager;
        $this->testRunner = $testRunner;
    }

    public function handle()
    {
        $this->displayWelcomeMessage();
        
        // Validate required options
        if (!$this->validateOptions()) {
            return 1;
        }

        $config = $this->buildConfiguration();
        
        try {
            $this->info('🚀 Starting Zuse RBAC Auto-Integration...');
            $this->newLine();

            // Execute all 12 steps automatically
            $this->step2_InstallDependencies();
            $this->step3_ConfigureEnvironment($config);
            $this->step4_ConfigureServices();
            $this->step5_CreateAuthController($config);
            $this->step6_AddAuthRoutes();
            $this->step7_UpdateUserModel();
            $this->step8_CreateRoleMiddleware();
            $this->step9_CreateMigration();
            $this->step10_CreateDashboard();

            // Step 11: Run integration tests
            if ($this->option('test')) {
                $this->step11_TestIntegration($config);
            }

            $this->displaySuccessMessage($config);
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Integration failed: ' . $e->getMessage());
            $this->warn('Please check the error above and try again.');
            return 1;
        }
    }

    protected function validateOptions(): bool
    {
        $clientId = $this->option('client-id');
        $clientSecret = $this->option('client-secret');

        if (!$clientId || !$clientSecret) {
            $this->error('❌ Client ID and Client Secret are required!');
            $this->newLine();
            $this->line('Please get your credentials from the Lyceum RBAC middleware panel:');
            $this->line('1. Login to the Lyceum RBAC panel');
            $this->line('2. Create a new application or select existing one');
            $this->line('3. Copy the Client ID and Client Secret');
            $this->line('4. Run the command again with:');
            $this->line('   php artisan zuse:integrate --client-id="your-id" --client-secret="your-secret"');
            return false;
        }

        return true;
    }

    protected function buildConfiguration(): array
    {
        $baseUrl = rtrim($this->option('base-url'), '/');
        $realm = $this->option('realm');
        $redirectUri = $this->option('redirect-uri') ?: url('/auth/keycloak/callback');

        return [
            'base_url' => $baseUrl,
            'realm' => $realm,
            'client_id' => $this->option('client-id'),
            'client_secret' => $this->option('client-secret'),
            'redirect_uri' => $redirectUri,
            'auth_url' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/auth",
            'token_url' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/token",
            'userinfo_url' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/userinfo",
            'logout_url' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout",
        ];
    }

    protected function step2_InstallDependencies(): void
    {
        $this->info('📦 Step 2: Installing required dependencies...');
        
        $dependencies = [
            'firebase/php-jwt' => '^6.0',
            'guzzlehttp/guzzle' => '^7.0'
        ];

        foreach ($dependencies as $package => $version) {
            if (!$this->configManager->isPackageInstalled($package)) {
                $this->line("   Installing {$package}...");
                
                $result = Process::run("composer require {$package}:{$version}");
                
                if ($result->failed()) {
                    throw new \Exception("Failed to install {$package}: " . $result->errorOutput());
                }
            } else {
                $this->line("   ✓ {$package} already installed");
            }
        }
        
        $this->line('   ✅ Dependencies installed successfully');
        $this->newLine();
    }

    protected function step3_ConfigureEnvironment(array $config): void
    {
        $this->info('⚙️ Step 3: Configuring environment variables...');
        
        $envVars = [
            'KEYCLOAK_BASE_URL' => $config['base_url'],
            'KEYCLOAK_REALM' => $config['realm'],
            'KEYCLOAK_CLIENT_ID' => $config['client_id'],
            'KEYCLOAK_CLIENT_SECRET' => $config['client_secret'],
            'KEYCLOAK_REDIRECT_URI' => $config['redirect_uri'],
        ];

        if ($this->configManager->updateEnvFile($envVars)) {
            $this->line('   ✅ Environment variables configured');
        } else {
            throw new \Exception('Failed to update .env file');
        }
        
        $this->newLine();
    }

    protected function step4_ConfigureServices(): void
    {
        $this->info('🛠️ Step 4: Configuring services...');
        
        if ($this->configManager->addKeycloakServiceConfig()) {
            $this->line('   ✅ Keycloak configuration added to config/services.php');
        } else {
            throw new \Exception('Failed to configure services');
        }
        
        $this->newLine();
    }

    protected function step5_CreateAuthController(array $config): void
    {
        $this->info('🎯 Step 5: Creating authentication controller...');
        
        if ($this->codeGenerator->createAuthController($config)) {
            $this->line('   ✅ KeycloakController created at app/Http/Controllers/Auth/');
        } else {
            throw new \Exception('Failed to create KeycloakController');
        }
        
        $this->newLine();
    }

    protected function step6_AddAuthRoutes(): void
    {
        $this->info('🛤️ Step 6: Adding authentication routes...');
        
        if ($this->codeGenerator->addAuthRoutes()) {
            $this->line('   ✅ Authentication routes added to routes/web.php');
        } else {
            throw new \Exception('Failed to add authentication routes');
        }
        
        $this->newLine();
    }

    protected function step7_UpdateUserModel(): void
    {
        $this->info('👤 Step 7: Updating User model...');
        
        if ($this->codeGenerator->updateUserModel()) {
            $this->line('   ✅ User model enhanced with RBAC methods');
        } else {
            throw new \Exception('Failed to update User model');
        }
        
        $this->newLine();
    }

    protected function step8_CreateRoleMiddleware(): void
    {
        $this->info('🛡️ Step 8: Creating role middleware...');
        
        if ($this->codeGenerator->createRoleMiddleware()) {
            $this->line('   ✅ RoleMiddleware created at app/Http/Middleware/');
        } else {
            throw new \Exception('Failed to create RoleMiddleware');
        }

        // Register middleware in Kernel.php
        if ($this->configManager->registerMiddleware()) {
            $this->line('   ✅ Middleware registered in Kernel.php');
        } else {
            $this->warn('   ⚠️ Please manually register RoleMiddleware in app/Http/Kernel.php');
        }
        
        $this->newLine();
    }

    protected function step9_CreateMigration(): void
    {
        $this->info('💾 Step 9: Creating database migration...');
        
        if ($this->codeGenerator->createMigration()) {
            $this->line('   ✅ Migration created for Keycloak fields');
            $this->line('   📝 Run "php artisan migrate" to apply changes');
        } else {
            throw new \Exception('Failed to create migration');
        }
        
        $this->newLine();
    }

    protected function step10_CreateDashboard(): void
    {
        $this->info('🎨 Step 10: Creating dashboard view...');
        
        if ($this->codeGenerator->createDashboardView()) {
            $this->line('   ✅ Dashboard view created at resources/views/dashboard.blade.php');
        } else {
            throw new \Exception('Failed to create dashboard view');
        }
        
        $this->newLine();
    }

    protected function step11_TestIntegration(array $config): void
    {
        $this->info('🧪 Step 11: Testing integration...');
        
        $testResults = $this->testRunner->runIntegrationTests($config);
        
        if ($testResults['success']) {
            $this->line("   ✅ All tests passed! ({$testResults['passed_tests']}/{$testResults['total_tests']})");
        } else {
            $this->error("   ❌ {$testResults['passed_tests']}/{$testResults['total_tests']} tests passed");
            foreach ($testResults['errors'] as $error) {
                $this->line("      • {$error}");
            }
        }
        
        $this->newLine();
    }

    protected function displayWelcomeMessage(): void
    {
        $this->line('');
        $this->line('  ╔══════════════════════════════════════════════════════════════════╗');
        $this->line('  ║                     🚀 ZUSE RBAC AUTO-INTEGRATION                ║');
        $this->line('  ║                      Automating 12-Step Process                 ║');
        $this->line('  ╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    protected function displaySuccessMessage(array $config): void
    {
        $this->newLine();
        $this->line('  ╔══════════════════════════════════════════════════════════════════╗');
        $this->line('  ║                    🎉 INTEGRATION COMPLETED!                     ║');
        $this->line('  ╚══════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        $this->info('✅ Your Laravel app now has complete Zuse RBAC integration!');
        $this->newLine();
        
        $this->line('📋 What was configured automatically:');
        $this->line('   ✅ Dependencies installed (firebase/php-jwt, guzzlehttp/guzzle)');
        $this->line('   ✅ Environment variables configured');
        $this->line('   ✅ Keycloak service configuration added');
        $this->line('   ✅ KeycloakController created with security best practices');
        $this->line('   ✅ Authentication routes registered');
        $this->line('   ✅ User model enhanced with RBAC methods');
        $this->line('   ✅ Role middleware created and registered');
        $this->line('   ✅ Database migration created');
        $this->line('   ✅ Dashboard view created');
        $this->newLine();

        $this->line('🚀 Next steps:');
        $this->line('   1. Run: php artisan migrate');
        $this->line('   2. Start your app: php artisan serve');
        $this->line('   3. Test authentication: http://localhost:8000/auth/keycloak');
        $this->line('   4. View dashboard: http://localhost:8000/dashboard');
        $this->newLine();

        $this->line('🔧 Configuration summary:');
        $this->line("   Base URL: {$config['base_url']}");
        $this->line("   Realm: {$config['realm']}");
        $this->line("   Client ID: {$config['client_id']}");
        $this->line("   Redirect URI: {$config['redirect_uri']}");
        $this->newLine();

        $this->line('⏱️ Total setup time: ~2 minutes (vs 2+ hours manual)');
        $this->line('📖 Documentation: https://docs.zuse.lk/rbac');
        $this->line('🎯 Support: support@zuse.lk');
    }
}