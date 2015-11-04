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
- [php-amqplib](https://github.com/videlalvaro/php-amqplib) - pure-PHP AMQP protocol implementation
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

TODO, see `benchmarks/` for basic use.

## Contributing

* Large part of the PHP code (almost everything in `Bunny\Protocol` namespace) is generated from spec in file
  [`spec/amqp-rabbitmq-0.9.1.json`](spec/amqp-rabbitmq-0.9.1.json). Look for `DO NOT EDIT!` in doc comments.

  To change geneted files change [`spec/generate.php`](spec/generate.php) and run:

  ```sh
  $ php ./spec/generate.php
  ```

## License

BunnyPHP is licensed under MIT license. See `LICENSE` file.
