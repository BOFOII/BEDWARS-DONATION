<?php

namespace bofoiii\bedwars\commands\subcmds\setup;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\utils\Utils;
use pocketmine\block\BlockLegacyIds;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class DragonPos extends BaseSubCommand
{

    /**
     * @return void
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new RawStringArgument("pos", true));
        $this->setPermission("newbedwars.cmd.dragonpos");
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

        if (!isset($args["pos"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§cUsage: /newbedwars dragonPos 1 or 2");
            return;
        }

        $arena = BedWars::getInstance()->setters[$sender->getName()];

        if (!(is_null($arena->data["dragonPos1"]) || is_null($arena->data["dragonPos2"]))) {
            BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
            $sender->sendMessage(BedWars::getInstance()->prefix . "§eSet teams spawn if you didn't!");
            return;
        }

        switch(strtolower($args["pos"])) {
            case "1":
                $arena->data["dragonPos1"] = Utils::vectorToString(new Vector3($sender->getPosition()->ceil()->getX(), $sender->getPosition()->ceil()->getY(), $sender->getPosition()->ceil()->getZ()));
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aPos " . $args["pos"] . " set!");
                Utils::addSound($sender, "mob.villager.yes");
                break;
            case "2":
                if (is_null($arena->data["dragonPos1"])) {
                    $sender->sendMessage(BedWars::getInstance()->prefix . "§cSet dragonPos 1 first!");
                    return;
                }
                $firstPos = Utils::stringToVector(":" , $arena->data["dragonPos1"]);
                $secondPos = $sender->getPosition()->ceil();

                $GLOBALS["blocks"] = [];

                $sender->sendMessage(BedWars::getInstance()->prefix . "§aImporting blocks. This may cause lag.");
                for($x = min($firstPos->getX(), $secondPos->getX()); $x <= max($firstPos->getX(), $secondPos->getX()); $x++) {
                    for($y = min($firstPos->getY(), $secondPos->getY()); $y <= max($firstPos->getY(), $secondPos->getY()); $y++) {
                        for($z = min($firstPos->getZ(), $secondPos->getZ()); $z <= max($firstPos->getZ(), $secondPos->getZ()); $z++) {
                            if($sender->getWorld()->getBlockAt($x, $y, $z)->getId() !== BlockLegacyIds::AIR) {
                                $GLOBALS["blocks"]["$x:$y:$z"] = Utils::vectorToString(new Vector3($x, $y, $z));
                            }
                        }
                    }
                }

                $arena->data["dragonPos2"] = Utils::vectorToString(new Vector3($sender->getPosition()->ceil()->getX(), $sender->getPosition()->ceil()->getY(), $sender->getPosition()->ceil()->getZ()));
                $arena->data["blocks"] = $GLOBALS["blocks"];
                $sender->sendMessage(BedWars::getInstance()->prefix . "§aPos " . $args["pos"] . " set!");
                Utils::addSound($sender, "mob.villager.yes");
                break;
        }

        if (is_null($arena->data["dragonPos1"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aSet the remaining position:");
            $sender->sendMessage("§eType: /newbedwars dragonPos 1");
            return;
        } else if (is_null($arena->data["dragonPos2"])) {
            $sender->sendMessage(BedWars::getInstance()->prefix . "§aSet the remaining position:");
            $sender->sendMessage("§eType: /newbedwars dragonPos 2");
            return;
        }
        BedWars::getInstance()->getServer()->getCommandMap()->dispatch($sender, "newbedwars help");
    }
}