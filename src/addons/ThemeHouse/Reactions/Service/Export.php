<?php

namespace ThemeHouse\Reactions\Service;

use XF\Mvc\Entity\Finder;
use XF\Service\AbstractXmlExport;

class Export extends AbstractXmlExport
{
    public function getRootName()
    {
        return 'reactions_export';
    }

    public function export(Finder $reactions)
    {
        $document = $this->createXml();
        $rootNode = $document->createElement($this->getRootName());
        $document->appendChild($rootNode);

        $reactions = $reactions->fetch();
        if ($reactions->count()) {
            $reactionTypes = $document->createElement('reactionTypes');
            foreach (\XF::app()->container('reactionTypes') as $reactionType) {
                $reactionTypeNode = $document->createElement('reactionType');

                $reactionTypeNode->setAttribute('reaction_type_id', $reactionType['reaction_type_id']);
                $reactionTypeNode->setAttribute('title', $reactionType['title']);
                $reactionTypeNode->setAttribute('display_order', $reactionType['display_order']);
                $reactionTypeNode->setAttribute('color', $reactionType['color']);

                $reactionTypes->appendChild($reactionTypeNode);
            }

            $rootNode->appendChild($reactionTypes);
            
            $reactionsNode = $document->createElement('reactions');
            foreach ($reactions as $reaction) {
                $reactionNode = $document->createElement('reaction');

                $reactionNode->setAttribute('title', $reaction['title']);
                $reactionNode->setAttribute('display_order', $reaction['display_order']);
                $reactionNode->setAttribute('like_wrapper', ($reaction['like_wrapper'] ? 1 : 0));
                $reactionNode->setAttribute('random', ($reaction['random'] ? 1 : 0));
                $reactionNode->setAttribute('enabled', ($reaction['enabled'] ? 1 : 0));

                $reactionNode->appendChild($document->createElement('reaction_type_id', $reaction['reaction_type_id']));
                $reactionNode->appendChild($document->createElement('styling_type', $reaction['styling_type']));
                $reactionNode->appendChild($document->createElement('reaction_text', $reaction['reaction_text']));
                $reactionNode->appendChild($document->createElement('image_url', $reaction['image_url']));
                $reactionNode->appendChild($document->createElement('image_url_2x', $reaction['image_url_2x']));
                $reactionNode->appendChild($document->createElement('image_type', $reaction['image_type']));
                $reactionNode->appendChild($document->createElement('styling', serialize($reaction['styling'])));
                $reactionNode->appendChild($document->createElement('user_criteria', serialize($reaction['user_criteria'])));
                $reactionNode->appendChild($document->createElement('react_handler', serialize($reaction['react_handler'])));
                $reactionNode->appendChild($document->createElement('options', serialize($reaction['options'])));

                $reactionsNode->appendChild($reactionNode);
            }

            $rootNode->appendChild($reactionsNode);

            return $document;
        } else {
            throw new \XF\PrintableException(\XF::phrase('th_please_select_at_least_one_reaction_to_export_reactions')->render());
        }
    }

    /**
     * @return \ThemeHouse\Reactions\Repository\Reaction
     */
    protected function getReactionRepo()
    {
        return $this->repository('ThemeHouse\Reactions:Reaction');
    }
}