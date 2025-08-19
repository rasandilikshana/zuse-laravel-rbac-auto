#!/bin/bash

# Zuse RBAC Auto-Integration Package - Local Installation Script
# This script helps install the package locally for testing before publication

echo "🚀 Zuse RBAC Auto-Integration - Local Installation Script"
echo "========================================================="
echo ""

# Check if we're in a Laravel project
if [ ! -f "composer.json" ]; then
    echo "❌ Error: This doesn't appear to be a Laravel project"
    echo "   Please run this script from your Laravel project root directory"
    exit 1
fi

# Check if Laravel is installed
if ! grep -q '"laravel/framework"' composer.json; then
    echo "❌ Error: Laravel framework not found in composer.json"
    echo "   Please ensure you're in a Laravel project directory"
    exit 1
fi

echo "✅ Laravel project detected"
echo ""

# Get the package path
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_PATH="$SCRIPT_DIR"

echo "📦 Package location: $PACKAGE_PATH"
echo ""

# Backup current composer.json
echo "🔄 Creating backup of composer.json..."
cp composer.json composer.json.backup
echo "✅ Backup created: composer.json.backup"
echo ""

# Add repository configuration
echo "🔧 Configuring composer.json for local package..."

# Use Python to modify composer.json (more reliable than sed for JSON)
python3 -c "
import json
import sys

try:
    with open('composer.json', 'r') as f:
        data = json.load(f)
    
    # Add repositories section if it doesn't exist
    if 'repositories' not in data:
        data['repositories'] = []
    
    # Check if our repository already exists
    package_repo = {
        'type': 'path',
        'url': '$PACKAGE_PATH',
        'options': {
            'symlink': True
        }
    }
    
    # Remove existing zuse package repo if any
    data['repositories'] = [repo for repo in data['repositories'] if not (repo.get('type') == 'path' and 'zuse-laravel-rbac-auto' in repo.get('url', ''))]
    
    # Add our repository
    data['repositories'].append(package_repo)
    
    # Add require if it doesn't exist
    if 'require' not in data:
        data['require'] = {}
    
    # Add our package to require
    data['require']['zuse/laravel-rbac-auto'] = '*@dev'
    
    # Write back to file
    with open('composer.json', 'w') as f:
        json.dump(data, f, indent=4)
    
    print('✅ composer.json updated successfully')
    
except Exception as e:
    print(f'❌ Error updating composer.json: {e}')
    sys.exit(1)
"

if [ $? -ne 0 ]; then
    echo "❌ Failed to update composer.json"
    echo "🔄 Restoring backup..."
    cp composer.json.backup composer.json
    exit 1
fi

echo ""

# Install the package
echo "📥 Installing package with Composer..."
composer require "zuse/laravel-rbac-auto:*@dev" --no-scripts

if [ $? -eq 0 ]; then
    echo "✅ Package installed successfully!"
    echo ""
    
    # Verify installation
    echo "🧪 Verifying installation..."
    if php artisan list | grep -q "zuse:integrate"; then
        echo "✅ Command 'zuse:integrate' is available!"
        echo ""
        
        echo "🎉 Installation Complete!"
        echo ""
        echo "📋 Next Steps:"
        echo "1. Get your client credentials from Lyceum RBAC panel"
        echo "2. Run the integration command:"
        echo "   php artisan zuse:integrate --client-id=\"your-id\" --client-secret=\"your-secret\" --test"
        echo ""
        echo "🔍 Test the installation:"
        echo "   php artisan zuse:integrate --help"
        echo ""
        echo "🔄 To uninstall:"
        echo "   composer remove zuse/laravel-rbac-auto"
        echo "   cp composer.json.backup composer.json  # Restore original"
        echo ""
    else
        echo "⚠️  Package installed but command not found"
        echo "   Try running: php artisan package:discover"
        echo "   Or: composer dump-autoload"
    fi
else
    echo "❌ Package installation failed"
    echo "🔄 Restoring backup..."
    cp composer.json.backup composer.json
    echo ""
    echo "💡 Manual installation steps:"
    echo "1. Add this to your composer.json repositories array:"
    echo "   {"
    echo "       \"type\": \"path\","
    echo "       \"url\": \"$PACKAGE_PATH\","
    echo "       \"options\": {"
    echo "           \"symlink\": true"
    echo "       }"
    echo "   }"
    echo ""
    echo "2. Then run: composer require \"zuse/laravel-rbac-auto:*@dev\""
fi