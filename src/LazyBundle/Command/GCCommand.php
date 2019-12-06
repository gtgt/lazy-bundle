<?php
namespace LazyBundle\Command;

use Doctrine\ORM\EntityManager;
use LazyBundle\Manager\AbstractManager;
use LazyBundle\Manager\ManagerRegistry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GCCommand extends Command implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry = null) {
        parent::__construct('lazy:gc');
    }

    /**
     * {@inheritDoc}
     */
    protected function configure() {
        $this->setDescription('Garbage collection of lazy managers.');
        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Run GC by not removing anything.');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        // find garbage processes
        /** @var AbstractManager $manager */
        foreach ($this->managerRegistry as $manager) {
            $garbage = $manager->findAllGarbage();
            if ($input->getOption('dry-run')) {
                foreach ($garbage as $check) {
                    $output->writeln((string)$check);
                }
                $output->writeln(sprintf('%d entities would be deleted.', $garbage->count()));
            } else {
                $manager->transactional(function(EntityManager $em) use ($garbage, $manager) {
                    foreach ($garbage as $check) {
                        $manager->delete($check);
                    }
                    $this->logger->info(sprintf('Deleted %d entities.', $garbage->count()));
                });
            }
        }
    }
}
