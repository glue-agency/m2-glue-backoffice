<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Module\ModuleListInterface;

class Plugins
{
    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var ComponentRegistrarInterface
     */
    private $componentRegistrar;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @param ModuleListInterface $moduleList
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param DirectoryList $directoryList
     */
    public function __construct(
        ModuleListInterface $moduleList,
        ComponentRegistrarInterface $componentRegistrar,
        DirectoryList $directoryList
    ) {
        $this->moduleList = $moduleList;
        $this->componentRegistrar = $componentRegistrar;
        $this->directoryList = $directoryList;
    }

    /**
     * @return array
     */
    public function collect(): array
    {
        $root = self::normalize($this->directoryList->getRoot());
        $plugins = [];

        foreach ($this->moduleList->getNames() as $name) {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $name);

            if (! $path) {
                continue;
            }

            $path = self::normalize($path);
            $package = null;

            if (self::isUnder($path, $root . '/app/code')) {
                $version = $this->setupVersion($path) ?: 'dev';
            } else {
                $composerFile = self::findComposerFile($path, $root);

                if ($composerFile === null) {
                    continue;
                }

                $package = $this->readComposer($composerFile);
                $packageName = (string) ($package['name'] ?? '');

                if ($packageName === '' || self::isCorePackage($packageName)) {
                    continue;
                }

                $version = $this->composerVersion($packageName, $package, $path);
            }

            $plugins[] = [
                'uid'       => $name,
                'handle'    => $name,
                'title'     => $name,
                'version'   => $version,
                'developer' => self::vendorOf($name),
            ];
        }

        return $plugins;
    }

    /**
     * @param string $packageName
     * @return bool
     */
    public static function isCorePackage(string $packageName): bool
    {
        return strpos($packageName, 'magento/') === 0;
    }

    /**
     * @param string $path
     * @param string $directory
     * @return bool
     */
    public static function isUnder(string $path, string $directory): bool
    {
        return strpos(self::normalize($path) . '/', rtrim(self::normalize($directory), '/') . '/') === 0;
    }

    /**
     * @param string $moduleName
     * @return string
     */
    public static function vendorOf(string $moduleName): string
    {
        return explode('_', $moduleName, 2)[0];
    }

    /**
     * @param string $path
     * @return string
     */
    public static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param string $path
     * @param string $root
     * @return string|null
     */
    private static function findComposerFile(string $path, string $root): ?string
    {
        $directory = $path;

        while (true) {
            if (is_file($directory . '/composer.json')) {
                return $directory . '/composer.json';
            }

            $parent = self::normalize(dirname($directory));

            if ($parent === $directory || $directory === $root) {
                return null;
            }

            $directory = $parent;
        }
    }

    /**
     * @param string $file
     * @return array
     */
    private function readComposer(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param string $packageName
     * @param array $package
     * @param string $path
     * @return string
     */
    private function composerVersion(string $packageName, array $package, string $path): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion($packageName);

                if ($version) {
                    return (string) $version;
                }
            } catch (\Throwable $e) {
            }
        }

        if (! empty($package['version'])) {
            return (string) $package['version'];
        }

        return $this->setupVersion($path) ?: 'dev';
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function setupVersion(string $path): ?string
    {
        $file = $path . '/etc/module.xml';

        if (! is_file($file)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        libxml_use_internal_errors($previous);

        if ($xml === false || ! isset($xml->module['setup_version'])) {
            return null;
        }

        return (string) $xml->module['setup_version'] ?: null;
    }
}
