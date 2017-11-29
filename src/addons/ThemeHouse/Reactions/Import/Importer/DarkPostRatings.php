<?php

namespace ThemeHouse\Reactions\Import\Importer;

use XF\Import\StepState;

class DarkPostRatings extends AbstractReactionImporter
{
    /**
     * @var \XF\Db\Mysqli\Adapter
     */
    protected $sourceDb;

    public static function getListInfo()
    {
        return [
            'target' => '[TH] Reactions',
            'source' => 'Post Ratings'
        ];
    }

    protected function getBaseConfigDefault()
    {
        return [];
    }

    public function validateBaseConfig(array &$baseConfig, array &$errors)
    {
        return true;
    }

    public function renderBaseConfigOptions(array $vars)
    {
        $vars['postRatings'] = $this->getPostRatings();
        $vars['reactions'] = $this->app->finder('ThemeHouse\Reactions:Reaction')->fetch();
        $vars['reactionTypes'] = $this->app->finder('ThemeHouse\Reactions:ReactionType')->fetch();

        return $this->app->templater()->renderTemplate('admin:th_import_config_dpr_reactions', $vars);
    }

    protected function getStepConfigDefault()
    {
        return [];
    }

    public function renderStepConfigOptions(array $vars)
    {

        return $this->app->templater()->renderTemplate('admin:th_import_step_config_dpr_reactions', $vars);
    }

    public function validateStepConfig(array $steps, array &$stepConfig, array &$errors)
    {
        return true;
    }

    public function getSteps()
    {
        return [
            'reactions' => ['title' => 'Reactions'],
            'reactedContent' => [
                'title' => 'Reacted content',
                'depends' => [
                    'reactions',
                ],
            ],
        ];
    }

    public function stepReactions(StepState $state)
    {
        $postRatings = $this->getPostRatings();
        foreach ($postRatings as $postRating) {
            $ratingMap = 0;
            if (isset($this->baseConfig['reaction_map'][$postRating['id']])) {
                $ratingMap = (int) $this->baseConfig['reaction_map'][$postRating['id']];
            }

            if ($ratingMap > 0) {
                $this->log('th_reaction', $postRating['id'], $ratingMap);
            } else {
                $reactionTypeId = $this->baseConfig['reaction_type_map'][$postRating['type']];
                $data = $this->mapKeys($postRating, [
                    'title',
                    'name' => 'image_url',
                ]);
                $data['image_url'] = 'styles/dark/ratings/' . $data['image_url'];
                $data['enabled'] = false;
                $data['reaction_type_id'] = $reactionTypeId;

                /** @var \ThemeHouse\Reactions\Import\Data\Reaction $import */
                $import = $this->newHandler('ThemeHouse\Reactions:Reaction');
                $import->bulkSet($data);
                $import->save($postRating['id']);
            }

            $state->imported ++;
        }

        return $state->complete();
    }

    public function getStepEndReactedContent()
    {
        return $this->db()->fetchOne('SELECT MAX(id) FROM dark_postrating') ?: 0;
    }

    public function stepReactedContent(StepState $state, array $stepConfig, $maxTime)
    {
        $limit = 50;

        $reactedContent = $this->db()->fetchAll('
            SELECT *
            FROM dark_postrating
            WHERE id > ? AND id <= ?
            LIMIT ' . $limit, [
            $state->startAfter,
            $state->end,
        ]);
        if (!$reactedContent) {
            return $state->complete();
        }

        foreach ($reactedContent as $react) {
            $oldId = $react['id'];
            $state->startAfter = $oldId;

            $import = $this->setupImportReactedContent($react);
            if ($import) {
                $import->save($react['id']);
                $state->imported++;
            }
        }

        return $state;
    }

    protected function setupImportReactedContent($reactedContent)
    {
        $this->typeMap('th_reaction');
        $reactionId = $this->lookupId('th_reaction', $reactedContent['rating']);
        if (!$reactionId) {
            return false;
        }

        $import = $this->newHandler('ThemeHouse\Reactions:ReactedContent');

        $data = $this->mapKeys($reactedContent, [
            'user_id' => 'react_user_id',
            'rated_user_id' => 'content_user_id',
            'date' => 'react_date',
            'post_id' => 'content_id',
        ]);
        $data['content_type'] = 'post';
        $data['reaction_id'] = $reactionId;

        $import->bulkSet($data);
        return $import;
    }

    protected function getPostRatings($removeLike=true)
    {
        $options = \XF::options();
        $db = \XF::app()->db();

        $postRatings = $db->fetchAll('
            SELECT *
            FROM dark_postrating_ratings');

        foreach ($postRatings as $key => &$postRating) {
            $phrase = \XF::phrase('dark_postrating_rating_' . $postRating['id'] . '_title');
            $postRating['title'] = $phrase->render('admin');
        }

        return $postRatings;
    }

    protected function doInitializeSource()
    {

    }
}