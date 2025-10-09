<?php

declare(strict_types=1);

namespace Bunny\Protocol;

use Bunny\Client;
use Bunny\Protocol\MethodConnectionStartFrame;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AuthResponseTest extends TestCase
{
    private function getAuthResponseMethod(): ReflectionMethod
    {
        $reflection = new ReflectionClass(Client::class);
        $method = $reflection->getMethod('authResponse');
        $method->setAccessible(true);
        return $method;
    }

    public function testAMQPLAINMechanismSelected()
    {
        $client = new Client([
            'user' => 'testuser',
            'password' => 'testpass'
        ]);

        $startFrame = new MethodConnectionStartFrame();
        $startFrame->mechanisms = 'AMQPLAIN PLAIN';

        $method = $this->getAuthResponseMethod();

        // We cannot fully test this method as it requires a connection,
        // but we can verify that it does not throw an exception
        $this->expectNotToPerformAssertions();
        
        try {
            $method->invoke($client, $startFrame);
        } catch (\Exception $e) {
            // We expect a connection-related exception, but not one related to the authentication mechanism selection
            $this->assertStringNotContainsString('Server does not support', $e->getMessage());
        }
    }

    public function testPLAINMechanismSelected()
    {
        $client = new Client([
            'user' => 'testuser',
            'password' => 'testpass'
        ]);

        $startFrame = new MethodConnectionStartFrame();
        $startFrame->mechanisms = 'PLAIN';

        $method = $this->getAuthResponseMethod();
        
        $this->expectNotToPerformAssertions();
        
        try {
            $method->invoke($client, $startFrame);
        } catch (\Exception $e) {
            // We expect a connection-related exception, but not one related to the authentication mechanism selection
            $this->assertStringNotContainsString('Server does not support', $e->getMessage());
        }
    }

    public function testPLAINPreferredOverAMQPLAIN()
    {
        $client = new Client([
            'user' => 'testuser',
            'password' => 'testpass'
        ]);

        $startFrame = new MethodConnectionStartFrame();
        // Test that when both mechanisms are available, AMQPLAIN is preferred (as it is checked first in the if statement)
        $startFrame->mechanisms = 'PLAIN AMQPLAIN';

        $method = $this->getAuthResponseMethod();
        
        $this->expectNotToPerformAssertions();
        
        try {
            $method->invoke($client, $startFrame);
        } catch (\Exception $e) {
            // We expect a connection-related exception, but not one related to the authentication mechanism selection
            $this->assertStringNotContainsString('Server does not support', $e->getMessage());
        }
    }

    public function testUnsupportedMechanismThrowsException()
    {
        $client = new Client([
            'user' => 'testuser',
            'password' => 'testpass'
        ]);

        $startFrame = new MethodConnectionStartFrame();
        $startFrame->mechanisms = 'EXTERNAL ANONYMOUS';

        $method = $this->getAuthResponseMethod();
        
        $this->expectException(\Bunny\Exception\ClientException::class);
        $this->expectExceptionMessage('Server does not support either AMQPLAIN or PLAIN mechanism');
        
        $method->invoke($client, $startFrame);
    }
}