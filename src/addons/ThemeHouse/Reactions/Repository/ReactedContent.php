<?php

namespace ThemeHouse\Reactions\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Entity;

class ReactedContent extends Repository
{
    /**
     * @param string $contentType
     * @param int $contentId
     * @param int $userId
     *
     * @return \XF\Entity\ReactedContent|null
     */
    public function getReactByContentAndReactor($contentType, $contentId, $userId)
    {
        return $this->finder('ThemeHouse\Reactions:ReactedContent')->where([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'react_user_id' => $userId
        ])->fetch();
    }

    /**
     * @param int $reactionId
     * @param string $contentType
     * @param int $contentId
     * @param int $userId
     *
     * @return \XF\Entity\ReactedContent|null
     */
    public function getReactByReactionContentReactor($reactionId, $contentType, $contentId, $userId)
    {
        return $this->finder('ThemeHouse\Reactions:ReactedContent')->where([
            'reaction_id' => $reactionId,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'react_user_id' => $userId
        ])->fetchOne();
    }

    /**
     * @param string $contentType
     * @param int $contentId
     *
     * @return Finder
     */
    public function findContentReacts($contentType, $contentId)
    {
        return $this->finder('ThemeHouse\Reactions:ReactedContent')
            ->where([
                'content_type' => $contentType,
                'content_id' => $contentId,
                'is_counted' => 1,
            ])->setDefaultOrder('react_date', 'DESC');
    }

    /**
     * @param $contentUserId
     *
     * @return Finder
     */
    public function findReactionsByContentUserId($contentUserId, $counted = 1)
    {
        if ($contentUserId instanceof \XF\Entity\User) {
            $contentUserId = $contentUserId->user_id;
        }

        return $this->finder('ThemeHouse\Reactions:ReactedContent')
            ->where([
                'content_user_id' => $contentUserId,
                'is_counted' => ($counted ? 1 : 0)
            ])->setDefaultOrder('react_date');
    }

    /**
     * @param $reactUserId
     *
     * @return Finder
     */
    public function findReactionsByReactUserId($reactUserId)
    {
        if ($reactUserId instanceof \XF\Entity\User) {
            $reactUserId = $reactUserId->user_id;
        }

        return $this->finder('ThemeHouse\Reactions:ReactedContent')
            ->where('react_user_id', $reactUserId)
            ->setDefaultOrder('react_date');
    }


    public function insertReact(Entity $entity, $react, $publish = true, $skipAlert = false)
    {
        $reactHandler = $this->getReactHandlerByEntity($entity, true);

        $react->is_counted = $reactHandler->reactsCounted($entity) && $reactHandler->reactionCounted($react->reaction_id);
        $react->save();

        if ($publish) {
            if ($react->Owner && !$skipAlert) {
                $reactHandler->sendReactAlert($react, $entity);
            }

            if ($react->Reactor) {
                $reactHandler->publishReactNewsFeed($react, $entity, $react);
            }
        }

        return $react;
    }

    public function deleteReact(Entity $entity, $react)
    {
        if (!$react->react_id) {
            $existingReacts = $this->getReactByContentAndReactor($react->content_type, $react->content_id, $react->react_user_id);
            if (!$existingReacts) {
                return false;
            }

            $reactCount = [];
            foreach ($existingReacts as $react) {
                $react->setNewReactCount($reactCount);
                $react->delete();
                $reactCount = $react->getNewReactCount();
            }
        }

        if ($react->react_id) {
            $existingReact = $this->getReactByReactionContentReactor($react->reaction_id, $react->content_type, $react->content_id, $react->react_user_id);
            if (!$existingReact) {
                return false;
            }

            $existingReact->delete();
        }

        return true;
    }

    /**
     * @param \ThemeHouse\Reactions\Entity\ReactedContent[] $reacts
     */
    public function addContentToReacts($reacts)
    {
        $contentMap = [];
        foreach ($reacts AS $key => $react) {
            $contentType = $react->content_type;
            if (!isset($contentMap[$contentType])) {
                $contentMap[$contentType] = [];
            }

            $contentMap[$contentType][$key] = $react->content_id;
        }

        foreach ($contentMap AS $contentType => $contentIds) {
            $handler = $this->getReactHandlerByType($contentType);
            if (!$handler) {
                continue;
            }

            $data = $handler->getContent($contentIds, false);

            foreach ($contentIds AS $reactId => $contentId) {
                $content = isset($data[$contentId]) ? $data[$contentId] : null;
                $reacts[$reactId]->setContent($content);
            }
        }
    }

