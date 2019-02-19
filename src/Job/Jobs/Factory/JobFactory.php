<?php

declare(strict_types=1);

namespace LinioPay\Idle\Job\Jobs\Factory;

use LinioPay\Idle\Job\Job;
use LinioPay\Idle\Job\Jobs\FailedJob;
use Psr\Container\ContainerInterface;
use Throwable;

class JobFactory
{
    /** @var ContainerInterface */
    protected $container;

    /** @var array */
    protected $jobConfig;

    public function __invoke(ContainerInterface $container) : JobFactory
    {
        $this->container = $container;

        $this->jobConfig = $container->get('job-config');

        return $this;
    }

    public function createJob(string $class, array $parameters) : Job
    {
        try {
            /** @var Job $job */
            $job = $this->container->get($class);
            $job->setParameters($parameters);

            return $job;
        } catch (Throwable $t) {
            return new FailedJob([
                'message' => $t->getMessage(),
                'file' => $t->getFile(),
                'line' => $t->getLine(),
            ]);
        }
    }
}