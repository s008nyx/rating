<?php

namespace ThemeHouse\Reactions\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class ConversationMaster extends XFCP_ConversationMaster
{
    public function getReactUsers()
    {
        if ($this->first_react_users) {
            $reacts = [];
            foreach ($this->first_react_users as $reactUser) {
                if (array_key_exists($reactUser['reaction_id'], \XF::app()->container('reactions'))) {
                    $react = $this->em()->create('ThemeHouse\Reactions:ReactedContent');
                    $react->bulkSet([
                        'content_id' => $this->getEntityId(),
                        'content_type' => 'conversation_message',
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
}