# PComposer - Fast PHP Package Manager

PComposer is a fast PHP package manager inspired by pnpm, designed to resolve Composer's drawback of having a global package directory. It uses a global package store to avoid re-downloading existing packages, making it much faster than traditional Composer installations.

## Features

- **Global Package Store**: Packages are stored globally and shared between projects
- **Symbolic Links**: Uses symbolic links in vendor directory to point to global store
- **Composer.json Compatibility**: Works with existing composer.json files
- **Lock File Support**: Uses `pcomposer.lock` for reproducible installations
- **Fast Installations**: Avoids re-downloading packages that are already installed
- **Disk Space Efficient**: Multiple projects can share the same packages
- **Familiar Commands**: Uses the same command structure as Composer

## Installation

### Option 1: Single File Distribution (Recommended)
Download the pre-built single-file executable:

```bash
# Download the latest version
curl -O https://github.com/your-repo/pcomposer/releases/download/v1.0.0/pcomposer-1.0.0.php

# Make executable
chmod +x pcomposer-1.0.0.php

# Use directly
./pcomposer-1.0.0.php install
```

### Option 2: Installer Scripts
Use the platform-specific installers:

**Unix/Linux/macOS:**
```bash
curl -O https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.sh
chmod +x install.sh
./install.sh
```

**Windows:**
```cmd
powershell -Command "(New-Object Net.WebClient).DownloadFile('https://github.com/your-repo/pcomposer/releases/download/v1.0.0/install.bat', 'install.bat')"
install.bat
```

### Option 3: From Source (Development)
1. Clone or download this repository
2. Run the installation script:
   ```bash
   chmod +x install.sh
   ./install.sh
   ```
   
   Or install manually:
   ```bash
   chmod +x pcomposer
   sudo ln -s $(pwd)/pcomposer /usr/local/bin/pcomposer
   ```

## Requirements

- PHP 7.4 or higher
- PHP extensions: `zip`, `json`, `curl` (or `allow_url_fopen` enabled)
- Unix-like system (Linux, macOS) for symbolic links

## Usage

### Basic Commands

```bash
# Install dependencies from composer.json
pcomposer install

# Update dependencies
pcomposer update

# Add a package
pcomposer require vendor/package

# Add a package with specific version
pcomposer require vendor/package "^1.0"

# Remove a package
pcomposer remove vendor/package

# List installed packages
pcomposer list

# Show package information
pcomposer show vendor/package

# Generate autoloader
pcomposer dump-autoload

# Clear global package cache
pcomposer clear-cache

# Show lock file information
pcomposer lock

# Remove lock file to force fresh install
pcomposer unlock

# Show version
pcomposer --version

# Show help
pcomposer --help
```

### Working with Projects

1. **Initialize a new project**:
   ```bash
   # Create composer.json manually or use existing one
   echo '{
     "name": "my/project",
     "require": {}
   }' > composer.json
   
   pcomposer install
   ```

2. **Add dependencies**:
   ```bash
   pcomposer require monolog/monolog
   pcomposer require symfony/http-foundation "^5.0"
   ```

3. **Install in existing project**:
   ```bash
   cd /path/to/your/project
   pcomposer install
   ```

### Lock File Management

PComposer uses a `pcomposer.lock` file to ensure reproducible installations across different environments. This is similar to Composer's `composer.lock` file.

**Key Benefits:**
- **Reproducible Builds**: Everyone gets the exact same versions
- **Faster Installations**: No need to resolve versions each time
- **Team Consistency**: All team members use identical dependencies
- **CI/CD Reliability**: Guarantees consistent deployments

**Lock File Commands:**
```bash
# View lock file information
pcomposer lock

# Remove lock file to force fresh version resolution
pcomposer unlock

# Install will automatically create/use lock file
pcomposer install

# Update will regenerate lock file with latest versions
pcomposer update
```

