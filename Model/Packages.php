<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

use Magento\Framework\Filesystem\DirectoryList;

/**
 * The Composer packages the shop requires directly: its root composer.json's
 * `require`, not its whole lock file, each at the version installed. Magento
 * itself is among them, as magento/product-community-edition. Platform
 * requirements (php, ext-*, lib-*) are not packages and are left out.
 */
class Packages
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @param DirectoryList $directoryList
     */
    public function __construct(DirectoryList $directoryList)
    {
        $this->directoryList = $directoryList;
    }

    /**
     * @return array
     */
    public function collect(): array
    {
        $root = self::readJson(rtrim($this->directoryList->getRoot(), '/') . '/composer.json');
        $installed = $this->installed();
        $packages = [];

        foreach (array_keys((array) ($root['require'] ?? [])) as $name) {
            $name = (string) $name;

            if (self::isPlatformRequirement($name)) {
                continue;
            }

            $packages[] = [
                'name'        => $name,
                'version'     => self::version($name, $installed[$name] ?? []),
                'description' => (string) ($installed[$name]['description'] ?? ''),
            ];
        }

        return $packages;
    }

    /**
     * php, an extension (ext-intl), a library (lib-curl) or Composer's own API:
     * things the server provides rather than packages, and the only names
     * without a vendor.
     *
     * @param string $name
     * @return bool
     */
    public static function isPlatformRequirement(string $name): bool
    {
        return strpos($name, '/') === false;
    }

    /**
     * @param string $name
     * @param array $installed
     * @return string
     */
    private static function version(string $name, array $installed): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion($name);

                if ($version) {
                    return (string) $version;
                }
            } catch (\Throwable $e) {
            }
        }

        return (string) ($installed['version'] ?? 'dev');
    }

    /**
     * Composer's own record of what it installed, by name, for descriptions.
     *
     * @return array
     */
    private function installed(): array
    {
        if (! class_exists(\Composer\InstalledVersions::class)) {
            return [];
        }

        $file = dirname((string) (new \ReflectionClass(\Composer\InstalledVersions::class))->getFileName()) . '/installed.json';
        $data = self::readJson($file);
        $packages = isset($data['packages']) ? $data['packages'] : $data;
        $byName = [];

        foreach ((array) $packages as $package) {
            if (is_array($package) && isset($package['name'])) {
                $byName[(string) $package['name']] = $package;
            }
        }

        return $byName;
    }

    /**
     * @param string $file
     * @return array
     */
    private static function readJson(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
