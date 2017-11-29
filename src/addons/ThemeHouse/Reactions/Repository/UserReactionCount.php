<?php

namespace ThemeHouse\Reactions\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Entity;

class UserReactionCount extends Repository
{
    /**
     * @param integer $userId
     * @param integer $reactionId
     * @param string $contentType
     * @return Finder
     */
    public function findUserReactionCountsByReactionIdContentTypeId($userId, $reactionId, $contentType)
    {
        return $this->finder('ThemeHouse\Reactions:UserReactionCount')
            ->where([
                'user_id' => $userId,
                'reaction_id' => $reactionId,
                'content_type' => $contentType,
            ]
        );
    }

    public function updateUserReactionCounts($reactionId, $contentType, array $counts = array())
    {
        $reactionCounts = [];

        $countTypes = ['received', 'given'];
        foreach ($countTypes as $countType) {
            if (array_key_exists($countType, $counts)) {
                $field = 'count_' . $countType;

                foreach ($counts[$countType] as $entry) {
                    $reactionCount = $this->em->create('ThemeHouse\Reactions:UserReactionCount');
                    $reactionCount->user_id = $entry['userId'];
                    $reactionCount->reaction_id = $reactionId;
                    $reactionCount->content_type = $contentType;

                    if (isset($entry['forceCount'])) {
                        $reactionCount->$field = $entry['amount'];
                    } else {
                        $reactionCount->$field += $entry['amount'];
                    }

                    $reactionCount->save();

                    $reactionCounts[] = $reactionCount;
                }
            }
        }

        return $reactionCount;
    }
}