<?php declare(strict_types=1);

namespace SwagMigrationNext\Test\Migration\Mapping;

use Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use SwagMigrationNext\Exception\LocaleNotFoundException;
use SwagMigrationNext\Migration\Mapping\MappingService;
use SwagMigrationNext\Migration\Mapping\MappingServiceInterface;
use SwagMigrationNext\Test\Migration\Services\MigrationProfileUuidService;
use Symfony\Component\HttpFoundation\Response;

class MappingServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var MappingServiceInterface
     */
    private $mappingService;

    /**
     * @var MigrationProfileUuidService
     */
    private $profileUuidService;

    protected function setUp()
    {
        $this->profileUuidService = new MigrationProfileUuidService($this->getContainer()->get('swag_migration_profile.repository'));

        $this->mappingService = new MappingService(
            $this->getContainer()->get('swag_migration_mapping.repository'),
            $this->getContainer()->get('locale.repository'),
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->get('country.repository'),
            $this->getContainer()->get('currency.repository')
        );
    }

    public function testCreateNewUuid(): void
    {
        $context = Context::createDefaultContext();

        $uuid1 = $this->mappingService->createNewUuid($this->profileUuidService->getProfileUuid(), 'product', '123', $context);
        static::assertNotNull($uuid1);

        $uuid2 = $this->mappingService->createNewUuid($this->profileUuidService->getProfileUuid(), 'product', '123', $context);
        static::assertSame($uuid1, $uuid2);
    }

    public function testReadExistingMappings(): void
    {
        $context = Context::createDefaultContext();
        $uuid1 = $this->mappingService->createNewUuid($this->profileUuidService->getProfileUuid(), 'product', '123', $context);

        $this->mappingService->writeMapping($context);

        $newMappingService = new MappingService(
            $this->getContainer()->get('swag_migration_mapping.repository'),
            $this->getContainer()->get('locale.repository'),
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->get('country.repository'),
            $this->getContainer()->get('currency.repository')
        );

        $uuid2 = $newMappingService->createNewUuid($this->profileUuidService->getProfileUuid(), 'product', '123', $context);

        static::assertSame($uuid1, $uuid2);
    }

    public function testGetUuidReturnsNull(): void
    {
        $context = Context::createDefaultContext();
        static::assertNull($this->mappingService->getUuid($this->profileUuidService->getProfileUuid(), 'product', '12345', $context));
    }

    public function testLocaleNotFoundException(): void
    {
        static::markTestSkipped('Remove when translation support is implemented');
        $context = Context::createDefaultContext();

        try {
            $this->mappingService->getLanguageUuid($this->profileUuidService->getProfileUuid(), 'foobar', $context);
        } catch (Exception $e) {
            /* @var LocaleNotFoundException $e */
            self::assertInstanceOf(LocaleNotFoundException::class, $e);
            self::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        }
    }
}
