<?php

namespace bofoiii\bedwars\utils;

use bofoiii\bedwars\BedWars;
use bofoiii\bedwars\task\IMGBBAsyncTask;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\entity\Skin;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\world\World;

class Utils
{
    public const BLACK = "§0";
    public const DARK_BLUE = "§1";
    public const DARK_GREEN = "§2";
    public const DARK_AQUA = "§3";
    public const DARK_RED = "§4";
    public const DARK_PURPLE = "§5";
    public const GOLD = "§6";
    public const GRAY = "§7";
    public const DARK_GRAY = "§8";
    public const BLUE = "§9";
    public const GREEN = "§a";
    public const AQUA = "§b";
    public const RED = "§c";
    public const PINK = "§d";
    public const YELLOW = "§e";
    public const WHITE = "§f";

    public const TeamColor = [
        "BLACK" => self::BLACK,
        "DARK_BLUE" => self::DARK_BLUE,
        "DARK_GREEN" => self::DARK_GREEN,
        "DARK_AQUA" => self::DARK_AQUA,
        "DARK_RED" => self::DARK_RED,
        "DARK_PURPLE" => self::DARK_PURPLE,
        "GOLD" => self::GOLD,
        "GRAY" => self::GRAY,
        "DARK_GRAY" => self::DARK_GRAY,
        "BLUE" => self::BLUE,
        "GREEN" => self::GREEN,
        "AQUA" => self::AQUA,
        "RED" => self::RED,
        "PINK" => self::PINK,
        "YELLOW" => self::YELLOW,
        "WHITE" => self::WHITE,
    ];

    public static $woolState = [];

    public const ACCEPTED_SKIN_SIZES = [
        64 * 32 * 4,
        64 * 64 * 4,
        128 * 128 * 4
    ];

    /**
     * @param string $tColor
     * @return string
     */
    public static function getChatColor(string $tColor): string
    {
        $teamColor = str_replace(" ", "_", strtoupper($tColor));
        if (isset(self::TeamColor[$teamColor])) {
            return self::TeamColor[$teamColor];
        }

        if ($teamColor == "CYAN") {
            return self::DARK_AQUA;
        }

        if ($teamColor == "LIGHT_GRAY") {
            return self::DARK_GRAY;
        }

        if ($teamColor == "ORANGE") {
            return self::GOLD;
        }

        return "§7";
    }

    /**
     * @param string $tColor
     * @return int
     */
    public static function getWoolMeta(string $tColor): int
    {
        return VanillaBlocks::WOOL()->setColor(self::getDyeColor($tColor))->asItem()->getStateId();
    }

    /**
     * @param int $meta
     *
     * @return string
     */
    public static function getTeamColor(int $meta): string
    {
        if ($meta == 0) {
            return "White";
        }

        if ($meta == 1) {
            return "Orange";
        }

        if ($meta == 2) {
            return "Magenta";
        }

        if ($meta == 3) {
            return "Aqua";
        }

        if ($meta == 4) {
            return "Yellow";
        }

        if ($meta == 5) {
            return "Green";
        }

        if ($meta == 6) {
            return "Pink";
        }

        if ($meta == 7) {
            return "Gray";
        }

        if ($meta == 8) {
            return "Light Gray";
        }

        if ($meta == 9) {
            return "Cyan";
        }

        if ($meta == 10) {
            return "Purple";
        }

        if ($meta == 11) {
            return "Blue";
        }

        if ($meta == 12) {
            return "Brown";
        }

        if ($meta == 14) {
            return "Red";
        }

        if ($meta == 15) {
            return "Black";
        }
        return "White";
    }

    public static function getDyeColor(string $tColor): DyeColor
    {
        if ($tColor == "White") {
            return DyeColor::WHITE();
        }

        if ($tColor == "Orange") {
            return DyeColor::ORANGE();
        }

        if ($tColor == "Magenta") {
            return DyeColor::MAGENTA();
        }

        if ($tColor == "Aqua") {
            return DyeColor::LIGHT_BLUE();
        }

        if ($tColor == "Yellow") {
            return DyeColor::YELLOW();
        }

        if ($tColor == "Green") {
            return DyeColor::GREEN();
        }

        if ($tColor == "Pink") {
            return DyeColor::PINK();
        }

        if ($tColor == "Gray") {
            return DyeColor::GRAY();
        }

        if ($tColor == "Light Gray") {
            return DyeColor::LIGHT_GRAY();
        }

        if ($tColor == "Cyan") {
            return DyeColor::CYAN();
        }

        if ($tColor == "Purple") {
            return DyeColor::PURPLE();
        }

        if ($tColor == "Blue") {
            return DyeColor::BLUE();
        }

        if ($tColor == "Brown") {
            return DyeColor::BROWN();
        }

        if ($tColor == "Red") {
            return DyeColor::RED();
        }

        if ($tColor == "Black") {
            return DyeColor::BLACK();
        }
        return DyeColor::WHITE();
    }

