<?php
namespace LazyBundle\Command;

use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use LazyBundle\Manager\SecurityCheckManager;
use LazyBundle\Services\Handler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GCCommand extends ContainerAwareCommand implements LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * @param SecurityCheckManager|null $manager
     * @param Handler|null $handler
     */
    public function __construct(SecurityCheckManager $manager = null, Handler $handler = null) {
        parent::__construct('security_check:gc');
    }

    protected function getManager() {
        return $this->getContainer()->get(SecurityCheckManager::class);
    }


    protected function getHandler() {
        return $this->getContainer()->get(Handler::class);
    }

    /**
     * {@inheritDoc}
     */
    protected function configure() {
        $this->setDescription('Garbage collection of security checks, risks and other data. It currently means: expire them after one month.');
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
        $manager = $this->getManager();
        $securityChecks = $manager->findAllGarbage();
        if ($input->getOption('dry-run')) {
            foreach ($securityChecks as $check) {
                $output->writeln((string)$check);
            }
            $output->writeln(sprintf('%d security checks would be deleted.', $securityChecks->count()));
        } else {
            $manager->transactional(function(EntityManager $em) use ($securityChecks, $manager) {
                foreach ($securityChecks as $check) {
                    $manager->delete($check);
                }
                $this->logger->info(sprintf('Deleted %d security checks.', $securityChecks->count()));
            });
        }
    }
}
