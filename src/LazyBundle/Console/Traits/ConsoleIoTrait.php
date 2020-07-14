<?php

namespace LazyBundle\Console\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use LazyBundle\Console\Style\LazyStyle;

trait ConsoleIoTrait {
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return LazyStyle
     */
    protected function createIo(InputInterface $input, OutputInterface $output): LazyStyle {
        return new LazyStyle($input, $output);
    }

    /**
     * @param LazyStyle $io
     *
     * @return \Closure
     */
    protected function createIoProgressClosure(LazyStyle $io): \Closure {
        return static function($param = NULL) use ($io) {
            if (is_numeric($param)) {
                $io->progressStart($param);
            } elseif ($param === false) {
                $io->progressFinish();
            } elseif ($io->isProgressBarStarted()) {
                $io->progressAdvance();
                /** @noinspection NotOptimalIfConditionsInspection */
                if (is_string($param)) {
                    $io->progressStatus($param);
                }
            } elseif (is_string($param)) {
                $io->note($param);
            }
        };
    }
}