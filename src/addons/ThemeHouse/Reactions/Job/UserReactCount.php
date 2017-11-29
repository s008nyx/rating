<?php

namespace ThemeHouse\Reactions\Job;

use XF\Job\AbstractJob;

class UserReactCount extends AbstractJob
{
    protected $defaultData = [
        'steps' => 0,
        'start' => 0,
        'batch' => 100
    ];

    public function run($maxRunTime)
    {
        $start = microtime(true);

        $this->data['steps']++;

        $db = $this->app->db();

        $ids = $db->fetchAllColumn($db->limit(
            "
                SELECT user_id
                FROM xf_user
                WHERE user_id > ?
                ORDER BY user_id
            ", $this->data['batch']
        ), $this->data['start']);
        if (!$ids) {
            return $this->complete();
        }

        $reactRepo = $this->app->repository('ThemeHouse\Reactions:ReactedContent');
        $reactionRepo = $this->app->repository('ThemeHouse\Reactions:Reaction');

        $done = 0;

        foreach ($ids AS $id) {
            if (microtime(true) - $start >= $maxRunTime) {
                break;
            }

            $this->data['start'] = $id;

            $count = [];
            $reacts = $reactRepo->findReactionsByContentUserId($id);
            if (!empty($reacts)) {
                foreach ($reacts as $react) {
                    $reaction = $reactionRepo->getReactionById($react['reaction_id']);
                    if ($reaction) {
                        $count[$reaction['reaction_type_id']] = max(0, (isset($count[$reaction['reaction_type_id']]) ? $count[$reaction['reaction_type_id']] : 0) + 1);
                    }
                }
            }

            $db->query("
                UPDATE xf_user
                SET react_count = ?
                WHERE user_id = ?
            ", [serialize($count), $id]);

            $done++;
        }

        $this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $start, $maxRunTime, 1000);

        return $this->resume();
    }

    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('rebuilding');
        $typePhrase = \XF::phrase('th_user_react_count_reactions');
        return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['start']);
    }

    public function canCancel()
    {
        return true;
    }

    public function canTriggerByChoice()
    {
        return true;
    }
}