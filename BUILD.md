# PComposer Build System

This document describes the build system for PComposer, which creates production-ready distribution packages.

## 🏗️ **Build Overview**

PComposer uses a build system that creates a **single-file executable** containing all source code, making it easy for users to install and use without dealing with source code complexity.

## 📦 **Build Outputs**

The build system creates the following files in the `dist/` directory:

- **`pcomposer-1.0.0.php`** - Single-file executable (83KB)
- **`install.sh`** - Unix/Linux/macOS installer script
- **`install.bat`** - Windows installer script
- **`README.md`** - Distribution documentation
- **`checksums.txt`** - SHA256 checksums for verification

## 🔧 **Build Scripts**

### Primary Build Script
```bash
php build.php
```

This is the main build script that:
1. Bundles all source files into a single PHP executable
2. Creates platform-specific installers
3. Generates documentation and checksums
4. Handles namespace declarations properly

## 🚀 **Building for Distribution**

### Prerequisites
- PHP 7.4 or higher
- All source files in `src/` directory
- Main executable `pcomposer` in root directory

### Build Process
```bash
# Clean previous builds
rm -rf dist/

# Run the build
php build.php

# Verify the build
cd dist/
php pcomposer-1.0.0.php --version
```

### Build Features
- **Single File**: All source code bundled into one executable
- **Cross-Platform**: Works on Linux, macOS, and Windows
- **Self-Contained**: No external dependencies beyond PHP
- **Versioned**: Includes version information in the build
- **Checksums**: SHA256 verification for security

## 📋 **Installation Methods**

### Method 1: Single File (Recommended)
```bash
# Download and use directly
curl -O https://github.com/your-repo/pcomposer/releases/download/v1.0.0/pcomposer-1.0.0.php
chmod +x pcomposer-1.0.0.php
./pcomposer-1.0.0.php install
```

### Method 2: Unix/Linux/macOS Installer
```bash
# Download and run installer
curl -O https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.sh
chmod +x install.sh
./install.sh
```

### Method 3: Windows Installer
```cmd
# Download and run installer
powershell -Command "(New-Object Net.WebClient).DownloadFile('https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.bat', 'install.bat')"
install.bat
```

## 🔍 **Build Verification**

### Syntax Check
```bash
php -l dist/pcomposer-1.0.0.php
```

### Functionality Test
```bash
cd dist/
php pcomposer-1.0.0.php --version
php pcomposer-1.0.0.php --help
```

### Installation Test
```bash
# Test installer
cd /tmp
mkdir test-install
cd test-install
cp /path/to/pcomposer-1.0.0.php .
cp /path/to/install.sh .
chmod +x install.sh
./install.sh
pcomposer --version
```

## 📊 **Build Statistics**

### File Sizes
- **Source Code**: ~25KB (7 PHP files)
- **Built Executable**: ~83KB (single file)
- **Compression Ratio**: ~3.3x (includes all dependencies)

### Performance
- **Build Time**: ~2-3 seconds
- **Memory Usage**: Minimal (file operations only)
- **Dependencies**: None (pure PHP)

## 🔧 **Customization**

### Version Updates
Edit the `$version` variable in `build.php`:
```php
$version = '1.0.1'; // Update version here
```

### Adding Files
Add new source files to the `$sourceFiles` array:
```php
$sourceFiles = [
    'src/PComposer.php',
    'src/GlobalStore.php',
    'src/PackageManager.php',
    'src/ComposerJsonParser.php',
    'src/VendorLinker.php',
    'src/LockFile.php',
    'src/Utils.php',
    'src/NewFile.php', // Add new files here
];
```

### Custom Installers
Modify the installer templates in the build script to add custom installation logic.

## 🚨 **Troubleshooting**

### Common Issues

1. **Namespace Declaration Error**
   - Ensure namespace is declared before any other code
   - Check for stray closing braces

2. **File Not Found**
   - Verify all source files exist in `src/` directory
   - Check file permissions

3. **Build Fails**
   - Ensure PHP 7.4+ is installed
   - Check for syntax errors in source files
   - Verify write permissions in build directory

### Debug Build
```bash
# Verbose build with error checking
php -d display_errors=1 build.php

# Check built file syntax
php -l dist/pcomposer-1.0.0.php
```

## 📈 **Release Process**

### Pre-Release Checklist
- [ ] All tests pass
- [ ] Version number updated
- [ ] Documentation updated
- [ ] Build runs successfully
- [ ] Built executable tested
- [ ] Checksums generated

### Release Steps
1. Update version in `build.php`
2. Run build: `php build.php`
3. Test built executable
4. Create GitHub release
5. Upload distribution files
6. Update download links in documentation

## 🔒 **Security Considerations**

- **Checksums**: All builds include SHA256 checksums
- **Source Verification**: Built from verified source code
- **No External Dependencies**: Self-contained executable
- **CLI Only**: Prevents web execution

## 📚 **Related Documentation**

- [README.md](README.md) - Main project documentation
- [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) - Project architecture
- [install.sh](install.sh) - Global installation script

---

**Build System Version**: 1.0.0  
**Last Updated**: 2025-08-12  
**Maintainer**: PComposer Team
