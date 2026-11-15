<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function array_key_exists;
use function array_merge_recursive;
use function array_replace_recursive;
use function array_shift;
use function explode;
use function is_array;
use function is_string;
use function parse_ini_file;
use function parse_ini_string;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;

use const E_WARNING;
use const INI_SCANNER_NORMAL;
use const INI_SCANNER_TYPED;

/**
 * INI config reader.
 * @api
 */
final class Ini extends AbstractReader
{
    /**
     * Separator for nesting levels of configuration data identifiers.
     */
    public private(set) string $nestSeparator = '.';

    /**
     * Flag which determines whether sections are processed or not.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public private(set) bool $processSections = true;

    /**
     * Flag which determines whether boolean, null, and integer values should be
     * returned as their proper types.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public private(set) bool $typedMode = false;

    /**
     * Get the scanner-mode constant value to be used with the built-in parse_ini_file function.
     * Either INI_SCANNER_NORMAL or INI_SCANNER_TYPED depending on $typedMode.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function getScannerMode(): int
    {
        return $this->typedMode ? INI_SCANNER_TYPED : INI_SCANNER_NORMAL;
    }

    /**
     * Set nest separator.
     *
     */
    public function setNestSeparator(string $separator): self
    {
        $this->nestSeparator = $separator;

        return $this;
    }

    /**
     * Marks whether sections should be processed.
     * When sections are not processed,section names are stripped and section
     * values are merged
     *
     * @see https://www.php.net/parse_ini_file
     *
     * @return $this
     */
    public function setProcessSections(bool $processSections): self
    {
        $this->processSections = $processSections;

        return $this;
    }

    /**
     * Set whether boolean, null, and integer values should be returned as their proper types.
     * When set to false, all values will be returned as strings.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function setTypedMode(bool $typedMode): self
    {
        $this->typedMode = $typedMode;

        return $this;
    }

    /**
     * Read a file whose existence has already been checked.
     *
     * @throws Exception\RuntimeException If the file cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readFile(string $filename): array
    {
        set_error_handler(
            /**
             * @throws Exception\RuntimeException If the file cannot be parsed.
             */
            static function ($error, $message = '') use ($filename) {
                throw new Exception\RuntimeException(
                    sprintf('Error reading INI file "%s": %s', $filename, $message),
                    $error,
                );
            },
            E_WARNING,
        );

        try {
            $ini = parse_ini_file($filename, $this->processSections, $this->getScannerMode());
        } finally {
            restore_error_handler();
        }

        return $this->process($ini);
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
        set_error_handler(
            /**
             * @throws Exception\RuntimeException If the document cannot be parsed.
             */
            static function ($error, $message = '') {
                throw new Exception\RuntimeException(
                    sprintf('Error reading INI string: %s', $message),
                    $error,
                );
            },
            E_WARNING,
        );

        try {
            $ini = parse_ini_string($string, $this->processSections, $this->getScannerMode());
        } finally {
            restore_error_handler();
        }

        return $this->process($ini);
    }

    /**
     * Process a nested section
     *
     * @return array<array-key, mixed>
     * @param array<array-key, mixed> $sections
     */
    private function buildNestedSection(array $sections, mixed $value): array
    {
        if (! $sections) {
            return $this->processSection($value);
        }

        $nestedSection = [];

        $first                 = array_shift($sections);
        $nestedSection[$first] = $this->buildNestedSection($sections, $value);

        return $nestedSection;
    }

    /**
     * Process data from the parsed ini file.
     *
     * @return array<array-key, mixed>
     * @param array<array-key, mixed> $data
     */
    private function process(array $data): array
    {
        $config = [];

        foreach ($data as $section => $value) {
            if (! is_array($value)) {
                $this->processKey($section, $value, $config);
                continue;
            }

            if (is_string($section) && str_contains($section, $this->nestSeparator)) {
                $sections = explode($this->nestSeparator, $section);
                $config   = array_merge_recursive($config, $this->buildNestedSection($sections, $value));
                continue;
            }

            $config[$section] = $this->processSection($value);
        }

        return $config;
    }

    /**
     * Process a key.
     *
     * @throws Exception\RuntimeException
     * @param array<array-key, mixed> $config
     */
    private function processKey(string|int $key, mixed $value, array &$config): void
    {
        if (! is_string($key) || ! str_contains($key, $this->nestSeparator)) {
            if ('@include' !== $key) {
                $config[$key] = $value;

                return;
            }

            if (null === $this->directory) {
                throw new Exception\RuntimeException('Cannot process @include statement for a string config');
            }

            $reader  = clone $this;
            $include = $reader->fromFile("{$this->directory}/{$value}");
            $config  = array_replace_recursive($config, $include);

            return;
        }

        $pieces = explode(
            separator: $this->nestSeparator,
            string   : $key,
            limit    : 2,
        );

        if ('' === $pieces[0] || '' === $pieces[1]) {
            throw new Exception\RuntimeException(sprintf('Invalid key "%s"', $key));
        }

        $existing = $config[$pieces[0]] ?? null;

        if (array_key_exists($pieces[0], $config) && ! is_array($existing)) {
            throw new Exception\RuntimeException(
                sprintf('Cannot create sub-key for "%s", as key already exists', $pieces[0]),
            );
        }

        if (! array_key_exists($pieces[0], $config)) {
            if ('0' === $pieces[0] && [] !== $config) {
                $config = [$pieces[0] => $config];
            }

            $config[$pieces[0]] ??= [];
        }

        $this->processKey($pieces[1], $value, $config[$pieces[0]]);
    }

    /**
     * Process a section.
     *
     * @return array<array-key, mixed>
     * @param array<array-key, mixed> $section
     */
    private function processSection(array $section): array
    {
        $config = [];

        foreach ($section as $key => $value) {
            $this->processKey($key, $value, $config);
        }

        return $config;
    }
}
