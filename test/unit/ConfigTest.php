<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

use function count;
use function is_string;
use function ob_end_clean;
use function ob_get_contents;
use function ob_start;
use function print_r;
use function range;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private array $all;
    private array $numericData;
    private array $menuData1;
    private array $toCombineA;
    private array $toCombineB;
    private array $leadingdot;
    private array $invalidkey;

    #[Test]
    public function array()
    {
        $config = new Config($this->all);

        ob_start();
        print_r($config->toArray());
        $contents = ob_get_contents();
        ob_end_clean();

        static::assertStringContainsString('Array', $contents);
        static::assertStringContainsString('[hostname] => all', $contents);
        static::assertStringContainsString('[user] => username', $contents);
    }

    #[Test]
    public function arrayAccess()
    {
        $config = new Config($this->all, true);

        static::assertSame('thisname', $config['name']);
        $config['name'] = 'anothername';
        static::assertSame('anothername', $config['name']);
        static::assertSame('multi', $config['one']['two']['three']);

        static::assertTrue(isset($config['hostname']));
        static::assertTrue(isset($config['db']['name']));

        unset($config['hostname']);
        unset($config['db']['name']);

        static::assertFalse(isset($config['hostname']));
        static::assertFalse(isset($config['db']['name']));
    }

    #[Test]
    public function arrayAccessModification()
    {
        $config = new Config($this->numericData, true);

        // Define some values we'll be using
        $poem = [
            'poem' => [
                'line 1' => 'Roses are red, bacon is also red,',
                'line 2' => 'Poems are hard,',
                'line 3' => 'Bacon.',
            ],
        ];

        $bacon = 'Bacon';

        // Add a value
        $config[] = $bacon;

        // Check if bacon now has a key that equals to 2
        static::assertEquals($bacon, $config[2]);

        // Now let's try setting an array with no key supplied
        $config[] = $poem;

        // This should now be set with key 3
        static::assertEquals($poem, $config[3]->toArray());
    }

    #[Test]
    public function countAfterMerge()
    {
        $data = new Config($this->toCombineB, true);
        $data->merge(
            new Config($this->toCombineA),
        );
        static::assertEquals(count($data->toArray()), $data->count());
    }

    #[Test]
    public function countingReportsTheNumberOfTopLevelNodes()
    {
        $data = new Config($this->menuData1);
        static::assertCount(3, $data->button);
    }

    #[Test]
    public function countWithDoubleKeys()
    {
        $config = new Config([], true);

        $config->foo = 1;
        $config->foo = 2;
        static::assertSame(2, $config->foo);
        static::assertCount(1, $config->toArray());
        static::assertCount(1, $config);
    }

    #[Test]
    public function errorWriteToReadOnly()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');
        $config       = new Config($this->all);
        $config->test = '32';
    }

    #[Test]
    public function extendedConfigHasSubnodesTheSameType()
    {
        $config = new TestAssets\ExtendedConfig([
            'node' => [
                'key' => 'value',
            ],
        ]);

        static::assertInstanceOf(TestAssets\ExtendedConfig::class, $config->node);
    }

    #[Test]
    public function isset()
    {
        $config = new Config($this->all, false);

        static::assertFalse(isset($config->notarealkey));
        static::assertTrue(isset($config->hostname)); // top level
        static::assertTrue(isset($config->db->name)); // one level down
    }

    #[Test]
    public function issetTreatsANullValueAsNotSetButKeepsOtherFalsyValuesSet()
    {
        // PHP's isset() semantics, which ArrayAccess::offsetExists() must mirror:
        // only null is "not set". get() is deliberately different and still returns
        // the null, because it is keyed on array_key_exists().
        // The contract members are called directly so this stays clear of the
        // no-isset lint rule; testIsset() above covers the isset() dispatch itself.
        $config = new Config([
            'nothing'     => null,
            'empty'       => '',
            'zero'        => 0,
            'false'       => false,
            'empty-array' => [],
        ]);

        static::assertFalse($config->__isset(name: 'nothing'));
        static::assertFalse($config->offsetExists(offset: 'nothing'));
        static::assertNull($config->get(
            name   : 'nothing',
            default: 'default-not-used',
        ));

        static::assertTrue($config->__isset(name: 'empty'));
        static::assertTrue($config->offsetExists(offset: 'empty'));
        static::assertTrue($config->__isset(name: 'zero'));
        static::assertTrue($config->offsetExists(offset: 'zero'));
        static::assertTrue($config->__isset(name: 'false'));
        static::assertTrue($config->offsetExists(offset: 'false'));
        static::assertTrue($config->__isset(name: 'empty-array'));
        static::assertTrue($config->offsetExists(offset: 'empty-array'));
    }

    #[Test]
    public function iterator()
    {
        // top level
        $config = new Config($this->all);
        $var    = '';
        foreach ($config as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $var .= "\nkey = {$key}, value = {$value}";
        }
        static::assertStringContainsString('key = name, value = thisname', $var);

        // 1 nest
        $var = '';
        foreach ($config->db as $key => $value) {
            $var .= "\nkey = {$key}, value = {$value}";
        }
        static::assertStringContainsString('key = host, value = 127.0.0.1', $var);

        // 2 nests
        $config = new Config($this->menuData1);
        $var    = '';
        foreach ($config->button->b1 as $key => $value) {
            $var .= "\nkey = {$key}, value = {$value}";
        }
        static::assertStringContainsString('key = L1, value = button1-1', $var);
    }

    #[Test]
    public function iteratorPrimitivesAdvanceThePointer()
    {
        $config = new Config(['first' => 1, 'second' => 2]);

        // current() must not arm the skip flag, and next() must advance the pointer.
        // Without this, a foreach over a Config never terminates, which is reported
        // as a hang rather than a failure.
        $config->current();
        $config->next();

        static::assertSame('second', $config->key());
    }

    #[Test]
    public function laminas1019EmptyKeys()
    {
        $config = new Config($this->invalidkey);
        $array  = $config->toArray();
        static::assertStringContainsString('test', $array[' ']);
        static::assertStringContainsString('test', $array['']);
    }

    #[Test]
    public function laminas1019HandlingInvalidKeyNames()
    {
        $config = new Config($this->leadingdot);
        $array  = $config->toArray();
        static::assertStringContainsString('dot-test', $array['.test']);
    }

    #[Test]
    public function laminas1417DefaultValues()
    {
        $config = new Config($this->all);
        $value  = $config->get(
            name   : 'notthere',
            default: 'default',
        );
        static::assertSame('default', $value);
        static::assertNull($config->notThere);
    }

    /**
     * @link https://getlaminas.org/issues/browse/Laminas-186
     *
     */
    #[Test]
    public function laminas186MergeReplacingUnnamedConfigSettings()
    {
        $arrayA = [
            'flag'      => true,
            'text'      => 'foo',
            'list'      => ['a', 'b', 'c'],
            'aSpecific' => 12,
        ];

        $arrayB = [
            'flag'      => false,
            'text'      => 'bar',
            'list'      => ['d', 'e'],
            'bSpecific' => 100,
        ];

        $mergeResult = [
            'flag'      => false,
            'text'      => 'bar',
            'list'      => ['a', 'b', 'c', 'd', 'e'],
            'aSpecific' => 12,
            'bSpecific' => 100,
        ];

        $configA = new Config($arrayA, true);
        $configB = new Config($arrayB);

        $configA->merge($configB); // merge B onto A
        static::assertEquals($mergeResult, $configA->toArray());
    }

    #[Test]
    public function laminas402()
    {
        $configArray = [
            'data1'  => 'someValue',
            'data2'  => 'someValue',
            'false1' => false,
            'data3'  => 'someValue',
        ];
        $config = new Config($configArray);
        static::assertEquals(count($configArray), count($config));
        foreach ($config as $key => $value) {
            static::assertEquals($configArray[$key], $value);
        }
    }

    #[Test]
    public function laminas408CountNotDecreasingOnUnset()
    {
        $configData = [
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
        ];
        $config = new Config($configData, true);
        static::assertCount(3, $config);
        unset($config->b);
        static::assertCount(2, $config);
    }

    #[Test]
    public function laminas4107EnsureCloneDoesNotKeepNestedReferences()
    {
        $parent    = new Config(['key' => ['nested' => 'parent']], true);
        $newConfig = clone $parent;
        $newConfig->merge(new Config(['key' => ['nested' => 'override']], true));

        static::assertSame(
            expected: 'override',
            actual  : $newConfig->key->nested,
            message : '$newConfig is not overridden',
        );
        static::assertSame(
            expected: 'parent',
            actual  : $parent->key->nested,
            message : '$parent has been overridden',
        );
    }

    #[Test]
    public function laminas43()
    {
        $configArray = [
            'controls' => [
                'visible' => [
                    'name'    => 'visible',
                    'type'    => 'checkbox',
                    'attribs' => [], // empty array
                ],
            ],
        ];
        $formConfig = new Config($configArray, true);
        static::assertSame([], $formConfig->controls->visible->attribs->toArray());
    }

    #[Test]
    public function laminas6995ToArrayDoesNotDisturbInternalIterator()
    {
        $config = new Config(range(
            start: 1,
            end  : 10,
        ));
        $config->rewind();
        static::assertSame(1, $config->current());

        $config->toArray();
        static::assertSame(1, $config->current());
    }

    #[Test]
    public function loadSingleSection()
    {
        $config = new Config($this->all, false);

        static::assertSame('all', $config->hostname);
        static::assertSame('live', $config->db->name);
        static::assertSame('multi', $config->one->two->three);
        static::assertNull($config->nonexistent); // property doesn't exist
    }

    #[Test]
    public function merge()
    {
        $configA = new Config($this->toCombineA, true);
        $configB = new Config($this->toCombineB);
        $configA->merge($configB);

        // config->
        static::assertSame(3, $configA->foo);
        static::assertSame(2, $configA->bar);
        static::assertSame('bar', $configA->text);

        // config->numerical-> ...
        static::assertInstanceOf(Config::class, $configA->numerical);
        static::assertSame('first', $configA->numerical->{0});
        static::assertSame('second', $configA->numerical->{1});

        // config->numerical->{2}-> ...
        static::assertInstanceOf(Config::class, $configA->numerical->{2});
        static::assertSame('third', $configA->numerical->{2}->{0});
        static::assertNull($configA->numerical->{2}->{1});

        // config->numerical->  ...
        static::assertSame('fourth', $configA->numerical->{3});
        static::assertSame('fifth', $configA->numerical->{4});

        // config->numerical->{5}
        static::assertInstanceOf(Config::class, $configA->numerical->{5});
        static::assertSame('sixth', $configA->numerical->{5}->{0});
        static::assertNull($configA->numerical->{5}->{1});

        // config->misaligned
        static::assertInstanceOf(Config::class, $configA->misaligned);
        static::assertSame('foo', $configA->misaligned->{2});
        static::assertSame('bar', $configA->misaligned->{3});
        static::assertSame('baz', $configA->misaligned->{4});
        static::assertNull($configA->misaligned->{0});

        // config->mixed
        static::assertInstanceOf(Config::class, $configA->mixed);
        static::assertSame('bar', $configA->mixed->foo);
        static::assertFalse($configA->mixed->{0});
        static::assertNull($configA->mixed->{1});

        // config->replaceAssoc
        static::assertNull($configA->replaceAssoc);

        // config->replaceNumerical
        static::assertTrue($configA->replaceNumerical);
    }

    #[Test]
    public function mergeHonoursAllowModificationsFlagAtAllLevels()
    {
        $config  = new Config(['key' => ['nested' => 'yes'], 'key2' => 'yes'], false);
        $config2 = new Config([], true);

        $config2->merge($config);

        $config2->key2 = 'no';

        static::assertSame('no', $config2->key2);

        $config2->key->nested = 'no';

        static::assertSame('no', $config2->key->nested);
    }

    #[Test]
    public function mergeIsRefusedAfterSetReadOnly(): void
    {
        $config = new Config(['a' => 'a'], true);
        $config->merge(new Config(['b' => 'b'], true));
        $config->setReadOnly();

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');

        $config->merge(new Config(['c' => 'c'], true));
    }

    #[Test]
    public function mergeIsRefusedOnAReadOnlyConfig(): void
    {
        // Merging changes the data, so it is refused for the same reason assigning is.
        // Configuration loaded for reading, such as a cached config, takes this path.
        $config = new Config(['a' => 'a']);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');

        $config->merge(new Config(['b' => 'b'], true));
    }

    #[Test]
    public function modification()
    {
        $config = new Config($this->all, true);

        // overwrite an existing key
        static::assertSame('thisname', $config->name);
        $config->name = 'anothername';
        static::assertSame('anothername', $config->name);

        // overwrite an existing multi-level key
        static::assertSame('multi', $config->one->two->three);
        $config->one->two->three = 'anothername';
        static::assertSame('anothername', $config->one->two->three);

        // create a new multi-level key
        $config->does = ['not' => ['exist' => 'yet']];
        static::assertSame('yet', $config->does->not->exist);
    }

    #[Test]
    public function noModifications()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');
        $config           = new Config($this->all);
        $config->hostname = 'test';
    }

    #[Test]
    public function noNestedModifications()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');
        $config           = new Config($this->all);
        $config->db->host = 'test';
    }

    #[Test]
    public function numericKeys()
    {
        $data = new Config($this->numericData);
        static::assertSame('test', $data->{1});
        static::assertSame(34, $data->{0});
    }

    /**
     * ensure that modification is not allowed after calling setReadOnly()
     */
    #[Test]
    public function setReadOnly()
    {
        $configData = [
            'a' => 'a',
        ];
        $config    = new Config($configData, true);
        $config->b = 'b';

        $config->setReadOnly();
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');
        $config->c = 'c';
    }

    #[Test]
    public function setReadOnlyAppliesToChildren()
    {
        $config = new Config($this->all, true);

        $config->setReadOnly();
        static::assertTrue($config->isReadOnly());
        static::assertTrue(
            condition: $config->one->isReadOnly(),
            message  : 'First level children are writable',
        );
        static::assertTrue(
            condition: $config->one->two->isReadOnly(),
            message  : 'Second level children are writable',
        );
    }

    #[Test]
    public function theSourceOfAMergeMayBeReadOnly(): void
    {
        // Only the receiver is modified, so a read-only source is usable.
        $config = new Config(['a' => 'a'], true);
        $config->merge(new Config(['b' => 'b']));

        static::assertSame(['a' => 'a', 'b' => 'b'], $config->toArray());
    }

    /**
     * Ensures that toArray() supports objects of types other than Webware_ConfigManager
     *
     * @return void
     */
    #[Test]
    public function toArraySupportsObjects()
    {
        $configData = [
            'a' => new stdClass(),
            'b' => [
                'c' => new stdClass(),
                'd' => new stdClass(),
            ],
        ];
        $config = new Config($configData);
        static::assertEquals($config->toArray(), $configData);
        static::assertInstanceOf('stdClass', $config->a);
        static::assertInstanceOf('stdClass', $config->b->c);
        static::assertInstanceOf('stdClass', $config->b->d);
    }

    #[Test]
    public function unset()
    {
        // allow modifications is on
        $config = new Config($this->all, true);

        static::assertTrue(isset($config->hostname));
        static::assertTrue(isset($config->db->name));

        unset($config->hostname);
        unset($config->db->name);

        static::assertFalse(isset($config->hostname));
        static::assertFalse(isset($config->db->name));
    }

    #[Test]
    public function unsetException()
    {
        // allow modifications is off - expect an exception
        $config = new Config($this->all, false);

        static::assertTrue(isset($config->hostname)); // top level

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('is read only');
        unset($config->hostname);
    }

    #[Test]
    public function unsettingAMiddleElementDuringForeachDoesNotSkipAnElement()
    {
        $config = new Config([
            'first'  => [1],
            'second' => [2],
            'third'  => [3],
        ], true);

        $keyList = [];
        foreach ($config as $key => $value) {
            $keyList[] = $key;
            if ('second' === $key) {
                unset($config->$key); // uses magic Webware\ConfigManager\Config::__unset() method
            }
        }

        static::assertSame('first', $keyList[0]);
        static::assertSame('second', $keyList[1]);
        static::assertSame('third', $keyList[2]);
    }

    #[Test]
    public function unsettingFirstElementDuringForeachDoesNotSkipAnElement()
    {
        $config = new Config([
            'first'  => [1],
            'second' => [2],
            'third'  => [3],
        ], true);

        $keyList = [];
        foreach ($config as $key => $value) {
            $keyList[] = $key;
            if ('first' === $key) {
                unset($config->$key); // uses magic Webware\ConfigManager\Config::__unset() method
            }
        }

        static::assertSame('first', $keyList[0]);
        static::assertSame('second', $keyList[1]);
        static::assertSame('third', $keyList[2]);
    }

    #[Test]
    public function unsettingLastElementDuringForeachDoesNotSkipAnElement()
    {
        $config = new Config([
            'first'  => [1],
            'second' => [2],
            'third'  => [3],
        ], true);

        $keyList = [];
        foreach ($config as $key => $value) {
            $keyList[] = $key;
            if ('third' === $key) {
                unset($config->$key); // uses magic Webware\ConfigManager\Config::__unset() method
            }
        }

        static::assertSame('first', $keyList[0]);
        static::assertSame('second', $keyList[1]);
        static::assertSame('third', $keyList[2]);
    }

    protected function setUp(): void
    {
        // Arrays representing common config configurations
        $this->all = [
            'hostname' => 'all',
            'name'     => 'thisname',
            'db'       => [
                'host' => '127.0.0.1',
                'user' => 'username',
                'pass' => 'password',
                'name' => 'live',
            ],
            'one'      => [
                'two' => [
                    'three' => 'multi',
                ],
            ],
        ];

        $this->numericData = [
            0 => 34,
            1 => 'test',
        ];

        $this->menuData1 = [
            'button' => [
                'b0' => [
                    'L1' => 'button0-1',
                    'L2' => 'button0-2',
                    'L3' => 'button0-3',
                ],
                'b1' => [
                    'L1' => 'button1-1',
                    'L2' => 'button1-2',
                ],
                'b2' => [
                    'L1' => 'button2-1',
                ],
            ],
        ];

        $this->toCombineA = [
            'foo'              => 1,
            'bar'              => 2,
            'text'             => 'foo',
            'numerical'        => [
                'first',
                'second',
                [
                    'third',
                ],
            ],
            'misaligned'       => [
                2 => 'foo',
                3 => 'bar',
            ],
            'mixed'            => [
                'foo' => 'bar',
            ],
            'replaceAssoc'     => [
                'foo' => 'bar',
            ],
            'replaceNumerical' => [
                'foo',
            ],
        ];

        $this->toCombineB = [
            'foo'              => 3,
            'text'             => 'bar',
            'numerical'        => [
                'fourth',
                'fifth',
                [
                    'sixth',
                ],
            ],
            'misaligned'       => [
                3 => 'baz',
            ],
            'mixed'            => [
                false,
            ],
            'replaceAssoc'     => null,
            'replaceNumerical' => true,
        ];

        $this->leadingdot = ['.test' => 'dot-test'];
        $this->invalidkey = [' ' => 'test', '' => 'test2'];
    }
}
