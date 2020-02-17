<?php

namespace TriTran\SqsQueueBundle\Service;

use Psr\Log\LoggerAwareTrait;

/**
 * Class BaseWorker
 * @package TriTran\SqsQueueBundle\Service
 */
class BaseWorker
{
    use LoggerAwareTrait;

    /**
     * @var int
     */
    private $consumed;

    /**
     * @param BaseQueue $queue
     * @param int $amount
     * @param int $limit Zero is all
     * @param int $timeLimit Zero is no time limit
     */
    public function start(BaseQueue $queue, int $amount = 0, int $limit = 1, int $timeLimit = 0)
    {
        $this->consumed = 0;
        $this->consume($queue, $amount, $limit, $timeLimit);
    }

    /**
     * @param BaseQueue $queue
     * @param int $amount
     * @param int $limit
     * @param int $timeLimit
     */
    private function consume(BaseQueue $queue, int $amount = 0, int $limit = 1, int $timeLimit = 0)
    {
        $startTime = time();

        while (true) {
            if ($amount && $this->consumed >= $amount) {
                break;
            }

            if ($timeLimit && (time() - $startTime) > $timeLimit) {
                break;
            }

            $this->fetchMessage($queue, $limit);
        }
    }

    /**
     * @param BaseQueue $queue
     * @param int $limit
     */
    private function fetchMessage(BaseQueue $queue, int $limit = 1)
    {
        $consumer = $queue->getQueueWorker();

        /** @var MessageCollection $result */
        $messages = $queue->receiveMessage($limit);

        $messages->rewind();
        while ($messages->valid()) {
            $this->consumed++;

            /** @var Message $message */
            $message = $messages->current();

            //$this->logger && $this->logger->info(sprintf('Processing message ID: %s', $message->getId()));
            $result = $consumer->process($message);

            if ($result !== false) {
                // $this->logger && $this->logger->info(
                //     sprintf('Successfully processed message ID: %s', $message->getId())
                // );
                $queue->deleteMessage($message);
            } else {
                $this->logger && $this->logger->warning(
                    sprintf('Cannot process message ID: %s, will release it back to queue', $message->getId())
                );
                $queue->releaseMessage($message);
            }

            $messages->next();
        }
    }
}
