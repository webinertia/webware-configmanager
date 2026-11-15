<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Override;

use function addslashes;
use function class_exists;
use function dirname;
use function interface_exists;
use function is_array;
use function is_bool;
use function is_int;
use function is_object;
use function is_string;
use function max;
use function preg_match;
use function str_pad;
use function str_repeat;
use function str_replace;
use function strlen;
use function trait_exists;
use function var_export;

use const STR_PAD_RIGHT;

/**
 * @api
 */
final class PhpArray extends AbstractWriter
{
    public const string INDENT_STRING = '    ';

    /**
     * Readable outside the class, writable only through the setters.
     */
    public private(set) bool $useBracketArraySyntax = true;

    public private(set) bool $useClassNameScalars = true;

    /**
     * Sets whether or not to use the PHP 5.4+ "[]" array syntax.
     */
    public function setUseBracketArraySyntax(bool $value): self
    {
        $this->useBracketArraySyntax = $value;

        return $this;
    }

    /**
     * Sets whether or not to render resolvable FQN strings as scalars, using the
     * PHP 5.5+ class keyword.
     */
    public function setUseClassNameScalars(bool $value): self
    {
        $this->useClassNameScalars = $value;

        return $this;
    }

    /**
     * processConfig(): defined by AbstractWriter.
     *
     * @param array<array-key, mixed> $config
     */
    #[Override]
    protected function processConfig(array $config): string
    {
        $arraySyntax = [
            'open'  => $this->useBracketArraySyntax ? '[' : 'array(',
            'close' => $this->useBracketArraySyntax ? ']' : ')',
        ];

        // A blank line after the opening tag is what the formatter produces, so the
        // generated file is already formatted when it is written.
        return "<?php\n\nreturn {$arraySyntax['open']}\n{$this->processIndented(
            $config,
            $arraySyntax,
        )}{$arraySyntax['close']};\n";
    }

    /**
     * Rewrite the serialized script so that paths beneath the destination
     * directory are expressed relative to __DIR__.
     *
     * This is a content transformation, not a write, so it happens before the
     * shared atomic write in AbstractWriter::toFile().
     */
    #[Override]
    protected function toContent(mixed $config, string $filename): string
    {
        $content = parent::toContent($config, $filename);

        // for Windows, paths are escaped.
        $dirname = str_replace(
            search : '\\',
            replace: '\\\\',
            subject: dirname($filename),
        );

        return str_replace(
            search : "'{$dirname}",
            replace: "__DIR__ . '",
            subject: $content,
        );
    }

    /**
     * Check whether a string represents a resolvable FQCN
     */
    private function checkStringIsFqn(string $string): bool
    {
        if (! preg_match('/^(?:\x5c[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$/', $string)) {
            return false;
        }

        return class_exists($string) || interface_exists($string) || trait_exists($string);
    }

    /**
     * Attempts to convert a FQN string to class name scalar.
     * Returns false if string is not a valid FQN or can not be resolved to an existing name.
     */
    private function fqnStringToClassNameScalar(string $string): string|false
    {
        if (strlen($string) < 1) {
            return false;
        }

        if ('\\' !== $string[0]) {
            $string = "\\{$string}";
        }

        if ($this->checkStringIsFqn($string)) {
            return "{$string}::class";
        }

        return false;
    }

    /**
     * Recursively processes a PHP config array structure into a readable format.
     *
     * Keys are padded to the widest key in the same array so the assignment
     * operators line up, which is what the project coding standard requires.
     *
     * @param array<array-key, mixed> $config
     * @param array{open: string, close: string} $arraySyntax
     */
    private function processIndented(array $config, array $arraySyntax, int $indentLevel = 1): string
    {
        /** @var list<array{key: string, value: mixed}> $entries */
        $entries  = [];
        $keyWidth = 0;

        foreach ($config as $key => $value) {
            $renderedKey = is_int($key) ? (string) $key : $this->processStringKey($key);
            $keyWidth    = max($keyWidth, strlen($renderedKey));
            $entries[]   = ['key' => $renderedKey, 'value' => $value];
        }

        $indent = str_repeat(self::INDENT_STRING, $indentLevel);
        $block  = '';

        foreach ($entries as $entry) {
            $block .=
                $indent
                . str_pad($entry['key'], $keyWidth, pad_type: STR_PAD_RIGHT)
                . ' => '
                . $this->renderValue($entry['value'], $arraySyntax, $indentLevel)
                . ",\n";
        }

        return $block;
    }

    /**
     * Process a string configuration key
     */
    private function processStringKey(string $key): string
    {
        if ($this->useClassNameScalars && false !== ($fqnKey = $this->fqnStringToClassNameScalar($key))) {
            return $fqnKey;
        }

        return "'" . addslashes($key) . "'";
    }

    /**
     * Process a string configuration value
     */
    private function processStringValue(string $value): string
    {
        if ($this->useClassNameScalars && false !== ($fqnValue = $this->fqnStringToClassNameScalar($value))) {
            return $fqnValue;
        }

        return var_export($value, return: true);
    }

    /**
     * Render a single value, recursing into arrays.
     *
     * @param array{open: string, close: string} $arraySyntax
     */
    private function renderValue(mixed $value, array $arraySyntax, int $indentLevel): string
    {
        if (is_array($value)) {
            if ([] === $value) {
                return "{$arraySyntax['open']}{$arraySyntax['close']}";
            }

            return (
                $arraySyntax['open']
                    . "\n"
                    . $this->processIndented($value, $arraySyntax, $indentLevel + 1)
                    . str_repeat(self::INDENT_STRING, $indentLevel)
                    . $arraySyntax['close']
            );
        }

        if (is_object($value)) {
            return var_export($value, return: true);
        }

        if (is_string($value)) {
            return $this->processStringValue($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return 'null';
        }

        // Anything left over was interpolated by the original implementation.
        return "{$value}";
    }
}
