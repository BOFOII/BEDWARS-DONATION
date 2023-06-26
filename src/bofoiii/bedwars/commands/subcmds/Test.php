<?php



namespace bofoiii\bedwars\commands\subcmds;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;

class Test extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("arena", false));
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
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cUsage: /newbedwars test <worldName>");
        }
        if (!isset(BedWars::getInstance()->arenas[$args["arena"]])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cArena " . $args["arena"] . " was not found!");
            return;
        }
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            BedWars::getInstance()->arenas[$args["arena"]]->joinToArena($player);
        }
    }
}