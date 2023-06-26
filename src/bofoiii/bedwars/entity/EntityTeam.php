<?php

/**
 * Special Thanks For: SandhyR, MadeAja, GamakCZ
 */

namespace bofoiii\bedwars\entity;

use pocketmine\player\Player;

interface EntityTeam {

    public function getOwner(): Player;
}