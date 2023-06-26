<?php

namespace bofoiii\bedwars\item;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\Villager;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class IronGolemSpawnEgg extends SpawnEgg
{

    public function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch): Entity
    {
        return new Villager(Location::fromObject($pos, $world, $yaw, $pitch));
    }
}
