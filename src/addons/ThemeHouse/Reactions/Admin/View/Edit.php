<?php

namespace ThemeHouse\Reactions\Admin\View;

class Edit extends \XF\Mvc\View
{
	public function renderHtml()
	{
		$reactHandlers = $this->params['reaction']->getReactHandler();
        foreach ($reactHandlers as $reactHandlerType) {
            $reactHandler = \XF::repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByType($reactHandlerType, true);
            $this->params['reactHandlersOptions'][$reactHandlerType] = $reactHandler->renderOptions(
                $this->renderer,
                $this->params
            );
        }
	}
}