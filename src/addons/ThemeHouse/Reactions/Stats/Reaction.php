<?php

namespace ThemeHouse\Reactions\Stats;

use XF\Admin\App;
use XF\Stats\AbstractHandler;

class Reaction extends AbstractHandler
{
    protected static $reactHandlers;
    protected static $reactStatsTypes = [];

    public static function getReactHandlers()
    {
        if (self::$reactHandlers === null) {
            $reactHandlerRepo = \XF::repository('ThemeHouse\Reactions:ReactHandler');
            self::$reactHandlers = $reactHandlerRepo->getReactHandlers();
        }

        return self::$reactHandlers;
    }

    public function getStatsTypes()
    {
        if (empty(self::$reactStatsTypes)) {
            foreach (self::getReactHandlers() as $reactHandler) {
                self::$reactStatsTypes['reaction_' . $reactHandler['object']->getContentType()] = $reactHandler['object']->getTitle() . ' ' . \XF::phrase('th_reactions_lc_reactions');
            }
        }

        return self::$reactStatsTypes;
    }

    public function getData($start, $end)
    {
        $db = $this->db();

        $content = [];
        foreach (self::getReactHandlers() as $reactHandler) {
            $handler = $reactHandler['object'];

            $content['reaction_' . $handler->getContentType()] = $db->fetchPairs(
                $this->getBasicDataQuery('xf_th_reacted_content', 'react_date', 'content_type = ?'),
                [$start, $end, $handler->getContentType()]
            );
        }

        return $content;
    }
}