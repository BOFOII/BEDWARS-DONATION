<?php

namespace bofoiii\bedwars\commands;

use CortexPE\Commando\BaseCommand;
use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\commands\subcmds\DeleteArena;
use bofoiii\bedwars\commands\subcmds\DisableArena;
use bofoiii\bedwars\commands\subcmds\EnableArena;
use bofoiii\bedwars\commands\subcmds\Help;
use bofoiii\bedwars\commands\subcmds\Join;
use bofoiii\bedwars\commands\subcmds\Test;
use bofoiii\bedwars\commands\subcmds\setup\AddGenerator;
use bofoiii\bedwars\commands\subcmds\setup\AutoCreateTeams;
use bofoiii\bedwars\commands\subcmds\setup\CreateTeam;
use bofoiii\bedwars\commands\subcmds\setup\DragonPos;
use bofoiii\bedwars\commands\subcmds\setup\RemoveTeam;
use bofoiii\bedwars\commands\subcmds\setup\Save;
use bofoiii\bedwars\commands\subcmds\setup\SetBed;
use bofoiii\bedwars\commands\subcmds\setup\SetShop;
use bofoiii\bedwars\commands\subcmds\setup\SetSpawn;
use bofoiii\bedwars\commands\subcmds\setup\SetType;
use bofoiii\bedwars\commands\subcmds\setup\SetUpgrade;
use bofoiii\bedwars\commands\subcmds\setup\SetWaitingSpawn;
use bofoiii\bedwars\commands\subcmds\SetupArena;
use bofoiii\bedwars\commands\subcmds\test\SuddenDeath;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class MainCommand extends BaseCommand
{

    public function getPermission()
    {
        return "newbedwars";
    }

    /**
     * @return void
     */
    protected function prepare(): void
    {
        //Normal Command
        $this->registerSubCommand(new Help("help", "Help Command"));
        $this->registerSubCommand(new Join("join", "Join arena command"));
        
        $this->registerSubCommand(new SetupArena("setupArena", "Setup arena command", ["setuparena"]));
        $this->registerSubCommand(new EnableArena("enableArena", "Enable arena command", ["enablearena"]));
        $this->registerSubCommand(new DisableArena("disableArena", "Disable arena command", ["disablearena"]));
        $this->registerSubCommand(new DeleteArena("deleteArena", "Delete arena command", ["deletearena"]));

        //Setup Command
        $this->registerSubCommand(new SetWaitingSpawn("setWaitingSpawn", "Set waiting arena command", ["setwaitingspawn"]));
        //$this->registerSubCommand(new DragonPos("dragonPos", "Set Dragon pos arena command", ["dragonpos"]));
        $this->registerSubCommand(new AutoCreateTeams("autoCreateTeams", "Auto create teams arena command", ["autocreateteams"]));
        $this->registerSubCommand(new CreateTeam("createTeam", "Create team arena command", ["createteam"]));
        $this->registerSubCommand(new RemoveTeam("removeTeam", "Remove Team arena command", ["removeteam"]));
        $this->registerSubCommand(new SetSpawn("setSpawn", "Set spawn arena command", ["setspawn"]));
        $this->registerSubCommand(new SetBed("setBed", "Set bed arena command", ["setbed"]));
        $this->registerSubCommand(new SetShop("setShop", "Set shop npc arena command", ["setshop"]));
        $this->registerSubCommand(new SetUpgrade("setUpgrade", "Set upgrade npc arena command", ["setupgrade"]));
        $this->registerSubCommand(new SetType("setType", "Set type arena command", ["settype"]));
        $this->registerSubCommand(new AddGenerator("addGenerator", "Add generator arena command", ["addgenerator"]));
        $this->registerSubCommand(new Save("save", "Save arena command"));

        // test command
        $this->registerSubCommand(new SuddenDeath("sudden", "Move to sudden death arena command"));
        $this->registerSubCommand(new Test("test", "Test arena command"));
        $this->setPermission($this->getPermission());
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
        BedWars::getInstance()->getServer()->dispatchCommand($sender, "newbedwars help");
    }
}
