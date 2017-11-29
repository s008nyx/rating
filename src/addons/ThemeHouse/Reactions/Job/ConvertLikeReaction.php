<?php

namespace ThemeHouse\Reactions\Job;

use XF\Job\AbstractRebuildJob;

class ConvertLikeReaction extends AbstractRebuildJob
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

        if (!$this->data['likeReaction']) {
            return false;
        }

        $likeReaction = $this->app->finder('ThemeHouse\Reactions:Reaction')->where('like_wrapper', '=', 1)->fetchOne();

        return $db->fetchAllColumn($db->limit("
            SELECT react_id
            FROM xf_th_reacted_content
            WHERE react_id > ?
            AND reaction_id = ?
            {$additionalConditionals}
            ORDER BY react_id
            ", $batch
        ), array(
            $start,
            $likeReaction->reaction_id,
        ));
    }

    protected function rebuildById($id)
    {
        /** @var \ThemeHouse\Reactions\Entity\ReactedContent $react */
        $react = $this->app->em()->find('ThemeHouse\Reactions:ReactedContent', $id);
        if (!$react) {
            return;
        }

        $react->toggleLike(true);
    }

    protected function getStatusType()
    {
        return \XF::phrase('th_content_react_count_reactions');
    }
}