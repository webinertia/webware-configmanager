<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Override;
use Psr\Container\ContainerInterface;

use function array_keys;
use function array_values;
use function class_exists;
use function in_array;
use function sprintf;
use function strtolower;

class StandaloneReaderPluginManager implements ContainerInterface
{
    /** @var array<string, class-string> */
    private $knownPlugins = [
        'ini'            => Reader\Ini::class,
        'json'           => Reader\Json::class,
        'xml'            => Reader\Xml::class,
        'yaml'           => Reader\Yaml::class,
        'javaproperties' => Reader\JavaProperties::class,
    ];

    /**
     * @param string $plugin
     * @return Reader\ReaderInterface
     * @throws Exception\PluginNotFoundException
     */
    #[Override]
    public function get($plugin)
    {
        if (! $this->has($plugin)) {
            throw new Exception\PluginNotFoundException(sprintf(
                'Config reader plugin by name %s not found',
                $plugin,
            ));
        }

        if (! class_exists($plugin)) {
            $plugin = $this->knownPlugins[strtolower($plugin)];
        }

        return new $plugin();
    }

    /**
     * @param string $plugin
     * @return bool
     */
    #[Override]
    public function has($plugin)
    {
        if (in_array($plugin, array_values($this->knownPlugins), strict: true)) {
            return true;
        }

        return in_array(strtolower($plugin), array_keys($this->knownPlugins), strict: true);
    }
}
