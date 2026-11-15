<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Override;
use Webware\ConfigManager\Exception;
use XMLWriter;

use function is_array;
use function is_numeric;
use function str_repeat;

/**
 * @api
 */
final class Xml extends AbstractWriter
{
    /**
     * processConfig(): defined by AbstractWriter.
     *
     * @param array<array-key, mixed> $config
     */
    #[Override]
    protected function processConfig(array $config): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString(str_repeat(
            string: ' ',
            times : 4,
        ));

        $writer->startDocument(
            version : '1.0',
            encoding: 'UTF-8',
        );
        $writer->startElement('webware-configmanager');

        foreach ($config as $sectionName => $data) {
            if (! is_array($data)) {
                $writer->writeElement($sectionName, (string) $data);
                continue;
            }

            $this->addBranch($sectionName, $data, $writer);
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Add a branch to an XML object recursively.
     *
     * @throws Exception\RuntimeException
     * @param array<array-key, mixed> $config
     */
    private function addBranch(string $branchName, array $config, XMLWriter $writer): void
    {
        $branchType = null;

        foreach ($config as $key => $value) {
            $keyType = is_numeric($key) ? 'numeric' : 'string';

            if (null !== $branchType && $branchType !== $keyType) {
                throw new Exception\RuntimeException('Mixing of string and numeric keys is not allowed');
            }

            if (null === $branchType) {
                $branchType = $keyType;

                if ('string' === $keyType) {
                    $writer->startElement($branchName);
                }
            }

            if ('numeric' === $branchType) {
                if (is_array($value)) {
                    $this->addBranch($branchName, $value, $writer);
                    continue;
                }

                $writer->writeElement($branchName, (string) $value);
                continue;
            }

            if (is_array($value)) {
                $this->addBranch($key, $value, $writer);
                continue;
            }

            $writer->writeElement($key, (string) $value);
        }

        if ('string' === $branchType) {
            $writer->endElement();
        }
    }
}
