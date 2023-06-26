<?php

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\game\Game;
use pocketmine\entity\projectile\Throwable;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\world\{Position, World};
use pocketmine\world\Explosion;
use pocketmine\math\RayTraceResult;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

class FireBall extends Throwable
{

    private int $explosionSize = 2 * 2;
    protected float $gravity = 0.0;
    protected float $drag = 0;
    protected float $damage = 2.0;
    private int $life = 0;
    public ?Game $game;
    public ?Player $owner;

    public function __construct(Location $location, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $nbt);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::FIREBALL;
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.0;
    }

    protected function getInitialGravity(): float
    {
        return 0.0;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.50, 0.50); //TODO: eye height ??
    }

    public function getName(): string
    {
        return "Fireball";
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if (!$this->isAlive() ||  $this->isClosed()) {
            return false;
        }

        $parent = parent::entityBaseTick($tickDiff);

        if (!$this->game instanceof Game || !$this->owner instanceof Player) {
            $this->flagForDespawn();
            return false;
        }

        $this->life++;
        if ($this->life > 200) {
            $this->flagForDespawn();
        }

        return $parent;
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source->getCause() === EntityDamageEvent::CAUSE_VOID) {
            parent::attack($source);
        }
        if ($source instanceof EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player) {
                $this->setMotion($damager->getDirectionVector()->multiply(0.5));
            }
        }
    }

    public function onHitBlock(Block $blockHit, RayTraceResult $hitResult): void
    {
        parent::onHitBlock($blockHit, $hitResult);
        $this->explode();
    }

    protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult): void
    {
        parent::onHitEntity($entityHit, $hitResult);
        $this->explode();
    }

    protected function doExplosionAnimation(): void
    {
        $minX = (int) floor($this->getPosition()->getX() - $this->explosionSize - 1);
        $maxX = (int) ceil($this->getPosition()->getX() + $this->explosionSize + 1);
        $minY = (int) floor($this->getPosition()->getY() - $this->explosionSize - 1);
        $maxY = (int) ceil($this->getPosition()->getY() + $this->explosionSize + 1);
        $minZ = (int) floor($this->getPosition()->getZ() - $this->explosionSize - 1);
        $maxZ = (int) ceil($this->getPosition()->getZ() + $this->explosionSize + 1);
        $bb = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
        $entities = $this->getWorld()->getNearbyEntities($bb, $this);

        foreach ($entities as $entity) {
            $distance = $entity->getPosition()->distance($this->getPosition()->asVector3()) / $this->explosionSize;

            if ($distance <= 2) {
                if ($entity instanceof  Player) {
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
