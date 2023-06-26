<?php

namespace bofoiii\bedwars\task;

use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\game\Game;
use bofoiii\bedwars\utils\Utils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class GameTask extends Task
{

    /** @var int $waitTime */
    public int $waitTime = 20;

    /** @var int $upgradeNextTime */
    public int $upgradeNextTime = 1;

    /** @var int|float $upgradeTime */
    public int|float $upgradeTime = 5 * 60;

    /** @var int|float $bedGoneTime */
    public int|float $bedGoneTime = 10 * 60;

    /** @var int|float $suddenDeathTime */
    public int|float $suddenDeathTime = 10 * 60;

    /** @var int|float $gameOverTime */
    public int|float $gameOverTime = 10 * 60;

    /** @var int $restartTime */
    public int $restartTime = 10;

    /** @var Game $game */
    public Game $game;

    /**
     * @param Game $game
     */
    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->reloadTimer();
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        $text = "§l§eBED WARS";
        if ($this->game->setup) return;
        $api = BedWars::getScore();
        switch ($this->game->phase) {
            case Game::PHASE_LOBBY:
                $this->game->world->setTime(5000);
                if (count($this->game->players) >= (2 * (int)$this->game->data["maxInTeam"])) {
                    if ($this->waitTime > 0) {
                        $this->waitTime--;
                        foreach ($this->game->players as $player) {
                            if (!$player->isOnline()) {
                                return;
                            }
                            $api->new($player, $player->getName(), $text);
                            $api->setLine($player, 1, " ");
                            $api->setLine($player, 2, "§fMap: §a" . $this->game->data["display-name"]);
                            $api->setLine($player, 3, "§fPlayers: §a" . count($this->game->players) . "/" . ($this->game->data["maxInTeam"] * count($this->game->data["teamName"])));
                            $api->setLine($player, 4, "  ");
                            $api->setLine($player, 5, "§fStarting in: §a" . $this->waitTime . "s");
                            $api->setLine($player, 6, "   ");
                            $api->setLine($player, 7, "§fMode: §a" . Utils::maxInTeamToGroup($this->game->data["maxInTeam"]));
                            $api->setLine($player, 8, "    ");
                            $api->setLine($player, 9, "§eplay.youripaddress.com");
                            $api->getObjectiveName($player);
                        }
                    }
                    if ($this->waitTime == 20) {
                        $this->game->broadcastMessage("§eThe game has starts in 20 seconds!");
                    }
                    if ($this->waitTime == 10) {
                        $this->game->broadcastMessage("§eThe game has starts in §610 §eseconds!");
                    }
                    if ($this->waitTime == 5) {
                        $this->game->broadcastMessage("§eThe game has starts in §c5 §eseconds!");
                        foreach ($this->game->players as $players) {
                            Utils::addSound($players, "random.toast");
                            $players->sendTitle("§e5");
                        }
                    }
                    if ($this->waitTime == 4) {
                        $this->game->broadcastMessage("§eThe game has starts in §c4 §eseconds!");
                        foreach ($this->game->players as $players) {
                            Utils::addSound($players, "random.toast");
                            $players->sendTitle("§e4");
                        }
                    }
                    if ($this->waitTime == 3) {
                        $this->game->broadcastMessage("§eThe game has starts in §c3 §eseconds!");
                        foreach ($this->game->players as $players) {
                            Utils::addSound($players, "random.toast");
                            $players->sendTitle("§c3");
                        }
                    }
                    if ($this->waitTime == 2) {
                        $this->game->broadcastMessage("§eThe game has starts in §c2 §eseconds!");
                        foreach ($this->game->players as $players) {
                            Utils::addSound($players, "random.toast");
                            $players->sendTitle("§c2");
                        }
                    }
                    if ($this->waitTime == 1) {
                        $this->game->broadcastMessage("§eThe game has starts in §c1 §esecond!");
                        foreach ($this->game->players as $players) {
                            Utils::addSound($players, "random.toast");
                            $players->sendTitle("§c1");
                        }
                    }
                    if ($this->waitTime == 0) {
                        $this->game->startGame();
                        foreach ($this->game->players as $players) {
                            $players->sendMessage("§a▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬");
                            $players->sendMessage("§f                                   §lBedWars");
                            $players->sendMessage("");
                            $players->sendMessage("§e§l    Protect your bed and destroy the enemy beds.");
                            $players->sendMessage("§e§l      Upgrade yourself and your team by collecting");
                            $players->sendMessage("§e§l   Iron, Gold, Emerald, and Diamond from generators");
                            $players->sendMessage("");
                            $players->sendMessage("§a▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬");
                            if ($this->game->data["maxInTeam"] == 1) {
                                $players->sendMessage("§c§lTeaming is not allowed in Solo mode!");
                            }
                        }
                    }
                } else {
                    foreach ($this->game->players as $player) {
                        if (!$player->isOnline()) {
                            return;
                        }
                        $api->new($player, $player->getName(), $text);
                        $api->setLine($player, 1, " ");
                        $api->setLine($player, 2, "§fMap: §a" . $this->game->data["display-name"]);
                        $api->setLine($player, 3, "§fPlayers: §a" . count($this->game->players) . "/" . ($this->game->data["maxInTeam"] * count($this->game->data["teamName"])));
                        $api->setLine($player, 4, "  ");
                        $api->setLine($player, 5, "§fWaiting...");
                        $api->setLine($player, 6, "   ");
                        $api->setLine($player, 7, "§fMode: §a" . Utils::maxInTeamToGroup($this->game->data["maxInTeam"]));
                        $api->setLine($player, 8, "    ");
                        $api->setLine($player, 9, "§eplay.youripaddress.com");
                        $api->getObjectiveName($player);
                        $this->waitTime = 20;
                    }
                }
                break;
            case Game::PHASE_GAME:
                $this->game->world->setTime(5000);
                $events = "";
                if ($this->upgradeNextTime <= 4) {
                    $this->upgradeTime--;
                    if ($this->upgradeNextTime == 1) {
                        $events = "§fDiamond II in: §a" . Utils::calculateTime($this->upgradeTime);
                    }
                    if ($this->upgradeNextTime == 2) {
                        $events = "§fEmerald II in: §a" . Utils::calculateTime($this->upgradeTime);
                    }
                    if ($this->upgradeNextTime == 3) {
                        $events = "§fDiamond III in: §a" . Utils::calculateTime($this->upgradeTime);
                    }
                    if ($this->upgradeNextTime == 4) {
                        $events = "§fEmerald III in: §a" . Utils::calculateTime($this->upgradeTime);
                    }
                    if ($this->upgradeTime == (0.0 * 60)) {
                        $this->upgradeTime = 5 * 60;
                        if ($this->upgradeNextTime == 1) {
                            $this->game->broadcastMessage("§bDiamond Generators §ehas been upgraded to Tier §cII");
                            $this->game->upgradeGeneratorTier("diamond", 2);
                        }
                        if ($this->upgradeNextTime == 2) {
                            $this->game->broadcastMessage("§2Emerald Generators §ehas been upgraded to Tier §cII");
                            $this->game->upgradeGeneratorTier("emerald", 2);
                        }
                        if ($this->upgradeNextTime == 3) {
                            $this->game->broadcastMessage("§bDiamond Generators §ehas been upgraded to Tier §cIII");
                            $this->game->upgradeGeneratorTier("diamond", 3);
                        }
                        if ($this->upgradeNextTime == 4) {
                            $this->game->broadcastMessage("§2Emerald Generators §ehas been upgraded to Tier §cIII");
                            $this->game->upgradeGeneratorTier("emerald", 3);
                        }
                        $this->upgradeNextTime++;
                    }
                } else {
                    if ($this->bedGoneTime > (-1.0 * 60)) {
                        $this->bedGoneTime--;
                        $events = "§fBed gone in: §a" . Utils::calculateTime($this->bedGoneTime);
                    }
                    if ($this->upgradeNextTime == 6) {
                        $this->suddenDeathTime--;
                        $events = "§fSudden Death in: §a" . Utils::calculateTime($this->suddenDeathTime);
                    }
                    if ($this->bedGoneTime == (0.0 * 60)) {
                        if ($this->upgradeNextTime == 5) {
                            $this->game->destroyAllBeds();
                            $this->upgradeNextTime = 6;
                            $this->suddenDeathTime--;
                        }
                        $this->game->world->setTime(5000);
                    }
                    if ($this->suddenDeathTime == (0.1 * 60)) {
                        if ($this->upgradeNextTime == 6) {
                            $this->upgradeNextTime = 7;
                            $this->game->setSuddenDeath();
                        }
                    }
                    if ($this->upgradeNextTime == 7) {
                        $this->gameOverTime--;
                        $events = "§fGame End in: §a" . Utils::calculateTime($this->gameOverTime);
                    }
                    if ($this->gameOverTime == (0.1 * 60)) {
                        $this->upgradeNextTime = 8;
                        $this->game->setDraw();
                    }
                }
                foreach ($this->game->players as $r) {
                    if (isset($this->game->respawnC[$r->getName()])) {
                        if ($this->game->respawnC[$r->getName()] <= 1) {
                            unset($this->game->respawnC[$r->getName()]);
                            $r->sendTitle("§aRESPAWNED!");
                            $this->game->respawn($r);
                        } else {
                            $this->game->respawnC[$r->getName()]--;
                            $r->sendSubtitle("§eYou will respawn in §c" . $this->game->respawnC[$r->getName()] . " §eseconds!");
                            $r->sendMessage("§eYou will respawn in §c" . $this->game->respawnC[$r->getName()] . " §eseconds!");
                        }
                    }
                }

                foreach ($this->game->players as $milk) {
                    if (isset($this->game->ifMilk[$milk->getId()])) {
                        if ($this->game->ifMilk[$milk->getId()] <= 0) {
                            unset($this->game->ifMilk[$milk->getId()]);
                        } else {
                            $this->game->ifMilk[$milk->getId()]--;
                        }
                    }
                }

                foreach ($this->game->players as $pt) {
                    $team = $this->game->getTeam($pt);
                    if (isset($this->game->utilityArena[$team]["haste"])) {
                        if ($this->game->getTeam($pt) == $team) {
                            if ($this->game->utilityArena[$team]["haste"] > 1) {
                                $eff = new EffectInstance(VanillaEffects::HASTE(), 60, ($this->game->utilityArena[$team]["haste"] - 2));
                                $eff->setVisible(false);
                                $pt->getEffects()->add($eff);
                            }
                        }
                    }
                    if (isset($this->game->utilityArena[$team]["health"])) {
                        if ($this->game->getTeam($pt) == $team) {
                            if ($this->game->utilityArena[$team]["health"] > 1) {
                                $eff = new EffectInstance(VanillaEffects::REGENERATION(), 60, 0);
                                $eff->setVisible(false);
                                $pt->getEffects()->add($eff);
                            }
                        }
                    }
                }

                foreach (array_merge($this->game->players, $this->game->spectators) as $player) {
                    $player->setScoreTag("§f" . $player->getHealth() . " §c");
                    $team =  $this->game->getTeam($player);
                    if (!$player->isOnline()) {
                        return;
                    }
                    if ($team == "") {
                        return;
                    }
                    if (!$player->getEffects()->has(VanillaEffects::INVISIBILITY())) {
                        if (isset($this->game->ifInvis[$player->getId()])) {
                            $this->game->setInvis($player, false);
                        }
                    }
                    $player->getHungerManager()->setFood(20);
                    $kills = $this->game->scoreData["kills"][$player->getName()] ?? 0;
                    $final_kills = $this->game->scoreData["final_kills"][$player->getName()] ?? 0;
                    $beds_broken = $this->game->scoreData["beds_broken"][$player->getName()] ?? 0;
                    $player->getHungerManager()->setFood(20);
                    $date = date("m/d/Y");
                    $date = explode("/", $date)[0] . "/" . explode("/", $date)[1] . "/" . substr(explode("/", $date)[2], 2);
                    $api->new($player, $player->getName(), $text);
                    $api->setLine($player, 1, "§7" . $date);
                    $api->setLine($player, 2,  " ");
                    $api->setLine($player, 3,  $events);
                    $api->setLine($player, 4, "  ");
                    $currentLine = 5;
                    foreach ($this->game->data["teamName"] as $teamName) {
                        $isPlayerTeam = ($teamName == $this->game->getTeam($player)) ? TextFormat::GRAY . "YOU" : "";
                        $stringFormat = TextFormat::BOLD . $this->game->data["teamColor"][$teamName] . ucfirst($teamName[0]) . " " . TextFormat::RESET . TextFormat::WHITE . ucfirst($teamName) . ": " . $this->game->statusTeam($teamName) .  " " . $isPlayerTeam;
                        $api->setLine($player, $currentLine, $stringFormat);
                        $currentLine++;
                    }
                    if (Utils::maxInTeamToGroup($this->game->data["maxInTeam"]) !== "solo" || Utils::maxInTeamToGroup($this->game->data["maxInTeam"]) !== "doubles") {
                        $api->setLine($player, $currentLine++, "       ");
                        $api->setLine($player, $currentLine++, "Kills: §a" . $kills);
                        $api->setLine($player, $currentLine++, "Final Kills: §a" . $final_kills);
                        $api->setLine($player, $currentLine++, "Beds Broken: §a" . $beds_broken);
                    }
                    $api->setLine($player, $currentLine++, "   ");
                    $api->setLine($player, $currentLine, "§eplay.youripaddress.com");
                    $api->getObjectiveName($player);
                }

                $aliveTeam = $this->game->getAliveTeams();
                if (count($aliveTeam) == 1) {
                    $this->game->setTeamsWin($aliveTeam[0]);
                }
                break;
            case Game::PHASE_RESTART:
                $this->restartTime--;
                switch ($this->restartTime) {
                    case 0:
                        foreach ($this->game->world->getPlayers() as $player) {
                            /*$player->setGamemode(GameMode::SURVIVAL());
                            $player->teleport(BedWars::getInstance()->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();
                            $player->getHungerManager()->setFood(20);
                            $player->setHealth(20);*/
                            $this->game->disconnectPlayer($player);
                            $api->remove($player);
                        }
                        break;
                    case -1:
                        $this->game->world = $this->game->mapReset->loadMap($this->game->world->getFolderName());
                        break;
                    case -6:
                        $this->game->loadArena(true);
                        $this->reloadTimer();
                        $this->game->destroyEntity();
                        break;
                }
                break;
        }
    }

    public function reloadTimer()
    {
        if (!empty($this->game->data["world"])) {
            $this->waitTime = 20;
            $this->upgradeNextTime = 1;
            $this->upgradeTime = 5 * 60;
            $this->bedGoneTime = 10 * 60;
            $this->suddenDeathTime = 10 * 60;
            $this->gameOverTime = 10 * 60;
            $this->restartTime = 10;
        }
    }
}
