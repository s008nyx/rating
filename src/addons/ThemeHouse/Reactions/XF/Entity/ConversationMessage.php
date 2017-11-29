<?php

namespace ThemeHouse\Reactions\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ConversationMessage extends XFCP_ConversationMessage
{
    public function getReactUsers()
    {
        if ($this->react_users_) {
            $reacts = [];
            foreach ($this->react_users_ as $reactUser) {
                if (array_key_exists($reactUser['reaction_id'], \XF::app()->container('reactions'))) {
                    $react = $this->em()->create('ThemeHouse\Reactions:ReactedContent');
                    $react->bulkSet([
                        'content_id' => $this->getEntityId(),
                        'content_type' => $this->getEntityContentType(),
                        'react_user_id' => $reactUser['user_id'],
                        'content_user_id' => $this->user_id,
                        'reaction_id' => $reactUser['reaction_id']
                    ]);

                    $reacts[] = $react;
                }
            }

            return $reacts;
        }
    }

    public function isFirstMessage()
    {
        $conversation = $this->Conversation;
        if (!$conversation) {
            return false;
        }

        if ($this->message_id == $conversation->first_message_id ) {
            return true;
        }

        if (!$conversation->first_message_id ) {
            return ($this->message_date == $conversation->start_date);
        }

        return false;
    }
}