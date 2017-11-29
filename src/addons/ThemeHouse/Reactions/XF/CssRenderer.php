<?php

namespace ThemeHouse\Reactions\XF;

class CssRenderer extends XFCP_CssRenderer
{
    protected function getRenderParams()
    {
        $params = parent::getRenderParams();

        if ($this->includeExtraParams) {
            $params['reactions'] = $this->app->container('reactions');
            $params['reactionTypes'] = $this->app->container('reactionTypes');
        }

        return $params;
    }
}