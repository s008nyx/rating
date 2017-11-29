<?php

namespace ThemeHouse\Reactions\React;

use XF\Mvc\Entity\Entity;
use ThemeHouse\Reactions\Entity\ReactedContent;

class ProfilePostComment extends AbstractHandler
{
    public function reactsCounted(Entity $entity)
    {
        if (!$entity->ProfilePost) {
            return false;
        }

        return ($entity->message_state == 'visible' && $entity->ProfilePost->message_state == 'visible');
    }

    public function getTitle()
    {
        return \XF::phrase('profile_post_comments');
    }

    public function getListTitle(Entity $entity)
    {
        return \XF::phrase('th_members_who_reacted_profile_post_comment_x_reactions', ['position' => $entity->profile_post_comment_id]);
    }

    public function getLinkDetails()
    {
        return [
            'link' => 'profile-posts/comments'
        ];
    }

    public function canReactContent(Entity $entity, ReactedContent $react = null, &$error = null)
    {
        $visitor = \XF::visitor();

        if ($entity->message_state != 'visible') {
            return false;
        }

        if (!$entity->ProfilePost) {
            return false;
        }

        return parent::canReactContent($entity, $react, $error);
    }

    public function getStateField()
    {
        return 'message_state';
    }

    public function isPublic()
    {
        return true;
    }
}