<?php

namespace LazyBundle\Command;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Description of DeployCommand
 */
class DeployFtpCommand extends Command {

    protected $defaultLftpOptions = 'set ftp:use-allo no; set ftp:use-feat no; set ftp:ssl-allow no; set ftp:list-options -a;';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $projectDir;

    public function __construct(string $projectDir) {
        $this->projectDir = $projectDir;
        parent::__construct('lazy:deploy:ftp');
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config): void {
        $this->config = $config;
    }

    protected function configure() {
        $this
            ->setDescription('Deploy the project using LFTP')
            ->addArgument('server', InputArgument::REQUIRED, 'Where you want to deploy your project')
            ->addOption('symlinks', null, InputOption::VALUE_NONE, 'Create symbolic links, don\'t follow them.')
            ->addOption('go', null, InputOption::VALUE_NONE, 'If set, the task will deploy the project')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'If set, no changes will be made')
            ->addOption('show-config', null, InputOption::VALUE_NONE, 'If set, task will show your deployment configuration')
            ->addOption('lftp-commands', null, InputOption::VALUE_OPTIONAL, 'If set, it replaces the default LFTP commands: ')
            ->setHelp(<<<EOT
The <info>deploy:ftp</info> command helps you to deploy your sources in your web server using LFTP.
By default, this command executes LFTP with your config information set under app/config/config.yml


<comment>Usage:</comment>
<info>app/console {$this->getName()} server [--go] [--show-config] [--lftp-commands] [--verbose]</info>

<comment>Example:</comment>

<info>./bin/console {$this->getName()} prod --go</info>
this command will execute the deployment quietly

<info>./bin/console {$this->getName()} prod --go  --verbose </info>
this command will execute the deployment in verbose mode

<info>./bin/console {$this->getName()} prod --show-config </info>
shows your prod server's config. these config should be set on app/config/config.yml

<info>./bin/console {$this->getName()} prod --lftp-commands="{$this->defaultLftpOptions}" </info>
    this command will execute the LFTP client with your own lftp options. the default options will be replaced by your own.

lazy:
    deploy_ftp:
        prod:
            hostname: "FTP.mysite.com"
            path: "/" # the base path to mirror in server
            port: "21" # default
            user: "Your FTP login"
            exclude_file: %kernel.root_dir%/config/lazy_deploy_ftp_exclude.txt


EOT
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);
        $config = $this->getConfig($input->getArgument('server'));

        if ($input->isInteractive() && !$input->getOption('show-config')) {
            if (empty($config['user'])) {
                $config['user'] = $io->ask('User');
            }
            if (empty($config['password'])) {
                $config['password'] = $io->askHidden('Password');
            }
        }

        $config['local_dir'] = $this->projectDir.'/'; //emplacement local

        $config['lftp_commands'] = $this->defaultLftpOptions;

        if ($input->getOption('lftp-commands')) {
            $config['lftp_commands'] = $input->getOption('lftp-commands');
        }

        $mirrorOptions = [];

        if ($input->getOption('dry-run')) {
            $mirrorOptions[] = '--dry-run';
        } elseif ($input->getOption('verbose')) {
            $mirrorOptions[] = '--verbose';
        } else {
            $mirrorOptions[] = '--log=/dev/stdout';
        }
        if (!$input->getOption('symlinks')) {
            $mirrorOptions[] = '--dereference --no-symlinks';
        }

        $config['mirror_options'] = implode(' ', $mirrorOptions);

        $this->showConfig($config, $io);
        //if just showing config, we show them and we exit
        if ($input->getOption('show-config')) {
            return 1;
        }

        if (!$input->getOption('go')) {
            if (!$input->isInteractive() || !$io->confirm('Do you confirm deployment?', true)) {
                $io->warning('Command aborted.');

                return 1;
            }
        }
        $ignored_dirs = '';

        if (isset($config['exclude_file'])) {
            try {
                $ignored_dirs = $this->getExcludeLftpString($config['exclude_file']);
            } catch (\Exception $e) {
                $io->comment(['Some errors was occured when trying to get the ignored files/directories.', 'Error message:', $e->getMessage()]);
                if (!$io->confirm('Do you want continue deployment?', true)) {
                    $io->error('Command aborted.');
                    return 1;
                }
            }
        }
        $url = sprintf('ftp://%s:%s@%s:%s', $config['user'], $config['password'], $config['hostname'], $config['port']);

