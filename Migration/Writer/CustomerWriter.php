<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Migration\Writer;

use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

class CustomerWriter extends AbstractWriter
{
    public function supports(): string
    {
        return DefaultEntities::CUSTOMER;
    }
}
