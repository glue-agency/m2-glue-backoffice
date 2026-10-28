<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Report
{
    private const MAX_LENGTH = 255;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var InstallUid
     */
    private $installUid;

    /**
     * @var Plugins
     */
    private $plugins;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ProductMetadataInterface $productMetadata
     * @param InstallUid $installUid
     * @param Plugins $plugins
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        InstallUid $installUid,
        Plugins $plugins
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->installUid = $installUid;
        $this->plugins = $plugins;
    }

    /**
     * @param string|null $repositoryName
     * @return array
     */
    public function build(?string $repositoryName = null): array
    {
        $payload = [
            'platform'   => 'magento',
            'title'      => $this->limit($this->title()),
            'version'    => $this->limit((string) $this->productMetadata->getVersion()),
            'phpVersion' => PHP_VERSION,
            'uid'        => $this->installUid->get(),
            'sites'      => $this->sites(),
            'plugins'    => array_map([$this, 'limitPlugin'], $this->plugins->collect()),
        ];

        if ($repositoryName !== null && $repositoryName !== '') {
            $payload['repository'] = ['name' => $this->limit($repositoryName)];
        }

        return $payload;
    }

    /**
     * @return string
     */
    private function title(): string
    {
        $name = trim((string) $this->scopeConfig->getValue('general/store_information/name'));

        if ($name !== '') {
            return $name;
        }

        foreach ($this->storeManager->getWebsites() as $website) {
            if ($website->getIsDefault()) {
                return (string) $website->getName();
            }
        }

        return 'Magento';
    }

    /**
     * @return array
     */
    private function sites(): array
    {
        $multipleWebsites = count($this->storeManager->getWebsites()) > 1;
        $sites = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getId() === 0) {
                continue;
            }

            $title = (string) $store->getName();

            if ($multipleWebsites) {
                $title = $store->getWebsite()->getName() . ' - ' . $title;
            }

            $code = (string) $store->getCode();

            $sites[] = [
                'uid'      => $this->limit($code),
                'handle'   => $this->limit($code),
                'title'    => $this->limit($title),
                'language' => Language::fromLocale(
                    (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $code)
                ),
                'url'      => $this->limit((string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true)),
                'enabled'  => $store->isActive() ? 1 : 0,
            ];
        }

        return $sites;
    }

    /**
     * @param array $plugin
     * @return array
     */
    private function limitPlugin(array $plugin): array
    {
        return array_map([$this, 'limit'], $plugin);
    }

    /**
     * @param string $value
     * @return string
     */
    private function limit(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
