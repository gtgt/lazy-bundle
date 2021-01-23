<?php
namespace LazyBundle\Command\Cron;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListCommand extends Command
{
    public static $defaultName = 'cron:list';
    /**
     * @var array
     */
    private $config;

    public function __construct(array $cronConfig)
    {
        parent::__construct(self::$defaultName);
        $this->config = $cronConfig;
    }

    protected function configure()
    {
        $this->setDescription('Returns list of jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $tableHeader = [
            'Name',
            'Command',
            'Schedule',
            'Enabled',
        ];

        $tableData = array_map(function (string $name, array $config) {
            return [
                $name,
                $config['command'],
                $config['schedule'],
                $config['enabled'] ? 'true' : 'false',
            ];
        }, array_keys($this->config['jobs']), $this->config['jobs']);

        $io->table($tableHeader, $tableData);

        return 0;
    }
}
