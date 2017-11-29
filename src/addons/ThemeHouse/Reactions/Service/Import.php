<?php

namespace ThemeHouse\Reactions\Service;

use XF\Mvc\Entity\Entity;

class Import extends \XF\Service\AbstractService
{
	public function importReactionTypes(array $reactionTypes, &$errors = [])
	{
		$this->db()->beginTransaction();

        if (!empty($reactionTypes)) {
            foreach ($reactionTypes AS $reactionTypeId => $reactionType) {
                $reactionTypeEm = $this->em()->create('ThemeHouse\Reactions:ReactionType');
                $reactionTypeEm->bulkSet($reactionType);

                if ($emErrors = $reactionTypeEm->getErrors()) {
                    foreach ($emErrors AS $field => $error) {
                        $errors[$field . '__' . $reactionTypeId] = $error;
                    }
                } else {
                    $entityManagers[] = $reactionTypeEm;
                }
            }

            if (empty($errors)) {
                /** @var Entity $em */
                foreach ($entityManagers AS $em) {
                    $em->save();
                }
                $this->db()->commit();
            } else {
                $this->db()->rollback();
            }
        }
	}

	public function importReactions(array $reactions, &$errors = [])
	{
		$this->db()->beginTransaction();

        if (!empty($reactions)) {
            foreach ($reactions AS $reactionId => $reaction) {
                $reactionEm = $this->em()->create('ThemeHouse\Reactions:Reaction');
                $reactionEm->bulkSet($reaction);

                if ($emErrors = $reactionEm->getErrors()) {
                    foreach ($emErrors AS $field => $error) {
                        $errors[$field . '__' . $reactionId] = $error;
                    }
                } else {
                    $entityManagers[] = $reactionEm;
                }
            }

            if (empty($errors)) {
                /** @var Entity $em */
                foreach ($entityManagers AS $em) {
                    $em->save();
                }
                $this->db()->commit();
            } else {
                $this->db()->rollback();
            }
        }
	}

	public function getReactionTypeFromXml(\SimpleXMLElement $xml)
	{
		$reactionTypes = [];
		$i = 0;

		foreach ($xml->reactionTypes->reactionType AS $reactionType) {
			$reactionTypes[$i] = [
				'reaction_type_id' => (string) $reactionType['reaction_type_id'],
				'title' => (string) $reactionType['title'],
				'display_order' => (int) $reactionType['display_order'],
				'color' => (string) $reactionType['color'],
			];

			$i++;
		}

        return $reactionTypes;
	}

	public function getReactionFromXml(\SimpleXMLElement $xml)
	{
		$reactions = [];
		$i = 0;

		foreach ($xml->reactions->reaction AS $reaction) {
			$reactions[$i] = [
				'title' => (string) $reaction['title'],
				'display_order' => (int) $reaction['display_order'],
				'like_wrapper' => ((int) $reaction['like_wrapper'] ? 1 : 0),
				'random' => ((int) $reaction['random'] ? 1 : 0),
				'enabled' => ((int) $reaction['enabled'] ? 1 : 0),
				'reaction_type_id' => (string) $reaction->reaction_type_id,
				'styling_type' => (string) $reaction->styling_type,
				'reaction_text' => (string) $reaction->reaction_text,
				'image_url' => (string) $reaction->image_url,
				'image_url_2x' => (string) $reaction->image_url_2x,
				'styling' => @unserialize($reaction->styling),
				'user_criteria' => @unserialize($reaction->user_criteria),
				'react_handler' => @unserialize($reaction->react_handler),
				'options' => @unserialize($reaction->options),
			];

			$i++;
		}

        return $reactions;
	}
}