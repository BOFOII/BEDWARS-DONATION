<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\block\Bed;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class SetSpawn extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("team", true));
        $this->setPermission("newbedwars.cmd.setspawn");
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
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cUsage: /newbedwars setSpawn <team>");
            if (count($arena->data["teamName"]) >= 1) {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                foreach ($arena->data["teamName"] as $team) {
                    $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                }
            }
            return;
        } else {
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
                $arena->data["teamSpawn"][$args["team"]] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aSpawn set for: " . $arena->data["teamColor"][$args["team"]] . $args["team"]);
                $radius = 17;
                for ($x = -$radius; $x < $radius; $x++) {
                    for ($y = -$radius; $y < $radius; $y++) {
                        for ($z = -$radius; $z < $radius; $z++) {
                            $location = $sender->getLocation()->asVector3()->add($x, $y, $z);
                            $block = $sender->getLocation()->getWorld()->getBlock($location);
                            if ($block instanceof Bed) {
                                $sender->teleport($location);
                                BedWars::getInstance()->getServer()->dispatchCommand($sender, "newbedwars setBed " . $args["team"]);
                                return;
                            }
                        }
                    }
                }
                if (count($arena->data["teamName"]) >= 1) {
                    $GLOBALS["remaining"] = "";
                    foreach ($arena->data["teamName"] as $team) {
                        if (!isset($arena->data["teamSpawn"][$team])) {
                            $GLOBALS["remaining"] = $GLOBALS["remaining"] . $arena->data["teamColor"][$team] . "▋";
                        }
                    }
                    if (strlen($GLOBALS["remaining"]) > 0) {
                        $sender->sendMessage(BedWars::getInstance()->prefix . "§cRemaining: " . $GLOBALS["remaining"]);
                    }
                }
            }
        }
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}