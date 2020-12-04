<?php

declare(strict_types=1);

namespace Bunny\Test\Library;

abstract class AbstractClientHelper
{
    /**
     * @param string $uri
     *
     * @return array
     */
    protected function parseAmqpUri($uri): array
    {
        $uriComponents = parse_url($uri);

        if (
            !isset($uriComponents['scheme'])
            || !in_array($uriComponents['scheme'], ['amqp', 'amqps'])
        ) {
            throw new \RuntimeException(
                sprintf(
                    'URI scheme must be "amqp" or "amqps". URI given: "%s"',
                    $uri
                )
            );
        }

        $options = [];

        if (isset($uriComponents['host'])) {
            $options['host'] = $uriComponents['host'];
        }

        if (isset($uriComponents['port'])) {
            $options['port'] = $uriComponents['port'];
        }

        if (isset($uriComponents['user'])) {
            $options['user'] = $uriComponents['user'];
        }

        if (isset($uriComponents['pass'])) {
            $options['password'] = $uriComponents['pass'];
        }

        if (isset($uriComponents['path'])) {
            $vhostCandidate = $uriComponents['path'];

            if (strpos($vhostCandidate, '/') === 0) {
                $vhostCandidate = substr($vhostCandidate, 1);
            }

            if (strpos($vhostCandidate, '/') !== false) {
                throw new \RuntimeException(
                    sprintf(
                        'An URI path component that is a valid vhost may not contain unescaped "/" characters. URI given: "%s"',
                        $uri
                    )
                );
            }

            $vhostCandidate = rawurldecode($vhostCandidate);

            $options['vhost'] = $vhostCandidate;
        }

        return $options;
    }
}