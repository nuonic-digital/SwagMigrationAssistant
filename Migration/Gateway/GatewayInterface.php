<?php declare(strict_types=1);

namespace SwagMigrationNext\Migration\Gateway;

interface GatewayInterface
{
    /**
     * Reads the given entity type from via context from its connection and returns the data
     */
    public function read(): array;

    public function readEnvironmentInformation(): array;
}