**Lock File Behavior:**
- `pcomposer install` uses locked versions if lock file exists and is up-to-date
- `pcomposer install` creates new lock file if none exists
- `pcomposer update` removes old lock file and creates new one with latest versions
- `pcomposer unlock` removes lock file to force fresh dependency resolution

## How It Works

### Global Package Store

PComposer stores packages in a global directory (`~/.pcomposer/store`) similar to pnpm's store. When you install a package:

1. PComposer checks if the package exists in the global store
2. If not found, it downloads and stores it globally
3. Creates a symbolic link in your project's `vendor/` directory pointing to the global store
4. Multiple projects can share the same package files

### Symbolic Links

Instead of copying packages to each project's vendor directory, PComposer creates symbolic links:

```
Your Project/
├── composer.json
├── vendor/
│   ├── monolog/          -> ~/.pcomposer/store/monolog/monolog/2.0.0
│   ├── symfony/          -> ~/.pcomposer/store/symfony/http-foundation/5.0.0
│   └── autoload.php
```

### Benefits

- **Speed**: No re-downloading of existing packages
- **Disk Space**: Shared packages across projects
- **Consistency**: Same package versions across projects
- **Compatibility**: Works with existing composer.json files

## Configuration

### Global Store Location

The global store is located at:
- Linux/macOS: `~/.pcomposer/store`
- Windows: `%USERPROFILE%\.pcomposer\store`

### Environment Variables

- `PCOMPOSER_HOME`: Override the home directory
- `PCOMPOSER_CACHE_DIR`: Override the cache directory

## Comparison with Composer

| Feature | Composer | PComposer |
|---------|----------|-----------|
| Package Storage | Local to each project | Global shared store |
| Installation Speed | Downloads every time | Reuses existing packages |
| Disk Usage | Duplicates packages | Shares packages |
| Command Interface | `composer` | `pcomposer` |
| Configuration | composer.json | composer.json (compatible) |
| Autoloader | Generated locally | Generated locally |

## Advanced Usage

### Custom Global Store Location

```bash
export PCOMPOSER_HOME=/custom/path
pcomposer install
```

### Package Information

```bash
# Show detailed package info
pcomposer show monolog/monolog

# List all packages in global store
pcomposer list --global
```

### Cache Management

```bash
# Clear global cache
pcomposer clear-cache

# Show cache statistics
pcomposer stats
```

## Troubleshooting

### Common Issues

1. **Symbolic link permissions**:
   ```bash
   # Ensure you have permission to create symbolic links
   sudo chmod 755 /path/to/your/project
   ```

2. **Global store access**:
   ```bash
   # Check global store permissions
   ls -la ~/.pcomposer/store
   ```

3. **Broken links**:
   ```bash
   # Repair broken symbolic links
   pcomposer repair-links
   ```

### Debug Mode

```bash
# Enable debug output
PCOMPOSER_DEBUG=1 pcomposer install
```

## Development

### Project Structure

```
pcomposer/
├── pcomposer              # Main executable
├── src/                   # Source code
│   ├── PComposer.php     # Main class
│   ├── GlobalStore.php   # Global package store
│   ├── PackageManager.php # Package downloading
│   ├── ComposerJsonParser.php # JSON handling
│   ├── VendorLinker.php  # Symbolic link management
│   └── Utils.php         # Utility functions
└── README.md
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Building from Source

If you want to build PComposer from source or create your own distribution:

```bash
# Clone the repository
git clone https://github.com/your-repo/pcomposer.git
cd pcomposer

# Run the build script
php build.php

# The built files will be in the dist/ directory
ls -la dist/
```

For detailed build information, see [BUILD.md](BUILD.md).

## License

This project is open source and available under the MIT License.

## Acknowledgments

- Inspired by [pnpm](https://pnpm.io/) for Node.js
- Compatible with [Composer](https://getcomposer.org/) ecosystem
- Uses [Packagist](https://packagist.org/) as the package registry

## Support

For issues, questions, or contributions, please open an issue on the GitHub repository.
