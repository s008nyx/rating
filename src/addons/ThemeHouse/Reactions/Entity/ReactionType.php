<?php

namespace ThemeHouse\Reactions\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ReactionType extends Entity
{
    public function getDefaultReactionTypes()
    {
        return [
            'positive',
            'neutral',
            'negative'
        ];
    }

    public function getProtectedReactionTypes()
    {
        return [
            'all',
        ];
    }

    public function canDeleteReactionType()
    {
        $reactions = $this->finder('ThemeHouse\Reactions:Reaction')
            ->where('reaction_type_id', 'LIKE', $this->reaction_type_id)
            ->fetch();

        if ($reactions->count()) {
            return false;
        }

        return true;
    }

	protected function verifyReactionTypeId($value, $key)
	{
        $value = strtolower($value);
        if (in_array($value, $this->getProtectedReactionTypes())) {
			$this->error(\XF::phrase('th_protected_reaction_type_id_reactions'), $key);
			return false;
        }

        $this->reaction_type_id = $value;

		return true;
	}

    protected function _preDelete()
    {
        if (in_array($this->reaction_type_id, $this->getDefaultReactionTypes())) {
            $this->error(\XF::phrase('th_cannot_delete_default_reaction_types_reactions'));
            return false;
        }

        if (!$this->canDeleteReactionType()) {
            $this->error(\XF::phrase('th_reactions_exist_using_reaction_type_reactions'));
            return false;
        }

        return true;
    }

    protected function _postSave()
    {
        $this->rebuildReactionTypeCache();
    }

    protected function _postDelete()
    {
        $this->rebuildReactionTypeCache();
    }

    protected function rebuildReactionTypeCache()
    {
        $repo = $this->getReactionTypeRepo();

        \XF::runOnce('reactionTypeCache', function() use ($repo) {
            $repo->rebuildReactionTypeCache();
        });
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_reaction_type';
        $structure->shortName = 'ThemeHouse\Reactions:ReactionType';
        $structure->primaryKey = 'reaction_type_id';
        $structure->columns = [
            'reaction_type_id' => ['type' => self::STR, 'maxLength' => 25,
                'unique' => 'th_reaction_type_id_must_be_unique_reactions',
                'verify' => 'verifyReactionTypeId',
            ],
            'title' => ['type' => self::STR, 'maxLength' => 50,
                'required' => 'please_enter_valid_title'
            ],
            'color' => ['type' => self::STR, 'maxLength' => 25,
                'required' => 'th_please_choose_color_reactions'
            ],
            'display_order' => ['type' => self::UINT, 'default' => 10]
        ];

        return $structure;
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\Reaction
     */
    protected function getReactionRepo()
    {
        return $this->repository('ThemeHouse\Reactions:Reaction');
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\ReactionType
     */
    protected function getReactionTypeRepo()
    {
        return $this->repository('ThemeHouse\Reactions:ReactionType');
    }
}