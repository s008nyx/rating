<?php

namespace ThemeHouse\Reactions\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Thread extends XFCP_Thread
{
    public function getReactUsers()
    {
        if ($this->first_react_users) {
            $reacts = [];
            if ($this->first_react_users) {
                foreach ($this->first_react_users_ as $reactUser) {
                    if (array_key_exists($reactUser['reaction_id'], \XF::app()->container('reactions'))) {
                        $react = $this->em()->create('ThemeHouse\Reactions:ReactedContent');
                        $react->bulkSet([
                            'content_id' => $this->getEntityId(),
                            'content_type' => 'thread',
                            'react_user_id' => $reactUser['user_id'],
                            'content_user_id' => $this->user_id,
                            'reaction_id' => $reactUser['reaction_id']
                        ]);

                        $reacts[] = $react;
                    }
                }
            }

            return $reacts;
        }
    }

    protected function threadMadeVisible()
    {
        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');
        $reactRepo->recalculateReactIsCounted('post', $this->post_ids);

        return parent::threadMadeVisible();
    }

    protected function threadHidden($hardDelete = false)
    {
        if (!$hardDelete) {
            // on hard delete the reacts will be removed which will do this
            /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
            $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');
            $reactRepo->fastUpdateReactIsCounted('post', $this->post_ids, false);
        }

        return parent::threadHidden($hardDelete);
    }

    protected function _postDeletePosts(array $postIds)
    {
        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');
        $reactRepo->fastDeleteReacts('post', $postIds);

        return parent::_postDeletePosts($postIds);
    }
}