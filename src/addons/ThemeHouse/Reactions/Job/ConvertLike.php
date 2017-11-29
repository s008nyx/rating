<?php

namespace ThemeHouse\Reactions\Job;

use XF\Job\AbstractRebuildJob;

class ConvertLike extends AbstractRebuildJob
{
    protected $defaultData = [
        'type' => null,
        'ids' => null,
        'likeReaction' => null,
    ];

    protected function setupData(array $data)
    {
        $likeReaction = $this->app->finder('ThemeHouse\Reactions:Reaction')->where('like_wrapper', '=', 1)->fetchOne();

        if ($likeReaction) {
            $this->defaultData['likeReaction'] = $likeReaction->reaction_id;
        }

        return parent::setupData($data);
    }

    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        $additionalConditionals = '';

        if (is_array($this->data['ids'])) {
            $additionalConditionals .= ' AND content_id IN (' . $db->quote($this->data['type']) . ')';
        }

        if (!$this->data['likeReaction']) {
            return false;
        }

        $contentTypes = \XF::app()->getContentTypeField('react_handler_class');
        $contentTypes = array_keys($contentTypes);

        return $db->fetchAllColumn($db->limit("
            SELECT like_id
            FROM xf_liked_content
            WHERE like_id > ?
            AND content_type IN (" . $db->quote($contentTypes) . ")
            {$additionalConditionals}
            ORDER BY like_id
            ", $batch
        ), $start);
    }

    protected function rebuildById($id)
    {
        $like = $this->app->em()->find('XF:LikedContent', $id);
        if (!$like) {
            return;
        }

        $this->app->repository('ThemeHouse\Reactions:ReactedContent')->convertLikeToReaction($like);
    }

    protected function getStatusType()
    {
        return \XF::phrase('th_content_react_count_reactions');
    }
}