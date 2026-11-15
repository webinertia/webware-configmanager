<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Override;
use Webware\ConfigManager\Exception;

use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function str_contains;

/**
 * @api
 */
final class Ini extends AbstractWriter
{
    /**
     * Separator for nesting levels of configuration data identifiers.
     */
    public private(set) string $nestSeparator = '.';

    /**
     * If true the INI string is rendered in the global namespace without
     * sections.
     */
    public private(set) bool $renderWithoutSections = false;

    /**
     * Set nest separator.
     */
    public function setNestSeparator(string $separator): self
    {
        $this->nestSeparator = $separator;

        return $this;
    }

    /**
     * Set if rendering should occur without sections or not.
     *
     * If set to true, the INI file is rendered without sections completely
     * into the global namespace of the INI file.
     */
    public function setRenderWithoutSectionsFlags(bool $withoutSections): self
    {
        $this->renderWithoutSections = $withoutSections;

        return $this;
    }

    /**
     * processConfig(): defined by AbstractWriter.
     * @param array<array-key, mixed> $config
     */
    #[Override]
    protected function processConfig(array $config): string
    {
        $iniString = '';

        if ($this->renderWithoutSections) {
            return $this->addBranch($config);
        }

        $config = $this->sortRootElements($config);

        foreach ($config as $sectionName => $data) {
            if (! is_array($data)) {
                $iniString .= "{$sectionName} = {$this->prepareValue($data)}\n";
                continue;
            }

            $iniString .= "[{$sectionName}]\n{$this->addBranch($data)}\n";
        }

        return $iniString;
    }

    /**
     * Add a branch to an INI string recursively.
     *
     * @param array<array-key, mixed> $config
     * @param list<string> $parents
     */
    private function addBranch(array $config, array $parents = []): string
    {
        $iniString = '';

        foreach ($config as $key => $value) {
            $group = [...$parents, (string) $key];

            if (is_array($value)) {
                $iniString .= $this->addBranch($value, $group);
                continue;
            }

            $iniString .=
                implode($this->nestSeparator, $group)
                . ' = '
                . $this->prepareValue($value)
                . "\n";
        }

        return $iniString;
    }

    /**
     * Prepare a value for INI.
     *
     * @throws Exception\RuntimeException If the value contains a double quote.
     */
    private function prepareValue(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // A non-string reaches str_contains() and raises a TypeError, as it always has.
        if (! str_contains($value, '"')) {
            return "\"{$value}\"";
        }

        throw new Exception\RuntimeException('Value can not contain double quotes');
    }

    /**
     * Root elements that are not assigned to any section needs to be on the
     * top of config.
     * @return array<array-key, mixed>
     * @param array<array-key, mixed> $config
     */
    private function sortRootElements(array $config): array
    {
        $sections = [];

        // Remove sections from config array.
        foreach ($config as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $sections[$key] = $value;
            unset($config[$key]);
        }

        // Read sections to the end.
        foreach ($sections as $key => $value) {
            $config[$key] = $value;
        }

        return $config;
    }
}
