<?php

namespace ThemeHouse\Reactions\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ReactionType extends Repository
{
    /**
     * @return Finder
     */
    public function findReactionTypesForList()
    {
        return $this->finder('ThemeHouse\Reactions:ReactionType')
            ->order('display_order');
    }

    public function getReactionTypeList()
    {
        $reactionTypes = $this->findReactionTypesForList()->fetch();

        return $reactionTypes->toArray();
    }

    public function getReactionTypeCacheData()
    {
        $reactionTypes = $this->finder('ThemeHouse\Reactions:ReactionType')
            ->order(['display_order', 'title'])
            ->fetch();

        $cache = [];

        foreach ($reactionTypes AS $reactionTypeId => $reactionType) {
            $reactionType = $reactionType->toArray();

            $cache[$reactionTypeId] = $reactionType;
        }

        return $cache;
    }

    public function rebuildReactionTypeCache()
    {
        $cache = $this->getReactionTypeCacheData();
        \XF::registry()->set('reactionTypes', $cache);

        $this->repository('XF:Style')->updateAllStylesLastModifiedDate();

        return $cache;
    }
}