    public function convertLikeToReaction(\XF\Entity\LikedContent $like, \ThemeHouse\Reactions\Entity\Reaction $likeReaction = null)
    {
        if ($likeReaction === null) {
            $likeReaction = $this->finder('ThemeHouse\Reactions:Reaction')->where('like_wrapper', '=', 1)->fetchOne();
        }

        if (!$likeReaction) {
            return false;
        }

        $existingReact = $this->getReactByReactionContentReactor($likeReaction->reaction_id, $like->content_type, $like->content_id, $like->like_user_id);

        if (!$existingReact) {
            $react = $this->em->create('ThemeHouse\Reactions:ReactedContent');
            $react->bulkSet([
                'reaction_id' => $likeReaction->reaction_id,
                'react_user_id' => $like->like_user_id,
                'content_type' => $like->content_type,
                'content_id' => $like->content_id,
                'content_user_id' => $like->content_user_id,
                'react_date' => $like->like_date,
            ]);

            $react->save();
        }

        return true;
    }

    public function buildReactedContent(Entity $entity, $reactionId = false)
    {
        $react = $this->getReactByReactionContentReactor($reactionId, $entity->getEntityContentType(), $entity->getEntityId(), \XF::visitor()->user_id);

        $reactHandler = $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByEntity($entity, true);

        if (!$react) {
            $react = $this->em->create('ThemeHouse\Reactions:ReactedContent');
            $react->bulkSet([
                'content_id' => $entity->getEntityId(),
                'content_type' => $entity->getEntityContentType(),
                'react_user_id' => \XF::visitor()->user_id,
                'content_user_id' => $reactHandler->getContentUserId($entity)
            ]);

            if ($reactionId) {
                $react->set('reaction_id', $reactionId);
            }
        }

        return $react;
    }

