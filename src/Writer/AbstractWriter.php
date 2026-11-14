<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use Webware\ConfigManager\Exception;

use function file_put_contents;
use function is_array;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const E_WARNING;
use const LOCK_EX;

abstract class AbstractWriter implements WriterInterface
{
    /**
     * toFile(): defined by Writer interface.
     *
     * @see    WriterInterface::toFile()
     *
     * @param  string  $filename
     * @param  mixed   $config
     * @param  bool $exclusiveLock
     * @return void
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function toFile($filename, $config, $exclusiveLock = true)
    {
        if ('' === $filename) {
            throw new Exception\InvalidArgumentException('No file name specified');
        }

        $flags = 0;
        if ($exclusiveLock) {
            $flags |= LOCK_EX;
        }

        set_error_handler(
            static function ($error, $message = '') use ($filename) {
                throw new Exception\RuntimeException(
                    sprintf('Error writing to "%s": %s', $filename, $message),
                    $error,
                );
            },
            E_WARNING,
        );

        try {
            file_put_contents($filename, $this->toString($config), $flags);
        } finally {
            restore_error_handler();
        }
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
}
