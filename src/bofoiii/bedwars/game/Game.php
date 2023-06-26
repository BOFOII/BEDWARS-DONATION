<?php

namespace bofoiii\bedwars\game;

use jojoe77777\FormAPI\SimpleForm;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\entity\CustomTNT;
use bofoiii\bedwars\entity\Generator;
use bofoiii\bedwars\entity\ShopVillager;
use bofoiii\bedwars\entity\UpgradeVillager;
use bofoiii\bedwars\entity\DragonTargetManager;
use bofoiii\bedwars\entity\EnderDragon;
use bofoiii\bedwars\entity\FireBall;
use bofoiii\bedwars\entity\IronGolem;
use bofoiii\bedwars\entity\SilverFish;
use bofoiii\bedwars\task\GameTask;
use bofoiii\bedwars\event\LobbyRemoveEvent;
use bofoiii\bedwars\item\ExtraBedWarsItem;
use bofoiii\bedwars\item\IronGolemSpawnEgg;
use bofoiii\bedwars\utils\Utils;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\Skin;
use pocketmine\inventory\Inventory;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\PotionType;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Random;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\EndermanTeleportSound;
use pocketmine\world\sound\IgniteSound;
use pocketmine\world\World;

class Game
{

    const MSG_MESSAGE = 0;
    const MSG_TIP = 1;
    const MSG_POPUP = 2;
    const MSG_TITLE = 3;

    const PHASE_LOBBY = 0;
    const PHASE_GAME = 1;
    const PHASE_RESTART = 3;

    /** @var BedWars $plugin */
    public BedWars $plugin;

    /** @var array $data */
    public array $data = [];

    /** @var array $index */
    public array $index = [];

    /** @var bool $setup */
    public bool $setup = false;

    /** @var ?World $world */
    public ?World $world = null;

    /** @var ?MapReset $mapReset */
    public ?MapReset $mapReset = null;

    /** @var int $phase */
    public int $phase = 0;

    /** @var Player[] $players */
    public array $players = [];

    /** @var Player[] $spectators */
    public array $spectators = [];

    /** @var array $inChest */
    public array $inChest = [];

    /** @var GameTask $gameTask */
    public GameTask $gameTask;

    /** @var array $teams */
    public array $teams = [];

    /** @var array $tempTeam */
    public array $tempTeam = [];

    /** @var array $pickaxeType */
    public array $pickaxeType = [];

    /** @var array $axeType */
    public array $axeType = [];

    /** @var array $armorType */
    public array $armorType = [];

    /** @var array $ifShears */
    public array $ifShears = [];

    /** @var array $utilityArena */
    public array $utilityArena = [];

    /** @var array $allTraps */
    public array $allTraps = [];

    /** @var array $ifInvis */
    public array $ifInvis = [];

    /** @var array $ifMilk */
    public array $ifMilk = [];

    /** @var array $respawnC */
    public array $respawnC = [];

    /** @var array $placedBlock */
    public array $placedBlock = [];

    /** @var array $scoreData */
    public array $scoreData = [
        "kills" => [],
        "final_kilss" => [],
        "bed_broken" => []
    ];

    /** @var Player[] $lastDamager */
    public array $lastDamager = [];

    /**
     * @param array $arenaFileData
     */
    public function __construct(array $arenaFileData)
    {
        $this->plugin = BedWars::getInstance();
        $this->data = $arenaFileData;
        $this->setup = !$this->enable(false);
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->gameTask = new GameTask($this), 20);

