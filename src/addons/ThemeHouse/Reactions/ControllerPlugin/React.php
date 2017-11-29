<?php

namespace ThemeHouse\Reactions\ControllerPlugin;

use XF\Mvc\Entity\Entity;
use ThemeHouse\Reactions\Entity\ReactedContent;

class React extends \XF\ControllerPlugin\AbstractPlugin
{
    public function actionToggleReact(Entity $entity, ReactedContent $react, $action)
    {
        $contentType = $entity->getEntityContentType();
        $contentId = $entity->getEntityId();

        $reactHandler = $this->getReactHandlerByEntity($entity, true);

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        if ($this->isPost()) {
            if ($action == 'insert') {
                $reactRepo->insertReact($entity, $react);
            }

            if ($action == 'delete') {
                $reactRepo->deleteReact($entity, $react);
            }

            $cache = $reactHandler->getContentReactCaches($entity);

            if ($this->filter('_xfWithData', 'bool')) {
                $viewParams = [ 
                    'content' => $entity,
                    'contentDetails' => [
                        'contentType' => $react->content_type,
                        'contentId' => $react->content_id,
                        'contentUserId' => $reactHandler->getContentUserId($entity)
                    ],
                    'templateVars' => [
                        'reacts' => isset($cache['cache']) ? $cache['cache'] : null,
                        'reactionListUrl' => $reactHandler->getListLink($entity)
                        
                    ],
                    'reacts' => isset($cache['cache']) ? $cache['cache'] : null,
                ];

                return $this->view('ThemeHouse\Reactions:React', '', $viewParams);
            } else {
                return $this->redirect($reactHandler->getReturnLink($entity));
            }
        } else {
            $isReacted = $reactRepo->getReactByContentAndReactor($react->content_type, $react->content_id, $react->react_user_id)->count();
            if (!$isReacted && $action == 'delete') {
                return $this->redirect($reactHandler->getReturnLink($entity));
            }

            $viewParams = [
                'reactionId' => $react->reaction_id,
                'contentType' => $react->content_type,
                'confirmUrl' => ($isReacted ? $reactHandler->getUnreactAllLink($react->content_id) : $reactHandler->getReactLink($react->content_id, $react->reaction_id)),
                'isReacted' => $isReacted
            ];

            return $this->view('ThemeHouse\Reactions:Confirm', 'th_confirm_reactions', $viewParams);
        }
    }

    public function actionList(Entity $entity)
    {
        $contentType = $entity->getEntityContentType();
        $contentId = $entity->getEntityId();

        $reactHandler = $this->getReactHandlerByEntity($entity, true);

        $page = $this->filterPage();
        $perPage = 50;

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $reacts = $reactRepo->findContentReacts($contentType, $contentId)
            ->with(['Reactor', 'Reaction'])
            ->limitByPage($page, $perPage, 1)
            ->fetch();

        $reactsCount = count($reacts);
        if (!$reactsCount) {
            return $this->message(\XF::phrase('th_no_one_has_reacted_this_content_yet_reactions'));
        }

        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $reactsByType = $reactRepo->sortReactsByType($reacts);
        $reactsByReactionType = $reactRepo->sortReactsByReactionsAndTypes($reacts, true);

        $viewParams = [
            'type' => $contentType,
            'id' => $contentId,

            'linkRoute' => $reactHandler->getListLink($entity, true),

            'reactsByType' => $reactsByType,
            'reactsByReactionType' => $reactsByReactionType,
            'reactsCount' => $reactsCount,

            'title' => $reactHandler->getListTitle($entity),
            'breadcrumbs' => $reactHandler->getListBreadcrumbs($entity)
        ];

        return $this->view('ThemeHouse\Reactions:React\Listing', 'th_reactions_list_reactions', $viewParams);
    }

    public function actionModify(Entity $entity)
    {
        $contentType = $entity->getEntityContentType();
        $contentId = $entity->getEntityId();

        $visitor = \XF::visitor();

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $reacts = $reactRepo->getReactByContentAndReactor($contentType, $contentId, $visitor->user_id);
        if (empty($reacts)) {
            return $this->message(\XF::phrase('th_you_no_reacts_reactions'));
        }

        $reactHandler = $this->getReactHandlerByEntity($entity, true);

        foreach ($reacts as $reactId => $react) {
            if (!$reactHandler->canUnreactContent($entity, $react)) {
                unset($reacts[$reactId]);
            }
        }

        $viewParams = [
            'type' => $contentType,
            'id' => $contentId,

            'reacts' => $reacts
        ];

        return $this->view('ThemeHouse\Reactions:React\Modify', 'th_reactions_modify_reactions', $viewParams);
    }

    protected function getReactHandlerByEntity(Entity $entity, $throw = false)
    {
        return $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByEntity($entity, $throw);
    }
}