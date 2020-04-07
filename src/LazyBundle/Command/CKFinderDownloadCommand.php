<?php
/*
 * This file is a part of the CKFinder bundle for Symfony.
 *
 * Copyright (C) 2016, CKSource - Frederico Knabben. All rights reserved.
 *
 * Licensed under the terms of the MIT license.
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace LazyBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class CKFinderDownloadCommand
 *
 * Command that downloads the CKFinder package and puts assets to the Resources/public directory of the bundle.
 */
class CKFinderDownloadCommand extends Command
{
    const ZIP_PACKAGE_URL = 'http://download.cksource.com/CKFinder/CKFinder%20for%20PHP/3.5.1/ckfinder_php_3.5.1.zip';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ckfinder:download')
             ->setDescription('Downloads the CKFinder distribution package and extracts it to CKSourceCKFinderBundle.');
    }

    /**
     * {@inheritdoc}
     * @noinspection PhpUnusedLocalVariableInspection
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetPublicPath = dirname(__DIR__).'/Resources/public';

        if (!is_writable($targetPublicPath)) {
            $output->writeln('<error>The CKSourceCKFinderBundle::Resources/public directory is not writable (used path: ' . $targetPublicPath . ').</error>');

            return 1;
        }

        $targetConnectorPath = dirname(__DIR__, 2).'/CKFinder';
        if (!file_exists($targetConnectorPath) && !mkdir($targetConnectorPath, 0770) && !is_dir($targetConnectorPath)) {
            $output->writeln('<error>The CKSourceCKFinderBundle::_connector directory can\'t be created (used path: ' . $targetConnectorPath . ').</error>');
            return 2;
        }
        if (!is_writable($targetConnectorPath)) {
            $output->writeln('<error>The CKSourceCKFinderBundle::_connector directory is not writable (used path: ' . $targetConnectorPath . ').</error>');
            return 2;
        }

        if (file_exists($targetPublicPath.'/ckfinder/ckfinder.js')) {
            $questionHelper = $this->getHelper('question');
            $questionText =
                'It looks like the CKFinder distribution package has already been installed. ' .
                "This command will overwrite the existing files.\nDo you want to proceed? [y/n]: ";
            $question = new ConfirmationQuestion($questionText, false);

            if (!$questionHelper->ask($input, $output, $question)) {
                return 3;
            }
        }

        /** @var ProgressBar $progressBar */
        $progressBar = null;

        $maxBytes = 0;
        $ctx = stream_context_create(array(), array(
            'notification' =>
            function ($notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax) use (&$maxBytes, $output, &$progressBar) {
                switch ($notificationCode) {
                    case STREAM_NOTIFY_FILE_SIZE_IS:
                        $maxBytes = $bytesMax;
                        $progressBar = new ProgressBar($output, $bytesMax);
                        break;
                    case STREAM_NOTIFY_PROGRESS:
                        $progressBar->setProgress($bytesTransferred);
                        break;
                }
            }
        ));

        $output->writeln('<info>Downlading the CKFinder 3 distribution package.</info>');

        $zipContents = @file_get_contents(self::ZIP_PACKAGE_URL, false, $ctx);

        if ($zipContents === false) {
            $output->writeln(
                '<error>Could not download the distribution package of CKFinder.</error>');

            return 4;
        }

        if ($progressBar) {
            $progressBar->finish();
        }

        $output->writeln("\n" . 'Extracting CKFinder to the CKSourceCKFinderBundle::Resources/public directory.');

        $tempZipFile = tempnam(sys_get_temp_dir(), 'tmp');
        file_put_contents($tempZipFile, $zipContents);
        /** @noinspection PhpComposerExtensionStubsInspection */
        $zip = new \ZipArchive();
        $zip->open($tempZipFile);

        $zipEntries = array();

        // These files won't be overwritten if already exists
        $filesToKeep = array(
            'ckfinder/config.js',
            'ckfinder/ckfinder.html'
        );

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (in_array($entry, $filesToKeep) && file_exists($targetPublicPath . '/' . $entry)) {
                continue;
            }

            $zipEntries[] = $entry;
        }

        $zip->extractTo($targetPublicPath, $zipEntries);
        $zip->close();

        $fs = new Filesystem();

        $output->writeln('Moving the CKFinder connector to the CKSourceCKFinderBundle::_connector directory.');
        $fs->mirror(
            $targetPublicPath . '/ckfinder/core/connector/php/vendor/cksource/ckfinder/src/CKSource/CKFinder',
            $targetConnectorPath
        );

        $output->writeln('Cleaning up.');
        $fs->remove(array(
            $tempZipFile,
            $targetPublicPath . '/ckfinder/core',
            $targetPublicPath . '/ckfinder/userfiles',
            $targetPublicPath . '/ckfinder/config.php',
            $targetPublicPath . '/ckfinder/README.md',
            $targetConnectorPath . '/README.md'
        ));

        $output->writeln('<info>Done. Happy coding!</info>');
        return 0;
    }
}
