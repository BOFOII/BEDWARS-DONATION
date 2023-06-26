<?php

namespace bofoiii\bedwars\provider;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use bofoiii\bedwars\game\Game;
use bofoiii\bedwars\BedWars;
use pocketmine\world\World;

class YamlProvider {

    /** @var BedWars $plugin */
    private BedWars $plugin;

    /**
     * @param BedWars $plugin
     */
    public function __construct(BedWars $plugin) {
        $this->plugin = $plugin;
        $this->init();
        $this->loadArenas();
    }

    public function init(): void
    {
        if (!is_dir($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if (!is_dir($this->getDataFolder() . "Arenas")) {
            @mkdir($this->getDataFolder() . "Arenas");
        }
        if (!is_dir($this->getDataFolder() . "Cache")) {
            @mkdir($this->getDataFolder() . "Cache");
        }
        if (!is_dir($this->getDataFolder() . "Skin")) {
            @mkdir($this->getDataFolder() . "Skin");
        }
        if (!is_file($this->getDataFolder() . "Skin/diamond.png")) {
            BedWars::getInstance()->saveResource("Skin/diamond.png");
        }
        if (!is_file($this->getDataFolder() . "Skin/emerald.png")) {
            BedWars::getInstance()->saveResource("Skin/emerald.png");
        }
        if (!is_file($this->getDataFolder() . "Skin/invisible.png")) {
            BedWars::getInstance()->saveResource("Skin/invisible.png");
        }
    }

    public function loadArenas(): void
    {
        foreach (glob($this->getDataFolder() . "Arenas" . DIRECTORY_SEPARATOR . "*.yml") as $arenaFile) {
            $config = new Config($arenaFile, Config::YAML);
            $name = basename($arenaFile, ".yml");
            $this->plugin->arenas[$name] = new Game($config->getAll());
        }
    }

    public function saveArenas(): void
    {
        foreach ($this->plugin->arenas as $fileName => $arena) {
            if ($arena->world instanceof World) {
                foreach ($arena->players as $player) {
                    if ($player instanceof Player) {
                        $player->teleport($player->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
                    }
                }

                $arena->mapReset->loadMap($arena->world->getFolderName(), true);
            }
            $config = new Config($this->getDataFolder() . "Arenas" . DIRECTORY_SEPARATOR . $fileName . ".yml", Config::YAML);
            $config->setAll($arena->data);
            $config->save();
        }
    }

    /**
     * @return string $dataFolder
     */
    private function getDataFolder(): string {
        return $this->plugin->getDataFolder();
    }
}