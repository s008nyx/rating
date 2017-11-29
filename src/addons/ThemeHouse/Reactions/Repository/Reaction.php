<?php

namespace ThemeHouse\Reactions\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Reaction extends Repository
{
    /**
     * @return Finder
     */
    public function findReactionsForList()
    {
        return $this->finder('ThemeHouse\Reactions:Reaction')
            ->order('display_order');
    }

    public function getReactionList()
    {
        $reactions = $this->findReactionsForList()->fetch();

        return $reactions->toArray();
    }

    public function getReactionById($reactionId)
    {
        $reactions = \XF::app()->container('reactions');
        if (!array_key_exists($reactionId, $reactions)) {
            return false;
        }

        return $reactions[$reactionId];
    }

    public function getReactionCacheData()
    {
        $reactions = $this->finder('ThemeHouse\Reactions:Reaction')
            ->order(['display_order', 'title'])
            ->fetch();

        $cache = [];

        foreach ($reactions AS $reactionId => $reaction) {
            $reaction = $reaction->toArray();

            if (!$reaction['enabled']) {
                continue;
            }

            if ($reaction['image_type'] == 'sprite' && !empty($reaction['styling']['image_sprite'])) {
                $unit = htmlspecialchars($reaction['styling']['image_sprite']['u']);

                $reaction['image_sprite_css'] = sprintf('width: %1$d%2$s; height: %3$d%4$s; background: url(\'%5$s\') no-repeat %6$d%7$s %8$d%9$s;',
                    (int) $reaction['styling']['image_sprite']['w'],
                    $unit,
                    (int) $reaction['styling']['image_sprite']['h'],
                    $unit,
                    htmlspecialchars($reaction['image_url']),
                    (int) $reaction['styling']['image_sprite']['x'],
                    $unit,
                    (int) $reaction['styling']['image_sprite']['y'],
                    $unit
                );

                if (!empty($reaction['styling']['image_sprite']['bs'])) {
                    $reaction['image_sprite_css'] .= ' background-size: ' . htmlspecialchars($reaction['styling']['image_sprite']['bs']) . $unit . ';';
                }
            }

            if ($reaction['styling_type'] == 'html_css') {
                foreach ($reaction['styling']['html_css'] as $stylingType => $content) {
                    $reaction['styling']['html_css'][$stylingType] = str_replace('{reactionId}', $reactionId, $content);
                }
            }

            $cache[$reactionId] = $reaction;
        }

        return $cache;
    }

    public function rebuildReactionCache()
    {
        $cache = $this->getReactionCacheData();
        \XF::registry()->set('reactions', $cache);

        $this->repository('XF:Style')->updateAllStylesLastModifiedDate();

        return $cache;
    }
}