        if ($this->setup) {
            if (empty($this->data)) {
                $this->createBasicData();
            }
        } else {
            $this->loadArena();
        }
    }

    /**
     * @param Player $player
     * @return void
     */
    public function joinToArena(Player $player): void
    {
        if (!$this->data["enabled"]) {
            $player->sendMessage(BedWars::getInstance()->prefix . "§cSorry but you can't join this arena at this moment");
            return;
        }
        if (count($this->players) > ($this->data["maxInTeam"] * count($this->data["teamName"]))) {
            $player->sendMessage(BedWars::getInstance()->prefix . "§cThis arena full!");
            return;
        }
        if ($this->inGame($player)) {
            return;
        }
        if ($this->phase == self::PHASE_GAME || $this->phase == self::PHASE_RESTART) {
            $player->sendMessage(BedWars::getInstance()->prefix . "§cArena has been started!");
            return;
        }

        $selected = false;
        for ($lS = 1; $lS <= ($this->data["maxInTeam"] * count($this->data["teamName"])); $lS++) {
            if (!$selected) {
                if (!isset($this->players[$lS])) {
                    $player->teleport(Position::fromObject(Utils::stringToVector(":", $this->data["waitingSpawn"]), $this->world));
                    foreach ($this->data["teamName"] as $team) {
                        if ($this->setTeam($player, $team)) {
                            $selected = true;
                            break;
                        }
                    }
                    $this->players[$lS] = $player;
                    $this->index[$player->getName()] = $lS;
                }
            }
        }

        $player->getEffects()->clear();
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEnderInventory()->clearAll();
        $player->setAllowFlight(false);
        $player->setFlying(false);
        $player->getCursorInventory()->clearAll();
        $player->setAbsorption(0);
        $player->getInventory()->setItem(8, VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§aReturn to lobby"));
        $player->getInventory()->setItem(0, VanillaBlocks::CHEST()->asItem()->setCustomName("Select Team"));
        $player->setGamemode(GameMode::ADVENTURE());
        $player->setHealth(20);
        $player->getHungerManager()->setFood(20);
        $player->setNameTagVisible();
        $this->broadcastMessage($this->data["teamColor"][$this->getTeam($player)] . $player->getDisplayName() . " §ehas joined (§b" . count($this->players) . "§e/§b" . ($this->data["maxInTeam"] * count($this->data["teamName"])) . "§e)!");
    }

    public function disconnectPlayer(Player $player, string $quitMsg = '', bool $death = false)
    {
        switch ($this->phase) {
            case Game::PHASE_LOBBY:
                $this->broadcastMessage($player->getDisplayName() . " §equit!");
                unset($this->players[array_search($player->getId(), array_column($this->players, 'id'))]);
                break;
            default:
                unset($this->players[$player->getName()]);
                break;
        }
        $api = BedWars::getScore();
        $api->remove($player);
        $player->setHealth(20);
        $player->getHungerManager()->setFood(20);
        $player->getInventory()->clearAll();
        $player->setGamemode(GameMode::SURVIVAL());
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getInventory()->clearAll();
        $player->getEffects()->clear();
        $player->setScoreTag("");
        $player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
        $team = $this->getTeam($player);
        if ($this->phase == self::PHASE_GAME) {
            $this->broadcastMessage($this->data["teamColor"][$this->getTeam($player)] . $player->getDisplayName() . " §7disconnected!");
            $count = 0;
            foreach ($this->players as $mate) {
                if ($this->getTeam($mate) == $team) {
                    $count++;
                }
            }
            if ($count <= 0) {
                $spawn = Utils::stringToVector(":", $this->data["teamBed"][$team]);
                foreach ($this->world->getEntities() as $g) {
                    if ($g instanceof Generator && $g->getPosition()->asVector3()->distance($spawn) < 20) {
                        $g->close();
                    }
                }
                $this->breakBed($team);
                $this->broadcastMessage("");
                $this->broadcastMessage("§l§fTEAM ELIMINATED > §r§b" . $this->data["teamColor"][$team] . $team . " Team §chas been eliminated!");
                $this->broadcastMessage("");
            }
            $this->unsetPlayer($player);
        }
    }
    public function initTeams()
    {
        if (!$this->setup) {
            unset($this->teams);
            unset($this->utilityArena);
            unset($this->allTraps);
            $this->teams = [];
        }
    }

    public function startGame()
    {
        $players = [];
        $this->initShop();
        $this->world->setTime(5000);
        foreach ($this->players as $player) {
            if ($player instanceof Player) {
                $api = BedWars::getScore();
                $api->remove($player);
                $this->axeType[$player->getId()] = 1;
                $this->pickaxeType[$player->getId()] = 1;
                $this->setColorTag($player);
                $player->setNoClientPredictions();
                $this->teleportToSpawn($player);
                $player->setNameTagVisible();
                $player->getInventory()->clearAll();
                $player->setGamemode(GameMode::SURVIVAL());
                $this->setArmor($player);
                $this->setSword($player, VanillaItems::WOODEN_SWORD());
                $player->setNoClientPredictions(false);
                $player->sendTitle("§l§aFIGHT!");
                $players[$player->getName()] = $player;
                $this->scoreData["kills"][$player->getName()] = 0;
                $this->scoreData["final_kills"][$player->getName()] = 0;
                $this->scoreData["beds_broken"][$player->getName()] = 0;
            }
        }
        $this->phase = self::PHASE_GAME;
        $this->players = $players;
        $this->prepareWorld();
        $this->removeTest();
    }

    public function removeTest()
    {
        //berani beda:3
        $ev = new LobbyRemoveEvent(Position::fromObject(Utils::stringToVector(":", $this->data["waitingSpawn"]), $this->world));
        $ev->call();
    }

    public function setColorTag(Player $player)
    {
        if ($this->getTeam($player) == "") {
            return;
        }
        $nametag = $player->getDisplayName();
        $player->setNametag($this->data["teamColor"][$this->getTeam($player)] . "§l " . ucfirst($this->getTeam($player)[0]) . "§r" . $this->data["teamColor"][$this->getTeam($player)] . " " . $nametag);
    }

    public function initShop()
    {
        foreach ($this->data["teamShop"] as $shop) {
            $pos = Location::fromObject(Utils::stringToVector(":", $shop), $this->world);
            $entity = new ShopVillager($pos);
            $entity->setNametag("§bITEM SHOP\n§l§eRIGHT CLICK");
            $entity->game = $this;
            $entity->spawnToAll();
        }
        foreach ($this->data["teamUpgrade"] as $upgrade) {
            $pos = Location::fromObject(Utils::stringToVector(":", $upgrade), $this->world);
            $entity = new UpgradeVillager($pos);
            if ($this->data["maxInTeam"] == 1 || $this->data["maxInTeam"] == 2) {
                $entity->setNametag("§b" . strtoupper(Utils::maxInTeamToGroup($this->data["maxInTeam"])) . "\n§bUPGRADES\n§l§eRIGHT CLICK");
            } else {
                $entity->setNametag("§bTEAM\n§bUPGRADE\n§l§eRIGHT CLICK");
            }
            $entity->game = $this;
            $entity->spawnToAll();
        }
    }

    /**
     * @param Player $player
     * @return void
     */
    public function teleportToSpawn(Player $player): void
    {
        $team = $this->getTeam($player);
        $vc = Utils::stringToVector(":", $this->data["teamSpawn"][$team]);
        $player->teleport($vc->add(0.5, 0.5, 0.5));
    }

    public function prepareWorld()
    {
        foreach ($this->data["teamName"] as $teams) {
            $this->utilityArena[$teams]["generator"] = 1;
            $this->utilityArena[$teams]["sharpness"] = 1;
            $this->utilityArena[$teams]["protection"] = 1;
            $this->utilityArena[$teams]["haste"] = 1;
            $this->utilityArena[$teams]["health"] = 1;
        }
        $this->initGenerator();
        $this->checkTeam();
        foreach ($this->world->getEntities() as $e) {
            if ($e instanceof ItemEntity) {
                $e->flagForDespawn();
            }
        }
    }

    public function initGenerator()
    {
        foreach ($this->data["teamName"] as $team) {
            $iPath = $this->plugin->getDataFolder() . "Skin/invisible.png";
            $iPos = Location::fromObject(Utils::stringToVector(":", $this->data["teamGenerator"][$team]["iron"]), $this->world);
            $iSkin = Utils::getSkinFromFile($iPath);
            $i = new Generator($iPos, new Skin($iSkin->getSkinId(), $iSkin->getSkinData(), '', "geometry.player_head", Generator::GEOMETRY));
            $i->type = "iron";
            $i->generatorLevel = 1;
            $i->setScale(0.000001);
            $i->spawnToAll();

            //
            $gPath = $this->plugin->getDataFolder() . "Skin/invisible.png";
            $gPos = Location::fromObject(Utils::stringToVector(":", $this->data["teamGenerator"][$team]["gold"]), $this->world);
            $gSkin = Utils::getSkinFromFile($gPath);
            $g = new Generator($gPos, new Skin($gSkin->getSkinId(), $gSkin->getSkinData(), '', "geometry.player_head", Generator::GEOMETRY));
            $g->type = "gold";
            $g->generatorLevel = 1;
            $g->setScale(0.000001);
            $g->spawnToAll();
        }
        foreach ($this->data["generator"]["diamond"] as $diamondGenerator) {
            $path = $this->plugin->getDataFolder() . "Skin/diamond.png";
            $pos = Location::fromObject(Utils::stringToVector(":", $diamondGenerator)->add(0, 1, 0), $this->world);
            $skin = Utils::getSkinFromFile($path);
            $g = new Generator($pos, new Skin($skin->getSkinId(), $skin->getSkinData(), '', "geometry.player_head", Generator::GEOMETRY));
            $g->type = "diamond";
            $g->generatorLevel = 1;
            $g->setScale(1.4);
            $g->spawnToAll();
        }
        foreach ($this->data["generator"]["emerald"] as $emeraldGenerator) {
            $path = $this->plugin->getDataFolder() . "Skin/emerald.png";
            $pos = Location::fromObject(Utils::stringToVector(":", $emeraldGenerator)->add(0, 1, 0), $this->world);
            $skin = Utils::getSkinFromFile($path);
            $g = new Generator($pos, new Skin($skin->getSkinId(), $skin->getSkinData(), '', "geometry.player_head", Generator::GEOMETRY));
            $g->type = "emerald";
            $g->generatorLevel = 1;
            $g->setScale(1.4);
            $g->spawnToAll();
        }
    }

    /**
     * @param string $type
     * @param int $level
     * @return void
     */
    public function upgradeGeneratorTier(string $type, int $level): void
    {
        foreach ($this->world->getEntities() as $e) {
            if ($e instanceof Generator && $e->type == $type) {
                $e->generatorLevel = $level;
            }
        }
    }

    /**
     * @param string $team
     * @return bool
     */
    public function bedStatus(string $team): bool
    {
        $vc = Utils::stringToVector(":", $this->data["teamBed"][$team]);
        if ($this->world->getBlock($vc) instanceof Bed) {
            $status = true;
        } else {
            $status = false;
        }
        return $status;
    }

    /**
     * @param string $team
     * @return string
     */
    public function statusTeam(string $team): string
    {
        $vc = Utils::stringToVector(":", $this->data["teamBed"][$team]);
        if ($this->world->getBlock($vc) instanceof Bed) {
            return "§a";
        } else {
            $count = $this->getCountTeam($team);
            if ($count == 0) {
                return "§c";
            } else {
                return "§a" . $count;
            }
        }
    }

    public function getAliveTeams(): array
    {
        $GLOBALS["aliveTeams"] = [];
        foreach ($this->data["teamName"] as $teamName) {
            if ($this->getCountTeam($teamName) >= 1) {
                $GLOBALS["aliveTeams"][] = $teamName;
            }
        }
        return $GLOBALS["aliveTeams"];
    }

    public function setSuddenDeath()
    {
        foreach ($this->players as $player) {
            $player->sendTitle("§cSudden Death");
            Utils::addSound($player, 'mob.enderdragon.growl');
        }

        $dragonTarget = new DragonTargetManager($this, Utils::stringToVector(":", $this->data["waitingSpawn"]));

        foreach ($this->getAliveTeams() as $teamName) {
            $dragonTarget->addDragon($teamName);
            $this->broadcastMessage("§cSUDDEN DEATH: §6+§b1 " . $this->data["teamColor"][$teamName] . $teamName . " Dragon!");
        }
    }

    public function destroyAllBeds()
    {
        $this->broadcastMessage("§c§lAll beds have been destroyed!");
        foreach ($this->data["teamName"] as $t) {
            $pos = Utils::stringToVector(":", $this->data["teamBed"][$t]);
            $bed = $this->world->getBlock($pos);
            if ($bed instanceof Bed) {
                $next = $bed->getOtherHalf();
                $this->world->setBlock($pos, VanillaBlocks::AIR());
                $this->world->setBlock($next->getPosition()->asVector3(), VanillaBlocks::AIR());
                foreach ($this->players as $player) {
                    if ($player instanceof Player) {
                        $player->sendTitle("§l§cBED DESTROYED", "§fAll beds have been destroyed!");
                        Utils::addSound($player, 'mob.wither.death');
                    }
                }
            }
        }
    }

    public function checkTeam()
    {
        foreach ($this->data["teamName"] as $team) {
            if ($this->getCountTeam($team) == 0) {
                $pos = Utils::stringToVector(":", $this->data["teamBed"][$team]);
                $bed = $this->world->getBlock($pos);
                if ($bed instanceof Bed) {
                    $this->world->setBlock($pos, VanillaBlocks::AIR());
                    $this->world->setBlock($bed->getOtherHalf()->getPosition()->asVector3(), VanillaBlocks::AIR());
                }
                foreach ($this->world->getEntities() as $g) {
                    if ($g instanceof Generator) {
                        if ($g->getPosition()->asVector3()->distance($pos) < 20) {
                            $g->close();
                        }
                    }
                }
            }
        }
    }


    /**
     * @param string $team
     * @param Player|null $player
     * @return void
     */
    public function breakBed(string $team, Player $player = null): void
    {
        if (!isset($this->data["teamBed"][$team])) return;
        $pos = Utils::stringToVector(":", $this->data["teamBed"][$team]);
        $bed = $this->world->getBlock($pos);

        if ($bed instanceof Bed) {
            $next = $bed->getOtherHalf();
            $this->world->addParticle($pos, new BlockBreakParticle($bed));
            $this->world->addParticle($next->getPosition()->asVector3(), new BlockBreakParticle($bed));
            $this->world->setBlock($pos, VanillaBlocks::AIR());
            $this->world->setBlock($next->getPosition()->asVector3(), VanillaBlocks::AIR());
        }

        if ($player instanceof Player) {
            $this->broadcastMessage("");
            $this->broadcastMessage("§l§fBED DESTRUCTION > §r" . $this->data["teamColor"][$team] . ucfirst($team) . " Bed §7was destroyed by " . $this->data["teamColor"][$this->getTeam($player)] . $player->getDisplayName() . "§7!");
            $this->scoreData["beds_broken"][$player->getName()]++;
            $this->broadcastMessage("");
        }

        foreach ($this->players as $p) {
            if ($p instanceof Player && $this->getTeam($p) == $team) {
                $p->sendTitle("§l§cBED DESTROYED", "§fYou will no longer respawn!");
                Utils::addSound($p, "mob.wither.death");
            }
        }
    }

    public function destroyEntity()
    {
        foreach ($this->world->getEntities() as $g) {
            if ($g instanceof Generator) {
                $g->close();
            } else if ($g instanceof EnderDragon) {
                $g->close();
            } else if ($g instanceof SilverFish) {
                $g->close();
            } else if ($g instanceof CustomTNT) {
                $g->close();
            } else if ($g instanceof IronGolem) {
                $g->close();
            } else if ($g instanceof ItemEntity) {
                $g->close();
            } else if ($g instanceof ShopVillager) {
                $g->close();
            } else if ($g instanceof UpgradeVillager) {
                $g->close();
            }
        }
        foreach ($this->world->getPlayers() as $p) {
            unset($this->tempTeam[$p->getName()]);
            $this->placedBlock = [];
        }
    }

    /**
     * @param string $team
     * @return void
     */
    public function setTeamsWin(string $team): void
    {
        $this->destroyEntity();
        foreach ($this->world->getPlayers() as $p) {
            $p->setNametag($p->getDisplayName());
            $p->setScoreTag("");
        }
        foreach ($this->spectators as $spectator) {
            $this->unsetPlayer($spectator);
        }
        foreach ($this->players as $player) {
            if ($this->getTeam($player) == $team) {
                // TODO : WIN EFFECT
                if ($player->hasPermission("night.effect")) {
                    BedWars::getInstance()->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function (): void {
                        $this->world->setTime(13000); // Night
                        $this->world->setTime(1000); // Morning
                        $this->world->setTime(6000); // Afternoon
                    }), 20, 20);
                }
                $player->sendTitle("§l§6VICTORY");
                $player->setHealth(20);
                $player->getHungerManager()->setFood(20);
                $player->setGamemode(GameMode::ADVENTURE());
                $player->getInventory()->clearAll();
                $player->getArmorInventory()->clearAll();
                $player->getCursorInventory()->clearAll();
                Utils::addSound($player, "random.levelup");
                $api = BedWars::getScore();
                $api->remove($player);
                $this->unsetPlayer($player);
                $player->getInventory()->setItem(8, VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§aReturn to lobby"));
            }
        }
        $this->placedBlock = [];
        $this->utilityArena = [];
        $this->axeType = [];
        $this->pickaxeType = [];
        $this->ifMilk = [];
        $this->inChest = [];
        $this->phase = self::PHASE_RESTART;
    }

    public function setDraw()
    {
        $this->destroyEntity();
        foreach ($this->world->getPlayers() as $p) {
            $p->setScoreTag("");
            $p->setNameTag($p->getDisplayName());
        }
        foreach ($this->players as $player) {
            if ((!$player instanceof Player) || (!$player->isOnline())) {
                $this->phase = self::PHASE_RESTART;
                return;
            }
            $player->setHealth(20);
            $player->getHungerManager()->setFood(20);
            $player->setGamemode(GameMode::ADVENTURE());
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getCursorInventory()->clearAll();
            $api = BedWars::getScore();
            $api->remove($player);
            $this->unsetPlayer($player);
            $player->getInventory()->setItem(8, VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§aReturn to lobby"));
        }
        $this->placedBlock = [];
        $this->utilityArena = [];
        $this->axeType = [];
        $this->pickaxeType = [];
        $this->ifMilk = [];
        $this->inChest = [];
        $this->broadcastMessage("§l§cGAME OVER", self::MSG_TITLE);
        $this->phase = self::PHASE_RESTART;
    }

    /**
     * @param Block $block
     * @return void
     */
    public function addPlacedBlock(Block $block): void
    {
        $this->placedBlock[Utils::vectorToString($block->getPosition()->asVector3())] = Utils::vectorToString($block->getPosition()->asVector3());
        // print_r($this->placedBlock);
    }

    /**
     * @param Player $player
     * @param $value
     * @return void
     */
    public function setInvis(Player $player, $value): void
    {
        $arm = $player->getArmorInventory();
        if ($value) {
            $this->ifInvis[$player->getId()] = $player;
            $hide = $this->armorInvis($player);
            foreach ($this->players as $p) {
                if ($player->getId() == $p->getId()) {
                    $pk2 = new InventoryContentPacket();
                    $pk2->windowId = $player->getNetworkSession()->getInvManager()->getWindowId($arm);
                    $pk2->items = [ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getHelmet())), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getChestplate())), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getChestplate())), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getBoots()))];
                    $player->getNetworkSession()->sendDataPacket($pk2);
                } else {
                    if ($this->getTeam($player) !== $this->getTeam($p)) {
                        $p->getNetworkSession()->sendDataPacket($hide);
                    }
                }
            }
        } else {
            if (isset($this->ifInvis[$player->getId()])) {
                unset($this->ifInvis[$player->getId()]);
            }
            $player->setInvisible(false);
            $nohide = $this->armorInvis($player, false);
            foreach ($this->players as $p) {
                if ($player->getId() == $p->getId()) {
                    $pk2 = new InventoryContentPacket();
                    $pk2->windowId = $player->getNetworkSession()->getInvManager()->getWindowId($arm);
                    $pk2->items = [ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getHelmet())), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getChestplate())), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getChestplate())), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getBoots()))];
                    $player->getNetworkSession()->sendDataPacket($pk2);
                } else {
                    if ($this->getTeam($player) !== $this->getTeam($p)) {
                        $p->getNetworkSession()->sendDataPacket($nohide);
                    }
                }
            }
        }
    }

    /**
     * @param Player $player
     * @param bool $hide
     * @return MobArmorEquipmentPacket
     */
    public function armorInvis(Player $player, bool $hide = true): MobArmorEquipmentPacket
    {
        if ($hide) {
            $pk = new MobArmorEquipmentPacket();
            $pk->actorRuntimeId = $player->getId();
            $pk->head = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaBlocks::AIR()));
            $pk->chest = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaBlocks::AIR()));
            $pk->legs = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaBlocks::AIR()));
            $pk->feet = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaBlocks::AIR()));
        } else {
            $arm = $player->getArmorInventory();
            $pk = new MobArmorEquipmentPacket();
            $pk->actorRuntimeId = $player->getId();

            $pk->head = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getHelmet()));
            $pk->chest = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getChestplate()));
            $pk->legs = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getLeggings()));
            $pk->feet = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($arm->getBoots()));
        }
        $player->getNetworkSession()->sendDataPacket($pk);
        return $pk;
    }

    /**
     * @param Vector3 $pos
     * @return bool
     */
    public function isAllowedPlace(Vector3 $pos): bool
    {
        foreach ($this->data["teamName"] as $team) {
            foreach ($this->data["teamSpawn"][$team] as $spawn) {
                if ($pos->distance($spawn) > 8) {
                    return true;
                }
            }
        }
        return false;
    }



    /**
     * @param Player $player
     * @return void
     */
    public function reduceTime(Player $player): void
    {
        if (in_array($this->gameTask->upgradeNextTime, [1, 2, 3, 4])) {
            if ($this->gameTask->upgradeTime > 70) {
                $this->gameTask->upgradeTime -= 50;
            } else {
                $player->sendMessage("§cPlease wait to reduce time again!");
            }

            return;
        }

        if ($this->gameTask->upgradeNextTime == 5) {
            if ($this->gameTask->bedGoneTime > 70) {
                $this->gameTask->bedGoneTime -= 50;
            } else {
                $player->sendMessage("§cPlease wait to reduce time again!");
            }
            return;
        }
        if ($this->gameTask->upgradeNextTime == 6) {
            if ($this->gameTask->suddenDeathTime > 70) {
                $this->gameTask->suddenDeathTime -= 50;
            } else {
                $player->sendMessage("§cPlease wait to reduce time again!");
            }
            return;
        }
        if ($this->gameTask->upgradeNextTime == 7) {
            if ($this->gameTask->gameOverTime > 70) {
                $this->gameTask->gameOverTime -= 50;
            } else {
                $player->sendMessage("§cPlease wait to reduce time again!");
            }
            return;
        }
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function bedState(Player $player): bool
    {
        $team = $this->getTeam($player);
        $state = false;
        if ($team !== "") {
            $vc = Utils::stringToVector(":", $this->data["teamBed"][$team]);
            if ($this->world->getBlock($vc) instanceof Bed) $state = true;
        }
        return $state;
    }

    /**
     * @param Player $player
     * @return void
     */
    public function dropItem(Player $player): void
    {
        foreach ($player->getInventory()->getContents() as $cont) {
            if (in_array($cont->getTypeId(), [
                BlockTypeIds::WOOL, BlockTypeIds::GLAZED_TERRACOTTA, BlockTypeIds::OBSIDIAN, 386, ItemTypeIds::DIAMOND, ItemTypeIds::GOLD_INGOT, ItemTypeIds::IRON_INGOT, BlockTypeIds::END_STONE, BlockTypeIds::LADDER, 241, BlockTypeIds::OAK_PLANKS, ItemTypeIds::POTION, ItemTypeIds::GOLDEN_APPLE, ItemTypeIds::FIRE_CHARGE, BlockTypeIds::TNT,
                123, // SPAWN EGG
                ItemTypeIds::SNOWBALL, ItemTypeIds::EGG
            ])) {
                $player->getWorld()->dropItem($player->getPosition(), $cont);
            }
        }
    }

    public function startRespawn(Player $player)
    {
        $player->getInventory()->clearAll();
        $player->getEffects()->clear();
        $player->setGamemode(GameMode::SPECTATOR());
        $player->setAllowFlight(true);
        $player->teleport($player->getPosition()->asVector3()->add(0, 5, 0));
        $player->sendTitle("§l§cYOU DIED!");
        $this->respawnC[$player->getName()] = 6;
        $axe = $this->getLessTier($player, true);
        $pickaxe = $this->getLessTier($player, false);
        $this->axeType[$player->getId()] = $axe;
        $this->pickaxeType[$player->getId()] = $pickaxe;
    }

    public function startSpectator(Player $player)
    {
        switch ($this->phase) {
            case Game::PHASE_LOBBY:
                $index = "";
                foreach ($this->players as $i => $p) {
                    if ($p->getId() == $player->getId()) {
                        $index = $i;
                    }
                }
                if ($index != "") {
                    unset($this->players[$index]);
                }
                break;
            default:
                unset($this->players[$player->getName()]);
                break;
        }
        $team = $this->getTeam($player);
        if ($this->phase == self::PHASE_GAME) {
            $count = 0;
            foreach ($this->players as $peler) {
                if ($this->getTeam($peler) == $team) {
                    if (!isset($this->spectators[$peler->getName()])) {
                        $count++;
                    }
                }
            }
            if ($count <= 0) {
                $spawn = Utils::stringToVector(":", $this->data["teamBed"][$team]);
                foreach ($this->world->getEntities() as $g) {
                    if ($g instanceof Generator) {
                        if ($g->getPosition()->asVector3()->distance($spawn) < 20) {
                            $g->close();
                        }
                    }
                }
                $this->broadcastMessage("");
                $this->broadcastMessage("§l§fTEAM ELIMINATED > §r" . $this->data["teamColor"][$team] . $team . " Team §chas been eliminated!");
                $this->broadcastMessage("");
            }
        }

        $player->sendTitle("§l§cYOU DIED!", "§7You are now spectator");
        $player->setScoreTag("");
        $player->setNameTag($player->getDisplayName());
        $this->tempTeam[$player->getName()] = $this->getTeam($player);
        $this->spectators[$player->getName()] = $player;
        unset($this->teams[$team][$player->getName()]);
        unset($this->armorType[$player->getName()]);
        unset($this->ifShears[$player->getName()]);
        unset($this->axeType[$player->getId()]);
        unset($this->inChest[$player->getId()]);
        unset($this->pickaxeType[$player->getId()]);
        unset($this->players[$player->getName()]);
        $player->getEffects()->clear();
        $player->setGamemode(GameMode::SPECTATOR());
        $player->setHealth(20);
        $player->setAllowFlight(true);
        $player->setFlying(true);
        $player->getHungerManager()->setFood(20);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getInventory()->setHeldItemIndex(4);
        $spawnLoc = $this->world->getSafeSpawn();
        $spawnPos = new Vector3(round($spawnLoc->getX()) + 0.5, $spawnLoc->getY() + 10, round($spawnLoc->getZ()) + 0.5);
        $player->teleport($spawnPos);
        $player->getInventory()->setItem(8, VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem()->setCustomName("§aReturn to lobby"));
        $player->getInventory()->setItem(4, VanillaItems::COMPASS()->setCustomName("§eSpectator"));
    }

    /**
     * @param Player $player
     * @return void
     */
    public function respawn(Player $player): void
    {
        $player->setGamemode(GameMode::SURVIVAL());
        $player->sendTitle("§l§aRESPAWNED!");
        $player->setHealth(20);
        $player->setAllowFlight(false);
        $player->setFlying(false);
        $player->getHungerManager()->setFood(20);
        $this->teleportToSpawn($player);
        $this->setArmor($player);
        $sword = VanillaItems::WOODEN_SWORD();
        $this->setSword($player, $sword);
        $axe = $this->getAxeByTier($player, false);
        $pickaxe = $this->getPickaxeByTier($player, false);
        if (isset($this->axeType[$player->getId()])) {
            if ($this->axeType[$player->getId()] > 1) {
                $player->getInventory()->addItem($axe);
            }
        }
        if (isset($this->pickaxeType[$player->getId()])) {
            if ($this->pickaxeType[$player->getId()] > 1) {
                $player->getInventory()->addItem($pickaxe);
            }
        }
    }

    /**
     * @param Player $player
     * @param string $team
     * @return bool
     */
    public function setTeam(Player $player, string $team): bool
    {
        if ($this->getCountTeam($team) < $this->data["maxInTeam"]) {
            if (($teamp = $this->getTeam($player)) !== "") {
                unset($this->teams[$teamp][$player->getName()]);
            }
            $this->teams[$team][$player->getName()] = $player;
            return true;
        }
        return false;
    }

    public function getTeam(Player $player): string
    {
        $resultTeam = "";
        foreach ($this->data["teamName"] as $team) {
            if (isset($this->tempTeam[$player->getName()])) {
                $resultTeam = $this->tempTeam[$player->getName()];
            }
            if (isset($this->teams[$team][$player->getName()])) {
                $resultTeam = $team;
            }
        }
        return $resultTeam;
    }

    public function getTeamMembers(String $tColor): array
    {
        return $this->teams[$tColor];
    }

    public function isInTeam(String $tColor, Player $player): bool
    {
        return isset($this->teams[$tColor][$player->getName()]);
    }

    /**
     * @param Player $player
     */
    public function selectTeam(Player $player)
    {
        // $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        // $menu->setName("Select Team");
        // $inventory = $menu->getInventory();
        // $a = 0;
        // $items = [];
        // foreach ($this->data["teamName"] as $team) {
        //     $wool = VanillaBlocks::WOOL()->setColor(Utils::getDyeColor($team))->asItem();
        //     $wool->setLore([TextFormat::GREEN . "Players: " . TextFormat::YELLOW . $this->getCountTeam($team)]);
        //     $wool->setCustomName(ucfirst($team) . " Team");
        //     if(!isset(Utils::$woolState[$team])) {
        //         Utils::$woolState[$team] = $wool->getStateId();
        //     }
        //     $inventory->addItem($wool);
        //     $items[$a] = $wool;
        //     $a++;
        // }


        // $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($player, $menu) {
        //     $item = $transaction->getItemClicked();

        //     $team = Utils::getTeamColor($item->getMeta());
        //     $playerTeam = $this->getTeam($player);
        //     if ($playerTeam == $team) {
        //         $player->sendMessage("You already in this team!");
        //         return;
        //     }
        //     if (!in_array($team, $this->data["teamName"])) {
        //         return;
        //     }
        //     if ($this->getCountTeam($team) >= $this->data["maxInTeam"]) {
        //         $player->sendMessage("Team is full");
        //         return;
        //     }
        //     $this->setTeam($player, $team);
        //     $player->sendMessage("You've joined team " . $team);
        //     $inventory = $menu->getInventory();
        //     $inventory->clearAll();
        //     $a = 0;
        //     $items = [];
        //     foreach ($this->data["teamName"] as $team) {
        //         $items[$a] = VanillaBlocks::WOOL()->setColor(Utils::getDyeColor($team))->asItem();
        //         $items[$a]->setLore([TextFormat::GREEN . "Players: " . TextFormat::YELLOW . $this->getCountTeam($team)]);
        //         $items[$a]->setCustomName(ucfirst($team) . " Team");
        //         $inventory->addItem($items[$a]);
        //         $a++;
        //     }
        // }));
        // $menu->send($player);
    }

    /**
     * @param string $teamName
     * @return int
     */
    public function getCountTeam(string $teamName): int
    {
        return isset($this->teams[$teamName]) ? count($this->teams[$teamName]) : 0;
    }

    public function unsetPlayer(Player $player)
    {
        unset($this->teams[$this->getTeam($player)][$player->getName()]);
        unset($this->armorType[$player->getName()]);
        unset($this->ifShears[$player->getName()]);
        unset($this->axeType[$player->getId()]);
        unset($this->inChest[$player->getId()]);
        unset($this->pickaxeType[$player->getId()]);
        unset($this->players[$player->getName()]);
        unset($this->spectators[$player->getName()]);
        unset($this->scoreData["kills"][$player->getName()]);
        unset($this->scoreData["final_kills"][$player->getName()]);
        unset($this->scoreData["beds_broken"][$player->getName()]);
        $player->setScoreTag("");
        $player->setNameTag($player->getDisplayName());
    }

    /**
     * @param string $message
     * @param int $id
     * @param string $subMessage
     * @return void
     */
    public function broadcastMessage(string $message, int $id = 0, string $subMessage = ""): void
    {
        foreach ($this->world->getPlayers() as $player) {
            switch ($id) {
                case self::MSG_MESSAGE:
                    $player->sendMessage($message);
                    break;
                case self::MSG_TIP:
                    $player->sendTip($message);
                    break;
                case self::MSG_POPUP:
                    $player->sendPopup($message);
                    break;
                case self::MSG_TITLE:
                    $player->sendTitle($message, $subMessage);
                    break;
            }
        }
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function inGame(Player $player): bool
    {
        switch ($this->phase) {
            case self::PHASE_LOBBY:
                $inGame = false;
                foreach ($this->players as $players) {
                    if ($players->getId() == $player->getId()) {
                        $inGame = true;
                    }
                }
                return $inGame;
            default:
                return isset($this->players[$player->getName()]) or isset($this->spectators[$player->getName()]) or isset($this->respawnC[$player->getName()]);
        }
    }



    /**
     * @param Player $player
     * @return bool
     */
    public function spectatorForm(Player $player): bool
    {
        $form = new SimpleForm(function (Player $player, $data = null) {
            $target = $data;
            if ($target === null) {
                return true;
            }
            foreach ($this->world->getPlayers() as $pl) {
                if ($player->getWorld()->getFolderName() == $this->world->getFolderName()) {
                    if ($pl->getName() == $target) {
                        if ($this->inGame($pl)) {
                            $player->teleport($pl->getPosition()->asVector3());
                            $player->sendMessage("§eYou spectator " . $pl->getName());
                        }
                    }
                }
            }
            return true;
        });
        $form->setTitle("§7Spectator Menu");
        $content = "§fSelect a player:";
        if (empty($this->players)) {
            $content = "§cNo Players!";
            $form->addButton("§c§lExit", 0, "textures/blocks/barrier");
            return true;
        }
        $form->setContent($content);
        $count = 0;
        foreach ($this->players as $pl) {
            $count++;
            $form->addButton("§7Teleport to " . $this->data["teamColor"][$this->getTeam($pl)] . $pl->getDisplayName(), 1, "", $pl->getName());
        }
        if ($count == count($this->players)) {
            $form->addButton("§c§lExit", 0, "textures/blocks/barrier");
        }
        $player->sendForm($form);
        return true;
    }

    /**
     * @param Player $player
     * @param string $item
     * @return void
     */
    public function messageBuy(Player $player, string $item): void
    {
        $pk = new LevelSoundEventPacket();
        $pk->sound = 81;
        $pk->extraData = 13;
        $pk->disableRelativeVolume = false;
        $pk->isBabyMob = false;
        $pk->entityType = ":";
        $pk->position = $player->getPosition();
        $player->getNetworkSession()->sendDataPacket($pk);
        $player->sendMessage("§aYou purchased §6" . $item);
    }

    /**
     * @param Player $player
     * @param string $item
     * @param int $cost
     * @return void
     */
    public function notEnought(Player $player, string $item, int $cost): void
    {
        switch (ucfirst($item)) {
            case "Iron":
                $count = 0;
                foreach ($player->getInventory()->getContents() as $allItem) {
                    if ($allItem->getTypeId() == ItemTypeIds::IRON_INGOT) {
                        $count = $allItem->getCount();
                    }
                }
                $need = $cost - $count;
                $player->sendMessage("§cYou don't have enought " . $item . "!" . " Need " . $need . " more!");
                break;
            case "Gold":
                $count = 0;
                foreach ($player->getInventory()->getContents() as $allItem) {
                    if ($allItem->getTypeId() == ItemTypeIds::GOLD_INGOT) {
                        $count = $allItem->getCount();
                    }
                }
                $need = $cost - $count;
                $player->sendMessage("§cYou don't have enought " . $item . "!" . " Need " . $need . " more!");
                break;
            case "Emerald":
                $count = 0;
                foreach ($player->getInventory()->getContents() as $allItem) {
                    if ($allItem->getTypeId() == ItemTypeIds::EMERALD) {
                        $count = $allItem->getCount();
                    }
                }
                $need = $cost - $count;
                $player->sendMessage("§cYou don't have enought " . $item . "!" . " Need " . $need . " more!");
                break;
            case "Diamond":
                $count = 0;
                foreach ($player->getInventory()->getContents() as $allItem) {
                    if ($allItem->getTypeId() == ItemTypeIds::DIAMOND) {
                        $count = $allItem->getCount();
                    }
                }
                $need = $cost - $count;
                $player->sendMessage("§cYou don't have enought " . $item . "!" . " Need " . $need . " more!");
                break;
            default:
                $player->sendMessage("§cYou don't have enought " . $item . "!");
                break;
        }
    }

    /**
     * @param Player $player
     * @return void
     */
    public function setArmor(Player $player): void
    {
        $team = $this->getTeam($player);
        $player->getArmorInventory()->clearAll();
        $enchant = null;
        if (isset($this->utilityArena[$team]["protection"])) {

            if ($this->utilityArena[$team]["protection"] == 2) {
                $enchant = new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 1);
            }
            if ($this->utilityArena[$team]["protection"] == 3) {
                $enchant = new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 2);
            }
            if ($this->utilityArena[$team]["protection"] == 4) {
                $enchant = new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 3);
            }
            if ($this->utilityArena[$team]["protection"] == 5) {
                $enchant = new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4);
            }
        }
        $color = Utils::getColor($team);
        $arm = $player->getArmorInventory();
        if (isset($this->armorType[$player->getName()])) {
            $armor = $this->armorType[$player->getName()];
            if ($armor == "chainmail") {
                $player->getArmorInventory()->clearAll();
                $helm = VanillaItems::LEATHER_CAP();
                if ($helm instanceof Armor) {
                    $helm->setCustomColor($color);
                    $helm->setUnbreakable();
                    if ($enchant !== null) {
                        $helm->addEnchantment($enchant);
                    }
                    $arm->setHelmet($helm);
                }
                $chest = VanillaItems::LEATHER_TUNIC();
                if ($chest instanceof Armor) {
                    $chest->setCustomColor($color);
                    if ($enchant !== null) {
                        $chest->addEnchantment($enchant);
                    }
                    $chest->setUnbreakable();
                    $arm->setChestplate($chest);
                }
                $leg = VanillaItems::CHAINMAIL_LEGGINGS();
                if ($leg instanceof Armor) {
                    if ($enchant !== null) {
                        $leg->addEnchantment($enchant);
                    }
                    $leg->setUnbreakable();
                    $leg->setCustomColor($color);
                    $arm->setLeggings($leg);
                }
                $boots = VanillaItems::CHAINMAIL_BOOTS();
                if ($boots instanceof Armor) {
                    $boots->setUnbreakable();
                    $boots->setCustomColor($color);
                    if ($enchant !== null) {
                        $boots->addEnchantment($enchant);
                    }
                    $arm->setBoots($boots);
                }
            }
            if ($armor == "iron") {
                $helm = VanillaItems::LEATHER_CAP();
                if ($helm instanceof Armor) {
                    $helm->setCustomColor($color);
                    $helm->setUnbreakable();
                    if ($enchant !== null) {
                        $helm->addEnchantment($enchant);
                    }
                    $arm->setHelmet($helm);
                }
                $chest = VanillaItems::LEATHER_TUNIC();
                if ($chest instanceof Armor) {
                    $chest->setCustomColor($color);
                    if ($enchant !== null) {
                        $chest->addEnchantment($enchant);
                    }
                    $chest->setUnbreakable();
                    $arm->setChestplate($chest);
                }
                $leg = VanillaItems::IRON_LEGGINGS();
                if ($leg instanceof Armor) {
                    if ($enchant !== null) {
                        $leg->addEnchantment($enchant);
                    }
                    $leg->setUnbreakable();
                    $arm->setLeggings($leg);
                }
                $boots = VanillaItems::IRON_BOOTS();
                if ($boots instanceof Armor) {
                    if ($enchant !== null) {
                        $boots->addEnchantment($enchant);
                    }
                    $boots->setUnbreakable();
                    $arm->setBoots($boots);
                }
            }
            if ($armor == "diamond") {
                $helm = VanillaItems::LEATHER_CAP();
                if ($helm instanceof Armor) {
                    $helm->setCustomColor($color);
                    $helm->setUnbreakable();
                    if ($enchant !== null) {
                        $helm->addEnchantment($enchant);
                    }
                    $arm->setHelmet($helm);
                }
                $chest = VanillaItems::LEATHER_TUNIC();
                if ($chest instanceof Armor) {
                    $chest->setCustomColor($color);
                    if ($enchant !== null) {
                        $chest->addEnchantment($enchant);
                    }
                    $chest->setUnbreakable();
                    $arm->setChestplate($chest);
                }
                $leg = VanillaItems::DIAMOND_LEGGINGS();
                if ($leg instanceof Armor) {
                    if ($enchant !== null) {
                        $leg->addEnchantment($enchant);
                    }
                    $leg->setUnbreakable();
                    $arm->setLeggings($leg);
                    $leg->setCustomColor($color);
                }
                $boots = VanillaItems::DIAMOND_BOOTS();
                if ($boots instanceof Armor) {
                    if ($enchant !== null) {
                        $boots->addEnchantment($enchant);
                    }
                    $boots->setCustomColor($color);
                    $boots->setUnbreakable();
                    $arm->setBoots($boots);
                }
            }
        } else {
            $helm = VanillaItems::LEATHER_CAP();
            if ($helm instanceof Armor) {
                $helm->setCustomColor($color);
                $helm->setUnbreakable();
                if ($enchant !== null) {
                    $helm->addEnchantment($enchant);
                }
                $arm->setHelmet($helm);
            }
            $chest = VanillaItems::LEATHER_TUNIC();
            if ($chest instanceof Armor) {
                $chest->setCustomColor($color);
                if ($enchant !== null) {
                    $chest->addEnchantment($enchant);
                }
                $chest->setUnbreakable();
                $arm->setChestplate($chest);
            }
            $leg = VanillaItems::LEATHER_PANTS();
            if ($leg instanceof Armor) {
                $leg->setCustomColor($color);
                if ($enchant !== null) {
                    $leg->addEnchantment($enchant);
                }
                $leg->setUnbreakable();
                $arm->setLeggings($leg);
            }
            $boots = VanillaItems::LEATHER_BOOTS();
            if ($boots instanceof Armor) {
                $boots->setCustomColor($color);
                if ($enchant !== null) {
                    $boots->addEnchantment($enchant);
                }
                $boots->setUnbreakable();
                $arm->setBoots($boots);
            }
        }
    }

    /**
     * @param Player $player
     * @param Item $sword
     * @return void
     */
    public function setSword(Player $player, Item $sword): void
    {
        if ($sword instanceof Durable) {
            $team = $this->getTeam($player);
            $enchant = null;
            if (isset($this->utilityArena[$team]["sharpness"])) {
                if ($this->utilityArena[$team]["sharpness"] == 2) {
                    $enchant = new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 1);
                }
            }
            if ($enchant !== null) {
                $sword->addEnchantment($enchant);
            }
            $sword->setUnbreakable();
            $player->getInventory()->removeItem($player->getInventory()->getItem(0));
            $player->getInventory()->setItem(0, $sword);
            if (isset($this->ifShears[$player->getName()])) {
                if (!$player->getInventory()->contains(VanillaItems::SHEARS())) {
                    $sh = VanillaItems::SHEARS();
                    if ($sh instanceof Durable) {
                        $sh->setUnbreakable();
                        $player->getInventory()->addItem($sh);
                    }
                }
            }
        }
    }

    /**
     * @param string $team
     * @param Player $player
     * @return void
     */
    public function upgradeGenerator(string $team, Player $player): void
    {
        $this->utilityArena[$team]["generator"]++;
        foreach ($this->world->getEntities() as $g) {
            if ($g instanceof Generator) {
                if ($g->type == "iron") {
                    $pos = Utils::stringToVector(":", $this->data["teamGenerator"][$team]["iron"]);
                    if ($g->getPosition()->distance($pos) < 2) {
                        $g->generatorLevel = $g->generatorLevel + 1;
                    }
                }
                if ($g->type == "gold") {
                    $pos = Utils::stringToVector(":", $this->data["teamGenerator"][$team]["gold"]);
                    if ($g->getPosition()->distance($pos) < 2) {
                        $g->generatorLevel = $g->generatorLevel + 1;
                    }
                }
                if ($g->type == "emeraldTeam") {
                    $pos = Utils::stringToVector(":", $this->data["teamGenerator"][$team]["emerald"]);
                    if ($g->getPosition()->distance($pos) < 2) {
                        $g->generatorLevel = $g->generatorLevel + 1;
                    }
                }
            }
        }
        foreach ($this->players as $t) {
            if ($this->getTeam($t) == $team) {
                $lvl = $this->utilityArena[$team]["generator"] - 1;
                if ($lvl == 1) {
                    $t->sendMessage("§a" . $player->getDisplayName() . " purchased §6Iron Forge");
                } else if ($lvl == 2) {
                    $t->sendMessage("§a" . $player->getDisplayName() . " purchased §6Golden Forge");
                } else if ($lvl == 3) {
                    $t->sendMessage("§a" . $player->getDisplayName() . " purchased §6Emerald Forge");
                } else if ($lvl == 4) {
                    $t->sendMessage("§a" . $player->getDisplayName() . " purchased §6Molten Forge");
                }
            }
        }
    }

    /**
     * @param string $team
     * @param Player $player
     * @return void
     */
    public function upgradeArmor(string $team, Player $player): void
    {
        $this->utilityArena[$team]["protection"]++;
        foreach ($this->players as $pt) {
            if ($this->getTeam($pt) == $team) {
                $lvl = $this->utilityArena[$team]["protection"] - 1;
                Utils::addSound($pt, 'random.levelup');
                $this->setArmor($pt);
                $pt->sendMessage("§a" . $player->getDisplayName() . " purchased §6Reinforced Armor " . Utils::intToRoman($lvl));
            }
        }
    }

    /**
     * @param string $team
     * @param Player $player
     * @return void
     */
    public function upgradeHaste(string $team, Player $player): void
    {
        $this->utilityArena[$team]["haste"]++;
        foreach ($this->players as $pt) {
            if ($this->getTeam($pt) == $team) {
                $lvl = $this->utilityArena[$team]["haste"] - 1;
                Utils::addSound($pt, 'random.levelup');
                $pt->sendMessage("§a" . $player->getDisplayName() . " purchased §6Maniac Miner " . Utils::intToRoman($lvl));
            }
        }
    }

    /**
     * @param string $team
     * @param Player $player
     * @return void
     */
    public function upgradeSword(string $team, Player $player): void
    {
        $this->utilityArena[$team]["sharpness"]++;
        foreach ($this->players as $pt) {
            if ($this->getTeam($pt) == $team) {
                Utils::addSound($pt, 'random.levelup');
                foreach ($pt->getInventory()->getContents() as $item) {
                    if ($item instanceof Sword) {
                        $this->setSword($pt, $item);
                    }
                }
                $pt->sendMessage("§a" . $player->getDisplayName() . " purchased §6Sharpened Swords");
            }
        }
    }

    /**
     * @param string $team
     * @param Player $player
     * @return void
     */
    public function upgradeHeal(string $team, Player $player): void
    {
        $this->utilityArena[$team]["health"]++;
        foreach ($this->players as $pt) {
            if ($this->getTeam($pt) == $team) {
                Utils::addSound($pt, 'random.levelup');
                $pt->sendMessage("§a" . $player->getDisplayName() . " purchased §6Heal Pool");
            }
        }
    }

    public function getCostUpgrade(string $team, string $type): float|int
    {
        $cost = 0;
        switch ($type) {
            case "protection":
                $data = $this->utilityArena[$team]["protection"];
                $cost = 2 * $data;
                break;
            case "haste":
                $data = $this->utilityArena[$team]["haste"];
                $cost = 2 * $data;
                break;
            case "generator":
                $data = $this->utilityArena[$team]["generator"];
                $cost = 2 * $data;
                break;
        }
        return $cost;
    }

    public function getTierUpgrade(string $team, string $type)
    {
        $tier = 0;
        switch ($type) {
            case "protection":
                $data = $this->utilityArena[$team]["protection"];
                if ($data == 5) {
                    $tier = 4;
                } else {
                    $tier = $data;
                }
                break;
            case "haste":
                $data = $this->utilityArena[$team]["haste"];
                if ($data == 3) {
                    $tier = 2;
                } else {
                    $tier = $data;
                }
                break;
            case "generator":
                $data = $this->utilityArena[$team]["generator"];
                if ($data == 5) {
                    $tier = 4;
                } else {
                    $tier = $data;
                }
                break;
        }
        return $tier;
    }

    public function setTrap(string $team, string $trap)
    {
        if (!isset($this->allTraps[$team]["trapSlot1"])) {
            $this->allTraps[$team]["trapSlot1"] = $trap;
        } else if (!isset($this->allTraps[$team]["trapSlot2"])) {
            $this->allTraps[$team]["trapSlot2"] = $trap;
        } else if (!isset($this->allTraps[$team]["trapSlot3"])) {
            $this->allTraps[$team]["trapSlot3"] = $trap;
        }
    }

    public function upgradeGUI(Player $player)
    {
        $team = $this->getTeam($player);
        $pinv = $player->getInventory();
        $costTrap = 0;
        if (!isset($this->allTraps[$team]["trapSlot1"])) {
            $costTrap = 1;
        } else if (!isset($this->allTraps[$team]["trapSlot2"])) {
            $costTrap = 2;
        } else if (!isset($this->allTraps[$team]["trapSlot3"])) {
            $costTrap = 4;
        } else {
            $costTrap = 4;
        }
        $sharpnessData = $this->utilityArena[$team]["sharpness"];
        $protectionData = $this->utilityArena[$team]["protection"];
        $hasteData = $this->utilityArena[$team]["haste"];
        $generatorData = $this->utilityArena[$team]["generator"];
        $healthData = $this->utilityArena[$team]["health"];
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("Upgrades & Traps");
        $menu->setListener(InvMenu::readonly());
        $inv = $menu->getInventory();

        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($menu): void {
            $player = $transaction->getPlayer();
            $team = $this->getTeam($player);
            $pinv = $player->getInventory();
            $item = $transaction->getItemClicked();
            $inv = $menu->getInventory();

            $costTrap = 0;
            if (!isset($this->allTraps[$team]["trapSlot1"])) {
                $costTrap = 1;
            } else if (!isset($this->allTraps[$team]["trapSlot2"])) {
                $costTrap = 2;
            } else if (!isset($this->allTraps[$team]["trapSlot3"])) {
                $costTrap = 4;
            } else {
                $costTrap = 4;
            }

            if ($item instanceof Sword && $item->getTypeId() == ItemTypeIds::IRON_SWORD) {
                if (isset($this->utilityArena[$team]["sharpness"])) {
                    $g = $this->utilityArena[$team]["sharpness"];
                    if ($g >= 2) {
                        return;
                    }
                    if ($pinv->contains(VanillaItems::DIAMOND()->setCount(4))) {
                        $pinv->removeItem(VanillaItems::DIAMOND()->setCount(4));
                        $this->upgradeSword($team, $player);
                        Utils::addSound($player, 'random.levelup');
                    } else {
                        $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    }
                    $player->removeCurrentWindow();
                    $this->upgradeGUI($player);
                }
            }
            if ($item instanceof Armor && $item->getTypeId() == ItemTypeIds::IRON_CHESTPLATE) {
                if (isset($this->utilityArena[$team]["protection"])) {
                    $g = $this->utilityArena[$team]["protection"];
                    if ($g >= 5) {
                        return;
                    }
                    if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getTierUpgrade($team, "protection")))) {
                        $pinv->removeItem(VanillaItems::DIAMOND()->setCount($this->getTierUpgrade($team, "protection")));
                        Utils::addSound($player, 'random.levelup');
                        $this->upgradeArmor($team, $player);
                    } else {
                        $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    }
                    $player->removeCurrentWindow();
                    $this->upgradeGUI($player);
                }
            }
            if ($item->getTypeId() == ItemTypeIds::GOLDEN_PICKAXE) {
                if (isset($this->utilityArena[$team]["haste"])) {
                    $g = $this->utilityArena[$team]["haste"];
                    if ($g >= 3) {
                        return;
                    }


                    if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getTierUpgrade($team, "haste")))) {
                        $pinv->removeItem(VanillaItems::DIAMOND()->setCount($this->getTierUpgrade($team, "haste")));
                        Utils::addSound($player, 'random.levelup');
                        $this->upgradeHaste($team, $player);
                    } else {
                        $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    }
                    $player->removeCurrentWindow();
                    $this->upgradeGUI($player);
                }
            }
            if ($item->getTypeId() == BlockTypeIds::FURNACE) {
                if (isset($this->utilityArena[$team]["generator"])) {
                    $g = $this->utilityArena[$team]["generator"];
                    if ($g >= 5) {
                        return;
                    }
                    if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getTierUpgrade($team, "generator")))) {
                        $pinv->removeItem(VanillaItems::DIAMOND()->setCount($this->getTierUpgrade($team, "generator")));
                        Utils::addSound($player, 'random.levelup');
                        $this->upgradeGenerator($team, $player);
                    } else {
                        $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    }
                    $player->removeCurrentWindow();
                    $this->upgradeGUI($player);
                }
            }
            if ($item->getTypeId() == BlockTypeIds::BEACON) {
                if (isset($this->utilityArena[$team]["health"])) {
                    $g = $this->utilityArena[$team]["health"];
                    if ($g >= 2) {
                        return;
                    }
                    if ($pinv->contains(VanillaItems::DIAMOND()->setCount(4))) {
                        $pinv->removeItem(VanillaItems::DIAMOND()->setCount(4));
                        Utils::addSound($player, 'random.levelup');
                        $this->upgradeHeal($team, $player);
                    } else {
                        $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    }
                    $player->removeCurrentWindow();
                    $this->upgradeGUI($player);
                }
            }
            if ($item->getName() == "§cIt's a trap!") {
                if (isset($this->allTraps[$team]) && sizeof($this->allTraps[$team]) == 3) {
                    return;
                }
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($costTrap))) {
                    $pinv->removeItem(VanillaItems::DIAMOND()->setCount($costTrap));
                    Utils::addSound($player, 'random.levelup');
                    $this->setTrap($team, "itsTrap");
                    foreach ($this->players as $p) {
                        if ($this->getTeam($p) == $team) {
                            $p->sendMessage("§a" . $player->getDisplayName() . " purchased §6It's a trap");
                        }
                    }
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                $player->removeCurrentWindow();
                $this->upgradeGUI($player);
            }
            if ($item->getName() == "§cCounter-Offensive Trap") {
                if (isset($this->allTraps[$team]) && sizeof($this->allTraps[$team]) == 3) {
                    return;
                }
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($costTrap))) {
                    $pinv->removeItem(VanillaItems::DIAMOND()->setCount($costTrap));
                    Utils::addSound($player, 'random.levelup');
                    $this->setTrap($team, "counterTrap");
                    foreach ($this->players as $p) {
                        if ($this->getTeam($p) == $team) {
                            $p->sendMessage("§a" . $player->getDisplayName() . " purchased §6Counter-Offensive Trap");
                        }
                    }
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                $player->removeCurrentWindow();
                $this->upgradeGUI($player);
            }
            if ($item->getName() == "§cAlarm Trap") {
                if (isset($this->allTraps[$team]) && sizeof($this->allTraps[$team]) == 3) {
                    return;
                }
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($costTrap))) {
                    $pinv->removeItem(VanillaItems::DIAMOND()->setCount($costTrap));
                    Utils::addSound($player, 'random.levelup');
                    $this->setTrap($team, "alarmTrap");
                    foreach ($this->players as $p) {
                        if ($this->getTeam($p) == $team) {
                            $p->sendMessage("§a" . $player->getDisplayName() . " purchased §6Alarm Trap");
                        }
                    }
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                $player->removeCurrentWindow();
                $this->upgradeGUI($player);
            }
            if ($item->getName() == "§cMiner Fatigue Trap") {
                if (isset($this->allTraps[$team]) && sizeof($this->allTraps[$team]) == 3) {
                    return;
                }
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($costTrap))) {
                    $pinv->removeItem(VanillaItems::DIAMOND()->setCount($costTrap));
                    Utils::addSound($player, 'random.levelup');
                    $this->setTrap($team, "minerTrap");
                    foreach ($this->players as $pt) {
                        if ($this->getTeam($pt) == $team) {
                            $pt->sendMessage("§a" . $player->getDisplayName() . " purchased §6Miner Fatigue Trap");
                        }
                    }
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                $player->removeCurrentWindow();
                $this->upgradeGUI($player);
            }
        }));

        if ($sharpnessData >= 2) {
            $inv->setItem(
                10,
                VanillaItems::IRON_SWORD()
                    ->setCustomName("§cSharpened Swords")
                    ->setLore([
                        "§7Your team permanently gains",
                        "§7Sharpness I on all swords and",
                        "§7axes!",
                        '',
                        "§7Cost: §b4 Diamonds",
                        '',
                        '§aMAXED!'
                    ])
            );
        } else {
            if ($pinv->contains(VanillaItems::DIAMOND()->setCount(4))) {
                $inv->setItem(
                    10,
                    VanillaItems::IRON_SWORD()
                        ->setCustomName("§cSharpened Swords")
                        ->setLore([
                            "§7Your team permanently gains",
                            "§7Sharpness I on all swords and",
                            "§7axes!",
                            '',
                            "§7Cost: §b4 Diamonds",
                            '',
                            '§eClick to purchase!'
                        ])
                );
            } else {
                $inv->setItem(
                    10,
                    VanillaItems::IRON_SWORD()
                        ->setCustomName("§cSharpened Swords")
                        ->setLore([
                            "§7Your team permanently gains",
                            "§7Sharpness I on all swords and",
                            "§7axes!",
                            '',
                            "§7Cost: §b4 Diamonds",
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
            }
        }

        if ($protectionData >= 5) {
            $inv->setItem(
                11,
                VanillaItems::IRON_CHESTPLATE()
                    ->setCustomName("§cReinforced Armor " . Utils::intToRoman($this->getTierUpgrade($team, "protection")))
                    ->setLore([
                        '§7Your team permanently gains',
                        '§7Protection on all armor pieces!',
                        '',
                        '§7Tier 1: Protection I, §b2 Diamonds',
                        '§7Tier 2: Protection II, §b4 Diamonds',
                        '§7Tier 3: Protection III, §b8 Diamonds',
                        '§7Tier 4: Protection IV, §b16 Diamonds',
                        '',
                        '§aMAXED!'
                    ])
            );
        } else {
            if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "protection")))) {
                $inv->setItem(
                    11,
                    VanillaItems::IRON_CHESTPLATE()
                        ->setCustomName("§cReinforced Armor " . Utils::intToRoman($this->getTierUpgrade($team, "protection")))
                        ->setLore([
                            '§7Your team permanently gains',
                            '§7Protection on all armor pieces!',
                            '',
                            '§7Tier 1: Protection I, §b2 Diamonds',
                            '§7Tier 2: Protection II, §b4 Diamonds',
                            '§7Tier 3: Protection III, §b8 Diamonds',
                            '§7Tier 4: Protection IV, §b16 Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
            } else {
                $inv->setItem(
                    11,
                    VanillaItems::IRON_CHESTPLATE()
                        ->setCustomName("§cReinforced Armor " . Utils::intToRoman($this->getTierUpgrade($team, "protection")))
                        ->setLore([
                            '§7Your team permanently gains',
                            '§7Protection on all armor pieces!',
                            '',
                            '§7Tier 1: Protection I, §b2 Diamonds',
                            '§7Tier 2: Protection II, §b4 Diamonds',
                            '§7Tier 3: Protection III, §b8 Diamonds',
                            '§7Tier 4: Protection IV, §b16 Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
            }
        }

        if ($hasteData >= 3) {
            $inv->setItem(
                12,
                VanillaItems::GOLDEN_PICKAXE()
                    ->setCustomName("§cManiac Miner " . Utils::intToRoman($this->getTierUpgrade($team, "haste")))
                    ->setLore([
                        '§7All players on your team',
                        '§7permanently gain Haste.',
                        '',
                        '§7Tier 1: Haste I, §b2 Diamonds',
                        '§7Tier 2: Haste II, §b4 Diamonds',
                        '',
                        '§aMAXED!'
                    ])
            );
        } else {
            if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "haste")))) {
                $inv->setItem(
                    12,
                    VanillaItems::GOLDEN_PICKAXE()
                        ->setCustomName("§cManiac Miner " . Utils::intToRoman($this->getTierUpgrade($team, "haste")))
                        ->setLore([
                            '§7All players on your team',
                            '§7permanently gain Haste.',
                            '',
                            '§7Tier 1: Haste I, §b2 Diamonds',
                            '§7Tier 2: Haste II, §b4 Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
            } else {
                $inv->setItem(
                    12,
                    VanillaItems::GOLDEN_PICKAXE()
                        ->setCustomName("§cManiac Miner " . Utils::intToRoman($this->getTierUpgrade($team, "haste")))
                        ->setLore([
                            '§7All players on your team',
                            '§7permanently gain Haste.',
                            '',
                            '§7Tier 1: Haste I, §b2 Diamonds',
                            '§7Tier 2: Haste II, §b4 Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
            }
        }

        if ($generatorData >= 5) {
            if ($this->getTierUpgrade($team, "generator") == 1) {
                $inv->setItem(
                    19,
                    VanillaBlocks::FURNACE()->asItem()
                        ->setCustomName("§cIron Forge")
                        ->setLore([
                            '§7Upgrade resource spawning on',
                            '§7your island.',
                            '',
                            '§7Tier 1: +50% Resources, §b2 Diamonds',
                            '§7Tier 2: +100% Resources, §b4 Diamonds',
                            '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                            '§7Tier 4: +200% Resources, §b16 Diamonds',
                            '',
                            '§aMAXED!'
                        ])
                );
            } else if ($this->getTierUpgrade($team, "generator") == 2) {
                $inv->setItem(
                    19,
                    VanillaBlocks::FURNACE()->asItem()
                        ->setCustomName("§cGolden Forge")
                        ->setLore([
                            '§7Upgrade resource spawning on',
                            '§7your island.',
                            '',
                            '§7Tier 1: +50% Resources, §b2 Diamonds',
                            '§7Tier 2: +100% Resources, §b4 Diamonds',
                            '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                            '§7Tier 4: +200% Resources, §b16 Diamonds',
                            '',
                            '§aMAXED!'
                        ])
                );
            } else if ($this->getTierUpgrade($team, "generator") == 3) {
                $inv->setItem(
                    19,
                    VanillaBlocks::FURNACE()->asItem()
                        ->setCustomName("§cEmerald Forge")
                        ->setLore([
                            '§7Upgrade resource spawning on',
                            '§7your island.',
                            '',
                            '§7Tier 1: +50% Resources, §b2 Diamonds',
                            '§7Tier 2: +100% Resources, §b4 Diamonds',
                            '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                            '§7Tier 4: +200% Resources, §b16 Diamonds',
                            '',
                            '§aMAXED!'
                        ])
                );
            } else if ($this->getTierUpgrade($team, "generator") == 4) {
                $inv->setItem(
                    19,
                    VanillaBlocks::FURNACE()->asItem()
                        ->setCustomName("§cMolten Forge")
                        ->setLore([
                            '§7Upgrade resource spawning on',
                            '§7your island.',
                            '',
                            '§7Tier 1: +50% Resources, §b2 Diamonds',
                            '§7Tier 2: +100% Resources, §b4 Diamonds',
                            '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                            '§7Tier 4: +200% Resources, §b16 Diamonds',
                            '',
                            '§aMAXED!'
                        ])
                );
            } else {
                $inv->setItem(
                    19,
                    VanillaBlocks::FURNACE()->asItem()
                        ->setCustomName("§cMolten Forge")
                        ->setLore([
                            '§7Upgrade resource spawning on',
                            '§7your island.',
                            '',
                            '§7Tier 1: +50% Resources, §b2 Diamonds',
                            '§7Tier 2: +100% Resources, §b4 Diamonds',
                            '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                            '§7Tier 4: +200% Resources, §b16 Diamonds',
                            '',
                            '§aMAXED!'
                        ])
                );
            }
        } else {
            if ($this->getTierUpgrade($team, "generator") == 1) {
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "generator")))) {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cIron Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                '§eClick to purchase!'
                            ])
                    );
                } else {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cIron Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                "§cYou don't have enough Diamonds!"
                            ])
                    );
                }
            } else if ($this->getTierUpgrade($team, "generator") == 2) {
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "generator")))) {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cGolden Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                '§eClick to purchase!'
                            ])
                    );
                } else {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cGolden Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                "§cYou don't have enough Diamonds!"
                            ])
                    );
                }
            } else if ($this->getTierUpgrade($team, "generator") == 3) {
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "generator")))) {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cEmerald Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                '§eClick to purchase!'
                            ])
                    );
                } else {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cEmerald Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                "§cYou don't have enough Diamonds!"
                            ])
                    );
                }
            } else if ($this->getTierUpgrade($team, "generator") == 4) {
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "generator")))) {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cMolten Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                '§eClick to purchase!'
                            ])
                    );
                } else {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cMolten Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                "§cYou don't have enough Diamonds!"
                            ])
                    );
                }
            } else {
                if ($pinv->contains(VanillaItems::DIAMOND()->setCount($this->getCostUpgrade($team, "generator")))) {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cMolten Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                '§eClick to purchase!'
                            ])
                    );
                } else {
                    $inv->setItem(
                        19,
                        VanillaBlocks::FURNACE()->asItem()
                            ->setCustomName("§cMolten Forge")
                            ->setLore([
                                '§7Upgrade resource spawning on',
                                '§7your island.',
                                '',
                                '§7Tier 1: +50% Resources, §b2 Diamonds',
                                '§7Tier 2: +100% Resources, §b4 Diamonds',
                                '§7Tier 3: Spawn emeralds, §b8 Diamonds',
                                '§7Tier 4: +200% Resources, §b16 Diamonds',
                                '',
                                "§cYou don't have enough Diamonds!"
                            ])
                    );
                }
            }
        }

        if ($healthData >= 2) {
            $inv->setItem(
                20,
                VanillaBlocks::BEACON()->asItem()
                    ->setCustomName("§cHeal Pool")
                    ->setLore([
                        '§7Creates a Regeneration field',
                        '§7around yor base!',
                        '',
                        '§7Cost: §b4 Diamonds',
                        '',
                        '§aMAXED!'
                    ])
            );
        } else {
            if ($pinv->contains(VanillaItems::DIAMOND()->setCount(1))) {
                $inv->setItem(
                    20,
                    VanillaBlocks::BEACON()->asItem()
                        ->setCustomName("§cHeal Pool")
                        ->setLore([
                            '§7Creates a Regeneration field',
                            '§7around yor base!',
                            '',
                            '§7Cost: §b4 Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
            } else {
                $inv->setItem(
                    20,
                    VanillaBlocks::BEACON()->asItem()
                        ->setCustomName("§cHeal Pool")
                        ->setLore([
                            '§7Creates a Regeneration field',
                            '§7around yor base!',
                            '',
                            '§7Cost: §b4 Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
            }
        }

        if (isset($this->allTraps[$team]) && sizeof($this->allTraps[$team]) == 3) {
            $inv->setItem(
                14,
                VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                    ->setCustomName("§cIt's a trap!")
                    ->setLore([
                        '§7Inflicts Blindness and Slowness',
                        '§7for 5 seconds.',
                        '',
                        '§7Cost: §b' . $costTrap . ' Diamonds',
                        '',
                        '§cTrap queue full!'
                    ])
            );
            $inv->setItem(
                15,
                VanillaItems::FEATHER()
                    ->setCustomName("§cCounter-Offensive Trap")
                    ->setLore([
                        '§7Grants Speed I for 15 seconds to',
                        '§7allied players near your base.',
                        '',
                        '§7Cost: §b' . $costTrap . ' Diamonds',
                        '',
                        '§cTrap queue full!'
                    ])
            );
            $inv->setItem(
                16,
                VanillaBlocks::REDSTONE_TORCH()->asItem()
                    ->setCustomName("§cAlarm Trap")
                    ->setLore([
                        '§7Reveales invisible players as',
                        '§7well as their name and team.',
                        '',
                        '§7Cost: §b' . $costTrap . ' Diamonds',
                        '',
                        '§cTrap queue full!'
                    ])
            );
            $inv->setItem(
                23,
                VanillaItems::IRON_PICKAXE()
                    ->setCustomName("§cMiner Fatigue Trap")
                    ->setLore([
                        '§7Inflict Mining Fatigue for 10',
                        '§7seconds.',
                        '',
                        '§7Cost: §b' . $costTrap . ' Diamonds',
                        '',
                        '§cTrap queue full!'
                    ])
            );
        } else {
            if ($pinv->contains(VanillaItems::DIAMOND()->setCount($costTrap))) {
                $inv->setItem(
                    14,
                    VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                        ->setCustomName("§cIt's a trap!")
                        ->setLore([
                            '§7Inflicts Blindness and Slowness',
                            '§7for 5 seconds.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
                $inv->setItem(
                    15,
                    VanillaItems::FEATHER()
                        ->setCustomName("§cCounter-Offensive Trap")
                        ->setLore([
                            '§7Grants Speed I for 15 seconds to',
                            '§7allied players near your base.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
                $inv->setItem(
                    16,
                    VanillaBlocks::REDSTONE_TORCH()->asItem()
                        ->setCustomName("§cAlarm Trap")
                        ->setLore([
                            '§7Reveales invisible players as',
                            '§7well as their name and team.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
                $inv->setItem(
                    23,
                    VanillaItems::IRON_PICKAXE()
                        ->setCustomName("§cMiner Fatigue Trap")
                        ->setLore([
                            '§7Inflict Mining Fatigue for 10',
                            '§7seconds.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            '§eClick to purchase!'
                        ])
                );
            } else {
                $inv->setItem(
                    14,
                    VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                        ->setCustomName("§cIt's a trap!")
                        ->setLore([
                            '§7Inflicts Blindness and Slowness',
                            '§7for 5 seconds.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
                $inv->setItem(
                    15,
                    VanillaItems::FEATHER()
                        ->setCustomName("§cCounter-Offensive Trap")
                        ->setLore([
                            '§7Grants Speed I for 15 seconds to',
                            '§7allied players near your base.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
                $inv->setItem(
                    16,
                    VanillaBlocks::REDSTONE_TORCH()->asItem()
                        ->setCustomName("§cAlarm Trap")
                        ->setLore([
                            '§7Reveales invisible players as',
                            '§7well as their name and team.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
                $inv->setItem(
                    23,
                    VanillaItems::IRON_PICKAXE()
                        ->setCustomName("§cMiner Fatigue Trap")
                        ->setLore([
                            '§7Inflict Mining Fatigue for 10',
                            '§7seconds.',
                            '',
                            '§7Cost: §b' . $costTrap . ' Diamonds',
                            '',
                            "§cYou don't have enough Diamonds!"
                        ])
                );
            }
        }

        for ($i = 27; $i <= 35; $i++) {
            $inv->setItem(
                $i,
                VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::GRAY())->asItem()->setCustomName("§8⬆ §7Purchasable")
                    ->setLore(["§8⬇ §7Traps Queue"])
            );
        }

        if (isset($this->allTraps[$team]) && count($this->allTraps[$team]) >= 1) {
            if (isset($this->allTraps[$team]["trapSlot1"])) {
                $type = $this->allTraps[$team]["trapSlot1"];
                switch ($type) {
                    case "itsTrap":
                        $inv->setItem(
                            39,
                            VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                                ->setCustomName("§cTrap #1: It's a trap!")
                                ->setLore([
                                    "§7The first enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "counterTrap":
                        $inv->setItem(
                            39,
                            VanillaItems::FEATHER()
                                ->setCustomName("§cTrap #1: Counter-Offensive Trap")
                                ->setLore([
                                    "§7The first enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "alarmTrap":
                        $inv->setItem(
                            39,
                            VanillaBlocks::REDSTONE_TORCH()->asItem()
                                ->setCustomName("§cTrap #1: Alarm Trap")
                                ->setLore([
                                    "§7The first enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "minerTrap":
                        $inv->setItem(
                            39,
                            VanillaItems::IRON_PICKAXE()
                                ->setCustomName("§cTrap #1: Miner Fatigue Trap")
                                ->setLore([
                                    "§7The first enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    default:
                        $inv->setItem(
                            39,
                            VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()
                                ->setCustomName("§cTrap #1: No Trap!")
                                ->setLore([
                                    "§7The first enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                }
            } else {
                $inv->setItem(
                    39,
                    VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()
                        ->setCustomName("§cTrap #1: No Trap!")
                        ->setLore([
                            "§7The first enemy to walk",
                            "§7into your base will trigger",
                            "§7this trap!",
                            '',
                            '§7Purchasing a trap will',
                            '§7queue it here. Its cost',
                            '§7will scale based on the',
                            '§7number of traps queued.',
                            '',
                            '§7Next trap: §b' . $costTrap . ' Diamond'
                        ])
                );
            }

            if (isset($this->allTraps[$team]["trapSlot2"])) {
                $type = $this->allTraps[$team]["trapSlot2"];
                switch ($type) {
                    case "itsTrap":
                        $inv->setItem(
                            40,
                            VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(2)
                                ->setCustomName("§cTrap #2: It's a trap!")
                                ->setLore([
                                    "§7The second enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "counterTrap":
                        $inv->setItem(
                            40,
                            VanillaItems::FEATHER()->setCount(2)
                                ->setCustomName("§2Trap #2: Counter-Offensive Trap")
                                ->setLore([
                                    "§7The second enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "alarmTrap":
                        $inv->setItem(
                            40,
                            VanillaBlocks::REDSTONE_TORCH()->asItem()->setCount(2)
                                ->setCustomName("§cTrap #2: Alarm Trap")
                                ->setLore([
                                    "§7The second enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "minerTrap":
                        $inv->setItem(
                            40,
                            VanillaItems::IRON_PICKAXE()->setCount(2)
                                ->setCustomName("§cTrap #2: Miner Fatigue Trap")
                                ->setLore([
                                    "§7The second enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    default:
                        $inv->setItem(
                            40,
                            VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()->setCount(2)
                                ->setCustomName("§cTrap #2: No Trap!")
                                ->setLore([
                                    "§7The second enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                }
            } else {
                $inv->setItem(
                    40,
                    VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()->setCount(2)
                        ->setCustomName("§cTrap #2: No Trap!")
                        ->setLore([
                            "§7The second enemy to walk",
                            "§7into your base will trigger",
                            "§7this trap!",
                            '',
                            '§7Purchasing a trap will',
                            '§7queue it here. Its cost',
                            '§7will scale based on the',
                            '§7number of traps queued.',
                            '',
                            '§7Next trap: §b' . $costTrap . ' Diamond'
                        ])
                );
            }

            if (isset($this->allTraps[$team]["trapSlot3"])) {
                $type = $this->allTraps[$team]["trapSlot3"];
                switch ($type) {
                    case "itsTrap":
                        $inv->setItem(
                            41,
                            VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(3)
                                ->setCustomName("§cTrap #3: It's a trap!")
                                ->setLore([
                                    "§7The third enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "counterTrap":
                        $inv->setItem(
                            41,
                            VanillaItems::FEATHER()->setCount(3)
                                ->setCustomName("§2Trap #3: Counter-Offensive Trap")
                                ->setLore([
                                    "§7The third enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "alarmTrap":
                        $inv->setItem(
                            41,
                            VanillaBlocks::REDSTONE_TORCH()->asItem()->setCount(3)
                                ->setCustomName("§cTrap #3: Alarm Trap")
                                ->setLore([
                                    "§7The third enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    case "minerTrap":
                        $inv->setItem(
                            41,
                            VanillaItems::IRON_PICKAXE()->setCount(3)
                                ->setCustomName("§cTrap #3: Miner Fatigue Trap")
                                ->setLore([
                                    "§7The third enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                    default:
                        $inv->setItem(
                            41,
                            VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()->setCount(3)
                                ->setCustomName("§cTrap #3: No Trap!")
                                ->setLore([
                                    "§7The third enemy to walk",
                                    "§7into your base will trigger",
                                    "§7this trap!",
                                    '',
                                    '§7Purchasing a trap will',
                                    '§7queue it here. Its cost',
                                    '§7will scale based on the',
                                    '§7number of traps queued.',
                                    '',
                                    '§7Next trap: §b' . $costTrap . ' Diamond'
                                ])
                        );
                        break;
                }
            } else {
                $inv->setItem(
                    41,
                    VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()->setCount(3)
                        ->setCustomName("§cTrap #3: No Trap!")
                        ->setLore([
                            "§7The third enemy to walk",
                            "§7into your base will trigger",
                            "§7this trap!",
                            '',
                            '§7Purchasing a trap will',
                            '§7queue it here. Its cost',
                            '§7will scale based on the',
                            '§7number of traps queued.',
                            '',
                            '§7Next trap: §b' . $costTrap . ' Diamond'
                        ])
                );
            }
        } else {
            $inv->setItem(
                39,
                VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()
                    ->setCustomName("§cTrap #1: No Trap!")
                    ->setLore([
                        "§7The first enemy to walk",
                        "§7into your base will trigger",
                        "§7this trap!",
                        '',
                        '§7Purchasing a trap will',
                        '§7queue it here. Its cost',
                        '§7will scale based on the',
                        '§7number of traps queued.',
                        '',
                        '§7Next trap: §b' . $costTrap . ' Diamond'
                    ])
            );
            $inv->setItem(
                40,
                VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()->setCount(2)
                    ->setCustomName("§cTrap #2: No Trap!")
                    ->setLore([
                        "§7The second enemy to walk",
                        "§7into your base will trigger",
                        "§7this trap!",
                        '',
                        '§7Purchasing a trap will',
                        '§7queue it here. Its cost',
                        '§7will scale based on the',
                        '§7number of traps queued.',
                        '',
                        '§7Next trap: §b' . $costTrap . ' Diamond'
                    ])
            );
            $inv->setItem(
                41,
                VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIGHT_GRAY())->asItem()->setCount(3)
                    ->setCustomName("§cTrap #3: No Trap!")
                    ->setLore([
                        "§7The third enemy to walk",
                        "§7into your base will trigger",
                        "§7this trap!",
                        '',
                        '§7Purchasing a trap will',
                        '§7queue it here. Its cost',
                        '§7will scale based on the',
                        '§7number of traps queued.',
                        '',
                        '§7Next trap: §b' . $costTrap . ' Diamond'
                    ])
            );
        }
        $menu->send($player);
    }

    /**
     * Dont forget to set paymentItem count
     */
    private function purchaseSword(Player $player, Item $paymentItem, Item $purchaseItem): void
    {
        $pinv = $player->getInventory();
        if (!$pinv->contains($purchaseItem)) {


            if ($pinv->contains($paymentItem)) {
                $pinv->removeItem($paymentItem);
                $this->messageBuy($player, $purchaseItem->getCustomName());
                $this->setSword($player, $purchaseItem);
            } else {
                $this->notEnought($player, str_replace(" Ingot", "", $paymentItem->getName()), $paymentItem->getCount());
                $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
            }
        } else {
            $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
            $player->sendMessage("§cYou already purchase this!");
        }
    }

    private function purchaseArmor(): void
    {
    }
    /**
     * @param Player $player
     * @return void
     */
    public function shopMenu(Player $player): void
    {
        $team = $this->getTeam($player);
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("Item Shop");
        $inv = $menu->getInventory();
        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction): void {
            $player = $transaction->getPlayer();
            $pinv = $player->getInventory();
            $item = $transaction->getItemClicked();
            $inv = $transaction->getAction()->getInventory();
            $in = $item->getCustomName();
            if (in_array($in, ["§fBlocks", "§fMelee", "§fArmor", "§fTools", "§fBow & Arrow", "§fPotions", "§fUtility"])) {
                $this->manageShop($player, $inv, $in);
                return;
            }
            if ($item instanceof Sword && $in == "§eStone Sword") {
                $this->purchaseSword($player, VanillaItems::IRON_INGOT()->setCount(10), VanillaItems::STONE_SWORD());
                return;
            }
            if ($item instanceof Sword && $in == "§eIron Sword") {
                $this->purchaseSword($player, VanillaItems::GOLD_INGOT()->setCount(7), VanillaItems::IRON_SWORD());
                return;
            }
            if ($item instanceof Sword && $in == "§eDiamond Sword") {
                $this->purchaseSword($player, VanillaItems::DIAMOND()->setCount(3), VanillaItems::DIAMOND_SWORD());
                return;
            }
            if ($in == "§eShears") {
                if (isset($this->ifShears[$player->getName()])) {
                    return;
                }
                if ($pinv->contains(VanillaItems::IRON_INGOT()->setCount(20))) {
                    $pinv->removeItem(VanillaItems::IRON_INGOT()->setCount(20));
                    $this->ifShears[$player->getName()] = $player;
                    $this->messageBuy($player, "Shears");
                    $sword = $pinv->getItem(0);
                    $this->setSword($player, $sword);
                } else {
                    $this->notEnought($player, "Gold", 20);
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                return;
            }
            if ($in == "§eKnockback Stick") {
                if ($pinv->contains(VanillaItems::GOLD_INGOT()->setCount(5))) {
                    $pinv->removeItem(VanillaItems::GOLD_INGOT()->setCount(5));
                    $this->messageBuy($player, "KnockBack Stick");
                    $stick = VanillaItems::STICK();
                    $stick->setCustomName("§bKnockback Stick");
                    $stick->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK(), 1));
                    $pinv->addItem($stick);
                } else {
                    $this->notEnought($player, "Gold", 5);
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                return;
            }
            if ($in == "§eBow (Power I)") {
                if ($pinv->contains(VanillaItems::GOLD_INGOT()->setCount(24))) {
                    $pinv->removeItem(VanillaItems::GOLD_INGOT()->setCount(24));
                    $this->messageBuy($player, "Bow (Power I)");
                    $bow = VanillaItems::BOW();
                    $bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::POWER(), 1));
                    $pinv->addItem($bow);
                } else {
                    $this->notEnought($player, "Gold", 24);
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                return;
            }
            if ($in == "§eBow (Power I, Punch I)") {
                if ($pinv->contains(VanillaItems::EMERALD()->setCount(2))) {
                    $pinv->removeItem(VanillaItems::EMERALD()->setCount(2));
                    $this->messageBuy($player, "Bow (Power I, Punch I)");

                    $bow = VanillaItems::BOW();
                    $bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::POWER(), 1));
                    $bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PUNCH(), 1));
                    $pinv->addItem($bow);
                } else {
                    $this->notEnought($player, "Emerald", 2);
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
                return;
            }
            if ($item instanceof Armor && $in == "§eChainmail Set") {
                if (isset($this->armorType[$player->getName()]) && in_array($this->armorType[$player->getName()], ["iron", "diamond"])) {
                    return;
                }
                if (isset($this->armorType[$player->getName()]) && $this->armorType[$player->getName()] !== "chainmail") {
                    if ($pinv->contains(VanillaItems::IRON_INGOT()->setCount(40))) {
                        $pinv->removeItem(VanillaItems::IRON_INGOT()->setCount(40));
                        $this->messageBuy($player, "Chainmail set");
                        $this->armorType[$player->getName()] = "chainmail";
                        $this->setArmor($player);
                    } else {
                        $this->notEnought($player, "Iron", 40);
                        $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    }
                    return;
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                }
            }
            if ($item instanceof Armor && $in == "§eIron Set") {
                if (isset($this->armorType[$player->getName()]) && $this->armorType[$player->getName()] == "diamond") {
                    return;
                }

                if ($pinv->contains(VanillaItems::GOLD_INGOT()->setCount(12))) {
                    $pinv->removeItem(VanillaItems::GOLD_INGOT()->setCount(12));
                    $this->messageBuy($player, "Iron set");
                    $this->armorType[$player->getName()] = "iron";
                    $this->setArmor($player);
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    $this->notEnought($player, "Gold", 12);
                }
                return;
            }
            if ($item instanceof Armor && $in == "§eDiamond Set") {
                if (isset($this->armorType[$player->getName()]) && $this->armorType[$player->getName()] == "diamond") {
                    return;
                }
                if ($pinv->contains(VanillaItems::EMERALD()->setCount(6))) {
                    $pinv->removeItem(VanillaItems::EMERALD()->setCount(6));
                    $this->messageBuy($player, "Diamond set");
                    $this->armorType[$player->getName()] = "diamond";
                    $this->setArmor($player);
                } else {
                    $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
                    $this->notEnought($player, "Emerald", 6);
                }
                return;
            }
            $this->buyItem($item, $player);
            if ($item instanceof Pickaxe) {
                $pickaxe = $this->getPickaxeByTier($player);
                $inv->setItem(20, $pickaxe);
            }
            if ($item instanceof Axe) {
                $axe = $this->getAxeByTier($player);
                $inv->setItem(21, $axe);
            }
        }));
        // Main Menu //
        $inv->setItem(1, VanillaBlocks::WOOL()->setColor(Utils::getDyeColor($team))->asItem()->setCustomName("§fBlocks"));
        $inv->setItem(2, VanillaItems::GOLDEN_SWORD()->setCustomName("§fMelee"));
        $inv->setItem(3, VanillaItems::CHAINMAIL_BOOTS()->setCustomName("§fArmor"));
        $inv->setItem(4, VanillaItems::STONE_PICKAXE()->setCustomName("§fTools"));
        $inv->setItem(5, VanillaItems::BOW()->setCustomName("§fBow & Arrow"));
        $inv->setItem(6, VanillaBlocks::BREWING_STAND()->asItem()->setCustomName("§fPotions"));
        $inv->setItem(7, VanillaBlocks::TNT()->asItem()->setCustomName("§fUtility"));

        // Block Menu //
        $this->manageShop($player, $inv, "§fBlocks");
        $menu->send($player);
    }

    /**
     * @param Player $player
     * @param Inventory $inv
     * @param string $type
     * @return void
     */
    public function manageShop(Player $player, Inventory $inv, string $type): void
    {
        $team = $this->getTeam($player);
        // BLOCKS //
        if ($type == "§fBlocks") {
            $inv->setItem(
                19,
                VanillaBlocks::WOOL()->setColor(Utils::getDyeColor($team))->asItem()->setCount(16)
                    ->setLore(["§f4 Iron"])
                    ->setCustomName("§eWool")
            );
            $inv->setItem(
                20,
                VanillaBlocks::GLAZED_TERRACOTTA()->setColor(Utils::getDyeColor($team))->asItem()->setCount(16)
                    ->setLore(["§f12 Iron"])
                    ->setCustomName("§eTerracotta")
            );
            $inv->setItem(
                21,
                VanillaBlocks::STAINED_GLASS()->setColor(Utils::getDyeColor($team))->asItem()->setCount(4)
                    ->setLore(["§f12 Iron"])
                    ->setCustomName("§eStained Glass")
            );
            $inv->setItem(
                22,
                VanillaBlocks::END_STONE()->asItem()->setCount(12)
                    ->setLore(["§f24 Iron"])
                    ->setCustomName("§eEnd Stone")
            );
            $inv->setItem(
                23,
                VanillaBlocks::LADDER()->asItem()->setCount(16)
                    ->setLore(["§f4 Iron"])
                    ->setCustomName("§eLadder")
            );
            $inv->setItem(
                24,
                VanillaBlocks::OAK_PLANKS()->asItem()->setCount(16)
                    ->setLore(["§64 Gold"])
                    ->setCustomName("§ePlank")
            );
            $inv->setItem(
                25,
                VanillaBlocks::OBSIDIAN()->asItem()->setCount(4)
                    ->setLore(["§24 Emerald"])
                    ->setCustomName("§eObsidian")
            );
            for ($i = 28; $i < 31; $i++) {
                $inv->setItem($i, VanillaItems::AIR());
            }
        }
        // SWORD //
        if ($type == "§fMelee") {
            $inv->setItem(
                19,
                VanillaItems::STONE_SWORD()
                    ->setLore(["§f10 Iron"])
                    ->setCustomName("§eStone Sword")
            );
            $inv->setItem(
                20,
                VanillaItems::IRON_SWORD()
                    ->setLore(["§67 Gold"])
                    ->setCustomName("§eIron Sword")
            );
            $inv->setItem(
                21,
                VanillaItems::DIAMOND_SWORD()
                    ->setLore(["§23 Emerald"])
                    ->setCustomName("§eDiamond Sword")
            );
            $stick = VanillaItems::STICK();
            $stick->setLore(["§65 Gold"]);
            $stick->setCustomName("§eKnockback Stick");
            $stick->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK(), 1));
            $inv->setItem(22, $stick);
            for ($i = 23; $i < 31; $i++) {
                $inv->setItem($i, VanillaItems::AIR());
            }
        }
        // ARMOR //
        if ($type == "§fArmor") {
            $inv->setItem(
                19,
                VanillaItems::CHAINMAIL_BOOTS()
                    ->setLore(["§f40 Iron"])
                    ->setCustomName("§eChainmail Set")
            );
            $inv->setItem(
                20,
                VanillaItems::IRON_BOOTS()
                    ->setLore(["§612 Gold"])
                    ->setCustomName("§eIron Set")
            );
            $inv->setItem(
                21,
                VanillaItems::DIAMOND_BOOTS()
                    ->setLore(["§26 Emerald"])
                    ->setCustomName("§eDiamond Set")
            );
            for ($i = 22; $i < 31; $i++) {
                $inv->setItem($i, VanillaItems::AIR());
            }
        }
        if ($type == "§fTools") {
            $inv->setItem(
                19,
                VanillaItems::SHEARS()
                    ->setLore(["§f20 Iron"])
                    ->setCustomName("§eShears")
            );
            $pickaxe = $this->getPickaxeByTier($player);
            $inv->setItem(20, $pickaxe);
            $axe = $this->getAxeByTier($player);
            $inv->setItem(21, $axe);
            for ($i = 22; $i < 31; $i++) {
                $inv->setItem($i, VanillaItems::AIR());
            }
        }
        if ($type == "§fBow & Arrow") {
            $inv->setItem(
                19,
                VanillaItems::ARROW()->setCount(8)
                    ->setLore(["§62 Gold"])
                    ->setCustomName("§eArrow")
            );
            $inv->setItem(
                20,
                VanillaItems::BOW()
                    ->setLore(["§612 Gold"])
                    ->setCustomName("§eBow")
            );
            $bowpower = VanillaItems::BOW();
            $bowpower->setLore(["§624 Gold"]);
            $bowpower->setCustomName("§eBow (Power I)");
            $bowpower->addEnchantment(new EnchantmentInstance(VanillaEnchantments::POWER(), 1));
            $inv->setItem(21, $bowpower);
            $bowpunch = VanillaItems::BOW();
            $bowpunch->setLore(["§22 Emerald"]);
            $bowpunch->setCustomName("§eBow (Power I, Punch I)");
            $bowpunch->addEnchantment(new EnchantmentInstance(VanillaEnchantments::POWER(), 1));
            $bowpunch->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PUNCH(), 1));
            $inv->setItem(22, $bowpunch);
            for ($i = 23; $i < 31; $i++) {
                $inv->setItem($i, VanillaItems::AIR());
            }
        }
        if ($type == "§fPotions") {
            $inv->setItem(
                19,
                VanillaItems::POTION()->setType(PotionType::STRONG_SWIFTNESS())->setLore(["§21 Emerald"])
                    ->setCustomName("§eSpeed Potion II (45 seconds)")
            );
            $inv->setItem(
                20,
                VanillaItems::POTION()->setType(PotionType::STRONG_LEAPING())
                    ->setLore(["§21 Emerald"])
                    ->setCustomName("§eJump Potion III (45 seconds)")
            );
            $inv->setItem(
                21,
                VanillaItems::POTION()->setType(PotionType::INVISIBILITY())
                    ->setLore(["§22 Emerald"])
                    ->setCustomName("§eInvisibility Potion (30 seconds)")
            );
            for ($i = 22; $i < 31; $i++) {
                $inv->setItem($i, VanillaItems::AIR());
            }
        }
        if ($type == "§fUtility") {
            $inv->setItem(
                19,
                VanillaItems::GOLDEN_APPLE()
                    ->setLore(["§63 Gold"])
                    ->setCustomName("§eGolden Apple")
            );
            $inv->setItem(
                20,
                VanillaItems::SNOWBALL()
                    ->setLore(["§f40 Iron"])
                    ->setCustomName("§eBedbug")
            );
            $inv->setItem(
                21,
                ExtraBedWarsItem::IRON_GOLEM_SPAWN_EGG()

                    ->setLore(["§f120 Iron"])
                    ->setCustomName("§eDream Defender")
            );
            $inv->setItem(
                22,
                VanillaItems::FIRE_CHARGE()
                    ->setLore(["§f40 Iron"])
                    ->setCustomName("§eFireball")
            );
            if ($this->data["maxInTeam"] == 1 || $this->data["maxInTeam"] == 2) {
                $inv->setItem(
                    23,
                    VanillaBlocks::TNT()->asItem()
                        ->setLore(["§64 Gold"])
                        ->setCustomName("§eTNT")
                );
            } else {
                $inv->setItem(
                    23,
                    VanillaBlocks::TNT()->asItem()
                        ->setLore(["§68 Gold"])
                        ->setCustomName("§eTNT")
                );
            }
            $inv->setItem(
                24,
                VanillaItems::ENDER_PEARL()
                    ->setLore(["§24 Emerald"])
                    ->setCustomName("§eEnder Pearl")
            );
            $inv->setItem(
                25,
                VanillaItems::EGG()
                    ->setLore(["§23 Emerald"])
                    ->setCustomName("§eEgg Bridge")
            );
            $inv->setItem(
                26,
                VanillaItems::MILK_BUCKET()
                    ->setLore(["§64 Gold"])
                    ->setCustomName("§eMagic Milk")
            );
            $inv->setItem(
                28,
                VanillaBlocks::CHEST()->asItem()
                    ->setLore(["§f24 Iron"])
                    ->setCustomName("§eCompact pop up tower")
            );
        }
    }

    /**
     * @param $player
     * @param bool $forshop
     * @return Item
     */
    public function getPickaxeByTier($player, bool $forshop = true): Item
    {
        if (isset($this->pickaxeType[$player->getId()])) {
            $tier = $this->pickaxeType[$player->getId()];
            $pickaxe = [
                1 => VanillaItems::WOODEN_PICKAXE(),
                2 => VanillaItems::WOODEN_PICKAXE(),
                3 => VanillaItems::IRON_PICKAXE(),
                4 => VanillaItems::GOLDEN_PICKAXE(),
                5 => VanillaItems::DIAMOND_PICKAXE(),
                6 => VanillaItems::DIAMOND_PICKAXE()
            ];
            $enchant = [
                1 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1),
                2 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1),
                3 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2),
                4 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2),
                5 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 3),
                6 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 3)
            ];
            $name = [
                1 => "§aWooden Pickaxe (Efficiency I)",
                2 => "§aWooden Pickaxe (Efficiency I)",
                3 => "§aIron Pickaxe (Efficiency II)",
                4 => "§aGolden Pickaxe (Efficiency II)",
                5 => "§aDiamond Pickaxe (Efficiency III)",
                6 => "§aDiamond Pickaxe (Efficiency III)"
            ];
            $lore = [
                1 => [
                    "§f10 Iron",
                    "§eTier: §cI",
                    ""
                ],
                2 => [
                    "§f10 Iron",
                    "§eTier: §cI",
                    ""
                ],
                3 => [
                    "§f10 Iron",
                    "§eTier: §cII",
                    ""
                ],
                4 => [
                    "§63 Gold",
                    "§eTier: §cIII",
                    ""
                ],
                5 => [
                    "§66 Gold",
                    "§eTier: §cIV",
                    ""
                ],
                6 => [
                    "§66 Gold",
                    "§eTier: §cV",
                    "§aMax",
                    ""
                ]
            ];
            $pickaxe[$tier]->addEnchantment($enchant[$tier]);
            if ($forshop) {
                $pickaxe[$tier]->setLore($lore[$tier]);
                $pickaxe[$tier]->setCustomName($name[$tier]);
            }
            return $pickaxe[$tier];
        }
        return VanillaItems::AIR();
    }

    /**
     * @param $player
     * @param bool $forshop
     * @return Item
     */
    public function getAxeByTier($player, bool $forshop = true): Item
    {
        if (isset($this->axeType[$player->getId()])) {
            $tier = $this->axeType[$player->getId()];
            $axe = [
                1 => VanillaItems::WOODEN_AXE(),
                2 => VanillaItems::WOODEN_AXE(),
                3 => VanillaItems::STONE_AXE(),
                4 => VanillaItems::IRON_AXE(),
                5 => VanillaItems::DIAMOND_AXE(),
                6 => VanillaItems::DIAMOND_AXE()
            ];
            $enchant = [
                1 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1),
                2 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1),
                3 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 1),
                4 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2),
                5 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 3),
                6 => new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 3)
            ];
            $name = [
                1 => "§aWooden Axe (Efficiency I)",
                2 => "§aWooden Axe (Efficiency I)",
                3 => "§aStone Axe (Efficiency I)",
                4 => "§aIron Axe (Efficiency II)",
                5 => "§aDiamond Axe (Efficiency III)",
                6 => "§aDiamond Axe (Efficiency III)"
            ];
            $lore = [
                1 => [
                    "§f10 Iron",
                    "§eTier: §cI",
                    ""
                ],
                2 => [
                    "§f10 Iron",
                    "§eTier: §cI",
                    ""
                ],
                3 => [
                    "§f10 Iron",
                    "§eTier: §cII",
                    '',
                    "§7This is an upgradable item.",
                    "§7will lose 1 tier upon",
                    "§7death!",
                    ""
                ],
                4 => [
                    "§63 Gold",
                    "§eTier: §cIII",
                    ""
                ],
                5 => [
                    "§66 Gold",
                    "§eTier: §cIV",
                    ""
                ],
                6 => [
                    "§66 Gold",
                    "§eTier: §cV",
                    "§aMax",
                    ""
                ]
            ];
            $axe[$tier]->addEnchantment($enchant[$tier]);
            if ($forshop) {
                $axe[$tier]->setLore($lore[$tier]);
                $axe[$tier]->setCustomName($name[$tier]);
            }
            return $axe[$tier];
        }
        return VanillaItems::AIR();
    }


    /**
     * @param Item $item
     * @param Player $player
     * @return void
     */
    public function buyItem(Item $item, Player $player): void
    {
        if (!isset($item->getLore()[0])) return;
        $lore = TextFormat::clean($item->getLore()[0]);
        $desc = explode(" ", $lore);
        $value = intval($desc[0]);
        $valueType = $desc[1];

        $payment = null;
        if ($value < 1) return;
        if (!$item instanceof Pickaxe && !$item instanceof Axe) {
            $item = $item->setLore([]);
        }
        switch ($valueType) {
            case "Iron":
                $payment = VanillaItems::IRON_INGOT()->setCount($value);
                break;
            case "Gold":
                $payment = VanillaItems::GOLD_INGOT()->setCount($value);
                break;
            case "Emerald":
                $payment = VanillaItems::EMERALD()->setCount($value);
                break;
            default:
                break;
        }

        if ($item instanceof Pickaxe || $item instanceof Axe) {
            $type = $item instanceof Pickaxe ? 'pickaxeType' : 'axeType';
            if (isset($this->$type[$player->getId()])) {
                if ($this->$type[$player->getId()] >= 6) {
                    return;
                }
            }
            $item = $item->setLore([]);
            $item->setUnbreakable();
            $c = 0;
            $i = 0;
            foreach ($player->getInventory()->getContents() as $slot => $isi) {
                if ($isi instanceof $item) {
                    $c++;
                    $i = $slot;
                }
            }

            if ($player->getInventory()->contains($payment)) {
                $this->$type[$player->getId()] = $this->getNextTier($player, $item instanceof Axe);
                $player->getInventory()->removeItem($payment);
                $this->messageBuy($player, $item->getName());

                if ($c > 0) {
                    $player->getInventory()->setItem($i, $item);
                } else {
                    $player->getInventory()->addItem($item);
                }
            } else {
                $this->notEnought($player, str_replace(" Ingot", '', $payment->getName()), $value);
                $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
            }
            return;
        }

        if ($player->getInventory()->contains($payment)) {
            $player->getInventory()->removeItem($payment);
            // $it = ItemFactory::getInstance()->get($item->getId(), $item->getMeta(), $item->getCount());
            $it = $item;
            if (in_array($item->getCustomName(), ["§eMagic Milk", "§eBedbug", "§beream Defender", "§eFireball", "§eInvisibility Potion (30 seconds)", "§eSpeed Potion II (45 seconds)", "§eJump Potion III (45 seconds)"])) {
                $it->setCustomName($item->getCustomName());
            }
            if ($player->getInventory()->canAddItem($it)) {
                $player->getInventory()->addItem($it);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $it);
            }
            $this->messageBuy($player, $item->getName());
        } else {
            $this->notEnought($player, str_replace(" Ingot", '', $payment->getName()), $value);
            $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound());
        }
    }



    /**
     * @param $player
     * @param bool $type
     * @return int|string
     */
    public function getLessTier($player, bool $type): int|string
    {
        if ($type) {
            if (isset($this->axeType[$player->getId()])) {
                $tier = $this->axeType[$player->getId()];
                $less = [
                    6 => 4,
                    5 => 4,
                    4 => 3,
                    3 => 2,
                    2 => 1,
                    1 => 1
                ];
                return $less[$tier];
            }
        } else {
            if (isset($this->pickaxeType[$player->getId()])) {
                $tier = $this->pickaxeType[$player->getId()];
                $less = [
                    6 => 4,
                    5 => 4,
                    4 => 3,
                    3 => 2,
                    2 => 1,
                    1 => 1
                ];
                return $less[$tier];
            }
        }
        return "";
    }

    /**
     * @param $player
     * @param bool $type
     * @return int|string
     */
    public function getNextTier($player, bool $type): int|string
    {
        $less = [
            6 => 4,
            5 => 4,
            4 => 3,
            3 => 2,
            2 => 1,
            1 => 1
        ];
        if ($type) {
            return isset($this->axeType[$player->getId()]) ? $less[$this->axeType[$player->getId()]] : "";
        } else {
            return isset($this->pickaxeType[$player->getId()]) ? $less[$this->pickaxeType[$player->getId()]] : "";
        }
    }

    public function spawnTNT(Position $position, $fuse = 80): void
    {
        $position->getWorld()->setBlock($position, VanillaBlocks::AIR());

        $mot = (new Random())->nextSignedFloat() * M_PI * 2;

        $tnt = new CustomTNT(Location::fromObject($position->add(0.5, 0, 0.5), $position->getWorld()), null);
        $tnt->game = $this;
        $tnt->setFuse($fuse);
        $tnt->setWorksUnderwater(false);
        $tnt->setMotion(new Vector3(-sin($mot) * 0.02, 0.2, -cos($mot) * 0.02));

        $tnt->spawnToAll();
        $tnt->broadcastSound(new IgniteSound());
    }

    public function spawnBedbug(Location $location, Player $player): void
    {
        if ($this->phase != self::PHASE_GAME) return;
        $entity = new SilverFish($location, null);
        $entity->game = $this;
        $entity->owner = $player;
        $entity->spawnToAll();
    }

    public function spawnDreamDefender(Location $location, Player $player): void
    {
        $entity = new IronGolem($location, null);
        $entity->game = $this;
        $entity->owner = $player;
        $entity->spawnToAll();
    }

    public function spawnFireball(Location $location, Player $player): void
    {
        $entity = new FireBall($location, null);
        $entity->game = $this;
        $entity->owner = $player;
        $entity->spawnToAll();
    }

    public function spawnTower(Player $player, Block $block)
    {
        // $rotation = ($player->getLocation()->getYaw() - 90.0) % 360.0;
        // if ($rotation < 0.0) {
        //     $rotation += 360.0;
        // }
        // $ih = $player->getInventory()->getItemInHand();
        // $ih->setCount($ih->getCount() - 1);
        // $player->getInventory()->setItemInHand($ih);
        // if (45.0 <= $rotation && $rotation < 135.0) {
        //     $TowerSouth = new TowerSouth($this, $player, $block);
        //     $TowerSouth->createTower();
        //     return;
        // }

        // if (225.0 <= $rotation && $rotation < 315.0) {
        //     $TowerNorth = new TowerNorth($this, $player, $block);
        //     $TowerNorth->createTower();
        //     return;
        // }

        // if (135.0 <= $rotation && $rotation < 225.0) {
        //     $TowerWest = new TowerWest($this, $player, $block);
        //     $TowerWest->createTower();
        //     return;
        // }

        // if (0.0 <= $rotation && $rotation < 45.0) {
        //     $TowerEast = new TowerEast($this, $player, $block);
        //     $TowerEast->createTower();
        //     return;
        // }

        // if (315.0 <= $rotation && $rotation < 360.0) {
        //     $TowerEast = new TowerEast($this, $player, $block);
        //     $TowerEast->createTower();
        //     return;
        // }
    }


    /**
     * @param bool $restart
     * @return void
     */
    public function loadArena(bool $restart = false): void
    {
        $this->plugin->getLogger()->info("§aStar load arena {$this->data['world']}");
        if (!$this->data["enabled"]) {
            $this->plugin->getLogger()->error("Arena is not enabled!");
            return;
        }

        if (!$this->mapReset instanceof MapReset) {
            $this->mapReset = new MapReset($this);
        }

        if (!$restart) {
            $this->plugin->getServer()->getPluginManager()->registerEvents(new GameListener($this), $this->plugin);
        } else {
            $this->gameTask->reloadTimer();
            $this->world = $this->mapReset->loadMap($this->data["world"]);
        }

        if (!$this->world instanceof World) {
            Server::getInstance()->getWorldManager()->loadWorld($this->data['world']);
            $world = Server::getInstance()->getWorldManager()->getWorldByName($this->data['world']);;
            if (!$world instanceof World) {
                $this->plugin->getLogger()->error("Arena world not found!");
                $this->setup = true;
                return;
            }
            $this->world = $world;
        }
        $this->initTeams();
        $this->phase = static::PHASE_LOBBY;
        $this->players = [];
    }

    /**
     * @param bool $loadArena
     * @return bool
     */
    public function enable(bool $loadArena = true): bool
    {
        if (empty($this->data)) {
            BedWars::getInstance()->getLogger()->error("Data cannot be empty");
            return false;
        }
        if (is_null($this->data["world"]) || is_null($this->data["waitingSpawn"]) || is_null($this->data["display-name"])) {
            BedWars::getInstance()->getLogger()->error("world or waitingSpawn or display-name cannot be empty");
            return false;
        }
        if (!is_int($this->data["maxInTeam"])) {
            BedWars::getInstance()->getLogger()->error("maxInTeam cannot be empty");
            return false;
        }
        if (count($this->data["teamName"]) < 2 || count($this->data["teamColor"]) < 2 || count($this->data["teamBed"]) < 2 || count($this->data["teamSpawn"]) < 2 || count($this->data["teamShop"]) < 2 || count($this->data["teamUpgrade"]) < 2 || count($this->data["teamGenerator"]) < 2 || count($this->data["generator"]) < 1) {
            BedWars::getInstance()->getLogger()->error("Team utility cannot be < 2");
            return false;
        }
        if (!$this->plugin->getServer()->getWorldManager()->isWorldGenerated($this->data["world"])) {
            BedWars::getInstance()->getLogger()->error("Cannot find world with name {$this->data["world"]} in server worlds");
            return false;
        }
        $this->data["enabled"] = true;
        $this->setup = false;
        if ($loadArena) $this->loadArena();
        return true;
    }

    private function createBasicData()
    {
        $this->data = [
            "display-name" => null,
            "maxInTeam" => null,
            "waitingSpawn" => null,
            "world" => null,
            "teamName" => [],
            "teamColor" => [],
            "teamBed" => [],
            "teamSpawn" => [],
            "teamShop" => [],
            "teamUpgrade" => [],
            "teamGenerator" => [],
            "generator" => [],
            "blocks" => [],
            "enabled" => false
        ];
    }

    public function __destruct()
    {
        unset($this->gameTask);
    }
}
