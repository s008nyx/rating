<?php

namespace ThemeHouse\Reactions\Pub\View;

use XF\Mvc\View;

class React extends View
{
    public function renderJson()
    {
        $content = $this->params['content'];
        $reacts = $this->params['reacts'];
        $contentDetails = $this->params['contentDetails'];
        $templateVars = $this->params['templateVars'];

        $templater = $this->renderer->getTemplater();
        $html = $templater->fn('reacts', [$content, $reacts, $contentDetails, $templateVars]);

        return [
            'html' => $this->renderer->getHtmlOutputStructure($html)
        ];
    }
}