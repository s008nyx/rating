<?php

namespace ThemeHouse\Reactions\Listener;

use XF\Mvc\Entity\Entity;

class EntityStructure
{
    public static function reactEntityHandlers(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        if (isset($structure->contentType) && $reactionHandler = \XF::Repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByType($structure->contentType)) {
            if ($stateField = $reactionHandler->getStateField()) {
                $structure->behaviors['ThemeHouse\Reactions:Reactable'] = ['stateField' => $stateField];
            }
            $structure->getters['react_users'] = true;
            $structure->columns['react_users'] = ['type' => Entity::SERIALIZED_ARRAY, 'default' => []];
            $structure->relations['Reacts'] = [
                'entity' => 'ThemeHouse\Reactions:ReactedContent',
                'type' => Entity::TO_MANY,
                'conditions' => [
                    ['content_type', '=', $structure->contentType],
                    ['content_id', '=', '$' . $structure->primaryKey]
                ],
                'key' => 'react_user_id',
                'order' => 'react_date'
            ];
        }
    }

    public static function xfPost(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        self::reactEntityHandlers($em, $structure);
    }

    public static function xfConversationMessage(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        self::reactEntityHandlers($em, $structure);
    }

    public static function xfProfilePost(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        self::reactEntityHandlers($em, $structure);
    }

    public static function xfProfilePostComment(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        self::reactEntityHandlers($em, $structure);
    }

    public static function reactFirstEntity(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['first_react_users'] = ['type' => Entity::SERIALIZED_ARRAY, 'default' => []];
    }

    public static function xfThread(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        self::reactFirstEntity($em, $structure);
    }

    public static function xfConversationMaster(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        self::reactFirstEntity($em, $structure);
    }

    public static function xfUser(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['react_count'] = ['type' => Entity::SERIALIZED_ARRAY, 'default' => []];
    }
}