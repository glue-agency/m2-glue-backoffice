<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

class InstallUid
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var WriterInterface
     */
    private $writer;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @param ResourceConnection $resource
     * @param WriterInterface $writer
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        ResourceConnection $resource,
        WriterInterface $writer,
        TypeListInterface $cacheTypeList
    ) {
        $this->resource = $resource;
        $this->writer = $writer;
        $this->cacheTypeList = $cacheTypeList;
    }

    /**
     * @return string
     */
    public function get(): string
    {
        $uid = $this->read();

        if ($uid !== '') {
            return $uid;
        }

        $uid = self::generate();
        $this->writer->save(Config::PATH_UID, $uid, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        $this->cacheTypeList->cleanType('config');

        return $uid;
    }

    /**
     * @return string
     */
    private function read(): string
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('core_config_data'), ['value'])
            ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->where('scope_id = ?', 0)
            ->where('path = ?', Config::PATH_UID);

        return trim((string) $connection->fetchOne($select));
    }

    /**
     * @return string
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