    /**
     * @param string $tColor
     * @return Color
     */
    public static function getColor(string $tColor): Color
    {
        if ($tColor == "White") {
            return Color::fromRGB(0xFFFFFF);
        }

        if ($tColor == "Orange") {
            return Color::fromRGB(0xFFA500);
        }

        if ($tColor == "Magenta") {
            return Color::fromRGB(0xFF00FF);
        }

        if ($tColor == "Aqua") {
            return Color::fromRGB(0x00FFFF);
        }

        if ($tColor == "Yellow") {
            return Color::fromRGB(0xFFFF00);
        }

        if ($tColor == "Green") {
            return Color::fromRGB(0x00FF00);
        }

        if ($tColor == "Pink") {
            return Color::fromRGB(0xFF00FF);
        }

        if ($tColor == "Gray") {
            return Color::fromRGB(0x808080);
        }

        if ($tColor == "Light Gray") {
            return Color::fromRGB(0xC0C0C0);
        }

        if ($tColor == "Cyan") {
            return Color::fromRGB(0x000080);
        }

        if ($tColor == "Purple") {
            return Color::fromRGB(0x800080);
        }

        if ($tColor == "Blue") {
            return Color::fromRGB(0x0000FF);
        }

        if ($tColor == "Brown") {
            return Color::fromRGB(0xA52A2A);
        }

        if ($tColor == "Red") {
            return Color::fromRGB(0xFF0000);
        }

        if ($tColor == "Black") {
            return Color::fromRGB(0x000000);
        }
        return Color::fromRGB(0xFFFFFF);
    }

    /**
     * @param string $path
     * @return Skin
     */
    public static function getSkinFromFile(string $path): Skin
    {
        $img = imagecreatefrompng($path);
        $bytes = '';
        $l = (int) getimagesize($path)[1];
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $r = ($rgba >> 16) & 0xff;
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        imagedestroy($img);
        return new Skin("Standard_Custom", $bytes);
    }

