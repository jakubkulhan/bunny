<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

use Bunny\Client;

final class ClientHelper
{
    public function createClient(): Client
    {
        return ClientFactory::createClient();
    }
}
