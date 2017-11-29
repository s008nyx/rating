<?php

namespace ThemeHouse\Reactions\React;

use ThemeHouse\Reactions\Entity\ReactedContent;
use XF\Mvc\Entity\Entity;

abstract class AbstractHandler
{
    protected $contentType;

    protected $reactions;
    protected $validReactions;

    protected $globalData;
    protected $content;

    protected $contentCacheFields = [
        'cache' => 'react_users',
        'first_cache' => 'first_react_users'
    ];

    public function __construct($contentType)
    {
        $this->contentType = $contentType;
        try {
            $this->reactions = \XF::app()->container('reactions');
        } catch (\Exception $e) {}

        $this->buildGlobalData();
    }

    abstract public function getTitle();
    abstract public function reactsCounted(Entity $entity);
    abstract public function getLinkDetails();
    abstract public function isPublic();

    public function buildGlobalData()
    {
        $visitor = \XF::visitor();

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = \XF::repository('ThemeHouse\Reactions:ReactedContent');

        if (empty($this->globalData['userReactCount'])) {
            $this->globalData['userReactCount'] = $reactRepo->getUserReactCountDaily($visitor->user_id);  
        }
    }

    public function buildValidReactionsList($action = 'all', $useCache = true)
    {
        if ($useCache && $this->validReactions) {
            return $this->validReactions;
        }

        $visitor = \XF::visitor();

        $reactions = \XF::app()->container('reactions');
        foreach ($reactions as $reactionId => $reaction) {
            if (!$reaction['enabled']) {
                unset($reactions[$reactionId]); continue;
            }

            $userCriteria = \XF::app()->criteria('XF:User', $reaction['user_criteria']);
            if (!$userCriteria->isMatched($visitor)) {
                unset($reactions[$reactionId]); continue;
            }

            $reactHandler = (array) $reaction['react_handler'];
            if (!in_array($this->contentType, $reactHandler)) {
                unset($reactions[$reactionId]); continue;
            }

            if ($action == 'react' || $action == 'all') {
                if ($this->getContent()) {
                    if (!$this->canReactContent($this->getContent()) || !$this->canReactWithReaction($reactionId)) {
                        unset($reactions[$reactionId]); continue;
                    }
                } else if (!$this->canReactWithReaction($reactionId)) {
                    unset($reactions[$reactionId]); continue;
                }
            }

            if ($action == 'unreact' || $action == 'all') {
                if ($this->getContent()) {
                    if (!$this->canUnreactContent($this->getContent()) || !$this->canUnreactWithReaction($reactionId)) {
                        unset($reactions[$reactionId]); continue;
                    }
                } else if (!$this->canUnreactWithReaction($reactionId)) {
                    unset($reactions[$reactionId]); continue;
                }
            }
        }

        if ($useCache) {
            $this->validReactions = $this->reactions = $reactions;
        }

        return $reactions;
    }

    public function getReactionById($reactionId, $throw = false)
    {
        if (array_key_exists($reactionId, $this->reactions)) {
            return $this->reactions[$reactionId];
        }

        if ($throw) {
            throw new \LogicException("Could not find the reaction ID (" . $reactionId . ")!");
        }

        return false;
    }

    public function removeReactionById($reactionId)
    {
        if (array_key_exists($reactionId, $this->reactions)) {
            unset($this->reactions[$reactionId]);
        }

        return true;
    }

    public function canViewContent(Entity $entity, ReactedContent $react, &$error = null)
    {
        if (method_exists($entity, 'canView')) {
            return $entity->canView($react, $error);
        }

        return true;
    }

    public function canReactWithReaction($reactionId, &$error = null)
    {
        $reaction = $this->getReactionById($reactionId);
        if ($reaction && isset($this->globalData['userReactCount'][$reactionId])) {
            if (isset($reaction['options']['user_max_per_day']) && $reaction['options']['user_max_per_day'] && $this->globalData['userReactCount'][$reactionId] >= $reaction['options']['user_max_per_day']) {
                $error = \XF::phraseDeferred('th_exceeded_daily_reacts_for_x_reactions', ['title' => $reaction['title']]);
                $this->removeReactionById($reactionId);
                return false;
            }
        }

        return true;
    }

