<?php

namespace ThemeHouse\Reactions\Admin\View;

class Handler extends \XF\Mvc\View
{
	public function renderJson()
	{
        $reactHandler = \XF::repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByType($this->params['react_handler_id'], true);

		return [
            'options_rendered' => $reactHandler->renderOptions($this->renderer, $this->params)
        ];
	}
}