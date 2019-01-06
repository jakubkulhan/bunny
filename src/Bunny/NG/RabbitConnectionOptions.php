<?php
namespace Bunny\NG;

use Bunny\NG\Sasl\SaslMechanismInterface;

/**
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
final class RabbitConnectionOptions
{

    /**
     * Hostname or IP address of the server.
     *
     * @var string
     */
    public $host;

    /**
     * Server port.
     *
     * @var int
     */
    public $port;

    /**
     * Available SASL mechanisms.
     *
     * @var SaslMechanismInterface[]
     */
    public $mechanisms;

    /**
     * Server virtual host.
     *
     * @var string
     */
    public $virtualHost;

    /**
     * Heartbeat timeout defines max time the connection will be kept idle. Every `heartbeat` seconds the client will send
     * heartbeat frame to the server. If client doesn't hear from the server in `heartbeat * 2` seconds, the connection
     * is assumed to be dead.
     *
     * @var float
     */
    public $heartbeat;

    /**
     * Timeout to establish the connection (connection parameters tuning, SASL exchange, opening virtual host).
     *
     * @var float
     */
    public $connectTimeout;

    /**
     * Parameters for TLS-secured connections.
     *
     * @var TlsOptions|null
     */
    public $tls;

    public static function new()
    {
        return new static();
    }

    public static function fromUrl(string $url)
    {
        throw new \LogicException("TODO");
    }

    /**
     * @param string $host
     * @return self
     */
    public function setHost(string $host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @param int $port
     * @return self
     */
    public function setPort(int $port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @param SaslMechanismInterface[] $mechanisms
     * @return self
     */
    public function setMechanisms(array $mechanisms)
    {
        $this->mechanisms = $mechanisms;
        return $this;
    }

    /**
     * @param SaslMechanismInterface $mechanism
     * @return self
     */
    public function addMechanism(SaslMechanismInterface $mechanism)
    {
        if ($this->mechanisms === null) {
            $this->mechanisms = [];
        }

        $this->mechanisms[] = $mechanism;

        return $this;
    }

    /**
     * @param string $virtualHost
     * @return self
     */
    public function setVirtualHost(string $virtualHost)
    {
        $this->virtualHost = $virtualHost;
        return $this;
    }

    /**
     * @param float $heartbeat
     * @return self
     */
    public function setHeartbeat(float $heartbeat)
    {
        $this->heartbeat = $heartbeat;
        return $this;
    }

    /**
     * @param float $connectTimeout
     * @return self
     */
    public function setConnectTimeout(float $connectTimeout)
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    /**
     * @param TlsOptions|null $tls
     * @return self
     */
    public function setTls(?TlsOptions $tls)
    {
        $this->tls = $tls;
        return $this;
    }

}
