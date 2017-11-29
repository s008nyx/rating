<?php

namespace ThemeHouse\Reactions\Import\Importer;

use XF\Import\Importer\AbstractImporter;

abstract class AbstractReactionImporter extends AbstractImporter
{
    public function canRetainIds()
    {
        return false;
    }

    public function getFinalizeJobs(array $stepsRun)
    {
        $jobs = [
            'ThemeHouse\Reactions:ConvertLike',
            'ThemeHouse\Reactions:ContentReactCount',
            'ThemeHouse\Reactions:ReactIsCounted',
            'ThemeHouse\Reactions:UserReactCount',
        ];

        return $jobs;
    }

    public function resetDataForRetainIds()
    {
        return false;
    }
}