<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class SetUpgrade extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("team", true));
        $this->setPermission("newbedwars.cmd.setupgrade");
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

        if (!isset($args["team"])) {
            $foundTeam = Utils::getNearestTeam($sender);
            if ($foundTeam == "") {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cCould not find any nearby team.");
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cMake sure you set the team's spawn first!");
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cOr if you set the spawn and it wasn't found automatically try using: /newbedwars setUpgrade <team>");
                $sender->sendTitle(" ", "§cCould not find any nearby team.", 5, 60, 5);
                Utils::addSound($sender, "mob.villager.no");
                if (count($arena->data["teamName"]) >= 1) {
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                    foreach ($arena->data["teamName"] as $team) {
                        $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                    }
                }
            } else {
                BedWars::getInstance()->getServer()->dispatchCommand($sender, "newbedwars setUpgrade " . $foundTeam);
            }
            return;
        } else {
            if (!in_array($args["team"], $arena->data["teamName"])) {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cThis team doesn't exist!");
                Utils::addSound($sender, "mob.villager.no");
                if (count($arena->data["teamName"]) >= 1) {
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                    foreach ($arena->data["teamName"] as $team) {
                        $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                    }
                    return;
                }
            } else {
                $arena->data["teamUpgrade"][$args["team"]] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aUpgrade npc set for: " . $arena->data["teamColor"][$args["team"]] . $args["team"]);
                $sender->sendTitle(" ", "§aUpgrade npc set for: " . $arena->data["teamColor"][$args["team"]] . $args["team"], 5, 40, 5);
                Utils::addSound($sender, "mob.villager.yes");
            }
        }
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}