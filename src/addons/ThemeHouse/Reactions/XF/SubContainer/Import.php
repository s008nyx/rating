<?php

namespace ThemeHouse\Reactions\XF\SubContainer;

class Import extends XFCP_Import
{
    public function initialize()
    {
        $initialize = parent::initialize();

        $importers = $this->container('importers');

        $this->container['importers'] = function() use ($importers) {
            $importers[] = 'ThemeHouse\Reactions:DarkPostRatings';
            return $importers;
        };

        return $initialize;
    }
}