    public function canReactContent(Entity $entity, ReactedContent $react = null, &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id) {
            return false;
        }

        if ($react && $react->reaction_id) {
            $reaction = $this->getReactionById($react->reaction_id);
            if (!$reaction) {
                $error = \XF::phraseDeferred('th_reaction_does_not_exist_reactions');
                $this->removeReactionById($react->reaction_id);
                return false;
            }
        }

        if (!$visitor->hasPermission('thReactions', 'canReact')) {
            $error = \XF::phraseDeferred('th_no_permission_to_react_reactions');
            return false;
        }

        if ($entity->user_id == $visitor->user_id) {
            $error = \XF::phraseDeferred('th_reaction_own_content_cheating_reactions');
            return false;
        }

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = \XF::repository('ThemeHouse\Reactions:ReactedContent');

        $exceedsMax = $reactRepo->checkIfCurrentMaxExceedsPermissions($visitor, $entity);
        if ($exceedsMax) {
            $error = \XF::phraseDeferred('th_exceeded_x_reacts_for_content_reactions', ['count' => $exceedsMax]);
            return false;
        }

        $dailyReactLimit = $visitor->hasPermission('thReactions', 'dailyReactLimit');
        if ($dailyReactLimit > 0 && $dailyReactLimit >= $this->globalData['userReactCount']['total']) {
            $error = \XF::phraseDeferred('th_exceeded_daily_reacts_reactions');
            return false;
        }

        if ($react && $react->reaction_id && !$this->canReactWithReaction($react->reaction_id, $error)) {
            $error = \XF::phraseDeferred('th_exceeded_daily_reacts_reactions');
            $this->removeReactionById($react->reaction_id);
            return false;
        }

        if (method_exists($entity, 'canReact')) {
            return $entity->canReact($react, $error);
        }

