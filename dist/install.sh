#!/bin/bash

# PComposer v1.0.0 Installer
# Simple installer for Unix/Linux/macOS

set -e

echo "🚀 Installing PComposer v1.0.0..."

# Detect installation directory
INSTALL_DIR="/usr/local/bin"
if [[ ! -w "$INSTALL_DIR" ]]; then
    echo "⚠️  Cannot write to $INSTALL_DIR, trying ~/.local/bin"
    INSTALL_DIR="$HOME/.local/bin"
    mkdir -p "$INSTALL_DIR"
fi

# Copy executable
cp "pcomposer-1.0.0.php" "$INSTALL_DIR/pcomposer"
chmod +x "$INSTALL_DIR/pcomposer"

# Add to PATH if needed
if [[ ":$PATH:" != *":$INSTALL_DIR:"* ]]; then
    echo "📝 Adding $INSTALL_DIR to PATH..."
    echo "export PATH=\"\$PATH:$INSTALL_DIR\"" >> "$HOME/.bashrc"
    echo "export PATH=\"\$PATH:$INSTALL_DIR\"" >> "$HOME/.zshrc"
fi

echo "✅ PComposer installed successfully!"
echo "📍 Location: $INSTALL_DIR/pcomposer"
echo "🔧 Usage: pcomposer --help"
