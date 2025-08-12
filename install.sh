#!/bin/bash

# PComposer Installation Script
# This script installs PComposer globally on your system

set -e

echo "=== PComposer Installation Script ==="
echo ""
echo "This script will install PComposer globally on your system."
echo "PComposer will be available as 'pcomposer' command from anywhere."
echo ""

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   echo "Warning: This script is running as root."
   echo "It's generally safer to run as a regular user and let the script use sudo when needed."
   echo "Continue anyway? (y/N)"
   read -r response
   if [[ ! "$response" =~ ^[Yy]$ ]]; then
       echo "Installation cancelled."
       exit 1
   fi
fi

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PCOMPOSER_PATH="$SCRIPT_DIR/pcomposer"

# Check if pcomposer executable exists
if [[ ! -f "$PCOMPOSER_PATH" ]]; then
    echo "Error: pcomposer executable not found at $PCOMPOSER_PATH"
    exit 1
fi

# Check if pcomposer is executable
if [[ ! -x "$PCOMPOSER_PATH" ]]; then
    echo "Making pcomposer executable..."
    chmod +x "$PCOMPOSER_PATH"
fi

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)

if [[ $PHP_MAJOR -lt 7 ]] || ([[ $PHP_MAJOR -eq 7 ]] && [[ $PHP_MINOR -lt 4 ]]); then
    echo "Error: PHP 7.4 or higher is required. Current version: $PHP_VERSION"
    exit 1
fi

echo "✓ PHP version $PHP_VERSION is compatible"

# Check required PHP extensions
echo "Checking PHP extensions..."
REQUIRED_EXTENSIONS=("zip" "json")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [[ ${#MISSING_EXTENSIONS[@]} -gt 0 ]]; then
    echo "Error: Missing required PHP extensions: ${MISSING_EXTENSIONS[*]}"
    echo "Please install them using your package manager:"
    echo "  Ubuntu/Debian: sudo apt-get install php-zip php-json"
    echo "  CentOS/RHEL: sudo yum install php-zip php-json"
    echo "  macOS: brew install php"
    exit 1
fi

echo "✓ All required PHP extensions are available"

# Create global installation directory
INSTALL_DIR="/usr/local/bin"

# Check if we need sudo for installation
NEED_SUDO=false
if [[ ! -w "$INSTALL_DIR" ]]; then
    echo "Note: $INSTALL_DIR requires elevated permissions."
    echo "The script will use sudo to install PComposer globally."
    NEED_SUDO=true
fi

# Create symbolic link
echo "Installing PComposer to $INSTALL_DIR..."
if [[ -L "$INSTALL_DIR/pcomposer" ]]; then
    echo "Removing existing symbolic link..."
    if [[ "$NEED_SUDO" == true ]]; then
        sudo rm "$INSTALL_DIR/pcomposer"
    else
        rm "$INSTALL_DIR/pcomposer"
    fi
fi

if [[ "$NEED_SUDO" == true ]]; then
    sudo ln -s "$PCOMPOSER_PATH" "$INSTALL_DIR/pcomposer"
else
    ln -s "$PCOMPOSER_PATH" "$INSTALL_DIR/pcomposer"
fi
echo "✓ PComposer installed successfully!"

# Test installation
echo "Testing installation..."
if command -v pcomposer >/dev/null 2>&1; then
    VERSION=$(pcomposer --version)
    echo "✓ $VERSION is now available globally"
else
    echo "Error: Installation failed. Please check your PATH."
    exit 1
fi

# Create global store directory
echo "Setting up global package store..."
mkdir -p "$HOME/.pcomposer/store"
echo "✓ Global store created at $HOME/.pcomposer/store"

echo ""
echo "=== Installation Complete! ==="
echo ""
echo "PComposer is now installed and ready to use."
echo ""
echo "Usage examples:"
echo "  pcomposer --version                    # Check version"
echo "  pcomposer --help                       # Show help"
echo "  pcomposer install                      # Install dependencies"
echo "  pcomposer require vendor/package       # Add a package"
echo "  pcomposer list                         # List packages"
echo ""
echo "For more information, see the README.md file."
echo ""
echo "Happy coding! 🚀"
