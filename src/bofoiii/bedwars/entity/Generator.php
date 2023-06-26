<?php

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\utils\Utils;
use pocketmine\entity\Human;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

class Generator extends Human
{

    public const GEOMETRY = '{"geometry.player_head":{"texturewidth":64,"textureheight":64,"bones":[{"name":"head","pivot":[0,24,0],"cubes":[{"origin":[-4,19,-4],"size":[8,8,8],"uv":[0,0]}]}]}}';
    /** @var string $type */
    public string $type;

    /** @var int|null $yaw  */
    public ?int $yaw;

    /** @var bool $emerald */
    public bool $emerald = false;

    /** @var int|null $c */
    public ?int $c = 0;

    /** @var int|null $c */
    public ?int $start = 0;

    /** @var int|null $goldTime */
    public ?int $goldTime = 6;

    /** @var int|null $ironTime */
    public ?int $ironTime = 2;

    /** @var int|null $diamondTime */
    public ?int $diamondTime = 30;

    /** @var int|null $emeraldTime */
    public ?int $emeraldTime = 70;

    /** @var int|null $emeraldTeamTime */
    public ?int $emeraldTeamTime = 70;

    /** @var int|null $generatorLevel */
    public ?int $generatorLevel = 1;

    /**
     * @param CompoundTag $nbt
     * @return void
     */
    public function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->setNameTagAlwaysVisible(true);
        $this->setHasGravity(false);
        $this->setForceMovementUpdate(false);
        $this->setHealth(100);
        $this->type = "";
        $this->yaw = 0;
    }

    public function onUpdate(int $currentTick): bool
    {
        if (!parent::onUpdate($currentTick) && $this->isClosed()) {
            return false;
        }

        $this->setRotation($this->yaw, 0.0);
        return true;
    }

    public function onDamage(EntityDamageEvent $source)
    {
        $source->cancel();
    }

    public function setSkin(Skin $skin) : void{
        parent::setSkin(new Skin($skin->getSkinId(), $skin->getSkinData(), '', 'geometry.player_head', self::GEOMETRY));
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->type == "") {
            return false;
        }
        $this->c++;
        if (!($this->type == "gold" || $this->type == "iron")) {
            $this->yaw += intval(5.5);
            if ($this->getLocation()->getYaw() >= 360) {
                $this->yaw = 0;
            }
        }
        if ($this->c == 20) {
            $this->start++;
            if (!($this->type == "gold" || $this->type == "iron" || $this->type == "emeraldTeam")) {
                if ($this->start == 5) {
                    $this->start = 0;
                }
                if ($this->start == 3) {
                    $entities = $this->getWorld()->getPlayers();
                    foreach ($entities as $entity) {
                        if ($entity instanceof Player) {
                            if ($entity->getPosition()->asVector3()->distance($this->getPosition()->asVector3()) < 3) {
                                Utils::addSound($entity, "beacon.activate");
                            } else if ($entity->getPosition()->asVector3()->distance($this->getPosition()->asVector3()) < 7) {
                                Utils::addSound($entity, "beacon.activate");
                            }
                        }
                    }
                }
            }
            if ($this->type == "iron") {
                $this->setNameTagAlwaysVisible(false);
                $this->ironTime--;
                $level = $this->generatorLevel;
                $gMax = 2;
                $oreCount = 0;
                $entities = $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(3, 3, 3));
                foreach ($entities as $entity) {
                    if ($entity instanceof ItemEntity) {
                        if ($entity->getItem()->getStateId() == ItemTypeIds::IRON_INGOT) {
                            $oreCount++;
                        }
                    }
                }
                $amount = 0;
                if ($level < 2) {
                    $amount = 1;
                }
                if ($level > 2 && $level < 5) {
                    $amount = 2;
                }
                if ($level == 5) {
                    $amount = 3;
                }
                if ($this->ironTime == 0) {
                    $this->ironTime = $gMax;
                    $players = array_filter(
                        $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(1, 1, 1)),
                        function ($entity) {
                            if ($entity instanceof Player && !$entity->isSpectator()) {
                                return $entity;
                            }
                            return false;
                        }
                    );

                    if (empty($players) && !($oreCount >= 32)) {
                        $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::IRON_INGOT()->setCount($amount), new Vector3(0, -1, 0));
                    } else {
                        if (count($players) <= 1 && !($oreCount >= 32)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::IRON_INGOT()->setCount($amount), new Vector3(0, -1, 0));
                        } else {
                            foreach ($players as $player) {
                                if ($player instanceof Player && !$player->isSpectator()) {
                                    if ($player->getInventory()->canAddItem(VanillaItems::IRON_INGOT()->setCount($amount))) {
                                        Utils::addSound($player, "random.pop");
                                        $player->getInventory()->addItem(VanillaItems::IRON_INGOT()->setCount($amount));
                                    } else {
                                        if (!($oreCount >= 32)) {
                                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::IRON_INGOT()->setCount($amount), new Vector3(0, -1, 0));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($this->type == "gold") {
                $this->setNameTagAlwaysVisible(false);
                $this->goldTime--;
                $level = $this->generatorLevel;
                $gMax = 6;
                $oreCount = 0;
                $entities = $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(3, 3, 3));
                foreach ($entities as $entity) {
                    if ($entity instanceof ItemEntity) {
                        if ($entity->getItem()->getStateId() == ItemTypeIds::GOLD_INGOT) {
                            $oreCount++;
                        }
                    }
                }
                $amount = 0;
                if ($level < 2) {
                    $amount = 1;
                }
                if ($level > 2 && $level < 5) {
                    $amount = 2;
                }
                if ($level == 5) {
                    $amount = 3;
                }
                if ($this->goldTime == 0) {
                    $this->goldTime = $gMax;
                    $players = array_filter(
                        $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(1, 1, 1)),
                        function ($entity) {
                            if ($entity instanceof Player && !$entity->isSpectator()) {
                                return $entity;
                            }
                            return false;
                        }
                    );

                    if (empty($players) && !($oreCount >= 7)) {
                        $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::GOLD_INGOT()->setCount($amount), new Vector3(0, -1, 0));
                    } else {
                        if (count($players) <= 1 && !($oreCount >= 7)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::GOLD_INGOT()->setCount($amount), new Vector3(0, -1, 0));
                        } else {
                            foreach ($players as $player) {
                                if ($player instanceof Player && !$player->isSpectator()) {
                                    if ($player->getInventory()->canAddItem(VanillaItems::GOLD_INGOT()->setCount($amount))) {
                                        Utils::addSound($player, "random.pop");
                                        $player->getInventory()->addItem(VanillaItems::GOLD_INGOT()->setCount($amount));
                                    } else {
                                        if (!($oreCount >= 7)) {
                                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::GOLD_INGOT()->setCount($amount), new Vector3(0, -1, 0));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($this->type == "emeraldTeam") {
                if ($this->generatorLevel >= 3) {
                    $this->setNameTagAlwaysVisible(false);
                    $this->emeraldTeamTime--;
                    $oreCount = 0;
                    $playerCount = 0;
                    $entities = $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(3, 3, 3));
                    foreach ($entities as $entity) {
                        if ($playerCount instanceof Player) {
                            $playerCount++;
                        }
                        if ($entity instanceof ItemEntity) {
                            if ($entity->getItem()->getStateId() == ItemTypeIds::EMERALD) {
                                $oreCount++;
                            }
                        }
                    }
                    if ($this->emeraldTeamTime == 0) {
                        $this->emeraldTeamTime = 70;

                        $players = array_filter(
                            $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(1, 1, 1)),
                            function ($entity) {
                                if ($entity instanceof Player && !$entity->isSpectator()) {
                                    return $entity;
                                }
                                return false;
                            }
                        );

                        if (empty($players) && !($oreCount >= 4)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::EMERALD(), new Vector3(0, -1, 0));
                        } else {
                            if (count($players) <= 1 && !($oreCount >= 4)) {
                                $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::EMERALD(), new Vector3(0, -1, 0));
                            } else {
                                foreach ($players as $player) {
                                    if ($player instanceof Player && !$player->isSpectator()) {
                                        if ($player->getInventory()->canAddItem(VanillaItems::EMERALD())) {
                                            Utils::addSound($player, "random.pop");
                                            $player->getInventory()->addItem(VanillaItems::EMERALD());
                                        } else {
                                            if (!($oreCount >= 4)) {
                                                $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, 0.5, 0), VanillaItems::EMERALD(), new Vector3(0, -1, 0));
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($this->type == "diamond") {
                $level = $this->generatorLevel;
                $tier = Utils::intToRoman($level);
                $this->diamondTime--;
                $this->setNameTag("§eTier §c" . $tier . "\n§bDiamond\n\n§eSpawns in §c" . $this->diamondTime . " §eseconds!");
                $oreCount = 0;
                $entities = $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(3, 3, 3));
                foreach ($entities as $entity) {
                    if ($entity instanceof ItemEntity) {
                        if ($entity->getItem()->getStateId() == ItemTypeIds::DIAMOND) {
                            $oreCount++;
                        }
                    }
                }
                $max = null;
                if ($level == 1) {
                    $max = 30;
                }
                if ($level == 2) {
                    $max = 20;
                }
                if ($level == 3) {
                    $max = 15;
                }
                if ($this->diamondTime == 0) {
                    $this->diamondTime = $max;
                    if ($level == 1) {
                        if (!($oreCount >= 4)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, -2, 0), VanillaItems::DIAMOND(), new Vector3(0, -1, 0));
                        }
                    } else if ($level == 2) {
                        if (!($oreCount >= 6)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, -2, 0), VanillaItems::DIAMOND(), new Vector3(0, -1, 0));
                        }
                    } else if ($level == 3) {
                        if (!($oreCount >= 8)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, -2, 0), VanillaItems::DIAMOND(), new Vector3(0, -1, 0));
                        }
                    }
                }
            }
            if ($this->type == "emerald") {
                $level = $this->generatorLevel;
                $tier = Utils::intToRoman($level);
                $this->emeraldTime--;
                $this->setNameTag("§eTier §c" . $tier . "\n§aEmerald\n\n§eSpawns in §c" . $this->emeraldTime . " §eseconds!");
                $oreCount = 0;
                $entities = $this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(3, 3, 3));
                foreach ($entities as $entity) {
                    if ($entity instanceof ItemEntity) {
                        if ($entity->getItem()->getStateId() == ItemTypeIds::EMERALD) {
                            $oreCount++;
                        }
                    }
                }
                $max = null;
                if ($level == 1) {
                    $max = 70;
                }
                if ($level == 2) {
                    $max = 50;
                }
                if ($level == 3) {
                    $max = 30;
                }
                if ($this->emeraldTime == 0) {
                    $this->emeraldTime = $max;
                    if ($level == 1) {
                        if (!($oreCount >= 4)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, -2, 0), VanillaItems::EMERALD(), new Vector3(0, -1, 0));
                        }
                    } else if ($level == 2) {
                        if (!($oreCount >= 6)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, -2, 0), VanillaItems::EMERALD(), new Vector3(0, -1, 0));
                        }
                    } else if ($level == 3) {
                        if (!($oreCount >= 8)) {
                            $this->getWorld()->dropItem($this->getPosition()->asVector3()->add(0, -2, 0), VanillaItems::EMERALD(), new Vector3(0, -1, 0));
                        }
                    }
                }
            }
            $this->c = 0;
        }
        return parent::entityBaseTick($tickDiff);
    }
}