    public static function playerToFaceSkin(Player $player)
    {
        $skin = $player->getSkin()->getSkinData();
        $height = 64;
        $width = 64;
        $head = 8;
        $helm = 40;
        switch (strlen($skin)) {
            case 64 * 32 * 4:
                $height = 32;
                $width = 64;
                $head = 4;
                $helm = 20;
                break;
            case 64 * 64 * 4:
                $height = 64;
                $width = 64;
                $head = 8;
                $helm = 40;
                break;
            case 128 * 128 * 4:
                $height = 128;
                $width = 128;
                $head = 16;
                $helm = 80;
                break;
        }

        $img = imagecreatetruecolor(128, 128);
        $skin_img = imagecreatetruecolor($width, $height);
        $head_img = imagecreatetruecolor($head, $head);

        /*
         * Skin Data To Skin
         */
        $skinPos = 0;
        imagefill($skin_img, 0, 0, imagecolorallocatealpha($skin_img, 0, 0, 0, 127));
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $r = ord($skin[$skinPos]);
                $skinPos++;
                $g = ord($skin[$skinPos]);
                $skinPos++;
                $b = ord($skin[$skinPos]);
                $skinPos++;
                $a = 127 - intdiv(ord($skin[$skinPos]), 2);
                $skinPos++;
                $col = imagecolorallocatealpha($skin_img, $r, $g, $b, $a);
                imagesetpixel($skin_img, $x, $y, $col);
            }
        }
        imagesavealpha($skin_img, true);

        /*
         * Skin To Face Skin
         */
        imagecopymerge($head_img, $skin_img, 0, 0, $head, $head, $head, $head, 100);
        for ($x = 0; $x < $head; $x++) {
            for ($y = 0; $y < $head; $y++) {
                $rgba = imagecolorat($skin_img, $x + $helm, $y + $head);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if (!$alpha >= 127) {
                    imagecopymerge($head_img, $skin_img, $x, $y, $x + $helm, $y + $head, 1, 1, 100);
                }
            }
        }
        imagecopyresized($img, $head_img, 0, 0, 0, 0, 128, 128, $head, $head);
        imagepng($img, BedWars::getInstance()->getDataFolder() . $player->getName() . ".png");
        BedWars::getInstance()->getServer()->getAsyncPool()->submitTask(new IMGBBAsyncTask(BedWars::getInstance()->getDataFolder() . $player->getName() . ".png", $player->getName()));
        BedWars::getInstance()->getServer()->getAsyncPool()->shutdown();
        imagedestroy($head_img);
        imagedestroy($skin_img);
        imagedestroy($img);
        return $img;
    }

    /**
     * @param int $time
     * @return string
     */
    public static function calculateTime(int $time): string
    {
        return gmdate("i:s", $time);
    }

    /**
     * @param $num
     * @return string
     */
    public static function intToRoman($num): string
    {
        $n = intval($num);
        $res = '';

        $roman_numerals = [
            'M'  => 1000,
            'CM' => 900,
            'D'  => 500,
            'CD' => 400,
            'C'  => 100,
            'XC' => 90,
            'L'  => 50,
            'XL' => 40,
            'X'  => 10,
            'IX' => 9,
            'V'  => 5,
            'IV' => 4,
            'I'  => 1
        ];

        foreach ($roman_numerals as $roman => $number) {
            $matches = intval($n / $number);
            $res .= str_repeat($roman, $matches);
            $n = $n % $number;
        }
        return $res;
    }

    /**
     * @param World $level
     * @param AxisAlignedBB $bb
     * @return array
     */
    public static function getCollisionBlocks(World $level, AxisAlignedBB $bb): array
    {
        $minX = (int) floor($bb->minX - 1);
        $minY = (int) floor($bb->minY - 1);
        $minZ = (int) floor($bb->minZ - 1);
        $maxX = (int) floor($bb->maxX + 1);
        $maxY = (int) floor($bb->maxY + 1);
        $maxZ = (int) floor($bb->maxZ + 1);

        $collides = [];

        for ($z = $minZ; $z <= $maxZ; ++$z) {
            for ($x = $minX; $x <= $maxX; ++$x) {
                for ($y = $minY; $y <= $maxY; ++$y) {
                    $block = $level->getBlockAt($x, $y, $z);
                    if ($bb->isVectorInside($block->getPosition())) {
                        $collides[] = $block;
                    }
                }
            }
        }

        return $collides;
    }

    /**
     * @param int $max
     * @return string
     */
    public static function maxInTeamToGroup(int $max): string
    {
        if ($max == 1) {
            return "Solo";
        } else if ($max == 2) {
            return "Doubles";
        } else if ($max == 3) {
            return "3v3v3v3";
        } else if ($max == 4) {
            return "4v4v4v4";
        }
        return "Custom";
    }

    /**
     * @param string $wool
     * @return string
     */
    public static function enName(string $wool): string
    {
        if ($wool == "Lime") {
            return "Green";
        } else if ($wool == "Light Blue") {
            return "Aqua";
        }
        return $wool;
    }

    /**
     * @param Player $player
     * @return mixed|string
     */
    public static function getNearestTeam(Player $player)
    {
        $GLOBALS["foundTeam"] = "";
        if (isset(BedWars::getInstance()->setters[$player->getName()])) {
            $arena = BedWars::getInstance()->setters[$player->getName()];
            if (!count($arena->data["teamName"]) >= 1) {
                return $GLOBALS["foundTeam"];
            }
            $GLOBALS["distance"] = 100;
            foreach ($arena->data["teamName"] as $team) {
                if (!isset($arena->data["teamSpawn"][$team])) continue;
                $GLOBALS["dis"] = $player->getPosition()->asVector3()->distance(self::stringToVector(":", $arena->data["teamSpawn"][$team]));
                if ($GLOBALS["dis"] <= 17) {
                    if ($GLOBALS["dis"] < $GLOBALS["distance"]) {
                        $GLOBALS["distance"] = $GLOBALS["dis"];
                        $GLOBALS["foundTeam"] = $team;
                    }
                }
            }
        }
        return $GLOBALS["foundTeam"];
    }


    /**
     * @param Vector3 $vector
     * @return string
     */
    public static function vectorToString(Vector3 $vector): string
    {
        return $vector->getX() . ":" . $vector->getY() . ":" . $vector->getZ();
    }

    /**
     * @param string $delimeter
     * @param string|null $string
     * @return Vector3|null
     */
    public static function stringToVector(string $delimeter, ?string $string): ?Vector3
    {
        if ($string !== null) {
            $split = explode($delimeter, $string);
            return new Vector3($split[0], $split[1], $split[2]);
        }
        return null;
    }

    /**
     * @param Player $player
     * @param string $soundName
     * @return void
     */
    public static function addSound(Player $player, string $soundName)
    {
        $pk = new PlaySoundPacket();
        $pk->x = $player->getPosition()->getX();
        $pk->y = $player->getPosition()->getY();
        $pk->z = $player->getPosition()->getZ();
        $pk->volume = 100;
        $pk->pitch = 1;
        $pk->soundName = $soundName;
        $player->getNetworkSession()->sendDataPacket($pk);
    }
}
