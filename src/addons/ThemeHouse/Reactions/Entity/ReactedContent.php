<?php

namespace ThemeHouse\Reactions\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository;

class ReactedContent extends Entity
{
    public function canView(&$error = null)
    {
        $handler = $this->getHandler();
        $content = $this->Content;

        if ($handler && $content) {
            return $handler->canViewContent($content, $this, $error);
        } else {
            return false;
        }
    }

    public function canReact(&$error = null)
    {
        $handler = $this->getHandler();
        $content = $this->Content;

        if ($handler && $content) {
            return $handler->canReactContent($content, $this, $error);
        } else {
            return false;
        }
    }

    public function canUnreact(&$error = null)
    {
        $handler = $this->getHandler();
        $content = $this->getContent();

        if ($handler && $content) {
            return $handler->canUnreactContent($content, $this, $error);
        } else {
            return false;
        }
    }

    public function getHandler()
    {
        return $this->getReactHandlerRepo()->getReactHandlerByType($this->content_type);
    }

    /**
     * @return null|Entity
     */
    public function getContent()
    {
        $handler = $this->getHandler();
        return $handler ? $handler->getContent($this->content_id) : null;
    }

    public function setContent(Entity $content = null)
    {
        $this->_getterCache['Content'] = $content;
    }

    public function getNewReactCount()
    {
        if (array_key_exists('NewReactCount', $this->_getterCache)) {
            return $this->_getterCache['NewReactCount'];
        }

        return false;
    }

    public function setNewReactCount(array $newReactCount = [])
    {
        $this->_getterCache['NewReactCount'] = $newReactCount;
    }

    public function render()
    {
        $handler = $this->getHandler();
        return $handler ? $handler->render($this) : '';
    }

    protected function _postSave()
    {
		if ($this->isInsert()) {
			if ($this->is_counted) {
				$this->adjustReactCount($this->react_user_id, $this->content_user_id, $this->reaction_id, $this->content_id, $this->content_type, 1);
			}

			$this->toggleLike();
		} else {
			if ($this->isChanged('content_user_id')) {
				if ($this->getExistingValue('is_counted')) {
					$this->adjustReactCount($this->react_user_id, $this->getExistingValue('content_user_id'), $this->reaction_id, $this->content_id, $this->content_type, -1);
				}
				if ($this->is_counted) {
					$this->adjustReactCount($this->react_user_id, $this->content_user_id, $this->reaction_id, $this->content_id, $this->content_type, 1);
				}
			} else if ($this->isChanged('is_counted')) {
				$this->adjustReactCount($this->react_user_id, $this->content_user_id, $this->reaction_id, $this->content_id, $this->content_type, $this->is_counted ? 1 : -1);
			}
		}

        if ($this->isChanged(['reaction_id', 'content_type', 'content_id', 'content_user_id', 'react_date', 'react_user_id'])) {
            $this->rebuildContentReactCache();
        }
    }

    protected function _postDelete()
    {
		if ($this->is_counted) {
			$this->adjustReactCount($this->react_user_id, $this->content_user_id, $this->reaction_id, $this->content_id, $this->content_type, -1);
		}

        $this->rebuildContentReactCache();

        $handler = $this->getHandler();
        if ($handler) {
            $handler->removeReactAlert($this);
            $handler->unpublishReactNewsFeed($this);
        }

        $this->toggleLike(false);
    }

