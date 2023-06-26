<?php

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\game\Game;
use bofoiii\bedwars\utils\Utils;
use Exception;
use pocketmine\block\Block;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Liquid;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Math;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class SilverFish extends Living implements EntityTeam
{

    public const MAX_TARGET_DISTANCE = 100;

    private int $tick = 20; // 1 SECOND
    private int $tickCounter = 0;
    private int $lifeTime = 120; // SECOND
    private int $attackDelay = 16; // 0.6 SECOND
    private int $attackDelayCounter = 0; // TICK
    private float|int $speed = 0.2;
    public int $stayTime = 0;
    public int $moveTime = 0;
    public ?Game $game;
    public ?Player $owner;

    public function __construct(Location $location, ?CompoundTag $nbt)
    {
        parent::__construct($location, $nbt);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::SILVERFISH;
    }

    public function getName(): string
    {
        return 'Silver Fish BedBug';
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.5, 0.5);
    }

    public function getOwner(): Player
    {
        return $this->owner;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->setHealth(20);
        $this->setMaxHealth(20);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        if (!$this->isAlive() ||  $this->isClosed()) {
            return false;
        }
        
        if (!$this->game instanceof Game || !$this->owner instanceof Player) {
            $this->flagForDespawn();
            return false;
        }

        if (!$this->owner->isOnline() || $this->owner->isClosed()) {
            $this->flagForDespawn();
            return false;
        }

        parent::entityBaseTick($tickDiff);
        $this->tickCounter++;
        if ($this->tickCounter >= $this->tick) {
            $this->lifeTime--;
            $this->tickCounter = 0;
        }

        if ($this->lifeTime <= 0) {
            $this->kill();
            $this->flagForDespawn();
            return false;
        }

        $target = $this->getTargetEntity(); // first target
        if ($target !== null) {
            $this->checkTarget($target);
        } else {
            $this->changeTarget();
        }

        $this->updateMove($tickDiff);
        $this->updateNameTag();

        return false;
    }

    private function updateNameTag(): void
    {
        $color = Utils::getChatColor($this->game->getTeam($this->owner));
        $bar = $this->getHealth();
        $this->setNametag($color . "§lBed Bug §r§7[" . $this->lifeTime . "]\n§r§f" . $bar);
    }

    private function checkTarget(Entity $target): void
    {
        if (!$target->isAlive() || $target->isClosed() || $this->getPosition()->distance($target->getPosition()->asVector3()) > self::MAX_TARGET_DISTANCE) {
            $this->setTargetEntity(null);
        } else {
            $this->attackTarget($target);
        }
    }

    private function attackTarget(Entity $target): void
    {
        if ($this->attackDelayCounter > $this->attackDelay  && $this->boundingBox->intersectsWith($target->getBoundingBox(), -1)) {
            $ev = new EntityDamageByEntityEvent($this->getOwner(), $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 1);
            $target->attack($ev);
            $this->getWorld()->broadcastPacketToViewers($this->getPosition()->asVector3(), ActorEventPacket::create(ActorEvent::ARM_SWING, ActorEvent::ARM_SWING, ActorEvent::ARM_SWING));
            $this->attackDelayCounter = 0;
        }
        $this->attackDelayCounter++;
    }

    private function updateMove(int $tickDiff): ?Entity
    {
        $target = $this->getTargetEntity();
        if ($target !== null) {
            $x = $target->getPosition()->getX() - $this->getPosition()->getX();
            $y = $target->getPosition()->getY() - ($this->getPosition()->getY() + $this->getEyeHeight());
            $z = $target->getPosition()->getZ() - $this->getPosition()->getZ();

            $diff = abs($x) + abs($z);
            if ($x ** 2 + $z ** 2 < 0.7) {
                $this->motion->x = 0;
                $this->motion->z = 0;
            } elseif ($diff > 0) {
                $this->motion->x = $this->speed * 0.15 * ($x / $diff);
                $this->motion->z = $this->speed * 0.15 * ($z / $diff);
                $this->getLocation()->yaw = -atan2($x / $diff, $z / $diff) * 180 / M_PI;
            }
            $this->getLocation()->pitch = $y == 0 ? 0 : rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2)));
            $this->lookAt($target->getPosition()->asVector3());
        }

        $dx = $this->motion->x * $tickDiff;
        $dz = $this->motion->z * $tickDiff;
        $isJump = false;
        $this->checkBlockIntersections();

        $bb = $this->boundingBox;

        $minX = (int) floor($bb->minX - 0.5);
        $minY = (int) floor($bb->minY - 0);
        $minZ = (int) floor($bb->minZ - 0.5);
        $maxX = (int) floor($bb->maxX + 0.5);
        $maxY = (int) floor($bb->maxY + 0);
        $maxZ = (int) floor($bb->maxZ + 0.5);

        for ($z = $minZ; $z <= $maxZ; ++$z) {
            for ($x = $minX; $x <= $maxX; ++$x) {
                for ($y = $minY; $y <= $maxY; ++$y) {
                    $block = $this->getWorld()->getBlockAt($x, $y, $z);
                    if (!$block->canBeReplaced()) {
                        foreach ($block->getCollisionBoxes() as $blockBB) {
                            if ($blockBB->intersectsWith($bb, -0.01)) {
                                $this->isCollidedHorizontally = true;
                            }
                        }
                    }
                }
            }
        }

        if ($this->isCollidedHorizontally or $this->isUnderwater()) {
            $isJump = $this->checkJump($dx, $dz);
        }
        if ($this->stayTime > 0) {
            $this->stayTime -= $tickDiff;
            $this->move(0, $this->motion->y * $tickDiff, 0);
        } else {
            $futureLocation = new Vector2($this->getPosition()->getX() + $dx, $this->getPosition()->getZ() + $dz);
            $this->move($dx, $this->motion->y * $tickDiff, $dz);
            $myLocation = new Vector2($this->getPosition()->getX(), $this->getPosition()->getZ());
            if (($futureLocation->x != $myLocation->x || $futureLocation->y != $myLocation->y) && !$isJump) {
                $this->moveTime -= 90 * $tickDiff;
            }
        }

        if (!$isJump) {
            if ($this->isOnGround()) {
                $this->motion->y = 0;
            } elseif ($this->motion->y > -$this->gravity * 4) {
                if (!($this->getWorld()->getBlock(new Vector3(Math::floorFloat($this->getPosition()->getX()), (int) ($this->getPosition()->getY() + 0.8), Math::floorFloat($this->getPosition()->getZ()))) instanceof Liquid)) {
                    $this->motion->y -= $this->gravity * 1;
                }
            } else {
                $this->motion->y -= $this->gravity * $tickDiff;
            }
        }
        $this->move($this->motion->x, $this->motion->y, $this->motion->z);

        parent::updateMovement();

        return $target;
    }

    private function changeTarget(): void
    {
        foreach ($this->getWorld()->getEntities() as $entity) {
            if ($entity === $this || $entity instanceof self) continue;

            if ($this->getPosition()->distanceSquared($entity->getPosition()->asVector3()) > self::MAX_TARGET_DISTANCE) continue;

            if (!$entity instanceof Player && !$entity instanceof EntityTeam) continue;

            if ($entity instanceof Player) {

                if ($entity->getGamemode()->getEnglishName() != GameMode::SURVIVAL()->getEnglishName())  continue;

                if ($this->game->getTeam($entity) == $this->game->getTeam($this->owner)) continue;
            } else if ($entity instanceof EntityTeam) {
                if ($this->game->getTeam($entity->getOwner()) == $this->game->getTeam($this->getOwner())) continue;
            }

            echo $entity::getNetworkTypeId() . PHP_EOL;

            // TODO : check entity is same team
            $this->setTargetEntity($entity);
        }
    }

    private function checkJump(int|float $dx, int|float $dz): bool
    {
        if ($this->motion->y == $this->gravity * 2) {
            return $this->getWorld()->getBlock(new Vector3(Math::floorFloat($this->getPosition()->getX()), (int) $this->getPosition()->getY(), Math::floorFloat($this->getPosition()->getZ()))) instanceof Liquid;
        } else {
            if ($this->getWorld()->getBlock(new Vector3(Math::floorFloat($this->getPosition()->getX()), (int) ($this->getPosition()->getY() + 0.8), Math::floorFloat($this->getPosition()->getZ()))) instanceof Liquid) {
                $this->motion->y = $this->gravity * 2;
                return true;
            }
        }
        if ($this->motion->y > 0.1 or $this->stayTime > 0) {
            return false;
        }

        $blockingBlock = $this->getWorld()->getBlock($this->getPosition()->asVector3());
        if ($blockingBlock->canBeReplaced()) {
            try {
                $blockingBlock = $this->getTargetBlock(2);
            } catch (Exception $ex) {
                return false;
            }
        }
        if ($blockingBlock != null and !$blockingBlock->canBeReplaced()) {
            $upperBlock = $this->getWorld()->getBlock($blockingBlock->getPosition()->add(0, 1, 0));
            $secondUpperBlock = $this->getWorld()->getBlock($blockingBlock->getPosition()->add(0, 2, 0));

            if ($upperBlock->canBeReplaced() && $secondUpperBlock->canBeReplaced()) {
                if ($blockingBlock instanceof Fence || $blockingBlock instanceof FenceGate) {
                    $this->motion->y = $this->gravity;
                } else if ($blockingBlock instanceof Slab or $blockingBlock instanceof Stair) {
                    $this->motion->y = $this->gravity * 4;
                } else if ($this->motion->y < ($this->gravity * 3.2)) { // Magic
                    $this->motion->y = $this->gravity * 3.2;
                } else {
                    $this->motion->y += $this->gravity * 0.25;
                }
                return true;
            } elseif (!$upperBlock->canBeReplaced()) {
                $this->getLocation()->yaw = $this->getLocation()->getYaw() + mt_rand(-120, 120) / 10;
            }
        }
        return false;
    }

    public function getTargetBlock(int $maxDistance, array $transparent = []): ?Block
    {
        $line = $this->getLineOfSight($maxDistance, 1, $transparent);
        if (!empty($line)) {
            return array_shift($line);
        }

        return null;
    }

    public function getLineOfSight(int $maxDistance, int $maxLength = 0, array $transparent = []): array
    {
        if ($maxDistance > 120) {
            $maxDistance = 120;
        }

        if (count($transparent) === 0) {
            $transparent = null;
        }

        $blocks = [];
        $nextIndex = 0;

        foreach (VoxelRayTrace::inDirection($this->getPosition()->asVector3(), $this->getDirectionVector(), $maxDistance) as $vector3) {
            $block = $this->getWorld()->getBlockAt((int) $vector3->x, (int) $vector3->y, (int) $vector3->z);
            $blocks[$nextIndex++] = $block;

            if ($maxLength !== 0 and count($blocks) > $maxLength) {
                array_shift($blocks);
                --$nextIndex;
            }

            $id = $block->getTypeId();

            if ($transparent === null) {
                if ($id !== 0) {
                    break;
                }
            } else {
                if (!isset($transparent[$id])) {
                    break;
                }
            }
        }

        return $blocks;
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($this->noDamageTicks > 0) {
            $source->cancel();
        } else if ($this->attackTime > 0) {
            $lastCause = $this->getLastDamageCause();
            if ($lastCause !== null and $lastCause->getBaseDamage() >= $source->getBaseDamage()) {
                $source->cancel();
            }
        }
        if ($source instanceof EntityDamageByEntityEvent) {
            $damaged = $source->getDamager();

            if ($damaged instanceof Player && $this->game->getTeam($damaged) == $this->game->getTeam($this->owner)) {
                return;
            }
            if ($damaged instanceof EntityTeam && $this->game->getTeam($damaged->getOwner()) == $this->game->getTeam($this->owner)) {
                return;
            }
            $source->setKnockback(0.1);
        }
        parent::attack($source);
    }

    public function getXpDropAmount(): int
    {
        return 0;
    }

    public function getDrops(): array
    {
        return [];
    }

    public function getOffsetPosition(Vector3 $vector3): Vector3
    {
        return $vector3->add(0, 0.001, 0); //TODO: +0.001 hack for MCPE falling underground
    }
}
