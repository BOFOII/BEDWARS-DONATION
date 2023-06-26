<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class RemoveTeam extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("team", true));
        $this->setPermission("newbedwars.cmd.removeteam");
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
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cUsage: /newbedwars removeTeam <team>");
            if (count($arena->data["teamName"]) >= 1) {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable teams: ");
                foreach ($arena->data["teamName"] as $team) {
                    $sender->sendMessage("§7§l• " . $arena->data["teamColor"][$team] . $team);
                }
            }
            return;
        }

        if (!isset($arena->data["teamColor"][$args["team"]])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cThis team doesn't exist: " . $args["team"]);
            $sender->sendTitle(" ", "§cTeam not found: " . $args["team"], 5, 40 ,5);
            return;
        }

        if (isset($arena->data["teamSpawn"][$args["team"]])) {
            unset($arena->data["teamSpawn"][$args["team"]]);
        }
        if (isset($arena->data["teamBed"][$args["team"]])) {
            unset($arena->data["teamBed"][$args["team"]]);
        }
        if (isset($arena->data["teamGenerator"][$args["team"]])) {
            unset($arena->data["teamGenerator"][$args["team"]]);
        }
        if (isset($arena->data["teamShop"][$args["team"]])) {
            unset($arena->data["teamShop"][$args["team"]]);
        }
        $sender->sendMessage(BedWars::getInstance()->prefix . "§aTeam removed: " . $arena->data["teamColor"][$args["team"]] . $args["team"]);
        $sender->sendTitle(" ", "§aTeam removed: " . $arena->data["teamColor"][$args["team"]] . $args["team"], 5, 40, 5);
        unset($arena->data["teamName"][array_search($args["team"], $arena->data["teamName"])]);
        unset($arena->data["teamColor"][$args["team"]]);
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}