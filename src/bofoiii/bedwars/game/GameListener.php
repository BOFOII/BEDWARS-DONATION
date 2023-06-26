<?php

namespace bofoiii\bedwars\game;

use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\entity\EnderDragon;
use bofoiii\bedwars\entity\EntityTeam;
use bofoiii\bedwars\entity\Generator;
use bofoiii\bedwars\entity\ShopVillager;
use bofoiii\bedwars\entity\UpgradeVillager;
use bofoiii\bedwars\event\LobbyRemoveEvent;
use bofoiii\bedwars\item\ExtraBedWarsItem;
use bofoiii\bedwars\utils\Utils;
use pocketmine\block\Air;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\block\inventory\ChestInventory;
use pocketmine\block\inventory\EnderChestInventory;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\PotionTypeIds;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\Pickaxe;
use pocketmine\item\Potion;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\Player;

class GameListener implements Listener
{

    public function __construct(private Game $game)
    {
    }

    /**
     * @param PlayerMoveEvent $ev
     * @return void
     */
    public function onShopMove(PlayerMoveEvent $ev): void
    {
        $player = $ev->getPlayer();
        $from = $ev->getFrom();
        $to = $ev->getTo();
        if ($from->distance($to) < 0.1) {
            return;
        }
        $maxDistance = 10;
        foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($maxDistance, $maxDistance, $maxDistance), $player) as $e) {
            if ($e instanceof Player) {
                continue;
            }
            $xdiff = $player->getPosition()->getX() - $e->getPosition()->getX();
            $zdiff = $player->getPosition()->getZ() - $e->getPosition()->getZ();
            $angle = atan2($zdiff, $xdiff);
            $yaw = (($angle * 180) / M_PI) - 90;
            $ydiff = $player->getPosition()->getY() - $e->getPosition()->getY();
            $v = new Vector2($e->getPosition()->getX(), $e->getPosition()->getZ());
            $dist = $v->distance(new Vector2($player->getPosition()->getX(), $player->getPosition()->getZ()));
            $angle = atan2($dist, $ydiff);
            $pitch = (($angle * 180) / M_PI) - 90;
            if (isset($this->game->spectators[$player->getName()])) {
                continue;
            }

            if ($e instanceof ShopVillager || $e instanceof UpgradeVillager) {
                $pk = new MoveActorAbsolutePacket();
                $pk->actorRuntimeId = $e->getId();
                $pk->position = $e->getPosition()->asVector3();
                $pk->pitch = $pitch;
                $pk->headYaw = $yaw;
                $pk->yaw = $yaw;
                $player->getNetworkSession()->sendDataPacket($pk);
            }
        }
    }

    public function onRemoveLobby(LobbyRemoveEvent $event)
    {
        $position = $event->getPosition();
        for ($x = -15; $x <= 16; $x++) {
            for ($y = -4; $y <= 10; $y++) {
                for ($z = -15; $z <= 16; $z++) {
                    $world = $position->getWorld();
                    $block = $position->add($x, $y, $z);
                    $world->setBlock($block, VanillaBlocks::AIR());
                }
            }
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     * @return void
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $source = $transaction->getSource();
        if ($this->game->phase !== Game::PHASE_GAME) return;

        if (!$this->game->inGame($source)) return;

        foreach ($transaction->getActions() as $action) {
            $item = $action->getSourceItem();

            if ($action instanceof SlotChangeAction) {
                if ($action->getInventory() instanceof PlayerInventory) {
                    if ($this->game->phase == Game::PHASE_LOBBY) {
                        $event->cancel();
                    }
                    if ($this->game->phase == Game::PHASE_RESTART) {
                        $event->cancel();
                    }
                }
                if (isset($this->game->inChest[$source->getId()]) && $action->getInventory() instanceof PlayerInventory) {
                    if ($item instanceof Pickaxe || $item instanceof Axe) {
                        $event->cancel();
                    }
                }
                if ($action->getInventory() instanceof ArmorInventory) {
                    if ($item instanceof Armor) {
                        $event->cancel();
                    }
                }
            }
        }
    }

    /**
     * @param ProjectileHitEntityEvent $event
     * @return void
     */
    public function onProjectileHitEntity(ProjectileHitEntityEvent $event): void
    {
        $pro = $event->getEntity();
        $hitEntity = $event->getEntityHit();
        $owner = $pro->getOwningEntity();
        if ($this->game->phase != Game::PHASE_GAME) return;
        if ($pro instanceof Arrow) {
            if ($owner instanceof Player && $hitEntity instanceof Player) {
                if ($this->game->inGame($owner)) {
                    $owner->sendMessage($this->game->data["teamColor"][$this->game->getTeam($owner)] . $hitEntity->getDisplayName() . " §7is on §c" . $hitEntity->getHealth() . " §7HP!");
                }
            }
        }
    }

    /**
     * @param ProjectileHitEvent $event
     * @return void
     */
    public function onProjectileHit(ProjectileHitEvent $event): void
    {
        $pro = $event->getEntity();
        $player = $event->getEntity()->getOwningEntity();
        if ($this->game->phase != Game::PHASE_GAME) return;
        if ($player instanceof Player) {
            if ($pro instanceof Snowball) {
                $this->game->spawnBedbug($pro->getLocation(), $player);
            }
        }
    }

    /**
     * @param ItemSpawnEvent $event
     * @return void
     */
    public function onItemSpawn(ItemSpawnEvent $event): void
    {
        $entity = $event->getEntity();
        if ($entity->getWorld()->getFolderName() !== $this->game->world->getFolderName()) return;
        $entities = $entity->getWorld()->getNearbyEntities($entity->getBoundingBox()->expandedCopy(1, 1, 1));
        if (empty($entities)) {
            return;
        }
        if ($entity instanceof ItemEntity) {
            $originalItem = $entity->getItem();
            foreach ($entities as $e) {
                if ($e instanceof ItemEntity and $entity->getId() !== $e->getId()) {
                    $item = $e->getItem();
                    if (in_array($originalItem->getTypeId(), [ItemTypeIds::DIAMOND, ItemTypeIds::EMERALD])) {
                        if ($item->getTypeId() === $originalItem->getTypeId()) {
                            $e->flagForDespawn();
                            $entity->getItem()->setCount(is_float($originalItem->getCount()) ? 0 : ($originalItem->getCount() + is_float($item->getCount()) ? 0 : $item->getCount()));
                        }
                    }
                }
            }
        }
    }

    /**
     * @param CraftItemEvent $event
     * @return void
     */
    public function onCraftItem(CraftItemEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->game->inGame($player)) {
            $event->cancel();
        }
    }


    /**
     * @param PlayerItemConsumeEvent $event
     * @return void
     */
    public function onConsume(PlayerItemConsumeEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        if ($this->game->inGame($player)) return;

        if ($item instanceof Potion) {

            if ($item->getType()->id() == PotionTypeIds::STRONG_SWIFTNESS) {
                $event->cancel();
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $eff = new EffectInstance(VanillaEffects::SPEED(), 900, 1);
                $eff->setVisible();
                $player->getEffects()->add($eff);
            }

            if ($item->getType()->id() == PotionTypeIds::STRONG_LEAPING) {
                $event->cancel();
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $eff = new EffectInstance(VanillaEffects::JUMP_BOOST(), 900, 3);
                $eff->setVisible();
                $player->getEffects()->add($eff);
            }

            if ($item->getType()->id() == PotionTypeIds::INVISIBILITY) {
                $event->cancel();
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $eff = new EffectInstance(VanillaEffects::INVISIBILITY(), 600, 1);
                $eff->setVisible();
                $player->getEffects()->add($eff);
                $this->game->setInvis($player, true);
            }
        }

        if ($item->getTypeId() == ItemTypeIds::MILK_BUCKET) {
            $event->cancel();
            $player->getInventory()->setItemInHand(VanillaItems::AIR());
            $this->game->ifMilk[$player->getId()] = 30;
            $player->sendMessage("§eTrap effected in 30 seconds!");
        }
    }

    /**
     * @param PlayerMoveEvent $event
     * @return void
     */
    public function onMove(PlayerMoveEvent $event): void
    {
        $player = $event->getPlayer();

        if (!$this->game->inGame($player)) return;

        if ($this->game->phase == Game::PHASE_LOBBY) {
            $lv = Utils::stringToVector(":", $this->game->data["waitingSpawn"]);
            $p = $lv->getY() - 3;
            if ($player->getWorld()->getFolderName() == $this->game->world->getFolderName()) {
                if ($player->getPosition()->getY() < $p) {
                    $player->teleport(Utils::stringToVector(":", $this->game->data["waitingSpawn"]));
                }
            }

            return;
        }

        if ($this->game->phase == Game::PHASE_RESTART) {
            return;
        }

        if (isset($this->game->ifMilk[$player->getId()])) return;
        if (isset($this->game->spectators[$player->getName()])) return;
        foreach ($this->game->data["teamName"] as $teams) {
            $pos = Utils::stringToVector(":", $this->game->data["teamBed"][$teams]);
            if ($player->getPosition()->asVector3()->distance($pos) < 15) {
                if ($this->game->getTeam($player) !== $teams) {
                    if (isset($this->game->allTraps[$teams]["trapSlot1"])) {
                        if ($this->game->allTraps[$teams]["trapSlot1"] == "itsTrap") {
                            if (isset($this->game->allTraps[$teams]["trapSlot2"])) {
                                $this->game->allTraps[$teams]["trapSlot1"] = $this->game->allTraps[$teams]["trapSlot2"];
                                if (isset($this->game->allTraps[$teams]["trapSlot3"])) {
                                    $this->game->allTraps[$teams]["trapSlot2"] = $this->game->allTraps[$teams]["trapSlot3"];
                                    unset($this->game->allTraps[$teams]["trapSlot3"]);
                                } else {
                                    unset($this->game->allTraps[$teams]["trapSlot2"]);
                                }
                            } else {
                                unset($this->game->allTraps[$teams]["trapSlot1"]);
                            }
                            $eff = new EffectInstance(VanillaEffects::BLINDNESS(), 5, 0);
                            $eff->setVisible();
                            $player->getEffects()->add($eff);
                            $eff = new EffectInstance(VanillaEffects::SLOWNESS(), 5, 0);
                            $eff->setVisible();
                            $player->getEffects()->add($eff);
                            foreach ($this->game->players as $p) {
                                if ($this->game->getTeam($p) == $teams) {
                                    $p->sendTitle("§l§cTRAP TRIGGERED", "§fYour It's a trap has been triggered!");
                                }
                            }
                        } else if ($this->game->allTraps[$teams]["trapSlot1"] == "counterTrap") {
                            if (isset($this->game->allTraps[$teams]["trapSlot2"])) {
                                $this->game->allTraps[$teams]["trapSlot1"] = $this->game->allTraps[$teams]["trapSlot2"];
                                if (isset($this->game->allTraps[$teams]["trapSlot3"])) {
                                    $this->game->allTraps[$teams]["trapSlot2"] = $this->game->allTraps[$teams]["trapSlot3"];
                                    unset($this->game->allTraps[$teams]["trapSlot3"]);
                                } else {
                                    unset($this->game->allTraps[$teams]["trapSlot2"]);
                                }
                            } else {
                                unset($this->game->allTraps[$teams]["trapSlot1"]);
                            }
                            foreach ($this->game->players as $p) {
                                if ($this->game->getTeam($p) == $teams) {
                                    $p->sendTitle("§l§cTRAP TRIGGERED", "§fYour Counter-Offensive Trap has been triggered!");
                                    $eff = new EffectInstance(VanillaEffects::SPEED(), 15, 0);
                                    $eff->setVisible();
                                    $p->getEffects()->add($eff);
                                }
                            }
                        } else if ($this->game->allTraps[$teams]["trapSlot1"] == "alarmTrap") {
                            if (isset($this->game->allTraps[$teams]["trapSlot2"])) {
                                $this->game->allTraps[$teams]["trapSlot1"] = $this->game->allTraps[$teams]["trapSlot2"];
                                if (isset($this->game->allTraps[$teams]["trapSlot3"])) {
                                    $this->game->allTraps[$teams]["trapSlot2"] = $this->game->allTraps[$teams]["trapSlot3"];
                                    unset($this->game->allTraps[$teams]["trapSlot3"]);
                                } else {
                                    unset($this->game->allTraps[$teams]["trapSlot2"]);
                                }
                            } else {
                                unset($this->game->allTraps[$teams]["trapSlot1"]);
                            }
                            $player->sendTitle("§c§lALARM!!!", "§fAlarm trap set off by " . $this->game->data["teamColor"][$teams] . $teams . " §fteam!");
                            if ($player->getEffects()->has(VanillaEffects::INVISIBILITY())) {
                                if (isset($this->game->ifInvis[$player->getId()])) {
                                    $player->getEffects()->remove(VanillaEffects::INVISIBILITY());
                                    $this->game->setInvis($player, false);
                                }
                            }
                            foreach ($this->game->players as $p) {
                                if ($this->game->getTeam($p) == $teams) {
                                    $p->sendTitle("§l§cTRAP TRIGGERED", "§fYour Alarm Trap has been triggered!");
                                }
                            }
                        } else if ($this->game->allTraps[$teams]["trapSlot1"] == "minerTrap") {
                            if (isset($this->game->allTraps[$teams]["trapSlot2"])) {
                                $this->game->allTraps[$teams]["trapSlot1"] = $this->game->allTraps[$teams]["trapSlot2"];
                                if (isset($this->game->allTraps[$teams]["trapSlot3"])) {
                                    $this->game->allTraps[$teams]["trapSlot2"] = $this->game->allTraps[$teams]["trapSlot3"];
                                    unset($this->game->allTraps[$teams]["trapSlot3"]);
                                } else {
                                    unset($this->game->allTraps[$teams]["trapSlot2"]);
                                }
                            } else {
                                unset($this->game->allTraps[$teams]["trapSlot1"]);
                            }
                            $eff = new EffectInstance(VanillaEffects::MINING_FATIGUE(), 15, 0);
                            $eff->setVisible();
                            $player->getEffects()->add($eff);
                            foreach ($this->game->players as $p) {
                                if ($this->game->getTeam($p) == $teams) {
                                    $p->sendTitle("§l§cTRAP TRIGGERED", "§fYour Miner Fatigue Trap has been triggered!");
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * @param PlayerChatEvent $event
     * @return void
     */
    public function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $msg = $event->getMessage();
        $team = $this->game->getTeam($player);
        if (!$this->game->inGame($player)) return;
        if ($player->getWorld()->getFolderName() != $this->game->world->getFolderName()) return;

        if ($this->game->phase == Game::PHASE_LOBBY) {
            foreach ($this->game->players as $players) {
                $players->sendMessage($player->getDisplayName() . " §7: " . $event->getMessage());
            }

            $event->cancel();
            return;
        }


        if ($this->game->phase == Game::PHASE_RESTART) {
            foreach ($this->game->players as $players) {
                $players->sendMessage($player->getDisplayName() . " §7: " . $event->getMessage());
            }

            $event->cancel();
            return;
        }

        if (isset($this->game->spectators[$player->getName()])) {
            foreach ($this->game->spectators as $pt) {
                $pt->sendMessage("§7[SPECTATOR] §r" . $player->getDisplayName() . ": §7" . $msg);
            }
        }

        if ($this->game->data["maxInTeam"] == 1) {
            if ($msg === "!reduceTime") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->reduceTime($player);
                } else {
                    if (!isset($this->game->spectators[$player->getName()])) {
                        $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($player)] . $player->getDisplayName() . "§f: §7" . $msg);
                    }
                }
            } else if ($msg === "!dragon") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->setSuddenDeath();
                } else {
                    if (!isset($this->game->spectators[$player->getName()])) {
                        $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($player)] . $player->getDisplayName() . "§f: §7" . $msg);
                    }
                }
            } else if ($msg === "!upgradeGenerator") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->upgradeGenerator($this->game->getTeam($player), $player);
                } else {
                    if (!isset($this->game->spectators[$player->getName()])) {
                        $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($player)] . $player->getDisplayName() . "§f: §7" . $msg);
                    }
                }
            } else if ($msg === "!bedbug") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->spawnBedbug(BedWars::getInstance()->getServer()->getPlayerExact("ItsBofoiii")->getLocation(), $player);
                } else {
                    if (!isset($this->game->spectators[$player->getName()])) {
                        $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($player)] . $player->getDisplayName() . "§f: §7" . $msg);
                    }
                }
            } else {
                if (!isset($this->game->spectators[$player->getName()])) {
                    $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($player)] . $player->getDisplayName() . "§f: §7" . $msg);
                }
            }
        } else {
            if ($msg === "!reduceTime") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->reduceTime($player);
                } else {
                    foreach ($this->game->players as $pt) {
                        if ($this->game->getTeam($pt) == $team) {
                            if (!isset($this->game->spectators[$player->getName()])) {
                                $pt->sendMessage($this->game->data["teamColor"][$team] . "TEAM > " . $player->getDisplayName() . ": §7" . $msg);
                            }
                        }
                    }
                }
            } elseif ($msg === "!dragon") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->setSuddenDeath();
                } else {
                    foreach ($this->game->players as $pt) {
                        if ($this->game->getTeam($pt) == $team) {
                            if (!isset($this->game->spectators[$player->getName()])) {
                                $pt->sendMessage($this->game->data["teamColor"][$team] . "TEAM > " . $player->getDisplayName() . ": §7" . $msg);
                            }
                        }
                    }
                }
            } else if ($msg === "!golem") {
                if (BedWars::getInstance()->getServer()->isOp($player->getName())) {
                    $this->game->spawnDreamDefender(BedWars::getInstance()->getServer()->getPlayerExact("ItsBofoiii")->getLocation(), $player);
                } else {
                    if (!isset($this->game->spectators[$player->getName()])) {
                        $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($player)] . $player->getDisplayName() . "§f: §7" . $msg);
                    }
                }
            } elseif (str_starts_with($msg, "!")) {
                if (!isset($this->game->spectators[$player->getName()])) {
                    $msg = substr($msg, 1);
                    if (trim($msg) !== "") {
                        $this->game->broadcastMessage("§6SHOUT > §7[" . $this->game->data["teamColor"][$this->game->getTeam($player)] . $this->game->getTeam($player) . "§7] §r" . $player->getDisplayName() . ": §7" . $msg);
                    }
                }
            } else {
                foreach ($this->game->players as $pt) {
                    if ($this->game->getTeam($pt) == $team) {
                        $pt->sendMessage($this->game->data["teamColor"][$team] . "TEAM > " . $player->getDisplayName() . ": §7" . $msg);
                    }
                }
            }
        }
        $event->cancel();
    }

    /**
     * @param PlayerExhaustEvent $event
     * @return void
     */
    public function onExhaust(PlayerExhaustEvent $event): void
    {
        $player = $event->getPlayer();
        if ($player instanceof Generator) {
            $event->cancel();
        }
        if ($this->game->phase == Game::PHASE_LOBBY || $this->game->phase == Game::PHASE_RESTART) {
            $event->cancel();
        }
    }

    /**
     * @param EntityRegainHealthEvent $event
     * @return void
     */
    public function onRegen(EntityRegainHealthEvent $event): void
    {
        $player = $event->getEntity();
        if ($event->isCancelled()) return;
        if ($player instanceof Player) {
            if ($event->getRegainReason() == $event::CAUSE_SATURATION) {
                $event->setAmount(0.001);
            }
        }
    }

    /**
     * @param InventoryOpenEvent $event
     * @return void
     */
    public function onInventoryOpen(InventoryOpenEvent $event): void
    {
        $player = $event->getPlayer();
        $inv = $event->getInventory();
        if ($this->game->inGame($player)) {
            if ($this->game->phase == Game::PHASE_GAME) {
                if ($inv instanceof ChestInventory || $inv instanceof EnderChestInventory) {
                    $this->game->inChest[$player->getId()] = $player;
                }
            }
        }
    }

    /**
     * @param InventoryCloseEvent $event
     * @return void
     */
    public function onInventoryClose(InventoryCloseEvent $event): void
    {
        $player = $event->getPlayer();
        $inv = $event->getInventory();
        if ($this->game->inGame($player)) {
            if ($this->game->phase == Game::PHASE_GAME) {
                if ($inv instanceof ChestInventory || $inv instanceof EnderChestInventory) {
                    if (isset($this->game->inChest[$player->getId()])) {
                        unset($this->game->inChest[$player->getId()]);
                    }
                }
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @return void
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        if (!$this->game->inGame($player)) return;

        // spectator next can be acces in any pahse
        if (isset($this->game->spectators[$player->getName()])) {
            $event->cancel();
            return;
        }

        if ($this->game->phase == Game::PHASE_LOBBY || $this->game->phase == Game::PHASE_RESTART) {
            $event->cancel();
            return;
        }

        $event->setXpDropAmount(0);
        if ($block instanceof Bed) {
            $next = $block->getOtherHalf();
            if ($next instanceof Bed) {
                foreach ($this->game->data["teamName"] as $teamName) {
                    if (Utils::vectorToString($block->getPosition()->asVector3()) == $this->game->data["teamBed"][$teamName]) {
                        if ($this->game->getTeam($player) !== $teamName) {
                            $this->game->breakBed($teamName, $player);
                            $event->setDrops([]);
                        } else {
                            $player->sendMessage("§cYou can't break bed your team");
                            $event->cancel();
                        }
                    }
                    if (Utils::vectorToString($next->getPosition()->asVector3()) == $this->game->data["teamBed"][$teamName]) {
                        if ($this->game->getTeam($player) !== $teamName) {
                            $this->game->breakBed($teamName, $player);
                            $event->setDrops([]);
                        } else {
                            $player->sendMessage("§cYou can't break bed your team");
                            $event->cancel();
                        }
                    }
                }
            }

            return;
        }

        $posString = Utils::vectorToString($block->getPosition());

        if (!isset($this->game->placedBlock[$posString])) {
            $event->cancel();
            $player->sendMessage("§cYou can't break block in here");
            return;
        }
    }

    public function onPlayerBedEnter(PlayerBedEnterEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->game->inGame($player)) {
            $event->cancel();
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @return void
     */
    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();
        $blocks = $event->getTransaction()->getBlocks();

        $current = $blocks->current();
        /** @var Block $currentBlock */
        $currentBlock = $current[3];
        if (!$this->game->inGame($player) || $this->game->phase != Game::PHASE_GAME) {
            return;
        }

        $teamSpawns = $this->game->data["teamSpawn"];
        $generators = $this->game->data["generator"];
        $currentBlockPos = $currentBlock->getPosition();

        foreach ($teamSpawns as $spawn) {
            $lv = Utils::stringToVector(":", $spawn);
            if ($currentBlockPos->distance($lv) < 8) {
                $event->cancel();
                $player->sendMessage("§cYou can't place blocks in here.");
                return;
            }
        }

        foreach ($generators as $allGenerator) {
            foreach ($allGenerator as $generator) {
                $lv = Utils::stringToVector(":", $generator);
                if ($currentBlockPos->distance($lv) < 1) {
                    $event->cancel();
                    $player->sendMessage("§cYou can't place blocks in here.");
                    return;
                }
            }
        }

        if ($currentBlockPos->getY() > 256) {
            $event->cancel();
            $player->sendMessage("§cYou have reached the build limit.");
            return;
        }

        if ($currentBlock instanceof TNT) {
            $event->cancel();
            $ih = $player->getInventory()->getItemInHand();
            $this->game->spawnTNT($currentBlockPos, 50);
            $ih->setCount($ih->getCount() - 1);
            $player->getInventory()->setItemInHand($ih);
            return;
        }

        if ($currentBlock instanceof Chest) {
            $event->cancel();
            $this->game->spawnTower($player, $currentBlock);
            return;
        }

        $this->game->addPlacedBlock($currentBlock);
    }


    /**
     * @param PlayerDropItemEvent $event
     * @return void
     */
    public function onItemDrop(PlayerDropItemEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        if (!$this->game->inGame($player)) {
            return;
        }

        // spectator next can be acces in any pahse
        if (isset($this->game->spectators[$player->getName()])) {
            $event->cancel();
            return;
        }

        if ($this->game->phase == Game::PHASE_RESTART || $this->game->phase == Game::PHASE_LOBBY) {
            $event->cancel();
            return;
        }


        if ($item instanceof Sword || $item instanceof Armor || $item->getTypeId() == ItemTypeIds::SHEARS || $item instanceof Pickaxe || $item instanceof Axe) {
            $event->cancel();
        }
    }

    /**
     * @param EntityMotionEvent $event
     * @return void
     */
    public function onEntityMotion(EntityMotionEvent $event): void
    {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            if (isset($this->game->spectators[$entity->getName()])) {
                $event->cancel();
            }

            return;
        }
        if ($entity instanceof ShopVillager || $entity instanceof UpgradeVillager || $entity instanceof Generator) {
            $event->cancel();
            return;
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @return void
     */
    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setQuitMessage("");
        if ($this->game->inGame($player)) {
            $this->game->disconnectPlayer($player);
        }
    }

    /**
     * @param DataPacketReceiveEvent $event
     * @return void
     */
    public function onReceivePacket(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();
        if ($packet instanceof InventoryTransactionPacket && $packet->trData instanceof UseItemOnEntityTransactionData && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_INTERACT) {
            if ($this->game->phase === 1) {
                if ($this->game->inGame($player)) {
                    $entity = $this->game->plugin->getServer()->getWorldManager()->findEntity($packet->trData->getActorRuntimeId());
                    if ($entity instanceof ShopVillager) {
                        $this->game->shopMenu($player);
                    } else if ($entity instanceof UpgradeVillager) {
                        $this->game->upgradeGUI($player);
                    }
                }
            }
        }
        if ($packet instanceof LevelSoundEventPacket) {
            if ($this->game->inGame($player)) {
                if (isset($this->game->spectators[$event->getOrigin()->getPlayer()->getName()])) {
                    $event->cancel();
                    $player->getNetworkSession()->sendDataPacket($packet);
                }
            } else if ($this->game->inGame($player) && $this->game->phase == 0) {
                if ($packet->sound == 42 || $packet->sound == 43 || $packet->sound == 41 || $packet->sound == 40 || $packet->sound == 35) {
                    $event->cancel();
                }
            }
        }
    }

    /**
     * @handleCancelled true
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        if ($player->getWorld()->getFolderName() == $this->game->world->getFolderName()) {
            if (!$this->game->inGame($player)) {
                return;
            }
            if ($item->getTypeId() == ExtraBedWarsItem::IRON_GOLEM_SPAWN_EGG) {
                $this->game->spawnDreamDefender(Location::fromObject($event->getBlock()->getPosition()->add(0, 1, 0), $player->getWorld()), $player);
                $event->cancel();
                return;
            }
        }
    }

    /**
     * @param PlayerItemUseEvent $event
     * @priority NORMAL
     * @handleCancelled true
     * @return void
     */
    public function onPlayerItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $itemN = $item->getCustomName();
        if ($player->getWorld()->getFolderName() == $this->game->world->getFolderName()) {
            if (!$this->game->inGame($player)) {
                return;
            }

            if ($itemN == "§aReturn to lobby") {
                $this->game->disconnectPlayer($player);
                return;
            }

            if ($itemN == "§eSpectator") {
                $this->game->spectatorForm($player);
                return;
            }
            if ($itemN == "Select Team") {
                $this->game->selectTeam($player);
                return;
            }

            if ($item->getTypeId() == ItemTypeIds::FIRE_CHARGE) {
                $location = Location::fromObject($player->getEyePos(), $player->getWorld(), $player->getLocation()->yaw, $player->getLocation()->pitch);
                $this->game->spawnFireball($location, $player);
                return;
            }
        }
    }


    /**
     * @param EntityItemPickupEvent $event
     * @return void
     */
    public function onEntityItemPickup(EntityItemPickupEvent $event): void
    {
        if ($event->getItem()->getTypeId() == ItemTypeIds::ARROW) {
            $inv = $event->getInventory();
            if ($inv instanceof PlayerInventory) {
                $player = $inv->getHolder();
                if ($event->isCancelled()) return;
                if (isset($this->game->spectators[$player->getName()])) {
                    $event->cancel();
                }
                if ($player instanceof Player && $player->getWorld()->getFolderName() == $this->game->world->getFolderName()) {
                    if ($this->game->phase == Game::PHASE_RESTART) {
                        $event->cancel();
                    }
                }
            }
        }
    }


    /**
     * @param EntityDamageEvent $event
     * @return void
     */
    public function onDamage(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();
        $isFinal = "";

        // entity area
        if ($entity instanceof Generator) {
            $event->cancel();
            return;
        }

        // human
        if ($this->game->phase === Game::PHASE_LOBBY || $this->game->phase === Game::PHASE_RESTART) {
            if ($entity instanceof Player) {
                if (!$this->game->inGame($entity)) return;
                $event->cancel();
            }
            return;
        }


        // if instanceof shop dll
        // jika yang terkena damage tidak player maka diam
        if (!$entity instanceof Player || !$this->game->inGame($entity)) return;

        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();

            if ($damager instanceof EnderDragon) {
                $entity->getPosition()->asVector3()->multiply(2);
            } else if ($damager instanceof Player) {
                if ($this->game->getTeam($damager) == $this->game->getTeam($entity)) {
                    $event->cancel();
                    return;
                }

                $this->game->lastDamager[$entity->getName()] = $damager;
            }
        }



        if (($entity->getHealth() - $event->getFinalDamage()) > 0) {
            if ($event->getCause() == $event::CAUSE_VOID) {
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    (new EntityDamageByEntityEvent($this->game->lastDamager[$entity->getName()], $entity, $event::CAUSE_VOID, 100))->call();
                    return;
                }
                (new EntityDamageEvent($entity, $event::CAUSE_VOID, 100))->call();
                return;
            }
            return;
        }

        $event->cancel();

        if (!$this->game->bedState($entity)) {
            $this->game->dropItem($entity);
            $isFinal = "§l§bFINAL KILL!";
            $this->game->startSpectator($entity);

            if (isset($this->game->lastDamager[$entity->getName()])) $this->game->scoreData["final_kills"][$this->game->lastDamager[$entity->getName()]->getName()]++;
        } else {
            $this->game->startRespawn($entity);
            $entity->teleport($this->game->world->getSafeSpawn());
            if (isset($this->game->lastDamager[$entity->getName()])) $this->game->scoreData["kills"][$this->game->lastDamager[$entity->getName()]->getName()]++;
        }

        $msg = "";
        switch ($event->getCause()) {
            case $event::CAUSE_CONTACT:
            case $event::CAUSE_ENTITY_ATTACK:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7was killed by " . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getName() . $isFinal;

                    unset($this->game->lastDamager[$entity->getName()]);
                }
                $this->game->broadcastMessage($msg);
                break;
            case $event::CAUSE_PROJECTILE:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7was killed by " . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getDisplayName() . " §7with projectile. " . $isFinal;
                } else {
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7death with projectile. " . $isFinal;
                }
                $this->game->broadcastMessage($msg);
                break;
            case $event::CAUSE_BLOCK_EXPLOSION:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7death with explosion by " . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getDisplayName() . "§7. " . $isFinal;
                    unset($this->game->lastDamager[$entity->getName()]);
                } else {
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7death by explosion. " . $isFinal;
                }
                $this->game->broadcastMessage($msg);
                break;
            case $event::CAUSE_FALL:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7fell from high place by " . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getDisplayName() . "§7. " . $isFinal;
                    unset($this->game->lastDamager[$entity->getName()]);
                } else {
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7fell from high place. " . $isFinal;
                }
                $this->game->broadcastMessage($msg);
                break;
            case $event::CAUSE_VOID:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7was thrown into void by §f" . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getDisplayName() . "§7. " . $isFinal;
                    unset($this->game->lastDamager[$entity->getName()]);
                } else {
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7fell into void. " . $isFinal;
                }
                $this->game->broadcastMessage($msg);
                break;
            case $event::CAUSE_ENTITY_EXPLOSION:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7was exploded by " . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getDisplayName() . "§7. " . $isFinal;
                    unset($this->game->lastDamager[$entity->getName()]);
                } else {
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7death by explosion. " . $isFinal;
                }
                $this->game->broadcastMessage($msg);
                break;
            default:
                if (isset($this->game->lastDamager[$entity->getName()])) {
                    $damager = $this->game->lastDamager[$entity->getName()];
                    $msg = $this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7was killed by " . $this->game->data["teamColor"][$this->game->getTeam($damager)] . $damager->getDisplayName() . " §7with projectile. " . $isFinal;
                }
                $this->game->broadcastMessage($this->game->data["teamColor"][$this->game->getTeam($entity)] . $entity->getDisplayName() . " §7death. " . $isFinal);
        }

        if (isset($this->game->lastDamager[$entity->getName()])) {
            unset($this->game->lastDamager[$entity->getName()]);
        }
    }
}
