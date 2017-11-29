<?php

namespace ThemeHouse\Reactions\Repository;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;

class ReactHandler extends Repository
{
    protected $reactHandlers = null;

    /**
     * @return \ThemeHouse\Reactions\React\AbstractHandler[]
     */
    public function getReactHandlers()
    {
        if ($this->reactHandlers === null) {
            $handlers = [];

            foreach (\XF::app()->getContentTypeField('react_handler_class') AS $contentType => $handlerClass) {
                if (class_exists($handlerClass)) {
                    $eclass = \XF::extendClass($handlerClass);
                    $handlers[$contentType] = [
                        'oclass' => $handlerClass,
                        'eclass' => $eclass,
                        'object' => new $eclass($contentType)
                    ];
                }
            }

            $this->reactHandlers = $handlers;
        }

        return $this->reactHandlers;
    }

    /**
     * @return array
     */
    public function getReactHandlersList()
    {
        $handlers = $this->getReactHandlers();

        $list = [];
        foreach ($handlers as $contentType => $handler) {
            $list[$contentType] = $handler['object']->getTitle();
        }

        return $list;
    }

    public function getReactHandlerByEntity(Entity $entity, $throw = false)
    {
        $reactHandler = $this->getReactHandlerByType($entity->getEntityContentType(), $throw);
        $reactHandler->setContent($entity);

        return $reactHandler;
    }

    public function getReactHandlerByType($type, $throw = false)
    {
        if (is_array($this->reactHandlers) && isset($this->reactHandlers[$type])) {
            $handlerClass = $this->reactHandlers[$type]['oclass'];
        } else {
            $handlerClass = \XF::app()->getContentTypeFieldValue($type, 'react_handler_class');
        }

        if (!$handlerClass) {
            if ($throw) {
                throw new \InvalidArgumentException("No react handler for '$type'");
            }

            return null;
        }

        if (!class_exists($handlerClass)) {
            if ($throw) {
                throw new \InvalidArgumentException("React handler for '$type' does not exist: $handlerClass");
            }

            return null;
        }

        $handlerClass = \XF::extendClass($handlerClass);

        if (!isset($this->reactHandlers[$type])) {
            $eclass = \XF::extendClass($handlerClass);
            $this->reactHandlers[$type] = [
                'oclass' => $handlerClass,
                'eclass' => $eclass,
                'object' => new $eclass($type)
            ];
        }

        return $this->reactHandlers[$type]['object'];
    }
}