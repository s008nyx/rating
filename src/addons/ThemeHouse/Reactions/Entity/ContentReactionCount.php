<?php

namespace ThemeHouse\Reactions\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ContentReactionCount extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_reaction_content_count';
        $structure->shortName = 'ThemeHouse\Reactions:ContentReactionCount';
        $structure->primaryKey = ['content_id', 'content_type'];
        $structure->columns = [
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
            'content_id' => ['type' => self::UINT, 'required' => true],
            'reaction_id' => ['type' => self::UINT, 'required' => true],
            'count' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->relations = [
            'Reaction' => [
                'entity' => 'ThemeHouse\Reactions:Reaction',
                'type' => self::TO_ONE,
                'conditions' => [['reaction_id', '=', '$reaction_id']],
                'primary' => true
            ],
            'ReactedContent' => [
                'entity' => 'ThemeHouse\Reactions:ReactedContent',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['content_id', '=', '$content_id'],
                    ['reaction_id', '=', '$reaction_id'],
                    ['content_type', '=', '$content_type']
                ],
                'primary' => true
            ],
        ];

        return $structure;
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\ContentReactionCount
     */
    protected function getContentReactionCountRepo()
    {
        return $this->repository('ThemeHouse\Reactions:ContentReactionCount');
    }
}