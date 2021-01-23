<?php
namespace LazyBundle\Factory;


use Jobby\Jobby;

class JobbyFactory implements JobbyFactoryInterface
{
    /**
     * @var array
     */
    private $config;
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(array $cronConfig, string $projectDir)
    {
        $this->config = $cronConfig;
        $this->projectDir = $projectDir;
    }

    public function generate(): Jobby
    {
        $jobby = new Jobby($this->config['globals']);
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
