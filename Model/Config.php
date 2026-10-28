<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    public const PATH_URL = 'glue_backoffice/general/url';
    public const PATH_TOKEN = 'glue_backoffice/general/token';
    public const PATH_UID = 'glue_backoffice/general/uid';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return rtrim((string) $this->scopeConfig->getValue(self::PATH_URL), '/') . '/';
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::PATH_TOKEN);

        return $value === '' ? '' : trim($this->encryptor->decrypt($value));
    }
}
