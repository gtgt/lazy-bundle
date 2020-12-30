<?php

namespace LazyBundle\Console\Style;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LazyStyle extends SymfonyStyle {
    /**
     * @var \ReflectionObject
     */
    private $reflection;
    /**
     * Maximum line length of the terminal
     *
     * @var int
     */
    protected $lineLength;

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @var int
     */
    protected $progressSkippedSteps = 0;

    public function __construct(InputInterface $input, OutputInterface $output) {
        parent::__construct($input, $output);
        // magic :)    (maybe do it with reflection?)
        $this->lineLength = ((array)$this)[' Symfony\Component\Console\Style\SymfonyStyle lineLength'];
        ProgressBar::setPlaceholderFormatterDefinition('remaining', function(ProgressBar $progressBar, OutputInterface $output) {
            $step = $progressBar->getProgress() - $this->progressSkippedSteps;
            if (!$step) {
                return 0;
            }
            $maxSteps = $progressBar->getMaxSteps() - $this->progressSkippedSteps;
            return Helper::formatTime(round((time() - $progressBar->getStartTime()) / $step * ($maxSteps - $step)));
        });
        ProgressBar::setPlaceholderFormatterDefinition('estimated', function(ProgressBar $progressBar, OutputInterface $output) {
            $step = $progressBar->getProgress() - $this->progressSkippedSteps;
            if (!$step) {
                return 0;
            }
            $maxSteps = $progressBar->getMaxSteps() - $this->progressSkippedSteps;
            return Helper::formatTime(round((time() - $progressBar->getStartTime()) / $step * $maxSteps));
        });
    }

    /**
     * {@inheritDoc}
     */
    public function createProgressBar(int $max = 0, int $barWidth = null): ProgressBar {
        $progressBar = parent::createProgressBar($max);
        $progressBar->minSecondsBetweenRedraws(1 / 10);
        $progressBar->setRedrawFrequency(null);
        $progressBar->setBarWidth($barWidth ?? $this->lineLength - 49);
        $format = ProgressBar::getFormatDefinition($max > 0 ? 'debug' : 'debug_nomax');
        $format .= "\n%message%";
        ProgressBar::setFormatDefinition('progress', $format);
        ProgressBar::setFormatDefinition('message', "%message%\n");
        $progressBar->setFormat('progress');
        $progressBar->setMessage(' ');
        return $progressBar;
    }

    public function progressFinish() {
        $this->progressBar = null;
        $this->progressSkippedSteps = 0;
        parent::progressFinish();
    }

    /**
     * @return ProgressBar
     */
    protected function getProgressBar(): ProgressBar {
        if (!$this->progressBar) {
            throw new \RuntimeException('The ProgressBar is not started.');
        }

        return $this->progressBar;
    }

    /**
     * @return bool
     */
    public function isProgressBarStarted(): bool {
        return (bool)$this->progressBar;
    }

    /**
     * {@inheritdoc}
     */
    public function progressStart(int $max = 0): ProgressBar {
        parent::progressStart($max);
        // magic :)    (maybe do it with reflection?)
        $this->progressBar = ((array)$this)[' Symfony\Component\Console\Style\SymfonyStyle progressBar'];
        return $this->getProgressBar();
    }

    /**
     * Skip steps (will not calculated into remaining time)
     *
     * @param int $step
     */
    public function progressSkip(int $step = 1): void {
        if ($step > 0) {
            $this->progressSkippedSteps += $step;
            $this->progressAdvance($step);
        }
    }

    /**
     * Sets currently processed item info
     *
     * @param $message
     */
    public function progressStatus(string $message, bool $forceRedraw = false): void {
        if (!$this->isProgressBarStarted() || (!$forceRedraw && !$this->progressCanBeRedrawn())) {
            return;
        }
        $progressBar = $this->getProgressBar();
        $message = str_replace("\n", '', $message);
        $length = mb_strlen($message);
        $maxLength = $this->lineLength - 1;
        $message = $length > $maxLength ? mb_substr($message, 0, $maxLength) : $message.str_repeat(' ', $maxLength - $length);
        $progressBar->setMessage(' '.$message);
        $progressBar->display();
    }

    /**
     * @return bool
     */
    public function progressCanBeRedrawn(): bool {
        // magic :)    (maybe do it with reflection?)
        $progressBar = (array)$this->getProgressBar();
        $timeInterval = microtime(true) - $progressBar[' Symfony\Component\Console\Helper\ProgressBar lastWriteTime'];
        // Throttling
        return ($timeInterval > $progressBar[' Symfony\Component\Console\Helper\ProgressBar minSecondsBetweenRedraws']) || ($timeInterval >= $progressBar[' Symfony\Component\Console\Helper\ProgressBar maxSecondsBetweenRedraws']);
    }

    /**
     * @param $max
     * @param bool $add Set true if you want add the number to the exsting maximum instead of replacing it.
     */
    public function progressSetMaxSteps($max, bool $add = false): void {
        if ($add) {
            $max += $this->getProgressBar()->getMaxSteps();
        }
        $this->getProgressBar()->setMaxSteps($max);
    }

    public function progressSetRedrawFreq(?int $freq): void {
        $progressBar = $this->getProgressBar();
        if ($freq === null) {
            $freq = $progressBar->getMaxSteps() / $progressBar->getBarWidth();
        }
        $progressBar->setRedrawFrequency($freq);
    }
}