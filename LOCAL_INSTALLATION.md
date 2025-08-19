# 🔧 Local Installation Guide for zuse/laravel-rbac-auto

Since the package isn't published to Packagist yet, here are several ways to install and test it locally.

## 📦 Method 1: Local Path Repository (Recommended)

### Step 1: Add Package to Your Laravel Project's composer.json

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../integration-packages/zuse-laravel-rbac-auto",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "zuse/laravel-rbac-auto": "*@dev"
    }
}
```

### Step 2: Install the Package

```bash
composer require "zuse/laravel-rbac-auto:*@dev"
```

### Step 3: Use the Package

```bash
php artisan zuse:integrate --client-id="your-id" --client-secret="your-secret"
```

---

## 📦 Method 2: Direct Copy Installation

### Copy Package to Your Project

```bash
# From your Laravel project root
mkdir -p packages/zuse
cp -r "../integration-packages/zuse-laravel-rbac-auto" "packages/zuse/laravel-rbac-auto"
```

### Update composer.json

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/zuse/laravel-rbac-auto"
        }
    ],
    "require": {
        "zuse/laravel-rbac-auto": "*@dev"
    }
}
```

### Install

```bash
composer require "zuse/laravel-rbac-auto:*@dev"
```

---

## 📦 Method 3: Manual Service Provider Registration

If you prefer not to modify composer.json:

### Step 1: Copy Package Files

```bash
# Copy source files to your project
mkdir -p app/ZuseRbac
cp -r "../integration-packages/zuse-laravel-rbac-auto/src/*" "app/ZuseRbac/"
cp -r "../integration-packages/zuse-laravel-rbac-auto/stubs" "resources/stubs/zuse-rbac"
```

### Step 2: Update Namespaces

Edit copied files and change namespace from:
```php
namespace Zuse\LaravelRbacAuto;
```

To:
```php
namespace App\ZuseRbac;
```

### Step 3: Register Service Provider

In `config/app.php`:
```php
'providers' => [
    // Other providers...
    App\ZuseRbac\ZuseRbacAutoServiceProvider::class,
],
```

---

## 📦 Method 4: Git Submodule (For Version Control)

### Add as Submodule

```bash
# From your Laravel project root
git submodule add /path/to/integration-packages/zuse-laravel-rbac-auto packages/zuse/laravel-rbac-auto
```

### Update composer.json

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/zuse/laravel-rbac-auto"
        }
    ],
    "require": {
        "zuse/laravel-rbac-auto": "*@dev"
    }
}
```

### Install

```bash
composer require "zuse/laravel-rbac-auto:*@dev"
```

---

## 🧪 Testing the Installation

Once installed using any method above:

### 1. Verify Installation

```bash
php artisan list | grep zuse
# Should show: zuse:integrate
```

### 2. Test Integration Command

```bash
php artisan zuse:integrate --help
# Should show the command help
```

### 3. Run Full Integration

```bash
php artisan zuse:integrate \
  --client-id="your-client-id" \
  --client-secret="your-secret" \
  --test
```

---

## 🔧 Troubleshooting

### "Class not found" Errors

```bash
# Clear and rebuild autoload
composer dump-autoload
php artisan clear-compiled
php artisan config:clear
```

### "Service Provider not found"

Make sure the service provider is properly registered:
```bash
php artisan package:discover
```

### Path Issues

Ensure the path in your repository configuration points to the correct directory:
```bash
# Check if path exists
ls -la ../integration-packages/zuse-laravel-rbac-auto/composer.json
```

---

## 🚀 Production Deployment Options

For production deployment, choose one of these options:

### Option 1: Private Packagist Repository

1. Create account at [packagist.com](https://packagist.com)
2. Submit package for approval
3. Once approved, install normally:
   ```bash
   composer require zuse/laravel-rbac-auto
   ```

### Option 2: Private Package Repository

Set up your own package repository:
```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.zuse.lk"
        }
    ]
}
```

### Option 3: Git Repository Installation

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/zuse-lk/laravel-rbac-auto.git"
        }
    ]
}
```

---

## ✅ Recommended Approach for Development

For immediate testing and development:

1. **Use Method 1 (Local Path Repository)** - it's the cleanest
2. **Test thoroughly** with real Keycloak credentials
3. **Prepare for publication** to Packagist once stable

```bash
# Quick setup
cd /path/to/your/laravel/project

# Add to composer.json repositories array:
# {"type": "path", "url": "../integration-packages/zuse-laravel-rbac-auto", "options": {"symlink": true}}

composer require "zuse/laravel-rbac-auto:*@dev"
php artisan zuse:integrate --client-id="test" --client-secret="test" --test
```

This approach allows you to develop and test the package while maintaining proper composer package structure!