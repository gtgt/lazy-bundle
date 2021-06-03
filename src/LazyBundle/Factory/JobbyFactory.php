<?php

namespace LazyBundle\Factory;

use LazyBundle\Util\Jobby;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Contracts\Cache\CacheInterface;

class JobbyFactory implements JobbyFactoryInterface {
    /**
     * @var array
     */
    private $config;
    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(array $cronConfig, string $projectDir, CacheInterface $cacheCron = null) {
        $this->config = $cronConfig;
        $this->projectDir = $projectDir;
        if ($cacheCron !== null && !$cacheCron instanceof CacheItemPoolInterface) {
            throw new InvalidConfigurationException(sprintf('Cron cache (%s) must implement %s.', get_class($cacheCron), CacheItemPoolInterface::class));
        }
        $this->cache = $cacheCron;
    }

    public function generate(): Jobby {
        $jobby = new Jobby($this->config['globals'], $this->cache);
        $phpEx = $this->config['php_executable'];

        foreach ($this->config['jobs'] as $jobName => $jobConfig) {
            if ($jobConfig['is_symfony_command']) {
                $jobConfig['command'] = "{$phpEx} {$this->projectDir}/bin/console {$jobConfig['command']}";
            }

            $jobby->add($jobName, $jobConfig);
        }

        return $jobby;
    }
}
