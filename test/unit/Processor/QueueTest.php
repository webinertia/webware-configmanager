<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Processor\Queue;
use WebwareTest\ConfigManager\Processor\TestAssets\IdentityProcessor;

use function iterator_to_array;

#[CoversClass(Queue::class)]
class QueueTest extends TestCase
{
    #[Test]
    public function testContainsAndHasPriority(): void
    {
        $processor = new IdentityProcessor();
        $other     = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert($processor, 7);

        static::assertTrue($queue->contains($processor));
        static::assertFalse($queue->contains($other));
        static::assertTrue($queue->hasPriority(7));
        static::assertFalse($queue->hasPriority(8));
    }

    #[Test]
    public function testExtractKeepsOneEntryWhenTheSameProcessorWasInsertedTwice(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();

        // High priority first, so the scan meets the lower priority duplicate afterwards.
        $queue->insert($processor, 10);
        $queue->insert($processor, 1);

        static::assertSame($processor, $queue->extract());
        static::assertCount(1, $queue);
        static::assertTrue($queue->contains($processor));
    }

    #[Test]
    public function testExtractRemovesTheHighestPriorityProcessor(): void
    {
        $high  = new IdentityProcessor();
        $low   = new IdentityProcessor();
        $queue = new Queue();
        $queue->insert($low, 1);
        $queue->insert($high, 10);

        static::assertSame($high, $queue->extract());
        static::assertCount(1, $queue);
        static::assertFalse($queue->contains($high));
        static::assertSame($low, $queue->extract());
        static::assertCount(0, $queue);
    }

    #[Test]
    public function testInsertReturnsTheQueueAndCountsTheProcessors(): void
    {
        $queue = new Queue();

        static::assertTrue($queue->isEmpty());
        static::assertCount(0, $queue);
        static::assertSame($queue, $queue->insert(new IdentityProcessor()));
        static::assertCount(1, $queue);
        static::assertFalse($queue->isEmpty());
    }

    #[Test]
    public function testIterationRunsTheHighestPriorityProcessorFirst(): void
    {
        $low     = new IdentityProcessor();
        $high    = new IdentityProcessor();
        $highest = new IdentityProcessor();
        $queue   = new Queue();
        $queue->insert($low, 1);
        $queue->insert($highest, 100);
        $queue->insert($high, 50);

        static::assertSame([$highest, $high, $low], iterator_to_array($queue, preserve_keys: false));
    }

    #[Test]
    public function testRemoveLeavesTheRemainingProcessorsOrderable(): void
    {
        $first  = new IdentityProcessor();
        $second = new IdentityProcessor();
        $third  = new IdentityProcessor();
        $queue  = new Queue();
        $queue->insert($first, 1);
        $queue->insert($second, 30);
        $queue->insert($third, 20);

        static::assertTrue($queue->remove($second));

        static::assertSame([$third, $first], iterator_to_array($queue, preserve_keys: false));
        static::assertCount(2, $queue);
    }

    #[Test]
    public function testRemoveReportsWhetherTheProcessorWasPresent(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert($processor);

        static::assertTrue($queue->remove($processor));
        static::assertFalse($queue->contains($processor));
        static::assertCount(0, $queue);
        static::assertFalse($queue->remove($processor));
    }

    #[Test]
    public function testTheQueueCanBeIteratedRepeatedly(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert($processor, 5);

        static::assertSame([$processor], iterator_to_array($queue, preserve_keys: false));
        static::assertSame([$processor], iterator_to_array($queue, preserve_keys: false));
        static::assertCount(1, $queue);
    }

    #[Test]
    public function testToArrayReturnsBothDataAndPriority(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert($processor, 7);

        static::assertSame(
            [['data' => $processor, 'priority' => 7]],
            $queue->toArray(Queue::EXTR_BOTH),
        );
    }

    #[Test]
    public function testToArrayReturnsDataInInsertionOrder(): void
    {
        $first  = new IdentityProcessor();
        $second = new IdentityProcessor();
        $queue  = new Queue();
        $queue->insert($first, 100);
        $queue->insert($second, 1);

        static::assertSame([$first, $second], $queue->toArray());
    }

    #[Test]
    public function testToArrayReturnsPriorities(): void
    {
        $queue = new Queue();
        $queue->insert(new IdentityProcessor(), 7);
        $queue->insert(new IdentityProcessor(), 3);

        static::assertSame([7, 3], $queue->toArray(Queue::EXTR_PRIORITY));
    }

    #[Test]
    public function testTopPeeksWithoutRemoving(): void
    {
        $high  = new IdentityProcessor();
        $low   = new IdentityProcessor();
        $queue = new Queue();
        $queue->insert($low, 1);
        $queue->insert($high, 10);

        static::assertSame($high, $queue->top());
        static::assertSame($high, $queue->top());
        static::assertCount(2, $queue);
    }
}