        $ftpcommand = sprintf('%s open %s; lcd %s; cd %s; mirror --overwrite --no-perms --parallel=4 --reverse --delete %s %s ; quit', $config['lftp_commands'], $url,
            $config['local_dir'], $config['path'], $config['mirror_options'], $ignored_dirs);
        // $io->note(sprintf('lftp -c %s', $ftpcommand));
        $processHelper = $this->getHelper('process');
        $regex = '#(get(\s-e)?\s-O|rm(\s-r)?|mkdir|rmdir|ln(\s-s)?)\s+([^\s]+)(?:\s+([^\s]+))?#';
        $pathStripRegex = '#(?:file:)?/'.trim($config['local_dir'], '/').'|(?:'.$url.')?/'.trim($config['path'], '/').'#';
        $leftover = '';
        $processHelper->run($output, (new Process(['lftp', '-c', $ftpcommand]))->setTimeout(null), 'Deploy failed :(', static function ($type, $data) use ($io, $url, $config, $regex, $pathStripRegex, &$leftover) {
            if (Process::ERR === $type) {
                $io->error($data);
            } else {
                $data = explode(PHP_EOL, $data);
                if ($leftover) {
                    $data[0] = $leftover.$data[0];
                    $leftover = '';
                }
                /** @noinspection PhpAssignmentInConditionInspection */
                while ($line = array_shift($data)) {
                    if (preg_match($regex, $line, $m)) {
                        $cmd = explode(' ', $m[1]);
                        $type = $cmd[0];
                        $path = '';
                        $style = null;

                        $pathLeft = $m[5] ?? null ? preg_replace($pathStripRegex, '', $m[5]) : '';
                        $pathRight = $m[6] ?? null ? preg_replace($pathStripRegex, '', $m[6]) : '';
                        if (!$data && (strpos($pathLeft, 'ftp:') === 0 || strpos($pathRight, $config['local_dir']) !== false || strpos($pathRight, $config['path']) !== false)) {
                            $leftover = $line;
                            continue;
                        }
                        switch ($type) {
                            case 'get':
                                $style = 'fg=black;bg=green';
                                $type = ' ^';
                                $path .= $pathRight ?: $pathLeft;
                                break;
                            case 'ln':
                                $style = 'fg=black;bg=yellow';
                                $type = ' >';
                                $path .= $pathLeft.($pathRight ? ' <info> >> </info>'.$pathRight : '');
                                //$path .= $m[5] ?? $m[4];
                                break;
                            case 'rm':
                            case 'rmdir':
                                $style = 'fg=white;bg=red';
                                $type = ($m[3] === '-r' ? '!' : ' ').'-';
                                $path .= $pathLeft;
                                break;
                            case 'mkdir':
                                $style = 'fg=white;bg=blue';
                                $type = ' +';
                                $path .= $pathLeft;
                                break;
                        }
                        $io->writeln(($style ? '<'.$style.'> ' : ' ').$type.($style ? ' </> ' : '  ').$path);
                    } else {
                        if ($data) {
                            $io->writeln($line);
                        } else {
                            $leftover = $line;
                        }
                    }
                }
            }
        });
        return 0;
    }

    /**
     * @param $config
     * @param SymfonyStyle $io
     */
    protected function showConfig($config, SymfonyStyle $io): void {
        $io->definitionList(
            'Config',
            new TableSeparator(),
            ['Host' => $config['hostname']],
            ['Remote path' => $config['path']],
            ['Port' => $config['port']],
            ['User' => $config['user']],
            ['Password' => str_repeat('*', strlen($config['password']))],
            ['Exclude File' => $config['exclude_file']],
            ['LFTP commands' => $config['lftp_commands']],
            ['LFTP mirror options' => $config['mirror_options']]
        );
    }

    /**
     * @param $server
     *
     * @return array
     */
    protected function getConfig($server): array {
        if (!isset($this->config[$server])) {
            throw new InvalidConfigurationException(sprintf('the configuration for server %s is not set under lazy.deploy_ftp', $server));
        }
        $conf = $this->config[$server];
        if (!isset($conf['hostname'])) {
            throw new InvalidConfigurationException(sprintf('the hostname for server %s is not set under lazy.deploy_ftp', $server));
        }
        return $conf;
    }

    /**
     * @param $filename
     *
     * @return string
     */
    protected function getExcludeLftpString($filename) {
        if (!file_exists($filename)) {
            throw new InvalidConfigurationException('the exclude file not found: '.$filename);
        }
        if (!is_readable($filename)) {
            throw new InvalidConfigurationException('the exclude file is not readable: '.$filename);
        }
        $toIgnore = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $ignored = [];

        foreach ($toIgnore as $path) {
            //lines to be ignored if starts with #
            if (!strncmp($path, '#', 1)) {
                continue;
            }
            // if starts with ~, it's a regexp
            if (!strncmp($path, '~', 1)) {
                $ignored[] = sprintf('--exclude %s', substr($path, 1));
            } else {
                $ignored[] = sprintf('--exclude-glob %s', $path);
            }
        }
        return implode(' ', $ignored);
    }

}
