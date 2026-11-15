<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use Webware\ConfigManager\Exception;

use function is_array;

abstract class AbstractWriter implements WriterInterface
{
    /**
     * toFile(): defined by Writer interface.
     *
     * The file is replaced atomically, so a reader sees either the previous
     * document or the new one and never a partial write.
     *
     * @see    WriterInterface::toFile()
     *
     * @param  string  $filename
     * @param  mixed   $config
     * @param  bool $exclusiveLock Ignored; retained for interface compatibility. An
     *                             advisory lock does not protect readers, because
     *                             they do not take one, so it is replaced by the
     *                             atomic rename.
     * @return void
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function toFile($filename, $config, $exclusiveLock = true)
    {
        FileWriter::write($filename, $this->toContent($config, $filename));
    }

    /**
     * toString(): defined by Writer interface.
     *
     * @see    WriterInterface::toString()
     *
     * @param  mixed   $config
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function toString($config)
    {
        if ($config instanceof Traversable) {
            $config = ArrayUtils::iteratorToArray($config);
        }

        if (! is_array($config)) {
            throw new Exception\InvalidArgumentException(__METHOD__ . ' expects an array or Traversable config');
        }

        return $this->processConfig($config);
    }

    /**
     * @return string
     */
    abstract protected function processConfig(array $config);

    /**
     * Serialize the configuration for a specific destination.
     *
     * Writers that need to rewrite the document in terms of where it will be
     * written override this instead of overriding toFile(). The destination is
     * validated before the configuration is serialized, so an unusable file name
     * is reported as such rather than as a problem with the configuration.
     *
     * @throws Exception\InvalidArgumentException
     */
    protected function toContent(mixed $config, string $filename): string
    {
        if ('' === $filename) {
            throw new Exception\InvalidArgumentException('No file name specified');
        }

        return $this->toString($config);
    }
}
