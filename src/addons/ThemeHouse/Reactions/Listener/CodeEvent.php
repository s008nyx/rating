<?php

namespace ThemeHouse\Reactions\Listener;

use XF\Container;

class CodeEvent
{
    public static function appSetup(\XF\App $app)
    {
        $app->offsetSet('reactions', $app->fromRegistry('reactions',
            function(Container $c) { return $c['em']->getRepository('ThemeHouse\Reactions:Reaction')->rebuildReactionCache(); }
        ));

        $app->offsetSet('reactionTypes', $app->fromRegistry('reactionTypes',
            function(Container $c) { return $c['em']->getRepository('ThemeHouse\Reactions:ReactionType')->rebuildReactionTypeCache(); }
        ));
    }

    public static function templaterGlobalData(\XF\App $app, array &$data) {
        try {
            $data['reactions'] = $app->container('reactions');
            $data['reactionTypes'] = $app->container('reactionTypes');
        } catch (\Exception $e) {}
    }
}