<?php

namespace bofoiii\bedwars\game;

use pocketmine\world\World;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class MapReset {

    /** @var Game $plugin */
    public Game $game;

    /**
     * @param Game $game
     */
    public function __construct(Game $game) {
        $this->game = $game;
    }

    /**
     * @param World $world
     */
    public function saveMap(World $world) {
        $world->save(true);

        $levelPath = $this->game->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $world->getFolderName();
        $zipPath = $this->game->plugin->getDataFolder() . "Cache" . DIRECTORY_SEPARATOR . $world->getFolderName() . ".zip";

        $zip = new ZipArchive();

        if(is_file($zipPath)) {
            unlink($zipPath);
        }

        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($levelPath)), RecursiveIteratorIterator::LEAVES_ONLY);

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if($file->isFile()) {
                $filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename();
                $localPath = substr($filePath, strlen($levelPath . DIRECTORY_SEPARATOR));
                $zip->addFile($filePath, $localPath);
            }
        }

        if (file_exists($this->game->plugin->getDataFolder() . "Cache" . DIRECTORY_SEPARATOR . $world->getFolderName() . ".zip")) $zip->close();
    }


    /**
     * @param string $folderName
     * @param bool $justSave
     *
     * @return World|null
     */
    public function loadMap(string $folderName, bool $justSave = false): ?World {
        if(!$this->game->plugin->getServer()->getWorldManager()->isWorldGenerated($folderName)) {
            return null;
        }

        if($this->game->plugin->getServer()->getWorldManager()->isWorldLoaded($folderName)) {
            $this->game->plugin->getServer()->getWorldManager()->unloadWorld($this->game->plugin->getServer()->getWorldManager()->getWorldByName($folderName));
        }

        $zipPath = $this->game->plugin->getDataFolder() . "Cache" . DIRECTORY_SEPARATOR . $folderName . ".zip";

        if(!file_exists($zipPath)) {
            $this->game->plugin->getServer()->getLogger()->error("Could not reload map ($folderName). File wasn't found, try save world in setup mode.");
            return null;
        }

        $zipArchive = new ZipArchive();
        $zipArchive->open($zipPath);
        $zipArchive->extractTo($this->game->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $folderName);
        $zipArchive->close();

        if($justSave) {
            return null;
        }

        $this->game->plugin->getServer()->getWorldManager()->loadWorld($folderName, true);
        return $this->game->plugin->getServer()->getWorldManager()->getWorldByName($folderName);
    }
}