    public function toggleLike($isCreate = true)
    {
        if (!$this->Reaction) {
            return false;
        }

        $reaction = $this->Reaction;
        if ($reaction->like_wrapper) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');

            $existingLike = $likeRepo->getLikeByContentAndLiker($this->content_type, $this->content_id, $this->react_user_id);

            if ($isCreate && !$existingLike) {
                $likeRepo->insertLike($this->content_type, $this->content_id, $this->Reactor, false);
            }

            if (!$isCreate && $existingLike) {
                $existingLike->delete();
            }
        }
    }

	protected function adjustReactCount($reactorUserId, $reactedUserId, $reactionId, $contentId, $contentType, $amount)
	{
		if (!$reactorUserId && !$reactedUserId) {
			return;
		}

        $this->adjustUserReactCount($reactionId, $amount);

		$this->db()->query("
            INSERT INTO xf_th_reaction_user_count (user_id, reaction_id, content_type) VALUES
                (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                count_given = GREATEST(0, count_given + ?)
        ", [
            $reactorUserId, $reactionId, $contentType, $amount,
        ]);

		$this->db()->query("
            INSERT INTO xf_th_reaction_user_count (user_id, reaction_id, content_type) VALUES
                (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                count_received = GREATEST(0, count_received + ?)
        ", [
            $reactedUserId, $reactionId, $contentType, $amount
        ]);

		$this->db()->query("
		    INSERT INTO xf_th_reaction_content_count (content_id, content_type, reaction_id) VALUES
		        (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                count = GREATEST(0, count + ?)
        ", [
            $contentId, $contentType, $reactionId, $amount
        ]);
	}

    protected function adjustUserReactCount($reactionId, $amount)
    {
        $reaction = $this->getReactionRepo()->getReactionById($reactionId);

        if (!$reactCount = $this->getNewReactCount()) {
            $reactCount = $this->Owner->react_count;
        }

        $reactCount[$reaction['reaction_type_id']] = max(0, (isset($reactCount[$reaction['reaction_type_id']]) ? $reactCount[$reaction['reaction_type_id']] : 0) + $amount);
        $this->setNewReactCount($reactCount);

		$this->db()->query("
			UPDATE xf_user
			SET react_count = ?
			WHERE user_id = ?
		", [serialize($reactCount), $this->content_user_id]);
    }

    protected function rebuildContentReactCache()
    {
        $repo = $this->getReactRepo();
        $repo->rebuildContentReactCache($this->content_type, $this->content_id, $this->reaction_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_reacted_content';
        $structure->shortName = 'ThemeHouse\Reactions:ReactedContent';
        $structure->primaryKey = 'react_id';
        $structure->columns = [
            'react_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'reaction_id' => ['type' => self::UINT, 'required' => true],
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
            'content_id' => ['type' => self::UINT, 'required' => true],
            'react_user_id' => ['type' => self::UINT, 'required' => true],
            'react_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'content_user_id' => ['type' => self::UINT, 'required' => true],
            'is_counted' => ['type' => self::BOOL, 'default' => true],
        ];
        $structure->getters = [
            'Content' => true,
            'NewReactCount' => true
        ];
        $structure->relations = [
            'Reactor' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$react_user_id']],
                'primary' => true
            ],
            'UserReactorCount' => [
                'entity' => 'ThemeHouse\Reactions:UserReactionCount',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$react_user_id'],
                    ['reaction_id', '=', '$reaction_id'],
                    ['content_type', '=', '$content_type'],
                ],
                'primary' => true
            ],
            'UserReactedCount' => [
                'entity' => 'ThemeHouse\Reactions:UserReactionCount',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$content_user_id'],
                    ['reaction_id', '=', '$reaction_id'],
                    ['content_type', '=', '$content_type'],
                ],
                'primary' => true
            ],
            'Owner' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$content_user_id']],
                'primary' => true
            ],
            'Reaction' => [
                'entity' => 'ThemeHouse\Reactions:Reaction',
                'type' => self::TO_ONE,
                'conditions' => [['reaction_id', '=', '$reaction_id']],
                'primary' => true
            ],
        ];

        $structure->defaultWith[] = 'Owner';

        return $structure;
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\ReactedContent
     */
    protected function getReactRepo()
    {
        return $this->repository('ThemeHouse\Reactions:ReactedContent');
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

    /**
     * @return \ThemeHouse\Reactions\Repository\ReactHandler
     */
    protected function getReactHandlerRepo()
    {
        return $this->repository('ThemeHouse\Reactions:ReactHandler');
    }
}