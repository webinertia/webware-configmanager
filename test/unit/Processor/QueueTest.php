<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Processor\Queue;
use Webware\ConfigManager\Processor\QueueContents;
use WebwareTest\ConfigManager\Processor\TestAssets\IdentityProcessor;

use function iterator_to_array;

#[CoversClass(Queue::class)]
class QueueTest extends TestCase
{
    #[Test]
    public function containsAndHasPriority(): void
    {
        $processor = new IdentityProcessor();
        $other     = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert(
            data    : $processor,
            priority: 7,
        );

        static::assertTrue($queue->contains($processor));
        static::assertFalse($queue->contains($other));
        static::assertTrue($queue->hasPriority(7));
        static::assertFalse($queue->hasPriority(8));
    }

    #[Test]
    public function extractKeepsOneEntryWhenTheSameProcessorWasInsertedTwice(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();

        // High priority first, so the scan meets the lower priority duplicate afterwards.
        $queue->insert(
            data    : $processor,
            priority: 10,
        );
        $queue->insert(
            data    : $processor,
            priority: 1,
        );

        static::assertSame($processor, $queue->extract());
        static::assertCount(1, $queue);
        static::assertTrue($queue->contains($processor));
    }

    #[Test]
    public function extractRemovesTheHighestPriorityProcessor(): void
    {
        $high  = new IdentityProcessor();
        $low   = new IdentityProcessor();
        $queue = new Queue();
        $queue->insert(
            data    : $low,
            priority: 1,
        );
        $queue->insert(
            data    : $high,
            priority: 10,
        );

        static::assertSame($high, $queue->extract());
        static::assertCount(1, $queue);
        static::assertFalse($queue->contains($high));
        static::assertSame($low, $queue->extract());
        static::assertCount(0, $queue);
    }

    #[Test]
    public function insertReturnsTheQueueAndCountsTheProcessors(): void
    {
        $queue = new Queue();

        static::assertTrue($queue->isEmpty());
        static::assertCount(0, $queue);
        static::assertSame($queue, $queue->insert(new IdentityProcessor()));
        static::assertCount(1, $queue);
        static::assertFalse($queue->isEmpty());
    }

    #[Test]
    public function iterationRunsTheHighestPriorityProcessorFirst(): void
    {
        $low     = new IdentityProcessor();
        $high    = new IdentityProcessor();
        $highest = new IdentityProcessor();
        $queue   = new Queue();
        $queue->insert(
            data    : $low,
            priority: 1,
        );
        $queue->insert(
            data    : $highest,
            priority: 100,
        );
        $queue->insert(
            data    : $high,
            priority: 50,
        );

        static::assertSame([$highest, $high, $low], iterator_to_array($queue, preserve_keys: false));
    }

    #[Test]
    public function removeLeavesTheRemainingProcessorsOrderable(): void
    {
        $first  = new IdentityProcessor();
        $second = new IdentityProcessor();
        $third  = new IdentityProcessor();
        $queue  = new Queue();
        $queue->insert(
            data    : $first,
            priority: 1,
        );
        $queue->insert(
            data    : $second,
            priority: 30,
        );
        $queue->insert(
            data    : $third,
            priority: 20,
        );

        static::assertTrue($queue->remove($second));

        static::assertSame([$third, $first], iterator_to_array($queue, preserve_keys: false));
        static::assertCount(2, $queue);
    }

    #[Test]
    public function removeReportsWhetherTheProcessorWasPresent(): void
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
    public function theQueueCanBeIteratedRepeatedly(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert(
            data    : $processor,
            priority: 5,
        );

        static::assertSame([$processor], iterator_to_array($queue, preserve_keys: false));
        static::assertSame([$processor], iterator_to_array($queue, preserve_keys: false));
        static::assertCount(1, $queue);
    }

    #[Test]
    public function toArrayReturnsBothDataAndPriority(): void
    {
        $processor = new IdentityProcessor();
        $queue     = new Queue();
        $queue->insert(
            data    : $processor,
            priority: 7,
        );

        static::assertSame(
            [['data' => $processor, 'priority' => 7]],
            $queue->toArray(QueueContents::Both),
        );
    }

    #[Test]
    public function toArrayReturnsDataInInsertionOrder(): void
    {
        $first  = new IdentityProcessor();
        $second = new IdentityProcessor();
        $queue  = new Queue();
        $queue->insert(
            data    : $first,
            priority: 100,
        );
        $queue->insert(
            data    : $second,
            priority: 1,
        );

        static::assertSame([$first, $second], $queue->toArray());
    }

    #[Test]
    public function toArrayReturnsPriorities(): void
    {
        $queue = new Queue();
        $queue->insert(
            data    : new IdentityProcessor(),
            priority: 7,
        );
        $queue->insert(
            data    : new IdentityProcessor(),
            priority: 3,
        );

        static::assertSame([7, 3], $queue->toArray(QueueContents::Priorities));
    }

    #[Test]
    public function topPeeksWithoutRemoving(): void
    {
        $high  = new IdentityProcessor();
        $low   = new IdentityProcessor();
        $queue = new Queue();
        $queue->insert(
            data    : $low,
            priority: 1,
        );
        $queue->insert(
            data    : $high,
            priority: 10,
        );

        static::assertSame($high, $queue->top());
        static::assertSame($high, $queue->top());
        static::assertCount(2, $queue);
    }
}
