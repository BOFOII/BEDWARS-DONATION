<?php

namespace bofoiii\bedwars\commands\subcmds;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\game\MapReset;
use pocketmine\command\CommandSender;

class EnableArena extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("arena", false));
        $this->setPermission("newbedwars.cmd.enable");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param array $args
     * @return void
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!isset($args["arena"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cUsage: /newbedwars enableArena <worldName>");
        }
        if (!isset(BedWars::getInstance()->arenas[$args["arena"]])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cArena " . $args["arena"] . " was not found!");
            return;
        }

        $arena = BedWars::getInstance()->arenas[$args["arena"]];

        if ($arena->data["enabled"]) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cThis arena is already enabled!");
            return;
        }

        if (!$arena->enable(false)) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cSetup is not completed!");
            return;
        }

        $arena->loadArena(false);
        $sender->sendMessage(BedWars::getInstance()->prefix .  "§aEnabling arena...");
    }
}