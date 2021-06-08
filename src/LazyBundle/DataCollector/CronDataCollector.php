<?php

namespace LazyBundle\DataCollector;

use Jobby\Jobby;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CronDataCollector extends AbstractDataCollector {
    /**
     * @var array
     */
    private $jobby;

    /**
     * @param array $cronConfig
     */
    public function __construct(Jobby $jobby) {
        $this->jobby = $jobby;
    }

    /**
     * @inheritDoc
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null) {
        $jobs = $this->jobby->getJobs();
        $this->data = [
            'jobs' => array_map(function(string $name, array $config) {
                return [$name, $config['command'], $config['schedule'], $config['enabled'] ? 'true' : 'false',];
            }, array_column($jobs, 0), array_column($jobs, 1)),
            'columns' => ['name', 'command', 'schedule', 'enabled'],
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
        return ['data'];
    }
}