<?php

namespace LazyBundle\Util;

use Cron\CronExpression;
use Jobby\Exception;
use Jobby\Helper;
use Jobby\Jobby as BaseJobby;
use LazyBundle\Exception\CronException;
use Symfony\Component\Filesystem\Filesystem;

class Jobby extends BaseJobby {
    public function getDefaultConfig(): array {
        return [
            'data_file' => '/tmp/cron.json'
        ] + parent::getDefaultConfig();
    }

    protected function getDefaultJobData(): array {
        return [
            'nextExecution' => null,
        ];
    }

    public function run() {
        $isUnix = ($this->helper->getPlatform() === Helper::UNIX);

        if ($isUnix && !extension_loaded('posix')) {
            throw new Exception('posix extension is required');
        }
        $now = new \DateTimeImmutable('now');

        foreach ($this->jobs as $jobConfig) {
            [$name, $config] = $jobConfig;
            $jobData = $this->getJobData($name);
            if ($jobData['nextExecution'] !== null && $now < \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $jobData['nextExecution'])) {
                continue;
            }
            if ($isUnix) {
                $this->runUnix($name, $config);
            } else {
                $this->runWindows($name, $config);
            }
            // use $now as argument?
            try {
                $this->editJsonEntry($name, ['nextExecution' => (new CronExpression($config['schedule']))->getNextRunDate()->format('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
                throw new CronException(sprintf('Job (%s) failed when calculating or writing next execution time.', $name));
            }
        }
    }

    /**
     * return the json file with all job info in it. If not exist, we create it add put cron like this :
     * key => nameOfMethodToExecute
     * [last_execution = null]
     *
     * @param null $name
     *
     * @return array
     */
    protected function getJobData($name = null): array {
        $file = $this->config['data_file'];
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
     */
    protected function writeJsonCron(array $data): void {
        $fs = new Filesystem();
        $file = $this->config['data_file'];
        $fs->dumpFile($file, json_encode($data));
    }
}