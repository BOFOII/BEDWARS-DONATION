<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class SetType extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("max", true));
        $this->setPermission("newbedwars.cmd.settype");
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

        if (!isset($args["max"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable type: ");
            $sender->sendMessage("§7• §eSolo");
            $sender->sendMessage("§7• §eDoubles");
            $sender->sendMessage("§7• §e3v3v3v3");
            $sender->sendMessage("§7• §e4v4v4v4");
            return;
        }

        $arena = BedWars::getInstance()->setters[$sender->getName()];

        switch(strtolower($args["max"])) {
            case "solo":
                $arena->data["maxInTeam"] = 1;
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aArena group changed to: §d" . $args["max"]);
                Utils::addSound($sender, "mob.villager.yes");
                break;
            case "doubles":
                $arena->data["maxInTeam"] = 2;
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aArena group changed to: §d" . $args["max"]);
                Utils::addSound($sender, "mob.villager.yes");
                break;
            case "3v3v3v3":
                $arena->data["maxInTeam"] = 3;
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aArena group changed to: §d" . $args["max"]);
                Utils::addSound($sender, "mob.villager.yes");
                break;
            case "4v4v4v4":
                $arena->data["maxInTeam"] = 4;
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aArena group changed to: §d" . $args["max"]);
                Utils::addSound($sender, "mob.villager.yes");
                break;
            default:
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable type: ");
                $sender->sendMessage("§7• §eSolo");
                $sender->sendMessage("§7• §eDoubles");
                $sender->sendMessage("§7• §e3v3v3v3");
                $sender->sendMessage("§7• §e4v4v4v4");
                Utils::addSound($sender, "mob.villager.no");
                break;
        }
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}