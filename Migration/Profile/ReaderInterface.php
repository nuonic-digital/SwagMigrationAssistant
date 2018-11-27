<?php declare(strict_types=1);

namespace SwagMigrationNext\Migration\Profile;

interface ReaderInterface
{
    /**
     * Reads data from source via the given gateway based on implementation
     */
    public function read(): array;
}
