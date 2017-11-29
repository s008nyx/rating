<?php

namespace ThemeHouse\Reactions\Admin\Controller;

use XF\Http\Request;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Reaction extends \XF\Admin\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('thReactions');
    }

    public function actionIndex()
    {
        $reactions = $this->getReactionRepo()->getReactionList();

        $viewParams = [
            'reactions' => $reactions,
            'exportView' => $this->filter('export', 'bool')
        ];

        return $this->view('ThemeHouse\Reactions:Listing', 'th_reaction_list_reactions', $viewParams);
    }

    public function reactionAddEdit(\ThemeHouse\Reactions\Entity\Reaction $reaction)
    {
        $userCriteria = $this->app->criteria('XF:User', $reaction->user_criteria);
        $reactionTypes = $this->getReactionTypeRepo()
            ->findReactionTypesForList()
            ->fetch()
            ->pluckNamed('title', 'reaction_type_id');

        $reactHandlers = $this->repository('ThemeHouse\Reactions:ReactHandler')->getReactHandlersList();

        $viewParams = [
            'reaction' => $reaction,
            'reactionTypes' => $reactionTypes,
            'reactHandlers' => $reactHandlers,
            'userCriteria' => $userCriteria
        ];

        return $this->view('ThemeHouse\Reactions:Edit', 'th_reaction_edit_reactions', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $reaction = $this->assertReactionExists($params['reaction_id']);
        return $this->reactionAddEdit($reaction);
    }

    public function actionAdd()
    {
        $reaction = $this->em()->create('ThemeHouse\Reactions:Reaction');

        return $this->reactionAddEdit($reaction);
    }

    protected function reactionSaveProcess(\ThemeHouse\Reactions\Entity\Reaction $reaction)
    {
        $entityInput = $this->filter([
            'title' => 'str',
            'reaction_type_id' => 'str',
            'styling_type' => 'str',
            'reaction_text' => 'str',
            'image_url' => 'str',
            'image_url_2x' => 'str',
            'image_type' => 'str',
            'styling' => 'array',
            'user_criteria' => 'array',
            'react_handler' => 'array-str',
            'options' => 'array',
            'display_order' => 'uint',
            'like_wrapper' => 'bool',
            'random' => 'bool',
            'enabled' => 'bool',
        ]);

        $reactHandlerAll = $this->filter('react_handler_all', 'bool');
        if ($reactHandlerAll) {
            $entityInput['react_handler'] = ['all'];
        }

        $form = $this->formAction();
        $form->basicEntitySave($reaction, $entityInput);

        return $form;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params['reaction_id']) {
            $reaction = $this->assertReactionExists($params['reaction_id']);
        } else {
            $reaction = $this->em()->create('ThemeHouse\Reactions:Reaction');
        }

        $this->reactionSaveProcess($reaction)->run();

        if ($this->request->exists('exit')) {
            $redirect = $this->buildLink('reactions');
        } else {
            $redirect = $this->buildLink('reactions/edit', $reaction);
        }

        return $this->redirect($redirect);
    }

    public function actionDelete(ParameterBag $params)
    {
        $reaction = $this->assertReactionExists($params['reaction_id']);
        if ($this->isPost()) {
            $reaction->delete();
            return $this->redirect($this->buildLink('reactions'));
        } else {
            $viewParams = [
                'reaction' => $reaction
            ];

            return $this->view('ThemeHouse\Reactions:Delete', 'th_reaction_delete_reactions', $viewParams);
        }
    }

	public function actionToggle()
	{
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('ThemeHouse\Reactions:Reaction', 'enabled');
	}

    public function actionExport()
    {
        $reactions = $this->finder('ThemeHouse\Reactions:Reaction')
            ->where('reaction_id', $this->filter('export', 'array'))
            ->order(['display_order', 'title']);

        return $this->plugin('XF:Xml')->actionExport($reactions, 'ThemeHouse\Reactions:Export');
    }

    public function actionImport()
    {
        if ($this->isPost()) {
            /** @var \ThemeHouse\Reactions\Service\Import $reactionImporter */
            $reactionImporter = $this->service('ThemeHouse\Reactions:Import');

            $upload = $this->request->getFile('upload', false);
            if (!$upload) {
                return $this->error(\XF::phrase('th_please_upload_valid_reactions_xml_file_reactions'));
            }

            $xml = \XF\Util\Xml::openFile($upload->getTempFile());
            if (!$xml || $xml->getName() != 'reactions_export') {
                return $this->error(\XF::phrase('th_please_upload_valid_reactions_xml_file_reactions'));
            }

            $reactionTypes = $reactionImporter->getReactionTypeFromXml($xml);
            if (!empty($reactionTypes)) {
                $reactionImporter->importReactionTypes($reactionTypes, $errors);

                if (!empty($errors)) {
                    return $this->error($errors);
                }
            }

            $reactions = $reactionImporter->getReactionFromXml($xml);
            $reactionImporter->importReactions($reactions, $errors);

            if (!empty($errors)) {
                return $this->error($errors);
            }

            return $this->redirect($this->buildLink('reactions'));
        } else {
            return $this->view('ThemeHouse\Reactions:Import', 'th_reaction_import_reactions');
        }
    }

    public function actionHandler()
    {
        $this->assertPostOnly();

        $reactHandlerId = $this->filter('react_handler_id', 'str');
        return $this->view('ThemeHouse\Reactions:Handler', null, [
            'react_handler_id' => $reactHandlerId,
        ]);
    }

    /**
     * @param string $id
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return \ThemeHouse\Reactions\Entity\Reaction
     */
    protected function assertReactionExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\Reactions:Reaction', $id, $with, $phraseKey);
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\Reaction
     */
    protected function getReactionRepo()
    {
        return $this->repository('ThemeHouse\Reactions:Reaction');
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\ReactionType
     */
    protected function getReactionTypeRepo()
    {
        return $this->repository('ThemeHouse\Reactions:ReactionType');
    }
}