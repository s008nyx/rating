<?php

namespace ThemeHouse\Reactions\React;

use XF\Mvc\Entity\Entity;
use ThemeHouse\Reactions\Entity\ReactedContent;

class Post extends AbstractHandler
{
    public function updateFirstContentCache(Entity $entity, $latestReacts)
    {
        $response = parent::updateFirstContentCache($entity, $latestReacts);

        if ($response && $entity->isUpdate() && $entity->isFirstPost()) {
            $entity->Thread->first_react_users = $latestReacts;
            $entity->Thread->save();
        }

        return $response;
    }

    public function reactsCounted(Entity $entity)
    {
        if (!$entity->Thread || !$entity->Thread->Forum) {
            return false;
        }

        return ($entity->message_state == 'visible' && $entity->Thread->discussion_state == 'visible');
    }

    public function getTitle()
    {
        return \XF::phrase('posts');
    }

    public function getListTitle(Entity $entity)
    {
        return \XF::phrase('th_members_who_reacted_message_x_reactions', ['position' => $entity->position + 1]);
    }

    public function getListBreadcrumbs(Entity $entity)
    {
        return $entity->Thread->getBreadcrumbs();
    }

    public function getLinkDetails()
    {
        return [
            'link' => 'posts'
        ];
    }

    public function renderOptions(\XF\Mvc\Renderer\AbstractRenderer $renderer, &$params)
    {
		$templater = $renderer->getTemplater();

		/** @var \XF\Repository\Node $nodeRepo */
		$nodeRepo = \XF::repository('XF:Node');
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getNodeList());

		// only list nodes that are forums or contain forums
		$nodeTree = $nodeTree->filter(null, function($id, $node, $depth, $children, $tree)
		{
			return ($children || $node->node_type_id == 'Forum');
		});

		$viewParams = [
			'nodeTree' => $nodeTree,
		] + $params;

        return $templater->renderTemplate('admin:th_reaction_handler_post_reactions', $viewParams);
    }

    public function verifyOptions(Entity $reaction, &$options, &$error)
    {
        $request = \XF::app()->request();

        $nodeCount = $request->filter('nodeCount', 'uint');

        if (!isset($options['allowed_node_ids'])) {
            if (empty($options['disabled_node_ids'])) {
                $options['allowed_node_ids'] = 'all';
            } else {
                $options['allowed_node_ids'] = [];
            }
        } else if (!is_array($options['allowed_node_ids']) && $options['allowed_node_ids'] == 'all') {
            $options['allowed_node_ids'] = 'all';
        } else if (in_array('all', $options['allowed_node_ids']) || $nodeCount == count($options['allowed_node_ids'])) {
            $options['allowed_node_ids'] = 'all';
        }

        if (!isset($options['disabled_node_ids'])) {
            if (empty($options['allowed_node_ids'])) {
                $options['disabled_node_ids'] = 'none';
            } else {
                $options['disabled_node_ids'] = [];
            }
        } else if (!is_array($options['disabled_node_ids']) && $options['disabled_node_ids'] == 'none') {
            $options['disabled_node_ids'] = 'none';
        } else if (in_array('none', $options['disabled_node_ids']) || $nodeCount == count($options['disabled_node_ids'])) {
            $options['disabled_node_ids'] = 'none';
        }

        if (is_array($options['allowed_node_ids']) && !empty($options['allowed_node_ids']) && is_array($options['disabled_node_ids']) && !empty($options['disabled_node_ids']) && empty(array_diff($options['allowed_node_ids'], $options['disabled_node_ids']))) {
            $error = \XF::phrase('th_forum_cannot_be_allowed_and_disabled_same_time_reactions');
			return false;
        }

        return true;
    }

    public function canReactWithReaction($reactionId, &$error = null)
    {
        $visitor = \XF::visitor();

        if ($entity = $this->getContent()) {
            if ($entity->message_state != 'visible') {
                return false;
            }

            if (!$entity->Thread) {
                return false;
            }

            $reaction = $this->getReactionById($reactionId);
            if (isset($reaction['options']['allowed_node_ids']) &&  $reaction['options']['allowed_node_ids'] != 'all' && !in_array($entity->Thread->node_id, $reaction['options']['allowed_node_ids'])) {
                $error = \XF::phraseDeferred('th_reaction_not_allowed_forum_reactions');
                return false;
            }

            if (isset($reaction['options']['disabled_node_ids']) && $reaction['options']['disabled_node_ids'] != 'none' && in_array($entity->Thread->node_id, $reaction['options']['disabled_node_ids'])) {
                $error = \XF::phraseDeferred('th_reaction_disabled_for_forum_reactions');
                return false;
            }
        }

        return parent::canReactWithReaction($reactionId, $error);
    }

    public function getEntityWith()
    {
        $visitor = \XF::visitor();

        return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
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