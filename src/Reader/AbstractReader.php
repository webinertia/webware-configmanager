<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function array_replace_recursive;
use function dirname;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;
use function trim;

/**
 * The parts of a reader that do not depend on the format.
 *
 * Reading a file is the same three steps whichever format it holds: refuse a file that is
 * absent or unreadable, record the directory so a nested `@include` resolves against it,
 * and hand the contents to the format. Only the last step differs, so it is the only one a
 * subclass writes. Both entry points are final, which is what makes the guard impossible to
 * forget: a reader cannot arrive without it, and the analyzer rejects a subclass that does
 * not implement the two format hooks.
 *
 * @api
 */
abstract class AbstractReader implements ReaderInterface
{
    /**
     * Directory of the file being read, or null when reading from a string.
     *
     * A nested `@include` resolves against this, so a reader that reads a string has no
     * directory and cannot include anything.
     */
    protected ?string $directory = null;

    /**
     * Read a configuration file.
     *
     * @throws Exception\RuntimeException If the file does not exist or cannot be read.
     * @return array<array-key, mixed>
     */
    #[Override]
    final public function fromFile(string $filename): array
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            throw new Exception\RuntimeException(sprintf(
                "File '%s' doesn't exist or not readable",
                $filename,
            ));
        }

        $this->directory = dirname($filename);

        return $this->readFile($filename);
    }

    /**
     * Read a configuration document from a string.
     *
     * @throws Exception\RuntimeException If the document cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    final public function fromString(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        $this->directory = null;

        return $this->readString($string);
    }

    /**
     * Read a file whose existence has already been checked.
     *
     * @throws Exception\RuntimeException If the contents cannot be parsed.
     * @return array<array-key, mixed>
     */
    abstract protected function readFile(string $filename): array;

    /**
     * Read a document that did not come from a file.
     *
     * @throws Exception\RuntimeException If the contents cannot be parsed.
     * @return array<array-key, mixed>
     */
    abstract protected function readString(string $string): array;

    /**
     * The message used when an `@include` is found but there is no directory to resolve
     * it against, which happens when the document came from a string.
     */
    protected function includeStatementMessage(): string
    {
        return 'Cannot process @include statement for a string';
    }

    /**
     * Read the file named by an `@include` directive.
     *
     * The clone matters: reading the included file reassigns `$directory`, so reading it
     * on `$this` would leave every later include in the same document resolving against
     * the wrong directory.
     *
     * @param mixed $value The directive's value, used as the path below the directory.
     * @throws Exception\RuntimeException If there is no directory to resolve against.
     * @return array<array-key, mixed>
     */
    protected function readInclude(mixed $value): array
    {
        $directory = $this->directory ?? throw new Exception\RuntimeException(
            $this->includeStatementMessage(),
        );

        return (clone $this)->fromFile("{$directory}/{$value}");
    }

    /**
     * Resolve every `@include` directive in a decoded document, recursively.
     *
     * A directive is a key named `@include` whose value names a sibling file. Its content
     * is merged under the keys already read, so a later file wins over an earlier one,
     * matching the merge order the rest of the package uses.
     *
     * @param array<array-key, mixed> $data
     * @throws Exception\RuntimeException If an include cannot be read.
     * @return array<array-key, mixed>
     */
    protected function withIncludes(array $data): array
    {
        /** @var mixed $value */
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->withIncludes($value);
            }

            if (is_string($key) && '@include' === trim($key)) {
                unset($data[$key]);
                $data = array_replace_recursive($data, $this->readInclude($value));
            }
        }

        return $data;
    }
}
