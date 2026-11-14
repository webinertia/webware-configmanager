<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;
use XMLReader;

use function array_key_exists;
use function array_replace;
use function dirname;
use function in_array;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const E_WARNING;
use const LIBXML_XINCLUDE;

/**
 * XML config reader.
 */
class Xml implements ReaderInterface
{
    /**
     * XML Reader instance.
     *
     * @var XMLReader
     */
    protected $reader;

    /**
     * Directory of the file to process.
     *
     * @var string
     */
    protected $directory;

    /**
     * Nodes to handle as plain text.
     *
     * @var array
     */
    protected $textNodes = [
        XMLReader::TEXT,
        XMLReader::CDATA,
        XMLReader::WHITESPACE,
        XMLReader::SIGNIFICANT_WHITESPACE,
    ];

    /**
     * fromFile(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromFile()
     *
     * @param  string $filename
     * @return array
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function fromFile($filename)
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            throw new Exception\RuntimeException(sprintf(
                "File '%s' doesn't exist or not readable",
                $filename,
            ));
        }
        $this->reader = new XMLReader();
        $this->reader->open($filename, null, LIBXML_XINCLUDE);

        $this->directory = dirname($filename);

        set_error_handler(
            static function ($error, $message = '') use ($filename) {
                throw new Exception\RuntimeException(
                    sprintf('Error reading XML file "%s": %s', $filename, $message),
                    $error,
                );
            },
            E_WARNING,
        );

        try {
            $return = $this->process();
        } finally {
            restore_error_handler();
            $this->reader->close();
        }

        return $return;
    }

    /**
     * fromString(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromString()
     *
     * @param  string $string
     * @return array|bool
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function fromString($string)
    {
        if ('' === $string) {
            return [];
        }
        $this->reader = new XMLReader();

        $this->reader->XML($string, null, LIBXML_XINCLUDE);

        $this->directory = null;

        set_error_handler(
            static function ($error, $message = '') {
                throw new Exception\RuntimeException(
                    sprintf('Error reading XML string: %s', $message),
                    $error,
                );
            },
            E_WARNING,
        );

        try {
            $return = $this->process();
        } finally {
            restore_error_handler();
            $this->reader->close();
        }

        return $return;
    }

    /**
     * Get all attributes on the current node.
     *
     * @return array
     */
    protected function getAttributes()
    {
        $attributes = [];

        if ($this->reader->hasAttributes) {
            while ($this->reader->moveToNextAttribute()) {
                $attributes[$this->reader->localName] = $this->reader->value;
            }

            $this->reader->moveToElement();
        }

        return $attributes;
    }

    /**
     * Process data from the created XMLReader.
     *
     * @return array
     */
    protected function process()
    {
        return $this->processNextElement();
    }

    /**
     * Process the next inner element.
     *
     * @return mixed
     */
    protected function processNextElement()
    {
        $children = [];
        $text     = '';

        while ($this->reader->read()) {
            if (XMLReader::ELEMENT === $this->reader->nodeType) {
                if (0 === $this->reader->depth) {
                    return $this->processNextElement();
                }

                $attributes = $this->getAttributes();
                $name       = $this->reader->name;

                $child = $this->reader->isEmptyElement ? [] : $this->processNextElement();

                if ($attributes) {
                    if (is_string($child)) {
                        $child = ['_' => $child];
                    }

                    if (! is_array($child)) {
                        $child = [];
                    }

                    $child = array_replace($child, $attributes);
                }

                if (! array_key_exists($name, $children)) {
                    $children[$name] = $child;
                    continue;
                }

                if (! is_array($children[$name]) || ! array_key_exists(0, $children[$name])) {
                    $children[$name] = [$children[$name]];
                }

                $children[$name][] = $child;
                continue;
            }

            if (XMLReader::END_ELEMENT === $this->reader->nodeType) {
                break;
            }

            if (in_array($this->reader->nodeType, $this->textNodes, strict: true)) {
                $text .= $this->reader->value;
            }
        }

        return [] === $children ? $text : $children;
    }
}
