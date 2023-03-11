<?php

namespace Bunny\Test\Library;

class EnvironmentException extends \RuntimeException
{
    public function __construct(string $envVariable)
    {
        $value = getenv($envVariable);

        $valueFormatted = $value===false?'false': '"' . $value . '"';

        parent::__construct(sprintf('Invalid value for env var %s: %s', $envVariable, $valueFormatted), 1);
    }
}