    public function rebuildContentReactCache($contentType, $contentId, $throw = true)
    {
        $reactHandler = $this->getReactHandlerByType($contentType, $throw);
        if (!$reactHandler) {
            return false;
        }

        $entity = $reactHandler->getContent($contentId, false);
        if (!$entity) {
            if ($throw) {
                throw new \InvalidArgumentException("No entity found for '$contentType' with ID $contentId");
            }

            return false;
        }

        $count = $this->db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_th_reacted_content
            WHERE is_counted = 1 AND content_type = ? AND content_id = ?
        ", [$contentType, $contentId]);

        if ($count) {
            $latest = $this->db()->fetchAll("
                SELECT reacted.react_user_id AS user_id, reacted.reaction_id
                FROM xf_th_reacted_content AS reacted
                WHERE reacted.is_counted = 1 AND reacted.content_type = ? AND reacted.content_id = ?
                ORDER BY reacted.react_date DESC
            ", [$contentType, $contentId]);
        } else {
            $latest = [];
        }

        $reactHandler->updateContentReacts($entity, $latest);

        return true;
    }

    public function recalculateReactIsCounted($contentType, $contentIds, $updateReactCount = true)
    {
        $reactHandler = $this->getReactHandlerByType($contentType, true);

        if (!is_array($contentIds)) {
            $contentIds = [$contentIds];
        }

        if (!$contentIds) {
            return;
        }

        $entities = $reactHandler->getContent($contentIds, false);
        $enableIds = [];
        $disableIds = [];

        foreach ($entities AS $id => $entity) {
            if ($reactHandler->reactsCounted($entity)) {
                $enableIds[] = $id;
            } else {
                $disableIds[] = $id;
            }
        }

        if ($enableIds) {
            $this->fastUpdateReactIsCounted($contentType, $enableIds, true, $updateReactCount);
        }

        if ($disableIds) {
            $this->fastUpdateReactIsCounted($contentType, $disableIds, false, $updateReactCount);
        }
    }

    public function fastUpdateReactIsCounted($contentType, $contentIds, $newValue, $updateReactCount = true)
    {
        if (!is_array($contentIds)) {
            $contentIds = [$contentIds];
        }

        if (!$contentIds) {
            return;
        }

        $newDbValue = $newValue ? 1 : 0;
        $oldDbValue = $newValue ? 0 : 1;

        $db = $this->db();
        if ($updateReactCount) {
            $updates = $db->fetchPairs("
                SELECT content_user_id, COUNT(*)
                FROM xf_th_reacted_content
                WHERE content_type = ?
                    AND content_id IN (" . $db->quote($contentIds) . ")
                    AND is_counted = ?
                GROUP BY content_user_id
            ", [$contentType, $oldDbValue]);
            if ($updates) {
                $db->beginTransaction();

                $db->update('xf_th_reacted_content',
                    ['is_counted' => $newDbValue],
                    'content_type = ?
                        AND content_id IN (' . $db->quote($contentIds) . ')
                        AND is_counted = ?',
                    [$contentType, $oldDbValue]
                );

                // $operator = $newDbValue ? '+' : '-';
                // unset($updates[0]);
                // foreach ($updates AS $userId => $totalChange) {
                    // $db->query("
                        // UPDATE xf_user
                        // SET react_count = GREATEST(0, react_count {$operator} ?)
                        // WHERE user_id = ?
                    // ", [$totalChange, $userId]);
                // }

                $db->commit();
            }
        } else {
            $db->update('xf_th_reacted_content',
                ['is_counted' => $newDbValue],
                'content_type = ?
                    AND content_id IN (' . $db->quote($contentIds) . ')
                    AND is_counted = ?',
                [$contentType, $oldDbValue]
            );
        }
    }

    public function fastDeleteReacts($contentType, $contentIds, $updateReactCount = true)
    {
        if (!is_array($contentIds)) {
            $contentIds = [$contentIds];
        }

        if (!$contentIds) {
            return;
        }

        $db = $this->db();

        if ($updateReactCount) {
            $updates = $db->fetchPairs("
                SELECT content_user_id, COUNT(*)
                FROM xf_th_reacted_content
                WHERE content_type = ?
                    AND content_id IN (" . $db->quote($contentIds) . ")
                    AND is_counted = 1
                GROUP BY content_user_id
            ", $contentType);
        } else {
            $updates = [];
        }

        $db->beginTransaction();
        if ($updates) {
            // unset($updates[0]);
            // foreach ($updates AS $userId => $totalChange) {
                // $db->query("
                    // UPDATE xf_user
                    // SET react_count = GREATEST(0, react_count - ?)
                    // WHERE user_id = ?
                // ", [$totalChange, $userId]);
            // }
        }

        $db->delete('xf_th_reacted_content',
            'content_type = ? AND content_id IN (' . $db->quote($contentIds) . ')',
            $contentType
        );

        $db->commit();
    }

    public function countUserReacts($userId, $content)
    {
        if ($userId instanceof \XF\Entity\User) {
            $userId = $userId->user_id;
        }

        if ($content instanceof Entity) {
            $content = $content->react_users;
        }

        if (empty($content)) {
            return false;
        }

        $count = 0;
        foreach ($content as $reactUser) {
            if ($reactUser->react_user_id == $userId) {
                $count++;
            }
        }

        return $count;
    }

    public function checkIfCurrentMaxExceedsPermissions($user, $content)
    {
        if ($content instanceof Entity) {
            $content = $content->react_users;
        }

        $maxPerContent = $user->hasPermission('thReactions', 'maxReactsPerContent');
        $currentMax = $this->countUserReacts($user, $content);
        if ($maxPerContent > 0 && $currentMax < $maxPerContent) {
            return false;
        }

        return $currentMax;
    }

    public function getUserReactCount($userId)
    {
        if ($userId instanceof \XF\Entity\User) {
            $userId = $userId->user_id;
        }

        return $this->db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_th_reacted_content
            WHERE content_user_id = ?
                AND is_counted = 1
        ", $userId);
    }

    public function getUserReactCountDaily($userId)
    {
        if ($userId instanceof \XF\Entity\User) {
            $userId = $userId->user_id;
        }

        $dailyCounts = $this->db()->fetchPairs("
            SELECT reaction_id, COUNT(*) AS count
            FROM xf_th_reacted_content
            WHERE react_user_id = ?
                AND DATE(FROM_UNIXTIME(react_date)) = CURDATE()
                GROUP BY reaction_id
        ", $userId);

        $dailyCounts['total'] = 0;
        foreach ($dailyCounts as $ratingId => $count) {
            $dailyCounts['total'] = $dailyCounts['total'] + $count;
        }

        return $dailyCounts;
    }

    public function sortReactsByType($reacts, $showAll = false)
    {
        $sortedReacts = [];
        $reactionTypes = \XF::app()->container('reactionTypes');

        foreach ($reacts as $react) {
            if ($showAll) {
                $sortedReacts['all'][] = $react;
            }

            if (array_key_exists($react->Reaction->reaction_type_id, $reactionTypes)) {
                $sortedReacts[$react->Reaction->reaction_type_id][] = $react;
            }
        }

        return $sortedReacts;
    }

    public function sortReactsByReactionsAndTypes($reacts, $showAll = false)
    {
        $sortedReacts = [];
        $reactionTypes = \XF::app()->container('reactionTypes');
        foreach ($reacts as $react) {
            if ($showAll) {
                $sortedReacts['all'][$react->reaction_id][] = $react;
            }

            if (array_key_exists($react->Reaction->reaction_type_id, $reactionTypes)) {
                $sortedReacts[$react->Reaction->reaction_type_id][$react->reaction_id][] = $react;
            }
        }

        return $sortedReacts;
    }

    /**
     * @param $userId
     *
     * @return Finder
     */
    public function findUserReacts($userId)
    {
        if ($userId instanceof \XF\Entity\User) {
            $userId = $userId->user_id;
        }

        $finder = $this->finder('ThemeHouse\Reactions:ReactedContent')
            ->with('Reactor')
            ->where('content_user_id', $userId)
            ->setDefaultOrder('react_date', 'DESC');

        return $finder;
    }

    public function getReactHandlerByEntity(Entity $entity, $throw = false)
    {
        return $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByEntity($entity, $throw);
    }

    public function getReactHandlerByType($type, $throw = false)
    {
        return $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByType($type, $throw);
    }
}