<?php
namespace Bunny\NG;

/**
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class TlsOptions
{

    /**
     * Path to file with PEM-encoded certificate authority data.
     *
     * @var string
     */
    public $certificateAuthorityPath;

    /**
     * Path to file with PEM-encoded client certificate AND private key.
     *
     * @var string
     */
    public $certificatePrivateKeyPath;

    /**
     * Passphrase for client private key.
     *
     * @var string
     */
    public $privateKeyPassword;

    /**
     * If true, connection will accept any certificate presented by server and hostname it declares.
     *
     * This SHOULD NOT be used in production as connections are then susceptible to man-in-the-middle attacks.
     *
     * @var bool
     */
    public $insecureSkipVerify = false;

    public static function new()
    {
        return new static();
    }

    /**
     * @param string $certificateAuthorityPath
     * @return self
     */
    public function setCertificateAuthorityPath(string $certificateAuthorityPath)
    {
        $this->certificateAuthorityPath = $certificateAuthorityPath;
        return $this;
    }

    /**
     * @param string $certificatePrivateKeyPath
     * @return self
     */
    public function setCertificatePrivateKeyPath(string $certificatePrivateKeyPath)
    {
        $this->certificatePrivateKeyPath = $certificatePrivateKeyPath;
        return $this;
    }

    /**
     * @param string $privateKeyPassword
     * @return self
     */
    public function setPrivateKeyPassword(string $privateKeyPassword)
    {
        $this->privateKeyPassword = $privateKeyPassword;
        return $this;
    }

    /**
     * @param bool $insecureSkipVerify
     * @return self
     */
    public function setInsecureSkipVerify(bool $insecureSkipVerify)
    {
        $this->insecureSkipVerify = $insecureSkipVerify;
        return $this;
    }

}
