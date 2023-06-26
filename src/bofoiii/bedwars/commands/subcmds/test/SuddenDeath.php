<?php

namespace bofoiii\bedwars\commands\subcmds\test;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class SuddenDeath extends BaseSubCommand
{

    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("arena", false));
        $this->setPermission("newbedwars.cmd.delete");
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$this->testPermissionSilent($sender)) {
            return;
        }

        if (!isset(BedWars::getInstance()->arenas[$args["arena"]])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cArena " . $args["arena"] . " was not found!");
            return;
        }

        $arena = BedWars::getInstance()->arenas[$args["arena"]];
        $arena->setSuddenDeath();
    }
}
