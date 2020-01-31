<?php
namespace Bunny\NG\Sasl;

/**
 * SASL client authentication.
 *
 * @see https://en.wikipedia.org/wiki/Simple_Authentication_and_Security_Layer
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
interface SaslMechanismInterface
{

    /**
     * Returns SASL mechanism name.
     *
     * @return string
     */
    public function mechanism(): string;

    /**
     * Respond to server SASL challenge.
     *
     * @param string|null $challenge For initial response challenge will be `null`.
     * @return string
     */
    public function respondTo(?string $challenge): string;

}
