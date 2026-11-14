<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Ini;

#[CoversClass(Ini::class)]
final class IniTest extends AbstractReaderTestCase
{
    #[Test]
    public function testFromFileDontParseSections()
    {
        $reader = $this->reader;
        $reader->setProcessSections(false);

        $arrayIni = $reader->fromFile($this->getTestAssetPath('sections'));

        static::assertSame('staging', $arrayIni['env']);
        static::assertSame('foo', $arrayIni['production_key']);
        static::assertSame('bar', $arrayIni['staging_key']);
    }

    #[Test]
    public function testFromFileIgnoresNestingInSectionNamesWhenSectionsNotProcessed()
    {
        $reader = $this->reader;
        $reader->setProcessSections(false);

        $arrayIni = $reader->fromFile($this->getTestAssetPath('nested-sections'));

        static::assertArrayNotHasKey('environments.production', $arrayIni);
        static::assertArrayNotHasKey('environments.staging', $arrayIni);
        static::assertArrayNotHasKey('environments', $arrayIni);
        static::assertArrayNotHasKey('production', $arrayIni);
        static::assertArrayNotHasKey('staging', $arrayIni);
        static::assertSame('staging', $arrayIni['env']);
        static::assertSame('foo', $arrayIni['production_key']);
        static::assertSame('bar', $arrayIni['staging_key']);
    }

    #[Test]
    public function testFromFileParseSections()
    {
        $arrayIni = $this->reader->fromFile($this->getTestAssetPath('sections'));

        static::assertSame('production', $arrayIni['production']['env']);
        static::assertSame('foo', $arrayIni['production']['production_key']);
        static::assertSame('staging', $arrayIni['staging : production']['env']);
        static::assertSame('bar', $arrayIni['staging : production']['staging_key']);
    }

    #[Test]
    public function testFromFileWithoutTypes()
    {
        $arrayIni = $this->reader->fromFile($this->getTestAssetPath('types'));

        static::assertSame('Bob Smith', $arrayIni['production']['name']);
        static::assertSame('55', $arrayIni['production']['age']);
        static::assertSame('55', $arrayIni['production']['age_str']);
        static::assertSame('1', $arrayIni['production']['is_married']);
        static::assertSame('', $arrayIni['production']['is_employed']);
        static::assertSame('', $arrayIni['production']['employer']);
    }

    #[Test]
    public function testFromFileWithTypes()
    {
        $reader = $this->reader;
        $reader->setTypedMode(true);
        $arrayIni = $reader->fromFile($this->getTestAssetPath('types'));

        static::assertSame('Bob Smith', $arrayIni['production']['name']);
        static::assertSame(55, $arrayIni['production']['age']);
        static::assertSame('55', $arrayIni['production']['age_str']);
        static::assertTrue($arrayIni['production']['is_married']);
        static::assertFalse($arrayIni['production']['is_employed']);
        static::assertNull($arrayIni['production']['employer']);
    }

    #[Test]
    public function testFromString()
    {
        $ini = <<<ECS
            test= "foo"
            bar[]= "baz"
            bar[]= "foo"

            ECS;

        $arrayIni = $this->reader->fromString($ini);
        static::assertSame('foo', $arrayIni['test']);
        static::assertSame('baz', $arrayIni['bar'][0]);
        static::assertSame('foo', $arrayIni['bar'][1]);
    }

    #[Test]
    public function testFromStringDontParseSections()
    {
        $ini = <<<ECS
            [production]
            env='production'
            production_key='foo'

            [staging : production]
            env='staging'
            staging_key='bar'

            ECS;
        $reader = $this->reader;
        $reader->setProcessSections(false);

        $arrayIni = $reader->fromString($ini);

        static::assertSame('staging', $arrayIni['env']);
        static::assertSame('foo', $arrayIni['production_key']);
        static::assertSame('bar', $arrayIni['staging_key']);
    }

    #[Test]
    public function testFromStringIgnoresNestingInSectionNamesWhenSectionsNotProcessed()
    {
        $ini = <<<ECS
            [environments.production]
            env='production'
            production_key='foo'

            [environments.staging]
            env='staging'
            staging_key='bar'
            ECS;
        $reader = $this->reader;
        $reader->setProcessSections(false);

        $arrayIni = $reader->fromString($ini);

        static::assertArrayNotHasKey('environments.production', $arrayIni);
        static::assertArrayNotHasKey('environments.staging', $arrayIni);
        static::assertArrayNotHasKey('environments', $arrayIni);
        static::assertArrayNotHasKey('production', $arrayIni);
        static::assertArrayNotHasKey('staging', $arrayIni);
        static::assertSame('staging', $arrayIni['env']);
        static::assertSame('foo', $arrayIni['production_key']);
        static::assertSame('bar', $arrayIni['staging_key']);
    }

