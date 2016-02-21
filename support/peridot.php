<?php

// use Evenement\EventEmitterInterface;
// use Peridot\Plugin\Watcher\WatcherPlugin;
//
// return function(EventEmitterInterface $emitter) {
//         $watcher = new WatcherPlugin($emitter);
//         $watcher->track(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'source');
// };

use Evenement\EventEmitterInterface;
use Peridot\Reporter\CodeCoverage\AbstractCodeCoverageReporter;
use Peridot\Reporter\CodeCoverageReporters;

return function (EventEmitterInterface $eventEmitter) {
        (new CodeCoverageReporters($eventEmitter))->register();

        // $eventEmitter->on('peridot.start', function (\Peridot\Console\Environment $environment) {
        //         $environment->getDefinition()->getArgument('path')->setDefault(__DIR__ . '/../specs');
        // });

        $eventEmitter->on('code-coverage.start', function (AbstractCodeCoverageReporter $reporter) {
                /*
                $reporter->addDirectoryToWhitelist(__DIR__ . '/../source')
                         ->addFilesToBlacklist([__DIR__ . '/../source/FluidXml.php56.php',
                                                __DIR__ . '/../source/FluidXml.php70.php']);
                */
                $reporter->addDirectoryToWhitelist(__DIR__ . '/../source');
                // $reporter->addFilesToWhitelist([__DIR__ . '/../source/FluidXml.php']);
        });
};
