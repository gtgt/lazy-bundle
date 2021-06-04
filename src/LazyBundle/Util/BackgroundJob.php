<?php

namespace LazyBundle\Util;

use Jobby\Helper;

class BackgroundJob extends \Jobby\BackgroundJob {
    public function __construct($job, array $config, Helper $helper = null) {
        parent::__construct($job, $config, $helper);
        if (isset($config['tmpDir'])) {
            $this->tmpDir = $config['tmpDir'];
        }
    }
}