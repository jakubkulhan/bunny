<?php
namespace Bunny\NG\Sasl;

/**
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class ExternalMechanism implements SaslMechanismInterface
{

    public function mechanism(): string
    {
        return "EXTERNAL";
    }

    public function respondTo(?string $challenge): string
    {
        return "";
    }

}