    #[Test]
    public function testFromStringNested()
    {
        $ini = <<<ECS
            bla.foo.bar = foobar
            bla.foobar[] = foobarArray
            bla.foo.baz[] = foobaz1
            bla.foo.baz[] = foobaz2

            ECS;

        $arrayIni = $this->reader->fromString($ini);
        static::assertSame('foobar', $arrayIni['bla']['foo']['bar']);
        static::assertSame('foobarArray', $arrayIni['bla']['foobar'][0]);
        static::assertSame('foobaz1', $arrayIni['bla']['foo']['baz'][0]);
        static::assertSame('foobaz2', $arrayIni['bla']['foo']['baz'][1]);
    }

    #[Test]
    public function testFromStringParseSections()
    {
        $ini = <<<ECS
            [production]
            env='production'
            production_key='foo'

            [staging : production]
            env='staging'
            staging_key='bar'

            ECS;
        $arrayIni = $this->reader->fromString($ini);

        static::assertSame('production', $arrayIni['production']['env']);
        static::assertSame('foo', $arrayIni['production']['production_key']);
        static::assertSame('staging', $arrayIni['staging : production']['env']);
        static::assertSame('bar', $arrayIni['staging : production']['staging_key']);
    }

    #[Test]
    public function testFromStringWithoutTypes()
    {
        $ini = <<<ECS
            [production]
            name="Bob Smith"
            age=55
            age_str="55"
            is_married=yes
            is_employed=FALSE
            employer=null
            ECS;
        $arrayIni = $this->reader->fromString($ini);

        static::assertSame('Bob Smith', $arrayIni['production']['name']);
        static::assertSame('55', $arrayIni['production']['age']);
        static::assertSame('55', $arrayIni['production']['age_str']);
        static::assertSame('1', $arrayIni['production']['is_married']);
        static::assertSame('', $arrayIni['production']['is_employed']);
        static::assertSame('', $arrayIni['production']['employer']);
    }

    #[Test]
    public function testFromStringWithSection()
    {
        $ini = <<<ECS
            [all]
            test= "foo"
            bar[]= "baz"
            bar[]= "foo"

            ECS;

        $arrayIni = $this->reader->fromString($ini);
        static::assertSame('foo', $arrayIni['all']['test']);
        static::assertSame('baz', $arrayIni['all']['bar'][0]);
        static::assertSame('foo', $arrayIni['all']['bar'][1]);
    }

    #[Test]
    public function testFromStringWithTypes()
    {
        $ini = <<<ECS
            [production]
            name="Bob Smith"
            age=55
            age_str="55"
            is_married=yes
            is_employed=FALSE
            employer=null
            ECS;
        $reader = $this->reader;
        $reader->setTypedMode(true);
        $arrayIni = $reader->fromString($ini);

        static::assertSame('Bob Smith', $arrayIni['production']['name']);
        static::assertSame(55, $arrayIni['production']['age']);
        static::assertSame('55', $arrayIni['production']['age_str']);
        static::assertTrue($arrayIni['production']['is_married']);
        static::assertFalse($arrayIni['production']['is_employed']);
        static::assertNull($arrayIni['production']['employer']);
    }

    #[Test]
    public function testInvalidIniFile()
    {
        $this->reader = new Ini();
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromFile($this->getTestAssetPath('invalid'));
    }

    #[Test]
    public function testInvalidString()
    {
        $ini = <<<ECS
            test== "foo"

            ECS;
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromString($ini);
    }

    /**
     * getTestAssetPath(): defined by AbstractReaderTestCase.
     *
     * @see    AbstractReaderTestCase::getTestAssetPath()
     *
     * @param  string $name
     * @return string
     */
    protected function getTestAssetPath($name)
    {
        return __DIR__ . '/TestAssets/Ini/' . $name . '.ini';
    }

    protected function setUp(): void
    {
        $this->reader = new Ini();
    }
}
