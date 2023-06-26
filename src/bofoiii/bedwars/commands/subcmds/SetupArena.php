<?php

namespace bofoiii\bedwars\commands\subcmds;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\game\Game;
use pocketmine\command\CommandSender;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\Limits;

class SetupArena extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("arena", false));
        $this->setPermission("newbedwars.cmd.setup");
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

        if (isset(BedWars::getInstance()->setters[$sender->getName()])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cYou are already in setup mode!");
            return;
        }

        if (!($args["arena"] == strtolower($args["arena"]))) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§c" . $args["arena"] . " mustn't contain capital letters! Rename your folder to: §a" . strtolower($args["arena"]));
            return;
        }

        if (strpos($args["arena"], "+")) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§c" . $args["arena"] . " mustn't contain this symbol: +");
            return;
        }

        if (!BedWars::getInstance()->getServer()->getWorldManager()->isWorldGenerated($args["arena"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§a" . $args["arena"] . " doesn't exist!");
            return;
        }

        if (!isset(BedWars::getInstance()->arenas[$args["arena"]])) {
            BedWars::getInstance()->arenas[$args["arena"]] = new Game([]);
        } else {
            BedWars::getInstance()->arenas[$args["arena"]]->data["enabled"] = false;
        }
        BedWars::getInstance()->setters[$sender->getName()] = BedWars::getInstance()->arenas[$args["arena"]];
        $sender->getInventory()->clearAll();
        if (!BedWars::getInstance()->getServer()->getWorldManager()->isWorldLoaded($args["arena"])) {
            BedWars::getInstance()->getServer()->getWorldManager()->loadWorld($args["arena"]);
        }
        BedWars::getInstance()->arenas[$args["arena"]]->data["world"] = $args["arena"];
        BedWars::getInstance()->arenas[$args["arena"]]->data["display-name"] = ucfirst($args["arena"]);
        $sender->teleport(BedWars::getInstance()->getServer()->getWorldManager()->getWorldByName($args["arena"])->getSpawnLocation());
        $sender->setGamemode(GameMode::CREATIVE());
        $sender->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), Limits::INT32_MAX, 2));
        $sender->sendMessage("\n\n");

        for ($i = 0; $i < 10; $i++) {
            $sender->sendMessage(" ");
        }

        $sender->sendMessage(BedWars::getInstance()->prefix . "§aYou were teleported to the §e" . $args["arena"] . "§a's spawn.");
        $sender->sendMessage(" ");
        if (!isset(BedWars::getInstance()->arenas[$args["arena"]])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§eHello " . $sender->getName() . "!");
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aPlease set the waiting spawn.");
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aIt is the place where players will wait the game to start.");
            $sender->sendMessage(BedWars::getInstance()->prefix . "§eType: /newbedwars setWaitingSpawn");
        } else {
            BedWars::getInstance()->getServer()->dispatchCommand($sender,"newbedwars help");
        }
    }
}