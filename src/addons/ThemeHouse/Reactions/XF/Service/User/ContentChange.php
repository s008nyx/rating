<?php

namespace ThemeHouse\Reactions\XF\Service\User;

class ContentChange extends XFCP_ContentChange
{
    public function __construct(\XF\App $app, $originalUserId, $originalUserName = null)
    {
        $this->steps[] = 'stepRebuildReacts';
        $this->updates['xf_reacted_content'] = [
            ['react_user_id'],
            ['content_user_id']
        ];

        return parent::__construct($app, $originalUserId, $originalUserName);
    }

    protected function stepRebuildReacts($lastOffset, $maxRunTime)
    {
        $newReactUserId = $this->newUserId !== null ? $this->newUserId : $this->originalUserId;

        $lastOffset = $lastOffset === null ? -1 : $lastOffset;
        $thisOffset = -1;
        $start = microtime(true);

        $reactHandlerRepo = $this->repository('ThemeHouse\Reactions:ReactHandler');

        foreach ($reactHandlerRepo->getReactHandlers() AS $contentType => $reactHandler) {
            $thisOffset++;
            if ($thisOffset <= $lastOffset) {
                continue;
            }

            $reactHandler['object']->updateRecentCacheForUserChange(
                $this->originalUserId, $newReactUserId
            );

            $lastOffset = $thisOffset;
            if ($maxRunTime && microtime(true) - $start > $maxRunTime) {
                return $lastOffset; // continue at this position
            }
        }

        return null;
    }
}