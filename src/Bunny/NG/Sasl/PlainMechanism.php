<?php
namespace Bunny\NG\Sasl;

use function sprintf;

/**
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class PlainMechanism implements SaslMechanismInterface
{

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function mechanism(): string
    {
        return "PLAIN";
    }

    public function respondTo(?string $challenge): string
    {
        return sprintf("\x00%s\x00%s", $this->username, $this->password);
    }

}
