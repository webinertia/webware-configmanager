# webware-configmanager

Nested object property based access to configuration data, with pluggable readers, writers and
processors.

`webware/webware-configmanager` is the fork of `laminas/laminas-config` and a drop-in replacement for
`laminas/laminas-config-aggregator`, so one package supplies both the aggregator and the configuration
object it produces.

This page covers the 1.0 release line.

## Installation

```bash
composer require webware/webware-configmanager
```

## The documents

| Page | What it covers |
|---|---|
| [Introduction](intro.md) | Reading a configuration file, and accessing the resulting data through nested object properties |
| [Theory of Operation](theory.md) | How stored configuration data becomes the associative array a `Config` object is built from |
| [Readers](reader.md) | The `Reader` interface and the INI, JSON, YAML and XML implementations |
| [Writers](writer.md) | The `Writer` interface and the formats it writes |
| [Processors](processor.md) | The `Processor` interface and per-value processing |
| [The Factory](factory.md) | Loading one or more configuration files into an array or a `Config`, including provider merge order |
