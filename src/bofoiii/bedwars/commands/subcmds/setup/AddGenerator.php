<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\block\BlockLegacyIds;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class AddGenerator extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("type", true));
        $this->registerArgument(1, new RawStringArgument("team", true));
        $this->setPermission("newbedwars.cmd.addgenerator");
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
        if (!$this->testPermissionSilent($sender)) {
            return;
        }

        if (!isset(BedWars::getInstance()->setters[$sender->getName()])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cYou're not in a setup session!");
            return;
        }


        $arena = BedWars::getInstance()->setters[$sender->getName()];

        if (!isset($args["type"])) {
            $team = Utils::getNearestTeam($sender);
            if ($team == "") {
                if ($sender->getWorld()->getBlock($sender->getPosition()->asVector3()->add(0, -1, 0))->getId() == BlockLegacyIds::DIAMOND_BLOCK) {
                    BedWars::getInstance()->getServer()->dispatchCommand($sender, "newbedwars addGenerator diamond");
                    return;
                }
                if ($sender->getWorld()->getBlock($sender->getPosition()->asVector3()->add(0, -1, 0))->getId() == BlockLegacyIds::EMERALD_BLOCK) {
                    BedWars::getInstance()->getServer()->dispatchCommand($sender, "newbedwars addGenerator emerald");
                    return;
                }

                $sender->sendMessage(BedWars::getInstance()->prefix . "§cCould not find any nearby team.");
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cMake sure you set the team's spawn first!");
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cOr if you set the spawn and it wasn't found automatically try using: /newbedwars addGenerator <team>");
                $sender->sendMessage(BedWars::getInstance()->prefix .  "§cOther use: /newbedwars addGenerator <emerald/diamond>");
                $sender->sendTitle(" ", "§cCould not find any nearby team.", 5, 60, 5);
                Utils::addSound($sender, "mob.villager.no");
                return;
            }
            $arena->data["teamGenerator"][$team]["iron"] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $arena->data["teamGenerator"][$team]["gold"] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $arena->data["teamGenerator"][$team]["emerald"] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aGenerator set for team: " . $arena->data["teamColor"][$team] . $team);
            $sender->sendTitle(" ", "§aGenerator set for team: " . $arena->data["teamColor"][$team] . $team, 5, 40, 5);
            Utils::addSound($sender, "mob.villager.yes");
            BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
            return;
        } else if ((strtolower($args["type"]) == "diamond") || (strtolower($args["type"]) == "emerald")) {
            $position = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            if (isset($arena->data["generator"][strtolower($args["type"])])) {
                if (in_array($position, $arena->data["generator"][strtolower($args["type"])])) {
                    $sender->sendMessage(BedWars::getInstance()->prefix .  "§cThis generator was already set!");
                    $sender->sendTitle(" ", "§cThis generator was already set!", 5, 30, 5);
                    Utils::addSound($sender, "mob.villager.no");
                    return;
                }
            }
            $arena->data["generator"][$args["type"]][] = $position;
            $sender->sendMessage(BedWars::getInstance()->prefix . "§a" . ucfirst(strtolower($args["type"])). " generator was added!");
            $sender->sendTitle(" ", "§a" . ucfirst(strtolower($args["type"])) . " generator was added!", 5, 60, 5);
            Utils::addSound($sender, "mob.villager.yes");
            BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
            return;
        } else if ((strtolower($args["type"]) == "iron") || (strtolower($args["type"]) == "gold")) {
            $GLOBALS["team"] = "";
            if (!isset($args["team"])) {
                $GLOBALS["team"] = Utils::getNearestTeam($sender);
            } else {
                $GLOBALS["team"] = $args["team"];
                if (!isset($arena->data["teamColor"][$GLOBALS["team"]])) {
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§cCould not find team: " . $GLOBALS["team"]);
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§cUse: /newbedwars createTeam if you want to create one.");
                    if (count($arena->data["teamName"]) >= 1) {
                        $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                        foreach ($arena->data["teamName"] as $team) {
                            $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                        }
                    }
                    $sender->sendTitle(" ", "§cCould not find any nearby team.", 5, 60, 5);
                    Utils::addSound($sender, "mob.villager.no");
                    return;
                }
            }
            if ($GLOBALS["team"] == "") {
                $sender->sendMessage(BedWars::getInstance()->prefix .  "§cCould not find any nearby team.");
                $sender->sendMessage(BedWars::getInstance()->prefix . "Try using: /newbedwars addGenerator <iron/gold/upgrade> <team>");
                return;
            }
            $arena->data["teamGenerator"][$GLOBALS["team"]][strtolower($args["type"])] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $sender->sendMessage(BedWars::getInstance()->prefix . "§a" . ucfirst(strtolower($args["type"])) . " generator added for team: " . Utils::getChatColor($GLOBALS["team"]) . $GLOBALS["team"]);
            $sender->sendTitle(" ", "§a" . ucfirst(strtolower($args["type"])) . " generator added for team: " . Utils::getChatColor($GLOBALS["team"]) . $GLOBALS["team"], 5, 60, 5);
            Utils::addSound($sender, "mob.villager.yes");
            BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
            return;
        } else if (isset($args["team"])) {
            if (!isset($arena->data["teamColor"][$args["team"]])) {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cCould not find team: " . $args["team"]);
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cUse: /newbedwars createTeam if you want to create one.");
                if (count($arena->data["teamName"]) >= 1) {
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                    foreach ($arena->data["teamName"] as $team) {
                        $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                    }
                }
                $sender->sendTitle(" ", "§cCould not find any nearby team.", 5, 60, 5);
                Utils::addSound($sender, "mob.villager.no");
                return;
            }
            $arena->data["teamGenerator"][$args["team"]]["iron"] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $arena->data["teamGenerator"][$args["team"]]["gold"] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aGenerator set for team: " . $arena->data["teamColor"][$args["team"]] . $args["team"]);
            $sender->sendTitle(" ", "§aGenerator set for team: " . $arena->data["teamColor"][$args["team"]] . $args["team"], 5, 40, 5);
            Utils::addSound($sender, "mob.villager.yes");
            BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
            return;
        }
    }
}