<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\block\Wool;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemBlock;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class AutoCreateTeams extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->setPermission("newbedwars.cmd.autocreateteams");
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

        $found = [];
        if (!isset($arena->data["teams"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aSearching for teams");
            foreach($sender->getInventory()->getContents() as $item) {
                if(!$item instanceof ItemBlock) {
                    continue;
                }
                $block =  $item->getBlock();
                if(!$block instanceof Wool) {
                    continue;
                }
                
                $found[] = $block->getColor()->getDisplayName();
            }

            if(empty($found)) {
                $sender->sendMessage(BedWars::getInstance()->prefix . "§cNo new teams were found.");
                return;
            }

            $sender->sendMessage(BedWars::getInstance()->prefix . "§a§lNEW TEAMS FOUND:");

            foreach($found as $tf) {
                if (count($arena->data["teamName"]) >= 1) {
                    if (!in_array(Utils::enName($tf), $arena->data["teamName"])) {
                        $sender->sendMessage("§7§l• " . Utils::getChatColor(Utils::enName($tf)) . Utils::enName($tf));
                        $arena->data["teamName"][] = Utils::enName($tf);
                        $arena->data["teamColor"][Utils::enName($tf)] = Utils::getChatColor(Utils::enName($tf));
                    }
                } else {
                    $sender->sendMessage("§8§l• " . Utils::getChatColor(Utils::enName($tf)) . Utils::enName($tf));
                    $arena->data["teamName"][] = Utils::enName($tf);
                    $arena->data["teamColor"][Utils::enName($tf)] = Utils::getChatColor(Utils::enName($tf));
                }

                BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
            }
        }
    }
}