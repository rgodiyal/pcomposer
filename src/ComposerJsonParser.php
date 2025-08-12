<?php

namespace PComposer;

/**
 * ComposerJsonParser handles reading and modifying composer.json files
 */
class ComposerJsonParser
{
    private string $projectRoot;
    private string $composerJsonPath;
    private array $data;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $this->composerJsonPath = $projectRoot . '/composer.json';
        $this->data = $this->loadComposerJson();
    }

    /**
     * Load composer.json file
     */
    private function loadComposerJson(): array
    {
        if (!file_exists($this->composerJsonPath)) {
            return [
                'name' => 'project/root',
                'description' => 'Project managed by PComposer',
                'type' => 'project',
                'require' => [],
                'require-dev' => [],
                'autoload' => [],
                'autoload-dev' => []
            ];
        }

        $content = file_get_contents($this->composerJsonPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in composer.json: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Save composer.json file
     */
    private function saveComposerJson(): void
    {
        $content = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->composerJsonPath, $content);
    }

    /**
     * Get all dependencies (require + require-dev)
     */
    public function getDependencies(): array
    {
        $dependencies = [];
        
        if (isset($this->data['require'])) {
            $dependencies = array_merge($dependencies, $this->data['require']);
        }
        
        if (isset($this->data['require-dev'])) {
            $dependencies = array_merge($dependencies, $this->data['require-dev']);
        }
        
        return $dependencies;
    }

    /**
     * Get production dependencies only
     */
    public function getProductionDependencies(): array
    {
        return $this->data['require'] ?? [];
    }

    /**
     * Get development dependencies only
     */
    public function getDevelopmentDependencies(): array
    {
        return $this->data['require-dev'] ?? [];
    }

    /**
     * Get autoload configuration
     */
    public function getAutoloadConfig(): array
    {
        return $this->data['autoload'] ?? [];
    }

    /**
     * Get development autoload configuration
     */
    public function getDevAutoloadConfig(): array
    {
        return $this->data['autoload-dev'] ?? [];
    }

    /**
     * Add a dependency to composer.json
     */
    public function addDependency(string $packageName, ?string $version = null, bool $isDev = false): void
    {
        $version = $version ?: '*';
        $section = $isDev ? 'require-dev' : 'require';
        
        if (!isset($this->data[$section])) {
            $this->data[$section] = [];
        }
        
        $this->data[$section][$packageName] = $version;
        
        // Sort dependencies alphabetically
        ksort($this->data[$section]);
        
        $this->saveComposerJson();
    }

    /**
     * Remove a dependency from composer.json
     */
    public function removeDependency(string $packageName): void
    {
        $removed = false;
        
        if (isset($this->data['require'][$packageName])) {
            unset($this->data['require'][$packageName]);
            $removed = true;
        }
        
        if (isset($this->data['require-dev'][$packageName])) {
            unset($this->data['require-dev'][$packageName]);
            $removed = true;
        }
        
        if ($removed) {
            $this->saveComposerJson();
        }
    }

    /**
     * Update a dependency version
     */
    public function updateDependency(string $packageName, string $version, bool $isDev = false): void
    {
        $section = $isDev ? 'require-dev' : 'require';
        
        if (isset($this->data[$section][$packageName])) {
            $this->data[$section][$packageName] = $version;
            $this->saveComposerJson();
        }
    }

    /**
     * Check if a dependency exists
     */
    public function hasDependency(string $packageName): bool
    {
        return isset($this->data['require'][$packageName]) || 
               isset($this->data['require-dev'][$packageName]);
    }

    /**
     * Get dependency version
     */
    public function getDependencyVersion(string $packageName): ?string
    {
        if (isset($this->data['require'][$packageName])) {
            return $this->data['require'][$packageName];
        }
        
        if (isset($this->data['require-dev'][$packageName])) {
            return $this->data['require-dev'][$packageName];
        }
        
        return null;
    }

    /**
     * Check if dependency is a development dependency
     */
    public function isDevDependency(string $packageName): bool
    {
        return isset($this->data['require-dev'][$packageName]);
    }

    /**
     * Add autoload configuration
     */
    public function addAutoloadConfig(string $type, array $config, bool $isDev = false): void
    {
        $section = $isDev ? 'autoload-dev' : 'autoload';
        
        if (!isset($this->data[$section])) {
            $this->data[$section] = [];
        }
        
        if (!isset($this->data[$section][$type])) {
            $this->data[$section][$type] = [];
        }
        
        $this->data[$section][$type] = array_merge($this->data[$section][$type], $config);
        $this->saveComposerJson();
    }

    /**
     * Remove autoload configuration
     */
    public function removeAutoloadConfig(string $type, string $key, bool $isDev = false): void
    {
        $section = $isDev ? 'autoload-dev' : 'autoload';
        
        if (isset($this->data[$section][$type][$key])) {
            unset($this->data[$section][$type][$key]);
            $this->saveComposerJson();
        }
    }

    /**
     * Get project name
     */
    public function getProjectName(): string
    {
        return $this->data['name'] ?? 'project/root';
    }

    /**
     * Set project name
     */
    public function setProjectName(string $name): void
    {
        $this->data['name'] = $name;
        $this->saveComposerJson();
    }

    /**
     * Get project description
     */
    public function getProjectDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    /**
     * Set project description
     */
    public function setProjectDescription(string $description): void
    {
        $this->data['description'] = $description;
        $this->saveComposerJson();
    }

    /**
     * Get project type
     */
    public function getProjectType(): string
    {
        return $this->data['type'] ?? 'project';
    }

    /**
     * Set project type
     */
    public function setProjectType(string $type): void
    {
        $this->data['type'] = $type;
        $this->saveComposerJson();
    }

    /**
     * Get all project metadata
     */
    public function getProjectMetadata(): array
    {
        return [
            'name' => $this->getProjectName(),
            'description' => $this->getProjectDescription(),
            'type' => $this->getProjectType(),
            'require' => $this->getProductionDependencies(),
            'require-dev' => $this->getDevelopmentDependencies(),
            'autoload' => $this->getAutoloadConfig(),
            'autoload-dev' => $this->getDevAutoloadConfig()
        ];
    }

    /**
     * Validate composer.json structure
     */
    public function validate(): array
    {
        $errors = [];
        
        // Check for required fields
        if (!isset($this->data['name'])) {
            $errors[] = "Missing 'name' field";
        }
        
        // Validate package name format
        if (isset($this->data['name']) && !preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/', $this->data['name'])) {
            $errors[] = "Invalid package name format";
        }
        
        // Validate dependencies
        foreach (['require', 'require-dev'] as $section) {
            if (isset($this->data[$section]) && !is_array($this->data[$section])) {
                $errors[] = "Invalid '$section' section";
            }
        }
        
        // Validate autoload configuration
        foreach (['autoload', 'autoload-dev'] as $section) {
            if (isset($this->data[$section]) && !is_array($this->data[$section])) {
                $errors[] = "Invalid '$section' section";
            }
        }
        
        return $errors;
    }

    /**
     * Create a new composer.json file
     */
    public function createComposerJson(string $name, string $description = '', string $type = 'project'): void
    {
        $this->data = [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'require' => [],
            'require-dev' => [],
            'autoload' => [],
            'autoload-dev' => []
        ];
        
        $this->saveComposerJson();
    }

    /**
     * Get the raw composer.json data
     */
    public function getRawData(): array
    {
        return $this->data;
    }
}
