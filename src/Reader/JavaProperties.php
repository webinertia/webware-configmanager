<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function array_replace_recursive;
use function dirname;
use function explode;
use function file_get_contents;
use function gettype;
use function is_file;
use function is_object;
use function is_readable;
use function is_string;
use function sprintf;
use function str_starts_with;
use function stripslashes;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * Java-style properties config reader.
 * @api
 */
final class JavaProperties implements ReaderInterface
{
    public const string DELIMITER_DEFAULT = ':';

    /**
     * Directory of the Java-style properties file
     *
     * @var string
     */
    private ?string $directory = null;

    /**
     * Delimiter for key/value pairs.
     *
     * @var string
     */
    private string $delimiter;

    /**
     * Whether or not to trim whitespace from discovered keys and values.
     */
    private Whitespace $whitespace;

    /**
     * @param mixed $delimiter Delimiter to use for key/value pairs; defaults
     *     to self::DELIMITER_DEFAULT (':')
     * @throws Exception\InvalidArgumentException For invalid $delimiter values.
     */
    public function __construct(
        mixed $delimiter = self::DELIMITER_DEFAULT,
        Whitespace $whitespace = Whitespace::Keep,
    ) {
        if (! is_string($delimiter) || '' === $delimiter) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid delimiter of type "%s"; must be a non-empty string',
                is_object($delimiter) ? $delimiter::class : gettype($delimiter),
            ));
        }

        $this->delimiter  = $delimiter;
        $this->whitespace = $whitespace;
    }

    /**
     * fromFile(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromFile()
     *
     * @throws Exception\RuntimeException If the file cannot be read.
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fromFile(string $filename): array
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            throw new Exception\RuntimeException(sprintf(
                "File '%s' doesn't exist or not readable",
                $filename,
            ));
        }

        $this->directory = dirname($filename);

        $config = $this->parse(file_get_contents($filename));

        return $this->process($config);
    }

    /**
     * fromString(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromString()
     *
     * @throws Exception\RuntimeException If an @include key is found.
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fromString(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        $this->directory = null;

        $config = $this->parse($string);

        return $this->process($config);
    }

    /**
     * Parse Java-style properties string
     *
     * @todo Support use of the equals sign "key=value" as key-value delimiter
     * @todo Ignore whitespace that precedes text past the first line of multiline values
     * @return array<array-key, mixed>
     */
    private function parse(string $string): array
    {
        $delimiter          = $this->delimiter;
        $delimLength        = strlen($delimiter);
        $result             = [];
        $lines              = explode("\n", $string);
        $key                = '';
        $value              = '';
        $isWaitingOtherLine = false;
        foreach ($lines as $i => $line) {
            // Ignore empty lines and commented lines
            if (
                '' === $line
                || (
                    ! $isWaitingOtherLine
                    && str_starts_with($line, '#')
                )
                || (
                    ! $isWaitingOtherLine
                    && str_starts_with($line, '!')
                )
            ) {
                continue;
            }

            // Add a new key-value pair or append value to a previous pair
            if (! $isWaitingOtherLine) {
                $key = substr(
                    string: $line,
                    offset: 0,
                    length: strpos($line, $delimiter),
                );
                $value = substr($line, strpos($line, $delimiter) + $delimLength, strlen($line));
            }

            if ($isWaitingOtherLine) {
                $value .= $line;
            }

            // Check if ends with single '\' (indicating another line is expected)
            $isWaitingOtherLine = false;

            if (
                strrpos(
                    haystack: $value,
                    needle  : "\\",
                ) === (strlen($value) - strlen("\\"))
            ) {
                $value = substr(
                    string: $value,
                    offset: 0,
                    length: -1,
                );
                $isWaitingOtherLine = true;
            }
            $key   = Whitespace::Trim === $this->whitespace ? trim($key) : $key;
            $value = Whitespace::Trim === $this->whitespace && ! $isWaitingOtherLine
                ? trim($value)
                : $value;

            $result[$key] = stripslashes($value);
            unset($lines[$i]);
        }

        return $result;
    }

    /**
     * Process the array for @include
     *
     * @throws Exception\RuntimeException If an @include key is found.
     * @return array<array-key, mixed>
     * @param array<array-key, mixed> $data
     */
    private function process(array $data): array
    {
        foreach ($data as $key => $value) {
            if (trim($key) !== '@include') {
                continue;
            }

            if (null === $this->directory) {
                throw new Exception\RuntimeException('Cannot process @include statement for a string');
            }
            $reader = clone $this;
            unset($data[$key]);
            $data = array_replace_recursive($data, $reader->fromFile("{$this->directory}/{$value}"));
        }
        return $data;
    }
}
