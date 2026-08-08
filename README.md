# BunnyPHP

[![Continuous Integration](https://github.com/jakubkulhan/bunny/actions/workflows/ci.yml/badge.svg)](https://github.com/jakubkulhan/bunny/actions/workflows/ci.yml)
[![Downloads this Month](https://img.shields.io/packagist/dm/bunny/bunny.svg)](https://packagist.org/packages/bunny/bunny)
[![Latest stable](https://img.shields.io/packagist/v/bunny/bunny.svg)](https://packagist.org/packages/bunny/bunny)


> Performant pure-PHP AMQP (RabbitMQ) non-blocking ReactPHP library

## Requirements

BunnyPHP requires PHP 8.1 and newer.

## Installation

Add as [Composer](https://getcomposer.org/) dependency:

```sh
$ composer require bunny/bunny:@^0.6dev
```

## Comparison

You might ask if there isn't a library/extension to connect to AMQP broker (e.g. RabbitMQ) already. Yes, there are
 multiple options:

- [ext-amqp](http://pecl.php.net/package/amqp) - PHP extension
- [php-amqplib](https://github.com/php-amqplib/php-amqplib) - pure-PHP AMQP protocol implementation

Why should you want to choose BunnyPHP instead?

* You want **nice idiomatic PHP API** to work with (I'm looking at you, php-amqplib). BunnyPHP interface follows PHP's common
  **coding standards** and **naming conventions**. See tutorial.

* You **can't (don't want to) install PECL extension** that has latest stable version in 2014. BunnyPHP isn't as such marked
  as stable yet. But it is already being used in production.

* You have **both classic CLI/FPM and [ReactPHP](http://reactphp.org/)** applications and need to connect to RabbitMQ.
  BunnyPHP comes with an **asynchronous** client with a **synchronous** API using [`Fibers`](https://reactphp.org/async/).

Apart from that BunnyPHP is more performant than main competing library, php-amqplib. See [`benchmark/` directory](https://github.com/jakubkulhan/bunny/tree/master/benchmark)
and [php-amqplib's `benchmark/`](https://github.com/videlalvaro/php-amqplib/tree/master/benchmark). (For `ext-amp` https://gist.github.com/WyriHaximus/65fd98e099820aded1b79e9111e02916 is used.)

Benchmarks were run as:

```sh
$ php benchmark/producer.php N & php benchmark/consumer.php
```

| Library     | N (# messages) | Produce sec | Produce msg/sec | Consume sec | Consume msg/sec |
|-------------|---------------:|------------:|----------------:|------------:|----------------:|
| php-amqplib | 100            | 0.000671    | 148998          | 0.001714    | 58343           |
| ext-amqp    | 100            | 0.000302    | 331042          | 0.008915    | 11217           |
| bunnyphp    | 100            | 0.000194    | 515271          | 0.000939    | 106508          |
| bunnyphp +/-|                |             | +345.8%/+155.6% |             | +182.5%/+949.5% |
| php-amqplib | 1000           | 0.004827    | 207167          | 0.015166    | 65937           |
| ext-amqp    | 1000           | 0.002399    | 416846          | 0.078373    | 12760           |
| bunnyphp    | 1000           | 0.001597    | 626202          | 0.011139    | 89773           |
| bunnyphp +/-|                |             | +302.2%/+150.2% |             | +136.1%/+703.5% |
| php-amqplib | 10000          | 0.060204    | 166102          | 0.147772    | 67672           |
| ext-amqp    | 10000          | 0.022735    | 439853          | 0.754800    | 13249           |
| bunnyphp    | 10000          | 0.016441    | 608232          | 0.106685    | 93734           |
| bunnyphp +/-|                |             | +366.1%/+138.2% |             | +138.5%/+707.4% |
| php-amqplib | 100000         | 1.158033    | 90276           | 1.477762    | 67670           |
| ext-amqp    | 100000         | 0.952319    | 105007          | 7.494665    | 13343           |
| bunnyphp    | 100000         | 0.812430    | 123088          | 1.073454    | 93157           |
| bunnyphp +/-|                |             | +136.3%/+117.2% |             | +137.6%/+698.1% |
| php-amqplib | 1000000        | 18.64132    | 53644           | 18.992902   | 52651           |
| ext-amqp    | 1000000        | 12.86827    | 77710           | 89.432139   | 11182           |
| bunnyphp    | 1000000        | 11.63421    | 85953           | 11.947426   | 83700           |
| bunnyphp +/-|                |             | +160.2%/+110.6% |             | +158.9%/+748.5% |

## Quick Start

### Producing

```php
use Bunny\Client;
use Bunny\Configuration;
use React\EventLoop\Loop;

use function React\Async\async;

$configuration = new Configuration(
    host:     'HOSTNAME',
    vhost:    'VHOST',    // The default vhost is '/'
    user:     'USERNAME', // The default user is 'guest'
    password: 'PASSWORD', // The default password is 'guest'
);

$bunny = new Client($configuration);
Loop::futureTick(async(static function (): void {
  $bunny->channel()->publish(
    body:       $message,     // The message you're publishing as a string
    routingKey: 'queue_name', // Routing key, in this example the queue's name
  );
  $bunny->disconnect();
}));

// Unlike the consumer example, we're not setting signal handlers before this a very quick operation
```

### Consuming

```php
use Bunny\Client;
use Bunny\Configuration;
use React\EventLoop\Loop;

use function React\Async\async;

$configuration = new Configuration(
    host:     'HOSTNAME',
    vhost:    'VHOST',    // The default vhost is '/'
    user:     'USERNAME', // The default user is 'guest'
    password: 'PASSWORD', // The default password is 'guest'
);

$consumerCleanUp = static function () {};
$bunny = new Client($configuration);
Loop::futureTick(async(static function () use (&$consumerCleanUp): void {
    $channel = $bunny->channel();
    $response = $channel->consume(
        async(static function (Message $message, Channel $channel, Client $bunny) {
            $success = handleMessage($message); // Handle your message here
    
            if ($success) {
                $channel->ack($message); // Acknowledge message
                return;
            }
    
            $channel->nack($message); // Mark message fail, message will be redelivered
        }),
        'queue_name',
    );
    $consumerCleanUp = static fn () => $channel->cancel($response->consumerTag);
}));

// Unlike the producer example, we do need signal handlers because this process can run for weeks and we want to shut down cleanly
$signals = [SIGINT, SIGTERM, SIGHUP];
$signalHandler = async(static function () use ($signals, &$signalHandler, &$consumerCleanUp, $bunny): void {
  foreach ($signals as $signal) {
      Loop::removeSignal($signal, $signalHandler);
  }
  
  $consumerCleanUp();
  $bunny->disconnect();
}))
foreach ($signals as $signal) {
    Loop::addSignal($signal, $signalHandler);
}
```

## Always run moving parts in a fiber

Since v0.6 Bunny has been rebuilt using [`Fibers`](https://reactphp.org/async/). This means that every method on `Client` and `Channel` must be called inside a fiber. In the examples this will be called using a `Loop::futureTick` call. In your applications this might be some other trigger like a HTTP request. As long as somewhere in the direct call stack this call is inside a fiber you'll be good.

All the examples from this point on will include a `Full Example` on how to use that specific example using a full connection cycle inside a fiber the example. For the above that would be the following, even tho it's not doing anything:

```php
use Bunny\Client;
use Bunny\Configuration;
use React\EventLoop\Loop;

use function React\Async\async;

$configuration = new Configuration(
    host:     'HOSTNAME',
    vhost:    'VHOST',    // The default vhost is '/'
    user:     'USERNAME', // The default user is 'guest'
    password: 'PASSWORD', // The default password is 'guest'
);

$bunny = new Client($configuration);
Loop::futureTick(async(static function (): void {
  $bunny->connect();
}));
```

## Tutorial

### Connecting

When instantiating the BunnyPHP `Client` accepts an array with connection options:

```php
use Bunny\Client;
use Bunny\Configuration;

$configuration = new Configuration(
    host:     'HOSTNAME',
    vhost:    'VHOST',    // The default vhost is '/'
    user:     'USERNAME', // The default user is 'guest'
    password: 'PASSWORD', // The default password is 'guest'
);

$bunny = new Client($configuration);
$bunny->connect();
```

<details>

<summary>Using DSN</summary>

```php
use Bunny\Client;
use Bunny\Configuration;

$configuration = Configuration::fromDSN('amqp://USERNAME:PASSWORD@HOSTNAME/VHOST');

$bunny = new Client($configuration);
$bunny->connect();
```

</details>

### Connecting securely using TLS(/SSL)

Options for TLS-connections should be specified as array `tls`:  

```php
use Bunny\Client;
use Bunny\Configuration;

$configuration = new Configuration(
    host:     'HOSTNAME',
    vhost:    'VHOST',    // The default vhost is '/'
    user:     'USERNAME', // The default user is 'guest'
    password: 'PASSWORD', // The default password is 'guest'
    tls:      [
        'cafile'      => 'ca.pem',
        'local_cert'  => 'client.cert',
        'local_pk'    => 'client.key',
    ],
);

$bunny = new Client($configuration);
$bunny->connect();
```

<details>

<summary>Using DSN</summary>

```php
use Bunny\Client;
use Bunny\Configuration;

$configuration = Configuration::fromDSN(
    'amqp://USERNAME:PASSWORD@HOSTNAME/VHOST?tls[cafile]=ca.pem&tls[local_cert]=client.cert&tls[local_pk]=client.key',
);

$bunny = new Client($configuration);
$bunny->connect();
```

</details>

For options description - please see [SSL context options](https://www.php.net/manual/en/context.ssl.php).

Note: invalid TLS configuration will cause connection failure.

See also [common configuration variants](examples/tls/).

### Providing client properties

Client Connections can [present their capabilities](https://www.rabbitmq.com/connections.html#capabilities) to
a server by presenting an optional `client_properties` table when establishing a connection.

For example, a connection name may be provided by setting the
[`connection_name` property](https://www.rabbitmq.com/connections.html#client-provided-names):

```php
use Bunny\Client;
use Bunny\Configuration;

$configuration = new Configuration(
    host:             'HOSTNAME',
    vhost:            'VHOST',    // The default vhost is '/'
    user:             'USERNAME', // The default user is 'guest'
    password:         'PASSWORD', // The default password is 'guest'
    clientProperties: [
        'connection_name' => 'My connection',
    ],
);

$bunny = new Client($configuration);
$bunny->connect();
```

Obviously this can be dynamic, for example, on Kubernetes you can include the pod, the namespace, and any other environment variable in it:

```php
use Bunny\Client;
use Bunny\Configuration;

$configuration = new Configuration(
    host:             'HOSTNAME',
    vhost:            'VHOST',    // The default vhost is '/'
    user:             'USERNAME', // The default user is 'guest'
    password:         'PASSWORD', // The default password is 'guest'
    clientProperties: [
        'connection_name' => 'Pod: ' . getenv('POD_NAME') . '; Release: ' . getenv('RELEASE_TAG') . '; Namespace: ' . getenv('POD_NAMESPACE'),
    ],
);

$bunny = new Client($configuration);
$bunny->connect();
```

### Publish a message

Now that we have a connection with the server we need to create a channel and declare a queue to communicate over before we can publish a message, or subscribe to a queue for that matter. On RabbitMQ 4 and newer, named queues must be durable; transient non-exclusive queues are deprecated and rejected by default.

```php
$channel = $bunny->channel();
$channel->queueDeclare('queue_name', durable: true);
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;
  
  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function (): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $channel->queueDeclare('queue_name', durable: true);
    $channel->close(); // Not required as the Client::disconnect() method handles this for us if we don't, but added for completeness sake
    $bunny->disconnect();
  }));
  ```
</details>

#### Publishing a message on a virtual host with quorum queues as a default

From RabbitMQ 4 queues will be standard defined as Quorum queues, those are by default durable, in order to connect to them you should use the queue declare method as follows. In the current version of RabbitMQ 3.11.15 this is already supported, if the virtual host is configured to have a default type of Quorum.

```php
$channel = $bunny->channel();
$channel->queueDeclare('queue_name', false, true); // Queue name
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;
  
  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function (): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $channel->queueDeclare('queue_name', false, true); // Queue name
    $channel->close(); // Not required as the Client::disconnect() method handles this for us if we don't, but added for completeness sake
    $bunny->disconnect();
  }));
  ```
</details>

With a communication channel set up, we can now publish a message to the queue:

```php
$channel->publish(
    $message,    // The message you're publishing as a string
    [],          // Any headers you want to add to the message
    '',          // Exchange name
    'queue_name', // Routing key, in this example the queue's name
);
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;
  
  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function (): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $channel->publish(
        $message,    // The message you're publishing as a string
        [],          // Any headers you want to add to the message
        '',          // Exchange name
        'queue_name', // Routing key, in this example the queue's name
    );
    $channel->close(); // Not required as the Client::disconnect() method handles this for us if we don't, but added for completeness sake
    $bunny->disconnect();
  }));
  ```
</details>

Alternatively:

```php
$channel->publish(
    body:       $message,     // The message you're publishing as a string
    routingKey: 'queue_name', // Routing key, in this example the queue's name
);
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;
  
  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function (): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $channel->publish(
        body:       $message,     // The message you're publishing as a string
        routingKey: 'queue_name', // Routing key, in this example the queue's name
    );
    $channel->close(); // Not required as the Client::disconnect() method handles this for us if we don't, but added for completeness sake
    $bunny->disconnect();
  }));
  ```
</details>

### Subscribing to a queue

Subscribing to a queue can be done in two ways. The first way will run indefinitely:

```php
$channel->consume(
    static function (Message $message, Channel $channel, Client $bunny) {
        $success = handleMessage($message); // Handle your message here

        if ($success) {
            $channel->ack($message); // Acknowledge message
            return;
        }

        $channel->nack($message); // Mark message fail, message will be redelivered
    },
    'queue_name',
);
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;

  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $consumerCleanUp = static function () {};
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function () use (&$consumerCleanUp): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $response = $channel->consume(
        async(static function (Message $message, Channel $channel, Client $bunny) {
            $success = handleMessage($message); // Handle your message here
    
            if ($success) {
                $channel->ack($message); // Acknowledge message
                return;
            }
    
            $channel->nack($message); // Mark message fail, message will be redelivered
        }),
        'queue_name',
    );
    $consumerCleanUp = static fn () => $channel->cancel($response->consumerTag);
  }));

  $signals = [SIGINT, SIGTERM, SIGHUP];
  $signalHandler = async(static function () use ($signals, &$signalHandler, &$consumerCleanUp, $bunny): void {
    foreach ($signals as $signal) {
        Loop::removeSignal($signal, $signalHandler);
    }
    
    $consumerCleanUp();
    $bunny->disconnect();
  }))
  foreach ($signals as $signal) {
      Loop::addSignal($signal, $signalHandler);
  }
  ```
</details>

### Pop a single message from a queue

```php
$message = $channel->get('queue_name');

// Handle message

$channel->ack($message); // Acknowledge message
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;
  
  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function (): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $message = $channel->get('queue_name');

    // Handle message
    
    $channel->ack($message); // Acknowledge message
    $channel->close(); // Not required as the Client::disconnect() method handles this for us if we don't, but added for completeness sake
    $bunny->disconnect();
  }));
  ```
</details>

### Prefetch count

A way to control how many messages are prefetched by BunnyPHP when consuming a queue is by using the channel's QOS method. In the example below only 5 messages will be prefetched. Combined with acknowledging messages this turns into an effective flow control for your applications, especially asynchronous applications. No new messages will be fetched unless one has been acknowledged.

```php
$channel->qos(
    0, // Prefetch size
    5,  // Prefetch count
);
```

<details>
  <summary>Full Example</summary>

  ```php
  use Bunny\Client;
  use Bunny\Configuration;
  use React\EventLoop\Loop;
  
  use function React\Async\async;
  
  $configuration = new Configuration(
      host:     'HOSTNAME',
      vhost:    'VHOST',    // The default vhost is '/'
      user:     'USERNAME', // The default user is 'guest'
      password: 'PASSWORD', // The default password is 'guest'
  );
  
  $consumerCleanUp = static function () {};
  $bunny = new Client($configuration);
  Loop::futureTick(async(static function () use (&$consumerCleanUp): void {
    $bunny->connect(); // Not required as the Client::channel() method handles this for us if we don't, but added for completeness sake
    $channel = $bunny->channel();
    $channel->qos(
        0, // Prefetch size
        5,  // Prefetch count
    );
    $response = $channel->consume(
        static function (Message $message, Channel $channel, Client $bunny) {
            $success = handleMessage($message); // Handle your message here
    
            if ($success) {
                $channel->ack($message); // Acknowledge message
                return;
            }
    
            $channel->nack($message); // Mark message fail, message will be redelivered
        },
        'queue_name',
    );
    $consumerCleanUp = static fn () => $channel->cancel($response->consumerTag);
  }));

  $signals = [SIGINT, SIGTERM, SIGHUP];
  $signalHandler = async(static function () use ($signals, &$signalHandler, &$consumerCleanUp, $bunny): void {
    foreach ($signals as $signal) {
        Loop::removeSignal($signal, $signalHandler);
    }
    
    $consumerCleanUp();
    $bunny->disconnect();
  }))
  foreach ($signals as $signal) {
      Loop::addSignal($signal, $signalHandler);
  }
  ```
</details>

### Asynchronous usage

**Node: Up to version `v0.5.x` Bunny had two different clients, one sync, and one async. As of `v0.6` both clients have been folder into one: An async client with a sync API.**

## AMQP interop

There is [amqp interop](https://github.com/queue-interop/amqp-interop) compatible wrapper(s) for the bunny library.

## Testing

To fully test this package, TLS certificates are required and a local RabbitMQ. On top of that a Code Style fixer and Static Analysis are used in this project. To make it as simple as possible for anyone working on this project a `Makefile` is in place to take care of all of that.

```shell
$ make
```

<details>

<summary>Testing details</summary>

Create client/server TLS certificates by running:

```shell
$ cd test/tls && make all && cd -
```

You need access to a RabbitMQ instance in order to run the test suite. The easiest way is to use the provided Docker Compose setup to create an isolated environment, including a RabbitMQ container, to run the test suite in.

**Docker Compose**

- Use Docker Compose to create a network with a RabbitMQ container and a PHP container to run the tests in. The project
  directory will be mounted into the PHP container.
  
  ```shell
  $ docker-compose up -d
  ```

  To test against different TLS configurations (as in CI builds), you can set environment variable `CONFIG_NAME=rabbitmq.tls.verify_none` before running `docker-compose up`.
  
- Optionally use `docker ps` to display the running containers.  

  ```shell
  $ docker ps --filter name=bunny
  [...] bunny_rabbit_node_1_1
  [...] bunny_bunny_1
  ```

- Enter the PHP container.

  ```shell
  $ docker exec -it bunny_bunny_1 bash
  ```
  
- Within the container, run:

  ```shell
  $ vendor/bin/phpunit
  ```

</details>

## Contributing

* Large part of the PHP code (almost everything in `Bunny\Protocol` namespace) is generated from spec in file
  [`spec/amqp-rabbitmq-0.9.1.json`](spec/amqp-rabbitmq-0.9.1.json). Look for `DO NOT EDIT!` in doc comments.

  To change generated files change [`spec/generate.php`](spec/generate.php) and run:

  ```sh
  $ php ./spec/generate.php
  ```

## Broker compatibility

Works well with RabbitMQ

Does not work with ActiveMQ because it requires AMQP 1.0 which is a completely different protocol (Bunny is implementing AMQP 0.9.1)

## License

BunnyPHP is licensed under MIT license. See `LICENSE` file.
