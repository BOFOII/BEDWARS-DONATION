<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\game\MapReset;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class Save extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->setPermission("newbedwars.cmd.save");
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
        $arena->enable();

        $sender->teleport(BedWars::getInstance()->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
        if(BedWars::getInstance()->getServer()->getWorldManager()->isWorldGenerated($arena->data["world"])) {
            $world = BedWars::getInstance()->getServer()->getWorldManager()->getWorldByName($arena->data["world"]);
            if(BedWars::getInstance()->getServer()->getWorldManager()->isWorldLoaded($arena->data["world"]))
                BedWars::getInstance()->getServer()->getWorldManager()->unloadWorld($world, true);
            if(!$arena->mapReset instanceof MapReset)
                $arena->mapReset = new MapReset($arena);
            $arena->mapReset->saveMap($world);
        }

        
        $config = new Config(BedWars::getInstance()->getDataFolder() . "Arenas" . DIRECTORY_SEPARATOR . $arena->data["world"] . ".yml", Config::YAML);
        $config->setAll($arena->data);
        $config->save();

        unset(BedWars::getInstance()->setters[$sender->getName()]);
        $sender->getEffects()->clear();
        $sender->sendMessage(BedWars::getInstance()->prefix . "§aArena changes saved!");
        $sender->sendMessage(BedWars::getInstance()->prefix . "§aYou can now enable it using:");
        $sender->sendMessage(BedWars::getInstance()->prefix . "§eType: /newbedwars enableArena " . $arena->data["world"]);
    }
}