<?php

namespace ThemeHouse\Reactions\XF\Repository;

class LikedContent extends XFCP_LikedContent
{
    public function toggleLike($contentType, $contentId, \XF\Entity\User $likeUser, $publish = true)
    {
        $isLiked = parent::toggleLike($contentType, $contentId, $likeUser, false);

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $reactHandler = $reactRepo->getReactHandlerByType($contentType);
        if (!$reactHandler) {
            return $isLiked;
        }

        $reaction = $this->finder('ThemeHouse\Reactions:Reaction')->where('like_wrapper', '=', 1)->fetchOne();
        $entityShortName = $this->app()->getContentTypeEntity($contentType);
        $entity = $this->em->find($entityShortName, $contentId);
        if ($reaction && $entity) {
            $existingReact = $reactRepo->getReactByReactionContentReactor($reaction->reaction_id, $contentType, $contentId, $likeUser->user_id);
            if ($isLiked && !$existingReact) {
                $react = $reactRepo->buildReactedContent($entity, $reaction->reaction_id);;
                $reactRepo->insertReact($entity, $react);
            }

            if (!$isLiked && $existingReact) {
                $existingReact->delete();
            }
        }

        return $isLiked;
    }
}