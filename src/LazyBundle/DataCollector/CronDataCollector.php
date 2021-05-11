<?php

namespace LazyBundle\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CronDataCollector extends AbstractDataCollector {
    /**
     * @var array
     */
    private $config;

    /**
     * @param array $cronConfig
     */
    public function __construct(array $cronConfig) {
        $this->config = $cronConfig;
    }

    /**
     * @inheritDoc
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null) {
        $this->data = [
            'jobs' => array_map(function(string $name, array $config) {
                return [$name, $config['command'], $config['schedule'], $config['enabled'] ? 'true' : 'false',];
            }, array_keys($this->config['jobs']), $this->config['jobs']),
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
}