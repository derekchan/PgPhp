PgPhp - An implementation of the postgre protocol in pure PHP
=============================================================

A pure PHP PostgreSQL connector that works - sometimes you just need a quick and dirty way to connect to PostgreSQL, but for any reasons you do not have access to the native driver, and you do not care about performance.

_Currently only works in Linux / UNIX-like environment as it depends on "/usr/bin/hexdump"_

### Usage

See [examples.php](examples.php)

### Installation

```
composer require derekchan/pg-php
```

## From the original author (@BraveSirRobin)

### Development status
Development status : Toy. Works for me, and it supports the protocol COPY command too, which is nice.

### Motivation
I wrote this library as a proof of concept and to learn a bit more about different TCP protocols. The main (only?) reason for wanting to do this is to be able to write an  "Event Machine" / "Twisted" type of server where you can do asynchronous I.O. to several different kinds of application endpoint, e.g. RabbitMQ, Postgres, MySql, HTTP, etc. etc.

### Future
Currently, and for the forseeable future I'll be devoting my "open source" time to my [Amqphp project](https://github.com/BraveSirRobin/amqphp), but eventually I'd like to expand this to be a fast, fully asyncronous, PHP-implemented "business process server".
