<?php

namespace ThemeHouse\Reactions\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

class ReactedContent extends AbstractEmulatedData
{
    public function getImportType()
    {
        return 'th_reacted_content';
    }

    public function getEntityShortName()
    {
        return 'ThemeHouse\Reactions:ReactedContent';
    }

    protected function postSave($oldId, $newId)
    {
        parent::postSave($oldId, $newId);

        /** @var \ThemeHouse\Reactions\Repository\ReactedContent $repository */
        $repository = $this->repository('ThemeHouse\Reactions:ReactedContent');

        /** @var \ThemeHouse\Reactions\Entity\ReactedContent $entity */
        $entity = $this->em()->find($this->getEntityShortName(), $newId);

        $content = $entity->getContent();

        if ($content) {
            $repository->insertReact($entity->getContent(), $entity, true, true);
        }
    }
}