<?php

namespace ThemeHouse\Reactions\Admin\Controller;

use XF\Http\Request;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class ReactionType extends \XF\Admin\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('thReactions');
    }

    public function actionIndex()
    {
        $reactionTypes = $this->getReactionTypeRepo()->getReactionTypeList();

        $viewParams = [
            'reactionTypes' => $reactionTypes,
        ];

        return $this->view('ThemeHouse\Reactions:ReactionType\Listing', 'th_reaction_type_list_reactions', $viewParams);
    }

    public function reactionTypeAddEdit(\ThemeHouse\Reactions\Entity\ReactionType $reactionType)
    {
        $viewParams = [
            'reactionType' => $reactionType,
        ];

        return $this->view('ThemeHouse\Reactions:ReactionType\Edit', 'th_reaction_type_edit_reactions', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $reactionType = $this->assertReactionTypeExists($params['reaction_type_id']);
        return $this->reactionTypeAddEdit($reactionType);
    }

    public function actionAdd()
    {
        $reactionType = $this->em()->create('ThemeHouse\Reactions:ReactionType');

        return $this->reactionTypeAddEdit($reactionType);
    }

    protected function reactionTypeSaveProcess(\ThemeHouse\Reactions\Entity\ReactionType $reactionType)
    {
        $entityInput = $this->filter([
            'reaction_type_id' => 'str',
            'title' => 'str',
            'color' => 'str',
            'display_order' => 'uint'
        ]);

        $form = $this->formAction();
        $form->basicEntitySave($reactionType, $entityInput);

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params['reaction_type_id']) {
            $reactionType = $this->assertReactionTypeExists($params['reaction_type_id']);
        } else {
            $reactionType = $this->em()->create('ThemeHouse\Reactions:ReactionType');
        }

        $this->reactionTypeSaveProcess($reactionType)->run();

        return $this->redirect($this->buildLink('reaction-types'));
    }

    public function actionDelete(ParameterBag $params)
    {
        $reactionType = $this->assertReactionTypeExists($params['reaction_type_id']);
        if (!$reactionType->canDeleteReactionType()) {
            return $this->error(\XF::phrase('th_reactions_exist_using_reaction_type_reactions'));
        }

        if ($this->isPost()) {
            $reactionType->delete();
            return $this->redirect($this->buildLink('reactions'));
        } else {
            $viewParams = [
                'reactionType' => $reactionType
            ];

            return $this->view('ThemeHouse\Reactions:ReactionType\Delete', 'th_reaction_type_delete_reactions', $viewParams);
        }
    }

    /**
     * @param string $id
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return \ThemeHouse\Reactions\Entity\ReactionType
     */
    protected function assertReactionTypeExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\Reactions:ReactionType', $id, $with, $phraseKey);
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\ReactionType
     */
    protected function getReactionTypeRepo()
    {
        return $this->repository('ThemeHouse\Reactions:ReactionType');
    }
}