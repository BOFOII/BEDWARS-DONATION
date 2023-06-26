<?php

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\game\Game;
use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

class UpgradeVillager extends Villager
{

    /** @var Game|null $game */
    public ?Game $game;

    public function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->game = null;
        $this->setNametagAlwaysVisible(true);
        $this->setHasGravity(false);
        $this->setForceMovementUpdate(false);
    }

    public function attack(EntityDamageEvent $source): void
    {
        if (is_null($this->game)) {
            $source->cancel();
            return;
        }
        $event = $source;
        $event->cancel();
        $player = $source->getEntity();
        $game = $this->game;
        if ($game->phase !== $game::PHASE_GAME) return;
        if ($event instanceof EntityDamageByEntityEvent) {
            if ($event->getCause() == $source::CAUSE_ENTITY_ATTACK) {
                $dmg = $event->getDamager();
                if ($dmg instanceof Player) {
                    if ($this->game->inGame($dmg)) {
                        if (!isset($this->game->spectators[$dmg->getName()])) {
                            $this->game->upgradeGUI($dmg);
                            $player->setHealth(20);
                            $event->cancel();
                        }
                    }
                }
            }
            return;
        }

        $event->cancel();
    }
}