<?php

declare(strict_types=1);

namespace LinioPay\Idle\Queue;

use LinioPay\Idle\Queue\Exception\InvalidMessageParameterException;
use LinioPay\Idle\TestCase;

class MessageTest extends TestCase
{
    public function testCanGetParameters()
    {
        $queueIdentifier = 'fooqueue';
        $body = 'foobody';
        $attributes = ['bar' => 'fuz'];
        $messageIdentifier = '123';
        $temporaryMetadata = ['foo' => 'bar'];
        $message = new Message($queueIdentifier, $body, $attributes, $messageIdentifier, $temporaryMetadata);

        $this->assertSame($queueIdentifier, $message->getQueueIdentifier());
        $this->assertSame($body, $message->getBody());
        $this->assertSame($attributes, $message->getAttributes());
        $this->assertSame($messageIdentifier, $message->getMessageIdentifier());
        $this->assertSame($temporaryMetadata, $message->getTemporaryMetadata());
    }

    public function testCanGetFromArraySuccessfully()
    {
        $message = Message::fromArray(['body' => 'mbody', 'queueIdentifier' => 'foo_queue']);

        $this->assertSame('mbody', $message->getBody());
        $this->assertSame('foo_queue', $message->getQueueIdentifier());
    }

    public function testFromArrayThrowsExceptionWhenMessageInvalid()
    {
        $this->expectException(InvalidMessageParameterException::class);
        Message::fromArray(['body' => 'mbody']);
    }
}