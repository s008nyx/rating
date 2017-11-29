<?php

namespace ThemeHouse\Reactions\Admin\View;

class Export extends \XF\Mvc\View
{
	public function renderXml()
	{
		/** @var \DOMDocument $document */
		$document = $this->params['xml'];

		$this->response->setDownloadFileName('reactions.xml');

		return $document->saveXml();
	}
}