<?php

namespace ThemeHouse\Reactions\React;

use XF\Mvc\Entity\Entity;
use ThemeHouse\Reactions\Entity\ReactedContent;

class ConversationMessage extends AbstractHandler
{
    public function updateFirstContentCache(Entity $entity, $latestReacts)
    {
        $response = parent::updateFirstContentCache($entity, $latestReacts);

        if ($response && $entity->isUpdate() && $entity->isFirstMessage()) {
            $entity->Conversation->first_react_users = $latestReacts;
            $entity->Conversation->save();
        }

        return $response;
    }

    public function reactsCounted(Entity $entity)
    {
        if (!$entity->Conversation) {
            return false;
        }

        return true;
    }

    public function getTitle()
    {
        return \XF::phrase('conversation_messages');
    }

    public function getListTitle(Entity $entity)
    {
        return \XF::phrase('th_members_who_reacted_conversation_message_x_reactions', ['position' => $entity->message_id]);
    }

    public function getLinkDetails()
    {
        return [
            'link' => 'conversations'
        ];
    }

    public function canReactContent(Entity $entity, ReactedContent $react = null, &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$entity->Conversation) {
            return false;
        }

        return parent::canReactContent($entity, $react, $error);
    }

    public function isPublic()
    {
        return true;
    }
}