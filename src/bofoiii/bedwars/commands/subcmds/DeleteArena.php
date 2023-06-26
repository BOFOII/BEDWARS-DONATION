<?php

namespace bofoiii\bedwars\commands\subcmds;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class DeleteArena extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("arena", false));
        $this->setPermission("newbedwars.cmd.delete");
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param array $args
     * @return void
     */
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

        foreach ($arena->players as $player) {
            if ($player instanceof Player) {
                $player->teleport(BedWars::getInstance()->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
            }
        }

        if(is_file($file = BedWars::getInstance()->getDataFolder() . "Arenas" . DIRECTORY_SEPARATOR . $args["arena"] . ".yml")) unlink($file);
        unset(BedWars::getInstance()->arenas[$args["arena"]]);

        $sender->sendMessage(BedWars::getInstance()->prefix . "§aSuccesfully delete arena " . $args["arena"] . "!");
    }
}