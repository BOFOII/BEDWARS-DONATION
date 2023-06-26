<?php

namespace bofoiii\bedwars\event;

use pocketmine\event\Event;
use pocketmine\world\Position;

class LobbyRemoveEvent extends Event{

    protected Position $position;

    public function __construct(Position $position){
        $this->position = $position;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }
}