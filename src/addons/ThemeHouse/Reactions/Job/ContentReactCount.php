<?php

namespace ThemeHouse\Reactions\Job;

use XF\Job\AbstractRebuildJob;

class ContentReactCount extends AbstractRebuildJob
{
    protected $defaultData = [
        'type' => null,
        'ids' => null,
    ];

    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        $additionalConditionals = '';

        if ($this->data['type']) {
            $additionalConditionals .= ' AND content_type = ' . $db->quote($this->data['type']);
        }
        if (is_array($this->data['ids'])) {
            $additionalConditionals .= ' AND content_id IN (' . $db->quote($this->data['type']) . ')';
        }

        return $db->fetchAllColumn($db->limit("
            SELECT react_id
            FROM xf_th_reacted_content
            WHERE react_id > ?
            {$additionalConditionals}
            ORDER BY react_id
            ", $batch
        ), $start);
    }

    protected function rebuildById($id)
    {
        $reactedContent = $this->app->em()->find('ThemeHouse\Reactions:ReactedContent', $id);
        if (!$reactedContent) {
            return;
        }

        $this->app->repository('ThemeHouse\Reactions:ReactedContent')->rebuildContentReactCache($reactedContent->content_type, $reactedContent->content_id, false);
    }

    protected function getStatusType()
    {
        return \XF::phrase('th_content_react_count_reactions');
    }
}