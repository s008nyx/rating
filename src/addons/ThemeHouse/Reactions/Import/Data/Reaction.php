<?php

namespace ThemeHouse\Reactions\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class Reaction extends AbstractEmulatedData
{
    public function getImportType()
    {
        return 'th_reaction';
    }

    public function getEntityShortName()
    {
        return 'ThemeHouse\Reactions:Reaction';
    }
}