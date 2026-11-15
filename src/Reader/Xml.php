<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;
use XMLReader;

use function array_key_exists;
use function array_replace;
use function in_array;
use function is_array;
use function is_string;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const E_WARNING;
use const LIBXML_XINCLUDE;

/**
 * XML config reader.
 * @api
 */
final class Xml extends AbstractReader
{
    /**
     * XML Reader instance.
     *
     * @var XMLReader
     */
    private XMLReader $reader;

    public function __construct()
    {
        $this->reader = new XMLReader();
    }

    /**
     * Nodes to handle as plain text.
     *
     * @var array
     */
    private array $textNodes = [
        XMLReader::TEXT,
        XMLReader::CDATA,
        XMLReader::WHITESPACE,
        XMLReader::SIGNIFICANT_WHITESPACE,
    ];

    /**
     * Read a file whose existence has already been checked.
     *
     * @throws Exception\RuntimeException If the document cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readFile(string $filename): array
    {
        $this->reader = new XMLReader();
        $this->reader->open(
            uri     : $filename,
            encoding: null,
            flags   : LIBXML_XINCLUDE,
        );

        return $this->readDocument(
            /**
             * @throws Exception\RuntimeException If the document cannot be parsed.
             */
            static function ($error, $message = '') use ($filename) {
                throw new Exception\RuntimeException(
                    sprintf('Error reading XML file "%s": %s', $filename, $message),
                    $error,
                );
            },
        );
    }

    /**
     * Read a document that did not come from a file.
     *
     * @throws Exception\RuntimeException If the document cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readString(string $string): array
    {
        $this->reader = new XMLReader();

        $this->reader->XML(
            source  : $string,
            encoding: null,
            flags   : LIBXML_XINCLUDE,
        );

        return $this->readDocument(
            /**
             * @throws Exception\RuntimeException If the document cannot be parsed.
             */
            static function ($error, $message = '') {
                throw new Exception\RuntimeException(
                    sprintf('Error reading XML string: %s', $message),
                    $error,
                );
            },
        );
    }

    /**
     * Get all attributes on the current node.
     *
     * @return array<array-key, mixed>
     */
    private function getAttributes(): array
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
     * @return array<array-key, mixed>|string
     */
    private function process(): array|string
    {
        return $this->processNextElement();
    }

    /**
     * Process the next inner element.
     *
     * @return array<array-key, mixed>|string
     */
    private function processNextElement(): array|string
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

    /**
     * Walk the open document, turning a parse warning into the package exception.
     *
     * The handler is installed around the walk only, so a warning raised while opening is
     * left alone, and it is always restored, so a throw cannot leave it installed.
     *
     * @param callable(int, string): never $onWarning
     * @return array<array-key, mixed>
     */
    private function readDocument(callable $onWarning): array
    {
        set_error_handler($onWarning, E_WARNING);

        try {
            $return = $this->process();
        } finally {
            restore_error_handler();
            $this->reader->close();
        }

        // A document whose root holds only text parsed to a bare string before typing;
        // the array contract rejects it here instead.
        // @mago-expect analysis:invalid-return-statement
        return $return;
    }
}