        return true;
    }

    public function canUnreactWithReaction($reactionId, &$error = null)
    {
        $visitor = \XF::visitor();

        $reaction = $this->getReactionById($reactionId);
        if (!$reaction) {
            $error = \XF::phraseDeferred('th_reaction_does_not_exist_reactions');
            $this->removeReactionById($reactionId);
            return false;
        }

        if (!empty($reaction['options']['prevent_unreact'])) {
            $error = \XF::phraseDeferred('th_react_x_cannot_be_removed_reactions', ['title' => $reaction['title']]);
            $this->removeReactionById($reactionId);
            return false;
        }

        return true;
    }

    public function canUnreactContent(Entity $entity, ReactedContent $react = null, &$error = null)
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return false;
        }

        if ($react && $react->reaction_id) {
            $reaction = $this->getReactionById($react->reaction_id);
            if (!$reaction) {
                $error = \XF::phraseDeferred('th_reaction_does_not_exist_reactions');
                $this->removeReactionById($react->reaction_id);
                return false;
            }
        }

        if ($react && $react->reaction_id && !$this->canUnreactWithReaction($react->reaction_id, $error)) {
            $this->removeReactionById($react->reaction_id);
            return false;
        }

        if (!$visitor->hasPermission('thReactionsModerator', 'canRemoveAllReacts') && !$visitor->hasPermission('thReactions', 'canRemoveOwnReacts')) {
            $error = \XF::phraseDeferred('th_no_permission_to_remove_reacts_reactions');
            return false;
        }

        if ($react && ($react->react_user_id == $visitor->user_id)) {
            if (!$visitor->hasPermission('thReactions', 'canRemoveOwnReacts')) {
                $error = \XF::phraseDeferred('th_react_x_cannot_be_removed_reactions', ['title' => $reaction['title']]);
                return false;
            }
        } else if (!$visitor->hasPermission('thReactionsModerator', 'canRemoveAllReacts')) {
            $error = \XF::phraseDeferred('th_react_x_cannot_be_removed_reactions', ['title' => $reaction['title']]);
            return false;
        }

        if (method_exists($entity, 'canUnreact')) {
            return $entity->canUnreact($react, $error);
        }

        return true;
    }

    public function canViewReactsList(Entity $entity, ReactedContent $react, &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('thReactions', 'canViewReactsList')) {
            $error = \XF::phraseDeferred('th_no_permission_to_view_reactions_reactions');
            return false;
        }

        return true;
    }

    public function updateContentReacts(Entity $entity, array $latestReacts)
    {
        $cacheField = isset($this->contentCacheFields['cache']) ? $this->contentCacheFields['cache'] : false;
        if (!$cacheField) {
            return false;
        }

        $entity->$cacheField = $latestReacts;
        $this->updateFirstContentCache($entity, $latestReacts);

        $entity->save();
    }

    public function updateFirstContentCache(Entity $entity, $latestReacts)
    {
        $cacheField = isset($this->contentCacheFields['first_cache']) ? $this->contentCacheFields['first_cache'] : false;
        if (!$cacheField) {
            return false;
        }

        return $this->reactsCounted($entity);
    }

    public function updateRecentCacheForUserChange($oldUserId, $newUserId)
    {
        if (empty($this->contentCacheFields['recent'])) {
            return;
        }

        $entityType = \XF::app()->getContentTypeEntity($this->contentType, false);
        if (!$entityType) {
            return;
        }

        $structure = \XF::em()->getEntityStructure($entityType);

        // note that xf_reacted_content must already be updated
        $oldFind = 's:7:"user_id";i:' . intval($oldUserId) . ';';
        $newReplace = 's:7:"user_id";i:' . intval($newUserId) . ';';

        $recentField = $this->contentCacheFields['recent'];
        $table = $structure->table;
        $primaryKey = $structure->primaryKey;

        \XF::db()->query("
            UPDATE (
                SELECT content_id FROM xf_reacted_content
                WHERE content_type = ?
                AND react_user_id = ?
            ) AS temp
            INNER JOIN {$table} AS react_table ON (react_table.`$primaryKey` = temp.content_id)
            SET react_table.`{$recentField}` = REPLACE(react_table.`{$recentField}`, ?, ?)
        ", [$this->contentType, $newUserId, $oldFind, $newReplace]);
    }

    public function getContentReactCaches(Entity $entity)
    {
        $cacheField = isset($this->contentCacheFields['cache']) ? $this->contentCacheFields['cache'] : false;
        $output = [];

        if ($cacheField) {
            $output['cache'] = $entity->$cacheField;
        }

        return $output;
    }

    public function renderOptions(\XF\Mvc\Renderer\AbstractRenderer $renderer, &$params)
    {
        return false;
    }

    public function verifyOptions(Entity $reaction, &$options, &$error)
    {
        return false;
    }

    public function sendReactAlert(ReactedContent $react, Entity $content)
    {
        $reaction = $this->getReactionById($react->reaction_id);
        if (isset($reaction['options']['alert']) && !$reaction['options']['alert']) {
            return false;
        }

        $canView = \XF::asVisitor($react->Owner, function() use ($content, $react) {
            return $this->canViewContent($content, $react);
        });

        if (!$canView) {
            return false;
        }

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = \XF::repository('XF:UserAlert');
        return $alertRepo->alertFromUser($react->Owner, $react->Reactor, $this->contentType, $react->content_id, 'react', ['reaction_id' => $react->reaction_id]);
    }

    public function removeReactAlert(ReactedContent $react)
    {
        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = \XF::repository('XF:UserAlert');
        $alertRepo->fastDeleteAlertsFromUser($react->react_user_id, $this->contentType, $react->content_id, 'react');
    }

    public function publishReactNewsFeed(ReactedContent $react, Entity $content)
    {
        $reaction = $this->getReactionById($react->reaction_id);
        if (isset($reaction['options']['newsfeed']) && !$reaction['options']['newsfeed']) {
            return false;
        }

        /** @var \XF\Repository\NewsFeed $newsFeedRepo */
        $newsFeedRepo = \XF::repository('XF:NewsFeed');
        $newsFeedRepo->publish($this->contentType, $react->content_id, 'react', $react->Reactor->user_id, $react->Reactor->username, ['reaction_id' => $react->reaction_id], $react->react_date);
    }

    public function unpublishReactNewsFeed(ReactedContent $react)
    {
        /** @var \XF\Repository\NewsFeed $newsFeedRepo */
        $newsFeedRepo = \XF::repository('XF:NewsFeed');
        $newsFeedRepo->unpublish($this->contentType, $react->content_id, $react->react_user_id, 'react');
    }

    public function reactionCounted($reactionId)
    {
        if ($reaction = $this->getReactionById($reactionId)) {
            if ($reaction['random']) {
                return false;
            }
        }

        return true;
    }

    public function getContentUserId(Entity $entity)
    {
        if (isset($entity->user_id)) {
            return $entity->user_id;
        } else if (isset($entity->User)) {
            $user = $entity->User;
            if ($user instanceof \XF\Entity\User) {
                return $user->user_id;
            } else {
                throw new \LogicException("Found a User relation but it did not match a user; please override");
            }
        }

        throw new \LogicException("Could not determine content user ID; please override");
    }

    public function getReturnLink(Entity $content, array $parameters = [])
    {
        $linkDetails = $this->getLinkDetails();
        return \XF::app()->router('public')->buildLink($linkDetails['link'], $content, $parameters);
    }

    public function getReactLink($contentId, $reactionId)
    {
        if ($contentId instanceof Entity) {
            $contentId = $contentId->getEntityId();
        }

        return \XF::app()->router('public')->buildLink('reactions/react/', ['content_type' => $this->contentType, 'content_id' => $contentId, 'reaction_id' => $reactionId]);
    }

    public function getUnreactSingleLink($reactId)
    {
        return \XF::app()->router('public')->buildLink('reactions/unreact/', ['react_id' => $reactId]);
    }

    public function getUnreactAllLink($contentId)
    {
        if ($contentId instanceof Entity) {
            $contentId = $contentId->getEntityId();
        }

        return \XF::app()->router('public')->buildLink('reactions/unreacts/', ['content_type' => $this->contentType, 'content_id' => $contentId]);
    }

    public function getModifyReactLink($contentId)
    {
        if ($contentId instanceof Entity) {
            $contentId = $contentId->getEntityId();
        }

        return \XF::app()->router('public')->buildLink('reactions/modify/', ['content_type' => $this->contentType, 'content_id' => $contentId]);
    }

    public function getListLink($contentId, $simple = false)
    {
        if ($simple) {
            return 'reactions/list/' . $this->contentType;
        }

        if ($contentId instanceof Entity) {
            $contentId = $contentId->getEntityId();
        }

        return \XF::app()->router('public')->buildLink('reactions/list/', ['content_type' => $this->contentType, 'content_id' => $contentId]);
    }

    public function getListTitle(Entity $entity)
    {
        return false;
    }

    public function getListBreadcrumbs(Entity $entity)
    {
        return false;
    }

    public function getEntityWith()
    {
        return [];
    }

    public function getStateField()
    {
        return false;
    }

    public function getContent($id = null, $useCache = true)
    {
        if (!$useCache) {
            return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
        }

        if ($this->content === null) {
            if (!$id) {
                return false;
            }

            $this->content = \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
        }

        return $this->content;
    }

    public function setContent(Entity $content)
    {
        $this->content = $content;
    }

    public function getContentType()
    {
        return $this->contentType;
    }
}