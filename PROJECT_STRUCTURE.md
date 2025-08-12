# PComposer Project Structure

This document describes the complete structure of the PComposer project and explains the purpose of each component.

## Directory Structure

```
pcomposer/
├── pcomposer                    # Main executable script
├── install.sh                   # Global installation script
├── composer.json               # Sample composer.json for testing
├── test.php                    # Test script to verify functionality
├── README.md                   # Main documentation
├── PROJECT_STRUCTURE.md        # This file
└── src/                        # Source code directory
    ├── PComposer.php          # Main orchestrator class
    ├── GlobalStore.php        # Global package store management
    ├── PackageManager.php     # Package downloading and installation
    ├── ComposerJsonParser.php # composer.json file handling
    ├── VendorLinker.php       # Symbolic link management
    └── Utils.php              # Utility functions
```

## Component Descriptions

### Core Executable

#### `pcomposer`
- **Purpose**: Main command-line interface
- **Functionality**: 
  - Parses command-line arguments
  - Routes commands to appropriate classes
  - Provides help and version information
  - Handles errors and exceptions

### Installation & Setup

#### `install.sh`
- **Purpose**: Automated global installation script
- **Functionality**:
  - Checks system requirements (PHP version, extensions)
  - Creates symbolic link in `/usr/local/bin`
  - Sets up global package store
  - Validates installation

#### `composer.json`
- **Purpose**: Sample project configuration
- **Functionality**:
  - Demonstrates proper composer.json format
  - Includes sample dependencies for testing
  - Shows autoload configuration

### Source Code Components

#### `src/PComposer.php`
- **Purpose**: Main orchestrator class
- **Key Methods**:
  - `install()`: Install dependencies
  - `update()`: Update dependencies
  - `requirePackage()`: Add new package
  - `removePackage()`: Remove package
  - `dumpAutoload()`: Generate autoloader

#### `src/GlobalStore.php`
- **Purpose**: Manages global package store (like pnpm's store)
- **Key Features**:
  - Stores packages in `~/.pcomposer/store`
  - Maintains metadata about installed packages
  - Prevents re-downloading existing packages
  - Handles package versioning

#### `src/PackageManager.php`
- **Purpose**: Handles package downloading and resolution
- **Key Features**:
  - Downloads packages from Packagist
  - Resolves version constraints
  - Extracts package archives
  - Manages dependencies recursively

#### `src/ComposerJsonParser.php`
- **Purpose**: Handles composer.json file operations
- **Key Features**:
  - Reads and writes composer.json
  - Validates JSON structure
  - Manages dependencies and autoload config
  - Provides project metadata

#### `src/VendorLinker.php`
- **Purpose**: Creates symbolic links in vendor directory
- **Key Features**:
  - Links packages from global store to vendor/
  - Manages symbolic link lifecycle
  - Cleans up unused links
  - Repairs broken links

#### `src/Utils.php`
- **Purpose**: Common utility functions
- **Key Features**:
  - File and directory operations
  - Version constraint parsing
  - HTTP requests
  - System information
  - Archive extraction

### Testing & Documentation

#### `test.php`
- **Purpose**: Verification script
- **Functionality**:
  - Tests all major components
  - Validates system requirements
  - Demonstrates basic usage
  - Provides debugging information

#### `README.md`
- **Purpose**: Main documentation
- **Content**:
  - Installation instructions
  - Usage examples
  - Feature descriptions
  - Troubleshooting guide

## Architecture Overview

### Data Flow

1. **Command Input**: User runs `pcomposer <command>`
2. **Parsing**: `pcomposer` script parses arguments
3. **Orchestration**: `PComposer` class routes to appropriate components
4. **Package Resolution**: `PackageManager` checks global store and downloads if needed
5. **Storage**: `GlobalStore` stores packages globally
6. **Linking**: `VendorLinker` creates symbolic links
7. **Configuration**: `ComposerJsonParser` manages project configuration
8. **Autoloading**: Generates autoloader for the project

### Key Design Principles

1. **Global Store**: Like pnpm, packages are stored once globally
2. **Symbolic Links**: Fast access to packages without duplication
3. **Composer Compatibility**: Works with existing composer.json files
4. **Modular Design**: Each component has a single responsibility
5. **Error Handling**: Comprehensive error handling and user feedback

### Performance Benefits

- **Disk Space**: Shared packages across projects
- **Installation Speed**: No re-downloading of existing packages
- **Network Usage**: Reduced bandwidth consumption
- **Consistency**: Same package versions across projects

## File Permissions

- `pcomposer`: Executable (755)
- `install.sh`: Executable (755)
- `src/*.php`: Readable (644)
- `*.json`: Readable (644)
- `*.md`: Readable (644)

## Dependencies

### System Requirements
- PHP 7.4+
- PHP extensions: zip, json
- Unix-like system (for symbolic links)

### External Services
- Packagist.org (package registry)
- GitHub/GitLab (package sources)

## Security Considerations

- Packages are downloaded from trusted sources (Packagist)
- Symbolic links prevent code injection
- File permissions are properly set
- Input validation on package names and versions

## Future Enhancements

- Support for private repositories
- Parallel package downloading
- Better version conflict resolution
- Windows compatibility
- Plugin system
- Performance optimizations
