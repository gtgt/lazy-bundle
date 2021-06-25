<?php

namespace LazyBundle\Util;

use Cron\CronExpression;
use Jobby\Exception;
use Jobby\Helper;
use Jobby\Jobby as BaseJobby;
use LazyBundle\Exception\CronException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Filesystem\Filesystem;

class Jobby extends BaseJobby {

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(array $config = [], CacheItemPoolInterface $cache = null) {
       parent::__construct($config);
       $this->cache = $cache;
   }

    protected function getDefaultJobData(): array {
        return [
            'nextExecution' => null,
        ];
    }

    public function runJob($name): void {
        $config = $this->getJobConfig($name);
        if ($this->helper->getPlatform() === Helper::UNIX) {
            $this->runUnix($name, $config);
        } else {
            $this->runWindows($name, $config);
        }
    }

    public function run() {
        if ($isUnix && !extension_loaded('posix')) {
            throw new Exception('posix extension is required');
        }
        $now = new \DateTimeImmutable('now');

        foreach ($this->jobs as $jobConfig) {
            [$name, $config] = $jobConfig;
            $jobData = $this->getJobData($name);
            if ($jobData['nextExecution'] !== null && $now < \DateTimeImmutable::createFromFormat('U', $jobData['nextExecution'])) {
                continue;
            }
            if ($isUnix) {
                $this->runUnix($name, $config);
            } else {
                $this->runWindows($name, $config);
            }
            // use $now as argument?
            try {
                $nextRunTime = (new CronExpression($config['schedule']))->getNextRunDate(new \DateTime('now'));
                $this->editJsonEntry($name, ['nextExecution' => $nextRunTime->format('U')]);
            } catch (\Exception $e) {
                throw new CronException(sprintf('Job (%s) failed when calculating or writing next execution time to file (%s).', $name, $this->getConfig()['dataFile']));
            }
        }
    }

    public function getJobConfig(string $name): array {
        foreach ($this->jobs as $config) {
            [$_name, $_config] = $config;
            if ($_name === $name) {
                return $_config;
            }
        }
        throw new \InvalidArgumentException(sprintf('Cron job named %s not found.', $name));
    }

    public function getNextExecutionTime(string $name): \DateTimeInterface {
        $jobData = $this->getJobData($name);
        if ($jobData['nextExecution'] !== null) {
            return \DateTime::createFromFormat('U', $jobData['nextExecution']);
        }
        $config = $this->getJobConfig($name);
        return (new CronExpression($config['schedule']))->getNextRunDate(new \DateTime('now'));
    }

    /**
     * return the json file with all job info in it. If not exist, we create it add put cron like this :
     * key => nameOfMethodToExecute
     * [last_execution = null]
     *
     * @param null $name
     *
     * @return array
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function getJobData(string $name = null): array {
        if ($this->cache instanceof CacheItemPoolInterface) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $cacheItem = $this->cache->getItem('cron.data');
            $fileData = $cacheItem->isHit() ? $cacheItem->get() : [];
        } else {
            $file = $this->config['dataFile'];
            if (!is_file($file)) {
                $fs = new Filesystem();
                $dir = dirname($file);
                if (!$fs->exists($dir)) {
                    $fs->mkdir($dir);
                }
                $fileData = [];
            } else {
                $fileData = json_decode(file_get_contents($file), true);
            }
        }
        $defaultJobData = $this->getDefaultJobData();
        $jobData = [];
        foreach ($this->jobs as $jobConfig) {
            $key = $jobConfig[0];
            $jobData[$key] = array_intersect_key($fileData[$key] ?? [], $defaultJobData) + $defaultJobData;
        }
        if ($jobData !== $fileData) {
            $this->writeJsonCron($jobData);
        }
        if ($name !== null) {
            return $jobData[$name];
        }
        return $jobData;
    }

    /**
     * method to edit an entry in json
     *
     * @param string $name
     * @param array $data
     */
    protected function editJsonEntry(string $name, array $data): void {
        $defaultJobData = $this->getDefaultJobData();
        $jobData = $this->getJobData();
        $jobData[$name] = array_intersect_key($data, $defaultJobData) + ($jobData[$name] ?? []);
        $this->writeJsonCron($jobData);
    }

    /**
     * method that writes the cron.json when we add or edit an entry
     *
     * @param array $data
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function writeJsonCron(array $data): void {
        if ($this->cache instanceof CacheItemPoolInterface) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $cacheItem = $this->cache->getItem('cron.data');
            $cacheItem->set($data);
            $cacheItem->expiresAfter(null);
            $this->cache->save($cacheItem);
        } else {
            $fs = new Filesystem();
            $file = $this->config['dataFile'];
            $fs->dumpFile($file, json_encode($data));
        }
    }
}