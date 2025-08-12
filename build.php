<?php

/**
 * PComposer Final Build Script
 * Creates a single-file executable for distribution
 */

$version = '1.0.0';
$distDir = 'dist';

echo "🏗️  Building PComposer v$version\n";
echo "================================\n\n";

// Create directory
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

// Create single-file executable
echo "📄 Creating single-file executable...\n";

$content = "#!/usr/bin/env php\n";
$content .= "<?php\n\n";
$content .= "/**\n";
$content .= " * PComposer v$version - Single File Distribution\n";
$content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
$content .= " * \n";
$content .= " * This file contains all PComposer source code bundled into a single executable.\n";
$content .= " * Users can simply download this file and run: php pcomposer-$version.php install\n";
$content .= " */\n\n";
// Add namespace declaration first
$content .= "namespace PComposer;\n\n";

$content .= "// Prevent direct web access\n";
$content .= "if (php_sapi_name() !== 'cli') {\n";
$content .= "    die('This script can only be run from the command line.');\n";
$content .= "}\n\n";

// Add all source files (without their own namespace declarations)
$sourceFiles = [
    'src/PComposer.php',
    'src/GlobalStore.php',
    'src/PackageManager.php',
    'src/ComposerJsonParser.php',
    'src/VendorLinker.php',
    'src/LockFile.php',
    'src/Utils.php'
];

foreach ($sourceFiles as $file) {
    if (file_exists($file)) {
        echo "  Adding: $file\n";
        $fileContent = file_get_contents($file);
        
        // Remove the opening PHP tag and namespace declaration
        $fileContent = preg_replace('/^<\?php\s*/', '', $fileContent);
        $fileContent = preg_replace('/^namespace PComposer;\s*/', '', $fileContent);
        
        $content .= "// Source: $file\n";
        $content .= $fileContent . "\n\n";
    }
}

// Add main executable logic
echo "  Adding: main executable logic\n";
$mainContent = file_get_contents('pcomposer');
$mainContent = preg_replace('/^#!\/usr\/bin\/env php\s*<\?php\s*/', '', $mainContent);
$mainContent = preg_replace('/require_once __DIR__ \. \'\/src\/[^\']+\';\s*/', '', $mainContent);

$content .= "// Main executable logic\n";
$content .= $mainContent;

// Write the file
$outputPath = "$distDir/pcomposer-$version.php";
file_put_contents($outputPath, $content);
chmod($outputPath, 0755);

echo "  ✅ Created: $outputPath\n";

// Create installers
echo "🔧 Creating installers...\n";

// Unix installer
$unixInstaller = "#!/bin/bash

# PComposer v$version Installer
# Simple installer for Unix/Linux/macOS

set -e

echo \"🚀 Installing PComposer v$version...\"

# Detect installation directory
INSTALL_DIR=\"/usr/local/bin\"
if [[ ! -w \"\$INSTALL_DIR\" ]]; then
    echo \"⚠️  Cannot write to \$INSTALL_DIR, trying ~/.local/bin\"
    INSTALL_DIR=\"\$HOME/.local/bin\"
    mkdir -p \"\$INSTALL_DIR\"
fi

# Copy executable
cp \"pcomposer-$version.php\" \"\$INSTALL_DIR/pcomposer\"
chmod +x \"\$INSTALL_DIR/pcomposer\"

# Add to PATH if needed
if [[ \":\$PATH:\" != *\":\$INSTALL_DIR:\"* ]]; then
    echo \"📝 Adding \$INSTALL_DIR to PATH...\"
    echo \"export PATH=\\\"\\\$PATH:\$INSTALL_DIR\\\"\" >> \"\$HOME/.bashrc\"
    echo \"export PATH=\\\"\\\$PATH:\$INSTALL_DIR\\\"\" >> \"\$HOME/.zshrc\"
fi

echo \"✅ PComposer installed successfully!\"
echo \"📍 Location: \$INSTALL_DIR/pcomposer\"
echo \"🔧 Usage: pcomposer --help\"
";

file_put_contents("$distDir/install.sh", $unixInstaller);
chmod("$distDir/install.sh", 0755);
echo "  ✅ Created: $distDir/install.sh\n";

// Copy the PHP installer
copy("dist/installer.php", "$distDir/installer.php");
echo "  ✅ Created: $distDir/installer.php\n";

// README
$readme = "# PComposer v$version - Distribution

This directory contains the built distribution files for PComposer v$version.

## Files

- **pcomposer-$version.php** - Single-file executable (works on all platforms)
- **install.sh** - Unix/Linux/macOS installer
- **install.bat** - Windows batch installer
- **install.ps1** - Windows PowerShell installer (recommended)
- **README.md** - This file

## Installation

### Option 1: Single File (Recommended)
```bash
# Download the file
curl -O https://github.com/your-repo/pcomposer/releases/download/v$version/pcomposer-$version.php

# Make executable
chmod +x pcomposer-$version.php

# Use directly
./pcomposer-$version.php install
```

### Option 2: Unix/Linux/macOS Installer
```bash
# Download and run installer
curl -O https://github.com/your-repo/pcomposer/releases/download/v$version/install.sh
chmod +x install.sh
./install.sh
```

### Option 3: Windows Installer
```cmd
# Download and run batch installer
powershell -Command \"(New-Object Net.WebClient).DownloadFile('https://github.com/your-repo/pcomposer/releases/download/v$version/install.bat', 'install.bat')\"
install.bat

# Or use PowerShell installer (recommended)
powershell -Command \"(New-Object Net.WebClient).DownloadFile('https://github.com/your-repo/pcomposer/releases/download/v$version/install.ps1', 'install.ps1')\"
powershell -ExecutionPolicy Bypass -File install.ps1
```

**Note**: After installation, open a NEW command prompt/PowerShell window for the `pcomposer` command to be available.

## Usage

After installation, use PComposer like this:

```bash
# Install dependencies
pcomposer install

# Add a package
pcomposer require monolog/monolog

# Update dependencies
pcomposer update

# Show help
pcomposer --help
```

## Requirements

- PHP 7.4 or higher
- PHP extensions: zip, json, curl (or allow_url_fopen enabled)
- Unix-like system (Linux, macOS) for symbolic links

## License

This software is open source and available under the MIT License.
";

file_put_contents("$distDir/README.md", $readme);
echo "  ✅ Created: $distDir/README.md\n";

// Create checksums
echo "🔍 Creating checksums...\n";

$checksums = [];
$files = glob("$distDir/*");

foreach ($files as $file) {
    if (is_file($file)) {
        $checksums[basename($file)] = hash_file('sha256', $file);
    }
}

$checksumContent = "# PComposer v$version Checksums\n";
$checksumContent .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($checksums as $file => $hash) {
    $checksumContent .= "$hash  $file\n";
}

file_put_contents("$distDir/checksums.txt", $checksumContent);
echo "  ✅ Created: $distDir/checksums.txt\n";

echo "\n✅ Build completed successfully!\n";
echo "📦 Distribution files created in: $distDir/\n";
echo "\nFiles created:\n";
system("ls -la $distDir/");
echo "\nTo test the build:\n";
echo "  cd $distDir\n";
echo "  php pcomposer-$version.php --version\n";
