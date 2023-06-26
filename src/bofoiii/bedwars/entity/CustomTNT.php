<?php

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\game\Game;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

class CustomTNT extends PrimedTNT
{

    public ?Game $game;
    public ?Player $owner;

    protected function doExplosionAnimation(): void
    {
        $minX = (int) floor($this->getPosition()->getX() - 4 - 1);
        $maxX = (int) ceil($this->getPosition()->getX() + 4 + 1);
        $minY = (int) floor($this->getPosition()->getY() - 4 - 1);
        $maxY = (int) ceil($this->getPosition()->getY() + 4 + 1);
        $minZ = (int) floor($this->getPosition()->getZ() - 4 - 1);
        $maxZ = (int) ceil($this->getPosition()->getZ() + 4 + 1);
        $bb = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
        $entities = $this->getWorld()->getNearbyEntities($bb, $this);

        foreach ($entities as $entity) {
            $distance = $entity->getPosition()->distance($this->getPosition()->asVector3()) / 4;

            if ($distance <= 2) {
                if ($entity instanceof  Player) {
                    // $motion = $entity->getPosition()->subtract($this->getPosition()->asVector3(), $this->getPosition()->asVector3(), $this->getPosition()->asVector3())->normalize();
                    $motion = $entity->getPosition()->subtractVector($this->getPosition())->normalize();
                    $ev = new EntityDamageByEntityEvent($this->owner, $entity, EntityDamageEvent::CAUSE_PROJECTILE, 3);
                    $entity->attack($ev);
                    $entity->setMotion($motion->multiply(2));
                }
            }
        }

        $this->flagForDespawn();
    }

    public function explode(): void
    {
        $ev = new EntityPreExplodeEvent($this, 4);
        $ev->call();
        if (!$ev->isCancelled()) {
            $explosion = new Explosion(Position::fromObject($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getWorld()), $ev->getRadius(), $this);
            if ($ev->isBlockBreaking()) {
                $explosion->explodeA();
            }
            $explosion->explodeB();
        }
        $this->doExplosionAnimation();
    }
}
