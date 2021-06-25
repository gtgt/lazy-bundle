<?php

namespace LazyBundle\DataCollector;

use Jobby\Jobby;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CronDataCollector extends AbstractDataCollector {
    /**
     * @var Jobby
     */
    private $jobby;

    /**
     * @var array
     */
    private $jobbyConfig;

    /**
     * @var array
     */
    private $jobbyJobs;

    /**
     * @param array $cronConfig
     */
    public function __construct(Jobby $jobby) {
        $this->jobby = $jobby;
        $this->jobbyConfig = $jobby->getConfig();
        $this->jobbyJobs = $jobby->getJobs();
    }

    public function runJob(string $name): ?string {
        try {
            $this->jobby->runJob($name);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null) {
        $jobs = $this->jobby->getJobs();
        $this->data = [
            'jobs' => array_map(function(string $name, array $config) {
                $nextExecutionTime = $this->jobby->getNextExecutionTime($name);
                return [$name, $config['command'], $config['schedule'], $nextExecutionTime ? \IntlDateFormatter::formatObject($nextExecutionTime) : '-', $config['enabled'] ? 'true' : 'false'];
            }, array_column($jobs, 0), array_column($jobs, 1)),
            'columns' => ['name', 'command', 'schedule', 'next', 'enabled'],
        ];
    }

    public function reset(): void {
        $this->data = [];
    }

    public function getName(): string {
        return self::class;
    }

    public function getJobs() {
        return $this->data['jobs'];
    }

    public function getColumns() {
        return $this->data['columns'];
    }

    public static function getTemplate(): ?string {
        return '@Lazy/Collector/cron.html.twig';
    }

    public function __sleep() {
        return ['data', 'jobbyConfig', 'jobbyJobs'];
    }

    public function __wakeup() {
        $this->jobby = new \LazyBundle\Util\Jobby($this->jobbyConfig);
        foreach ($this->jobbyJobs as $job) {
            $this->jobby->add(...$job);
        }
    }
}