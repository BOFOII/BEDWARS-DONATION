<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\block\Bed;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class SetBed extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("team", true));
        $this->setPermission("newbedwars.cmd.setbed");
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
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cOr if you set the spawn and it wasn't found automatically try using: /newbedwars setBed <team>");
                $sender->sendTitle(" ", "§cCould not find any nearby team.", 5, 60, 5);
                Utils::addSound($sender, "mob.villager.no");
                if (count($arena->data["teamName"]) >= 1) {
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                    foreach ($arena->data["teamName"] as $team) {
                        $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                    }
                }
            } else {
                BedWars::getInstance()->getServer()->dispatchCommand($sender, "newbedwars setBed " . $foundTeam);
            }
            return;
        }

        if (!($sender->getWorld()->getBlock($sender->getPosition()->asVector3()->add(0, -0.5, 0)) instanceof Bed || $sender->getWorld()->getBlock($sender->getPosition()->asVector3()->add(0, 0.5, 0)) instanceof Bed || $sender->getWorld()->getBlock($sender->getPosition()->asVector3()) instanceof Bed)) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cYou must stay on a bed while using this command!");
            $sender->sendTitle(" ", "§cYou must stay on a bed.", 5, 40, 5);
            Utils::addSound($sender, "mob.villager.no");
            return;
        }

        if (!in_array($args["team"], $arena->data["teamName"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cThis team doesn't exist!");
            if (count($arena->data["teamName"]) >= 1) {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                foreach ($arena->data["teamName"] as $team) {
                    $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                }
                return;
            }
        } else {
            $arena->data["teamBed"][$args["team"]] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aBed set for: " . $arena->data["teamColor"][$args["team"]] . $args["team"]);
            $sender->sendTitle(" ", "§aBed set for: " . $arena->data["teamColor"][$args["team"]] . $args["team"], 5, 40, 5);
            Utils::addSound($sender, "mob.villager.yes");
        }
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}