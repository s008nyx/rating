<?php

namespace ThemeHouse\Reactions\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class UserReactionCount extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_reaction_user_count';
        $structure->shortName = 'ThemeHouse\Reactions:UserReactionCount';
        $structure->primaryKey = ['user_id', 'reaction_id'];
        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],
            'reaction_id' => ['type' => self::UINT, 'required' => true],
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
            'count_received' => ['type' => self::UINT, 'default' => 0],
            'count_given' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$user_id']],
                'primary' => true
            ],
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
                    ['react_user_id', '=', '$user_id'],
                    ['reaction_id', '=', '$reaction_id'],
                    ['content_type', '=', '$content_type']
                ],
                'primary' => true
            ],
        ];

        return $structure;
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\UserReactionCount
     */
    protected function getUserReactionCountRepo()
    {
        return $this->repository('ThemeHouse\Reactions:UserReactionCount');
    }
}