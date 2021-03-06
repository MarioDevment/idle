<?php

declare(strict_types=1);

namespace LinioPay\Idle\Message\Messages\PublishSubscribe\Service\Google\PubSub;

use Google\Cloud\PubSub\Message as GoogleCloudMessage;
use Google\Cloud\PubSub\PubSubClient;
use LinioPay\Idle\Message\Exception\FailedReceivingMessageException;
use LinioPay\Idle\Message\Exception\InvalidMessageParameterException;
use LinioPay\Idle\Message\Message as MessageInterface;
use LinioPay\Idle\Message\Messages\PublishSubscribe\Message\SubscriptionMessage;
use LinioPay\Idle\Message\Messages\PublishSubscribe\Service\DefaultService;
use LinioPay\Idle\Message\Messages\PublishSubscribe\SubscriptionMessage as SubscriptionMessageInterface;
use LinioPay\Idle\Message\Messages\PublishSubscribe\TopicMessage as TopicMessageInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Service extends DefaultService
{
    const IDENTIFIER = 'google-pubsub';

    /** @var TestPubSubClient */
    protected $client;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(PubSubClient $client, array $config, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function publish(TopicMessageInterface $message, array $parameters = []) : bool
    {
        $this->logger->info('Idle publishing a message.', ['service' => self::IDENTIFIER, 'message' => $message->toArray()]);

        try {
            $topicIdentifier = $message->getTopicIdentifier();
            $topic = $this->client->topic($topicIdentifier);

            $properties = [
                'data' => $message->getBody(),
            ];

            if (!empty($message->getAttributes())) {
                $properties = array_merge($properties, [
                    'attributes' => $message->getAttributes(),
                ]);
            }

            // Publish a message to the topic.
            $result = $topic->publish($properties, array_replace_recursive(
                $this->getPublishParameterConfig(),
                $parameters
            ));

            $message->setMessageId((string) $result['messageIds'][0] ?? '');

            return true;
        } catch (Throwable $throwable) {
            $this->logger->critical('Idle publish encountered an error.', [
                'service' => self::IDENTIFIER,
                'message' => $message->toArray(),
                'error' => $this->throwableToArray($throwable),
            ]);

            if (!$this->isPublishErrorSuppressed()) {
                throw $throwable;
            }
        }

        return false;
    }

    /**
     * @return SubscriptionMessage[]
     *
     * @throws Throwable
     */
    public function pull(string $subscriptionIdentifier, array $parameters = []) : array
    {
        $this->logger->info('Idle pulling a message.', ['service' => self::IDENTIFIER, 'subscription' => $subscriptionIdentifier]);

        try {
            $subscription = $this->client->subscription($subscriptionIdentifier);

            $result = $subscription->pull(array_replace_recursive(
                $this->getPullParameterConfig(),
                $parameters
            ));

            return $this->buildMessagesFromResult($subscriptionIdentifier, $result);
        } catch (Throwable $throwable) {
            $this->logger->critical('Idle pull encountered an error.', [
                'service' => self::IDENTIFIER,
                'queue' => $subscriptionIdentifier,
                'error' => $this->throwableToArray($throwable),
            ]);

            if (!$this->isPullErrorSuppressed()) {
                throw $throwable;
            }
        }

        return [];
    }

    public function pullOneOrFail(string $subscriptionIdentifier, array $parameters = []) : MessageInterface
    {
        try {
            $messages = $this->pull($subscriptionIdentifier, array_merge($parameters, ['maxMessages' => 1]));
        } catch (Throwable $throwable) {
        } finally {
            if (empty($messages) || !is_a($messages[0], MessageInterface::class)) {
                throw new FailedReceivingMessageException(self::IDENTIFIER, SubscriptionMessage::IDENTIFIER, $subscriptionIdentifier, $throwable ?? null);
            }
        }

        return $messages[0];
    }

    public function acknowledge(SubscriptionMessageInterface $message, array $parameters = []) : bool
    {
        $this->logger->info('Idle acknowledging a message.', ['service' => self::IDENTIFIER, 'message' => $message->toArray()]);

        try {
            $metadata = $message->getTemporaryMetadata();

            if (empty($metadata['gcMessage']) || !is_a($metadata['gcMessage'], GoogleCloudMessage::class)) {
                throw new InvalidMessageParameterException('gcMessage');
            }

            $subscription = $this->client->subscription($message->getSubscriptionIdentifier());

            $subscription->acknowledge($metadata['gcMessage'], array_replace_recursive(
                $this->getAcknowledgeParameterConfig(),
                $parameters
            ));

            return true;
        } catch (Throwable $throwable) {
            $this->logger->critical('Idle acknowledge encountered an error.', [
                'service' => self::IDENTIFIER,
                'message' => $message->toArray(),
                'error' => $this->throwableToArray($throwable),
            ]);

            if (!$this->isAcknowledgeErrorSuppressed()) {
                throw $throwable;
            }
        }

        return false;
    }

    /**
     * @return SubscriptionMessage[]
     */
    protected function buildMessagesFromResult(string $subscriptionIdentifier, array $resultMessages) : array
    {
        $out = [];

        /** @var GoogleCloudMessage $message */
        foreach ($resultMessages as $message) {
            $subscriptionMessage = new SubscriptionMessage(
                $subscriptionIdentifier,
                $message->data(),
                $message->attributes(),
                $message->id(),
                [
                    'gcMessage' => $message,
                ]
            );

            $subscriptionMessage->setService($this);

            $out[] = $subscriptionMessage;
        }

        return $out;
    }
}
