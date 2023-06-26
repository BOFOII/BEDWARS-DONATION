<?php

namespace bofoiii\bedwars\commands\subcmds;

use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Help extends BaseSubCommand
{
    /**
     * @return void
     */
    protected function prepare(): void
    {
        //NOOP
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param array $args
     * @return void
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) return;
        if (isset(BedWars::getInstance()->setters[$sender->getName()])) {
            $arena = BedWars::getInstance()->setters[$sender->getName()];
            $GLOBALS["bedNotSet"] = "";
            $GLOBALS["spawnNotSet"] = "";
            $GLOBALS["shopNotSet"] = "";
            $GLOBALS["upgradeNotSet"] = "";
            $GLOBALS["generatorNotSet"] = "";
            //$GLOBALS["dragonPosMSG"] = "§c(NOT SET)";
            if (count($arena->data["teamName"]) >= 1) {
                foreach ($arena->data["teamName"] as $team) {
                    if (!isset($arena->data["teamSpawn"][$team])) {
                        $GLOBALS["spawnNotSet"] = $GLOBALS["spawnNotSet"] . $arena->data["teamColor"][$team] . "▋";
                    }
                    if (!isset($arena->data["teamBed"][$team])) {
                        $GLOBALS["bedNotSet"] = $GLOBALS["bedNotSet"] . $arena->data["teamColor"][$team] . "▋";
                    }
                    if (!isset($arena->data["teamShop"][$team])) {
                        $GLOBALS["shopNotSet"] = $GLOBALS["shopNotSet"] . $arena->data["teamColor"][$team] . "▋";
                    }
                    if (!isset($arena->data["teamUpgrade"][$team])) {
                        $GLOBALS["upgradeNotSet"] = $GLOBALS["upgradeNotSet"] . $arena->data["teamColor"][$team] . "▋";
                    }
                    if (!(isset($arena->data["teamGenerator"][$team]["iron"]) && isset($arena->data["teamGenerator"][$team]["gold"]))) {
                        $GLOBALS["generatorNotSet"] = $GLOBALS["generatorNotSet"] . $arena->data["teamColor"][$team] . "▋";
                    }
                }
            }

            /*if (!is_null($arena->data["dragonPos1"]) && is_null($arena->data["dragonPos2"])) {
                $GLOBALS["dragonPosMSG"] = "§c(POS 2 NOT SET)";
            } else if (is_null($arena->data["dragonPos1"]) && !is_null($arena->data["dragonPos2"])) {
                $GLOBALS["dragonPosMSG"] = "§c(POS 1 NOT SET)";
            } else if (!is_null($arena->data["dragonPos1"])) {
                $GLOBALS["dragonPosMSG"] = "§a(SET)";
            }*/

            $sender->sendMessage("");
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aSetup Commands");
            $sender->sendMessage("§8§l• §r§7/newbedwars " . ((!is_null($arena->data["waitingSpawn"])) ? "§m" : "") . "setWaitingSpawn§r " . ((!is_null($arena->data["waitingSpawn"])) ? "§a(SET)" : "§c(NOT SET)"));
            //$sender->sendMessage("§8§l• §r§7/newbedwars " . ((!is_null($arena->data["dragonPos1"]) && !is_null($arena->data["dragonPos2"])) ? "§m" : "") . "dragonPos 1/2§r " . $GLOBALS["dragonPosMSG"]);
            $sender->sendMessage("§8§l• §r§7/newbedwars autoCreateTeams §e(auto detect)");
            $sender->sendMessage("§8§l• §r§7/newbedwars createTeam <name> <color> §e(" . (isset($arena->data["teamName"]) ? count($arena->data["teamName"]) : 0) . " CREATED)");
            $sender->sendMessage("§8§l• §r§7/newbedwars removeTeam <name>");
            if (count($arena->data["teamName"]) >= 1) {
                $sender->sendMessage("§8§l• §r§7/newbedwars " . ((strlen($GLOBALS["spawnNotSet"]) == 0) ? "§m" : "") . "setSpawn§r " . ((strlen($GLOBALS["spawnNotSet"]) == 0) ? "§a(ALL SET)" : "§c(Remaining: " . $GLOBALS["spawnNotSet"] . "§r§c)"));
                $sender->sendMessage("§8§l• §r§7/newbedwars " . ((strlen($GLOBALS["bedNotSet"]) == 0) ? "§m" : "") . "setBed§r " . ((strlen($GLOBALS["bedNotSet"]) == 0) ? "§a(ALL SET)" : "§c(Remaining: " . $GLOBALS["bedNotSet"] . "§r§c)"));
                $sender->sendMessage("§8§l• §r§7/newbedwars " . ((strlen($GLOBALS["shopNotSet"]) == 0) ? "§m" : "") . "setShop§r " . ((strlen($GLOBALS["shopNotSet"]) == 0) ? "§a(ALL SET)" : "§c(Remaining: " . $GLOBALS["shopNotSet"] . "§r§c)"));
                $sender->sendMessage("§8§l• §r§7/newbedwars " . ((strlen($GLOBALS["upgradeNotSet"]) == 0) ? "§m" : "") . "setUpgrade§r " . ((strlen($GLOBALS["upgradeNotSet"]) == 0) ? "§a(ALL SET)" : "§c(Remaining: " . $GLOBALS["upgradeNotSet"] . "§r§c)"));
                $sender->sendMessage("§8§l• §r§7/newbedwars " . "addGenerator§r " . ((strlen($GLOBALS["generatorNotSet"]) == 0) ? "" : "§c(Remaining: " . $GLOBALS["generatorNotSet"] . "§r§c) ") . "§e(§2E" . (isset($arena->data["generator"]["emerald"]) ? count($arena->data["generator"]["emerald"]) : 0) . " §bD" . (isset($arena->data["generator"]["diamond"]) ? count($arena->data["generator"]["diamond"]) : 0) . "§e)");
            } else {
                $sender->sendMessage("§8§l• §r§7/newbedwars setSpawn §c(NOT SET)");
                $sender->sendMessage("§8§l• §r§7/newbedwars setBed §c(NOT SET)");
                $sender->sendMessage("§8§l• §r§7/newbedwars setShop §c(NOT SET)");
                $sender->sendMessage("§8§l• §r§7/newbedwars setUpgrade §c(NOT SET)");
                $sender->sendMessage("§8§l• §r§7/newbedwars " . "addGenerator§r " . "§e(§2E" . (isset($arena->data["generator"]["emerald"]) ? count($arena->data["generator"]["emerald"]) : 0) . " §bD" . (isset($arena->data["generator"]["diamond"]) ? count($arena->data["generator"]["diamond"]) : 0) . "§e)");
            }
            $sender->sendMessage("§8§l• §r§7/newbedwars " . ((!is_null($arena->data["maxInTeam"])) ? "§m" : "") . "setType§r " . ((!is_null($arena->data["maxInTeam"])) ? "§a(SET)" : "§c(NOT SET)"));
            $sender->sendMessage("§8§l• §r§7/newbedwars save");
        } else {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aCommands");
            $sender->sendMessage("§8§l• §r§7/newbedwars join <worldName>");
            $sender->sendMessage("§8§l• §r§7/newbedwars setupArena <worldName>");
            $sender->sendMessage("§8§l• §r§7/newbedwars enableArena <worldName>");
            $sender->sendMessage("§8§l• §r§7/newbedwars disableArena <worldName>");
            $sender->sendMessage("§8§l• §r§7/newbedwars deleteArena <worldName>");
        }
    }
}