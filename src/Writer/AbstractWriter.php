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
        // Resolved before serialization: a writer that builds paths out of the
        // destination must name the directory the file really lives in, which is the
        // link target rather than the link.
        $resolved = FileWriter::resolve($filename);

        FileWriter::write($resolved, $this->toContent($config, $resolved));
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
     * already validated and resolved by the time this is called, so an override can
     * rely on it naming the directory the file really lives in.
     *
     * @param string $_filename Unused here; it exists so an override can see the
     *                          destination.
     * @throws Exception\InvalidArgumentException
     */
    protected function toContent(mixed $config, string $_filename): string
    {
        return $this->toString($config);
    }
}
