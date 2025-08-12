# PComposer v1.0.0 - Distribution

This directory contains the built distribution files for PComposer v1.0.0.

## Files

- **pcomposer-1.0.0.php** - Single-file executable (works on all platforms)
- **install.sh** - Unix/Linux/macOS installer
- **install.bat** - Windows batch installer
- **install.ps1** - Windows PowerShell installer (recommended)
- **README.md** - This file

## Installation

### Option 1: Single File (Recommended)
```bash
# Download the file
curl -O https://github.com/your-repo/pcomposer/releases/download/v1.0.0/pcomposer-1.0.0.php

# Make executable
chmod +x pcomposer-1.0.0.php

# Use directly
./pcomposer-1.0.0.php install
```

### Option 2: Unix/Linux/macOS Installer
```bash
# Download and run installer
curl -O https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.sh
chmod +x install.sh
./install.sh
```

### Option 3: Windows Installer
```cmd
# Download and run batch installer
powershell -Command "(New-Object Net.WebClient).DownloadFile('https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.bat', 'install.bat')"
install.bat

# Or use PowerShell installer (recommended)
powershell -Command "(New-Object Net.WebClient).DownloadFile('https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.ps1', 'install.ps1')"
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
