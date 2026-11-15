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
use function preg_match;
use function str_repeat;
use function str_replace;
use function strlen;
use function trait_exists;
use function var_export;

class PhpArray extends AbstractWriter
{
    /**
     * @var string
     */
    public const INDENT_STRING = '    ';

    /** @var bool */
    protected $useBracketArraySyntax = false;

    /** @var bool */
    protected $useClassNameScalars = false;

    /**
     * @return boolean
     */
    public function getUseClassNameScalars()
    {
        return $this->useClassNameScalars;
    }

    /**
     * processConfig(): defined by AbstractWriter.
     *
     * @return string
     */
    #[Override]
    public function processConfig(array $config)
    {
        $arraySyntax = [
            'open'  => $this->useBracketArraySyntax ? '[' : 'array(',
            'close' => $this->useBracketArraySyntax ? ']' : ')',
        ];

        return "<?php\nreturn {$arraySyntax['open']}\n{$this->processIndented(
            $config,
            $arraySyntax,
        )}{$arraySyntax['close']};\n";
    }

    /**
     * Sets whether or not to use the PHP 5.4+ "[]" array syntax.
     *
     * @param  bool $value
     * @return self
     */
    public function setUseBracketArraySyntax($value)
    {
        $this->useBracketArraySyntax = $value;
        return $this;
    }

    /**
     * Sets whether or not to render resolvable FQN strings as scalars, using PHP 5.5+ class-keyword
     *
     * @param boolean $value
     * @return self
     */
    public function setUseClassNameScalars($value)
    {
        $this->useClassNameScalars = $value;
        return $this;
    }

    /**
     * Check whether a string represents a resolvable FQCN
     *
     * @param string $string
     * @return bool
     */
    protected function checkStringIsFqn($string)
    {
        if (! preg_match('/^(?:\x5c[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$/', $string)) {
            return false;
        }

        return class_exists($string) || interface_exists($string) || trait_exists($string);
    }

    /**
     * Attempts to convert a FQN string to class name scalar.
     * Returns false if string is not a valid FQN or can not be resolved to an existing name.
     *
     * @param string $string
     * @return bool|string
     */
    protected function fqnStringToClassNameScalar($string)
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
     * @param  int   $indentLevel
     * @return string
     */
    protected function processIndented(array $config, array $arraySyntax, &$indentLevel = 1)
    {
        $arrayString = '';

        foreach ($config as $key => $value) {
            $arrayString .= str_repeat(self::INDENT_STRING, $indentLevel);
            $arrayString .= (is_int($key) ? $key : $this->processStringKey($key)) . ' => ';

            if (is_array($value)) {
                if ([] === $value) {
                    $arrayString .= "{$arraySyntax['open']}{$arraySyntax['close']},\n";
                    continue;
                }

                $indentLevel++;
                $arrayString .=
                    $arraySyntax['open']
                    . "\n"
                    . $this->processIndented($value, $arraySyntax, $indentLevel)
                    . str_repeat(self::INDENT_STRING, --$indentLevel)
                    . $arraySyntax['close']
                    . ",\n";
                continue;
            }

            if (is_object($value)) {
                $arrayString .= var_export($value, return: true) . ",\n";
                continue;
            }

            if (is_string($value)) {
                $arrayString .= "{$this->processStringValue($value)},\n";
                continue;
            }

            if (is_bool($value)) {
                $arrayString .= ($value ? 'true' : 'false') . ",\n";
                continue;
            }

            if (null === $value) {
                $arrayString .= "null,\n";
                continue;
            }

            $arrayString .= "{$value},\n";
        }

        return $arrayString;
    }

    /**
     * Process a string configuration key
     *
     * @param string $key
     * @return string
     */
    protected function processStringKey($key)
    {
        if ($this->useClassNameScalars && false !== ($fqnKey = $this->fqnStringToClassNameScalar($key))) {
            return $fqnKey;
        }

        return "'" . addslashes($key) . "'";
    }

    /**
     * Process a string configuration value
     *
     * @param string $value
     * @return string
     */
    protected function processStringValue($value)
    {
        if ($this->useClassNameScalars && false !== ($fqnValue = $this->fqnStringToClassNameScalar($value))) {
            return $fqnValue;
        }

        return var_export($value, return: true);
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
}
