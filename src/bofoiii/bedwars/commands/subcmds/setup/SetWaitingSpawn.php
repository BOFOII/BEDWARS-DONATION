<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class SetWaitingSpawn extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->setPermission("newbedwars.cmd.setwaitingspawn");
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
        $arena->data["waitingSpawn"] = Utils::vectorToString(new Vector3(floor($sender->getPosition()->getX()), floor($sender->getPosition()->getY()), floor($sender->getPosition()->getZ())));
        $sender->sendMessage(BedWars::getInstance()->prefix . "§aWaiting spawn set for §e" . $arena->data["world"] .  "§a!");
        BedWars::getInstance()->getServer()->dispatchCommand($sender, "nbw autoCreateTeams");
    }
}