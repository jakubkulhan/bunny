# BunnyPHP

[![Build Status](https://travis-ci.org/jakubkulhan/bunny.svg?branch=master)](https://travis-ci.org/jakubkulhan/bunny)
[![Downloads this Month](https://img.shields.io/packagist/dm/bunny/bunny.svg)](https://packagist.org/packages/bunny/bunny)
[![Latest stable](https://img.shields.io/packagist/v/bunny/bunny.svg)](https://packagist.org/packages/bunny/bunny)


> Performant pure-PHP AMQP (RabbitMQ) sync/async (ReactPHP) library

## Requirements

BunnyPHP requires PHP `>= 5.4.0`.

## Installation

Add as [Composer](https://getcomposer.org/) dependency:

```sh
$ composer require bunny/bunny:@dev
```

## Comparison

You might ask if there isn't a library/extension to connect to AMQP broker (e.g. RabbitMQ) already. Yes, there are
 multiple options:

- [ext-amqp](http://pecl.php.net/package/amqp) - PHP extension
- [php-amqplib](https://github.com/php-amqplib/php-amqplib) - pure-PHP AMQP protocol implementation
- [react-amqp](https://github.com/JCook21/ReactAMQP) - ext-amqp binding to ReactPHP

Why should you want to choose BunnyPHP instead?

* You want **nice idiomatic PHP API** to work with (I'm looking at you, php-amqplib). BunnyPHP interface follows PHP's common
  **coding standards** and **naming conventions**. See tutorial.

* You **can't (don't want to) install PECL extension** that has latest stable version in 2014. BunnyPHP isn't as such marked
  as stable yet. But it is already being used in production.

* You have **both classic CLI/FPM and [ReactPHP](http://reactphp.org/)** applications and need to connect to RabbitMQ.
  BunnyPHP comes with both **synchronous and asynchronous** clients with same PHP-idiomatic interface. Async client uses
  [react/promise](https://github.com/reactphp/promise).

Apart from that BunnyPHP is more performant than main competing library, php-amqplib. See [`benchmark/` directory](https://github.com/jakubkulhan/bunny/tree/master/benchmark)
and [php-amqplib's `benchmark/`](https://github.com/videlalvaro/php-amqplib/tree/master/benchmark).

Benchmarks were run as:

```sh
$ php benchmark/producer.php N & php benchmark/consumer.php
```

| Library     | N (# messages) | Produce sec | Produce msg/sec | Consume sec | Consume msg/sec |
|-------------|---------------:|------------:|----------------:|------------:|----------------:|
| php-amqplib | 100            | 0.0131      | 7633            | 0.0446      | 2242            |
| bunnyphp    | 100            | 0.0128      | 7812            | 0.0488      | 2049            |
| bunnyphp +/-|                |             | +2.3%           |             | -8.6%           |
| php-amqplib | 1000           | 0.1218      | 8210            | 0.4801      | 2082            |
| bunnyphp    | 1000           | 0.1042      | 9596            | 0.2919      | 3425            |
| bunnyphp +/-|                |             | +17%            |             | +64%            |
| php-amqplib | 10000          | 1.1075      | 9029            | 5.1824      | 1929            |
| bunnyphp    | 10000          | 0.9078      | 11015           | 2.9058      | 3441            |
| bunnyphp +/-|                |             | +22%            |             | +78%            |
| php-amqplib | 100000         | 20.7005     | 4830            | 69.0360     | 1448            |
| bunnyphp    | 100000         | 9.7891      | 10215           | 35.7305     | 2789            |
| bunnyphp +/-|                |             | +111%           |             | +92%            |

## Tutorial

### Connecting

When instantiating the BunnyPHP `Client` accepts an array with connection options:

```php
$connection = [
    'host'      => 'HOSTNAME',
    'vhost'     => 'VHOST',    // The default vhost is /
    'user'      => 'USERNAME', // The default user is guest
    'password'  => 'PASSWORD', // The default password is guest
];

$bunny = new Client($connection);
$bunny->connect();
```

### Publish a message

Now that we have a connection with the server we need to create a channel and declare a queue to communicate over before we can publish a message, or subscribe to a queue for that matter.

```php
$channel = $bunny->channel();
$channel->queueDeclare('queue_name'); // Queue name
```

With a communication channel set up, we can now publish a message to the queue:

```php
$channel->publish(
    $message,    // The message you're publishing as a string
    [],          // Any headers you want to add to the message
    '',          // Exchange name
    'queue_name' // Routing key, in this example the queue's name
);
```

### Subscribing to a queue

Subscribing to a queue can be done in two ways. The first way will run indefinitely:

```php
$channel->run(
    function (Message $message, Channel $channel, Client $bunny) {
        $success = handleMessage($message); // Handle your message here

        if ($success) {
            $channel->ack($message); // Acknowledge message
            return;
        }

        $channel->nack($message); // Mark message fail, message will be redelivered
    },
    'queue_name'
);
```

The other way lets you run the client for a specific amount of time consuming the queue before it stops:

```php
$channel->consume(
    function (Message $message, Channel $channel, Client $client){
        $channel->ack($message); // Acknowledge message
    },
    'queue_name'
);
$bunny->run(12); // Client runs for 12 seconds and then stops
```

### Pop a single message from a queue

```php
$message = $channel->get('queue_name');

// Handle message

$channel->ack($message); // Acknowledge message
```

### Prefetch count

A way to control how many messages are prefetched by BunnyPHP when consuming a queue is by using the channel's QOS method. In the example below only 5 messages will be prefetched. Combined with acknowledging messages this turns into an effective flow control for your applications, especially asynchronous applications. No new messages will be fetched unless one has been acknowledged.

```php
$channel->qos(
    0, // Prefetch size
    5  // Prefetch count
);
```

### Asynchronous usage

Bunny supports both synchronous and asynchronous usage utilizing [ReactPHP](https://github.com/reactphp). The following example shows setting up a client and consuming a queue indefinitely.

```php
(new Async\Client($connection)->connect()->then(function (Client $client) {
   return $client->channel();
})->then(function (Channel $channel) {
   return $channel->qos(0, 5)->then(function () use ($channel) {
       return $channel;
   });
})->then(function (Channel $channel) use ($event) {
   $channel->consume(
       function (Message $message, Channel $channel, Client $client) use ($event) {
           // Handle message

           $channel->ack($message);
       },
       'queue_name'
   );
});
```

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
