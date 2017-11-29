<?php

namespace ThemeHouse\Reactions\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;
use XF\Mvc\Entity\Entity;
use ThemeHouse\Reactions\Entity\ReactedContent;

class React extends \XF\Pub\Controller\AbstractController
{
    public function actionReact(ParameterBag $params)
    {
        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $entity = $this->buildContentEntity($params->content_id, $params->content_type);
        $react = $reactRepo->buildReactedContent($entity, $params->reaction_id);

        $this->assertReactionPermissions($entity, $react, ['canReact']);

        $this->verifyReactionId($entity, $react);

        /** @var \ThemeHouse\Reactions\ControllerPlugin\React $reactPlugin */
        $reactPlugin = $this->plugin('ThemeHouse\Reactions:React');
        return $reactPlugin->actionToggleReact($entity, $react, 'insert');
    }

    public function actionUnreact(ParameterBag $params)
    {
        $react = \XF::finder('ThemeHouse\Reactions:ReactedContent')->where('react_id', $params->react_id)->fetchOne();
        if (!$react) {
            throw $this->exception($this->notFound("Provided react ID not found"));
        }

        $entity = $this->buildContentEntity($react->content_id, $react->content_type);

        $this->assertReactionPermissions($entity, $react, ['canUnreact']);

        /** @var \ThemeHouse\Reactions\ControllerPlugin\React $reactPlugin */
        $reactPlugin = $this->plugin('ThemeHouse\Reactions:React');
        return $reactPlugin->actionToggleReact($entity, $react, 'delete');
    }

    public function actionUnreacts(ParameterBag $params)
    {
        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $entity = $this->buildContentEntity($params->content_id, $params->content_type);
        $react = $reactRepo->buildReactedContent($entity, $params->reaction_id);

        $this->assertReactionPermissions($entity, $react, ['canUnreact']);

        /** @var \ThemeHouse\Reactions\ControllerPlugin\React $reactPlugin */
        $reactPlugin = $this->plugin('ThemeHouse\Reactions:React');
        return $reactPlugin->actionToggleReact($entity, $react, 'delete');
    }

    public function actionList(ParameterBag $params)
    {
        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $entity = $this->buildContentEntity($params->content_id, $params->content_type);
        $react = $reactRepo->buildReactedContent($entity);

        $this->assertReactionPermissions($entity, $react, ['canViewReactsList']);

        /** @var \ThemeHouse\Reactions\ControllerPlugin\React $reactPlugin */
        $reactPlugin = $this->plugin('ThemeHouse\Reactions:React');

        return $reactPlugin->actionList($entity);
    }

    public function actionModify(ParameterBag $params)
    {
        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $reactRepo */
        $reactRepo = $this->repository('ThemeHouse\Reactions:ReactedContent');

        $entity = $this->buildContentEntity($params->content_id, $params->content_type);
        $react = $reactRepo->buildReactedContent($entity);

        $this->assertReactionPermissions($entity, $react, ['canUnreact']);

        /** @var \ThemeHouse\Reactions\ControllerPlugin\React $reactPlugin */
        $reactPlugin = $this->plugin('ThemeHouse\Reactions:React');

        return $reactPlugin->actionModify($entity, $react);
    }

    protected function verifyReactionId(Entity $entity, ReactedContent &$react)
    {
        $reactHandler = $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByEntity($entity, true);
        $reaction = $reactHandler->getReactionById($react->reaction_id);

        if ($reaction && $reaction['random']) {
            $reactions = $reactHandler->getReactions();
            $validReactions = [];
            foreach ($reactions as $tempReactionId => $reaction) {
                if ($reaction['random']) {
                    continue;
                }

                if ($reactHandler->canReactContent($entity, $react)) {
                    $validReactions[] = $tempReactionId;
                }
            }

            if (empty($validReactions)) {
                throw $this->exception($this->notFound(\XF::phraseDeferred('th_random_reaction_not_found_reactions')));
            }

            $this->plugin('ThemeHouse\Reactions:React')->actionToggleReact($entity, $react, 'insert');
            $react->reaction_id = $validReactions[array_rand($validReactions)];
        }
    }

    protected function buildContentEntity($contentId, $contentType)
    {
        if (!$contentType) {
            throw $this->exception($this->notFound("Provided entity must defined a content type in its structure"));
        }

        $reactHandler = $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByType($contentType, true);

        $entity = $reactHandler->getContent($contentId);
        if (!$entity) {
            throw $this->exception($this->notFound("No entity found for '$contentType' with ID $contentId"));
        }

        return $entity;
    }

    protected function assertReactionPermissions(Entity $entity, ReactedContent $react, array $checkPermissions = [])
    {
        $reactHandler = $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlerByEntity($entity, true);

        if (!$reactHandler->canViewContent($entity, $react, $error)) {
            throw $this->exception($this->noPermission($error));
        }

        if (in_array('canReact', $checkPermissions)) {
            if (!$reactHandler->canReactContent($entity, $react, $error)) {
                throw $this->exception($this->noPermission($error));
            }
        }

        if (in_array('canUnreact', $checkPermissions)) {
            if (!$reactHandler->canUnreactContent($entity, $react, $error)) {
                throw $this->exception($this->noPermission($error));
            }
        }

        if (in_array('canViewReactsList', $checkPermissions)) {
            if (!$reactHandler->canViewReactsList($entity, $react, $error)) {
                throw $this->exception($this->noPermission($error));
            }
        }
    }

	protected function getReactedContentRepo()
	{
		return $this->repository('ThemeHouse\Reactions:ReactedContent');
	}
}