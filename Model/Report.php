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
     * @var Packages
     */
    private $packages;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ProductMetadataInterface $productMetadata
     * @param InstallUid $installUid
     * @param Packages $packages
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        InstallUid $installUid,
        Packages $packages
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->installUid = $installUid;
        $this->packages = $packages;
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
            'edition'    => self::edition((string) $this->productMetadata->getEdition()),
            'phpVersion' => PHP_VERSION,
            'uid'        => $this->installUid->get(),
            'storeViews' => $this->storeViews(),
            'packages'   => array_map([$this, 'limitAll'], $this->packages->collect()),
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
     * Magento's edition as Adobe names it: Open Source, or Adobe Commerce.
     *
     * @param string $edition
     * @return string
     */
    public static function edition(string $edition): string
    {
        switch ($edition) {
            case 'Community':
                return 'Open Source';
            case 'Enterprise':
                return 'Adobe Commerce';
            case 'B2B':
                return 'Adobe Commerce B2B';
            default:
                return $edition;
        }
    }

    /**
     * Every store view but admin, with the website it belongs to.
     *
     * @return array
     */
    private function storeViews(): array
    {
        $storeViews = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getId() === 0) {
                continue;
            }

            $code = (string) $store->getCode();

            $storeViews[] = [
                'uid'     => $this->limit($code),
                'code'    => $this->limit($code),
                'name'    => $this->limit((string) $store->getName()),
                'website' => $this->limit((string) $store->getWebsite()->getName()),
                'locale'  => $this->limit(
                    (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $code)
                ),
                'url'     => $this->limit((string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true)),
                'enabled' => $store->isActive() ? 1 : 0,
            ];
        }

        return $storeViews;
    }

    /**
     * @param array $row
     * @return array
     */
    private function limitAll(array $row): array
    {
        return array_map([$this, 'limit'], $row);
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
