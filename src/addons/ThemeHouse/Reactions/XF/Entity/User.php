<?php

namespace ThemeHouse\Reactions\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public function getReactTotalCount()
    {
        if ($this->react_count) {
            $total = 0;
            foreach ($this->react_count as $reactionTypeId => $reactCount) {
                $total = $total + $reactCount;
            }

            return $total;
        }
    }
}