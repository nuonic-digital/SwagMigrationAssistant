<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Profile\Shopware6\Converter;

use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\Converter\ConverterInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Profile\Shopware6\Converter\CrossSellingConverter;
use SwagMigrationAssistant\Profile\Shopware6\DataSelection\DataSet\CrossSellingDataSet;
use SwagMigrationAssistant\Profile\Shopware6\Mapping\Shopware6MappingServiceInterface;

#[Package('services-settings')]
class CrossSellingConverterTest extends ShopwareConverterTest
{
    protected function createConverter(Shopware6MappingServiceInterface $mappingService, LoggingServiceInterface $loggingService, MediaFileServiceInterface $mediaFileService): ConverterInterface
    {
        return new CrossSellingConverter($mappingService, $loggingService);
    }

    protected function createDataSet(): DataSet
    {
        return new CrossSellingDataSet();
    }

    protected static function getFixtureBasePath(): string
    {
        return __DIR__ . '/../../../_fixtures/Shopware6/CrossSelling/';
    }
}
