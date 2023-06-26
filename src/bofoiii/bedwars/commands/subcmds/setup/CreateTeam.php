<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

class CreateTeam extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("team", true));
        $this->registerArgument(1, new RawStringArgument("color", true));
        $this->setPermission("newbedwars.cmd.createteam");
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


        if (!isset($args["team"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cUsage: /newbedwars createTeam <name> <color>");
            return;
        } else if (!isset($args["color"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable colors: ");
            $sender->sendMessage("§l" . Utils::BLACK . "BLACK" . "§r§7, §l" . Utils::DARK_BLUE . "DARK_BLUE" . "§r§7, §l" . Utils::DARK_GREEN . "DARK_GREEN" . "§r§7, §l" . Utils::DARK_AQUA . "DARK_AQUA" . "§r§7, §l" . Utils::DARK_RED . "DARK_RED" . "§r§7, §l" . Utils::DARK_PURPLE . "DARK_PURPLE" . "§r§7, §l" . Utils::DARK_GRAY . "DARK_GRAY" . "§r§7, §l" . Utils::GOLD . "GOLD" . "§r§7, §l" . Utils::GRAY . "GRAY" . "§r§7, §l" . Utils::BLUE . "BLUE" . "§r§7, §l" . Utils::GREEN . "GREEN" . "§r§7, §l" . Utils::AQUA . "AQUA" . "§r§7, §l" . Utils::RED . "RED" . "§r§7, §l" . Utils::PINK . "PINK" . "§r§7, §l" . Utils::YELLOW . "YELLOW" . "§r§7, §l" . Utils::WHITE . "WHITE");
            return;
        }
        $arena = BedWars::getInstance()->setters[$sender->getName()];

        if (!isset(Utils::TeamColor[strtoupper($args["color"])])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cInvalid color!");
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aAvailable colors: ");
            $sender->sendMessage("§l" . Utils::BLACK . "BLACK" . "§r§7, §l" . Utils::DARK_BLUE . "DARK_BLUE" . "§r§7, §l" . Utils::DARK_GREEN . "DARK_GREEN" . "§r§7, §l" . Utils::DARK_AQUA . "DARK_AQUA" . "§r§7, §l" . Utils::DARK_RED . "DARK_RED" . "§r§7, §l" . Utils::DARK_PURPLE . "DARK_PURPLE" . "§r§7, §l" . Utils::DARK_GRAY . "DARK_GRAY" . "§r§7, §l" . Utils::GOLD . "GOLD" . "§r§7, §l" . Utils::GRAY . "GRAY" . "§r§7, §l" . Utils::BLUE . "BLUE" . "§r§7, §l" . Utils::GREEN . "GREEN" . "§r§7, §l" . Utils::AQUA . "AQUA" . "§r§7, §l" . Utils::RED . "RED" . "§r§7, §l" . Utils::PINK . "PINK" . "§r§7, §l" . Utils::YELLOW . "YELLOW" . "§r§7, §l" . Utils::WHITE . "WHITE");
            return;
        }

        if (in_array($args["team"], $arena->data["teamName"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§c" . $args["team"] . " team already exists!");
            return;
        }
        $arena->data["teamName"][] = $args["team"];
        $arena->data["teamColor"][Utils::enName($args["team"])] = Utils::getChatColor(Utils::enName($args["color"]));
        $sender->sendMessage(BedWars::getInstance()->prefix . "§a" . $args["team"] . " created!");
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}
