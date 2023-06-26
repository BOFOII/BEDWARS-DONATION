<?php

namespace bofoiii\bedwars;

use CortexPE\Commando\PacketHooker;
use muqsit\invmenu\InvMenuHandler;
use muqsit\simplepackethandler\SimplePacketHandler;
use bofoiii\bedwars\commands\MainCommand;
use bofoiii\bedwars\entity\Generator;
use bofoiii\bedwars\entity\ShopVillager;
use bofoiii\bedwars\entity\CustomTNT;
use bofoiii\bedwars\entity\Egg;
use bofoiii\bedwars\entity\EnderDragon;
use bofoiii\bedwars\entity\FireBall;
use bofoiii\bedwars\entity\IronGolem;
use bofoiii\bedwars\entity\SilverFish;
use bofoiii\bedwars\entity\UpgradeVillager;
use bofoiii\bedwars\game\Game;
use bofoiii\bedwars\item\ItemFactory;
use bofoiii\bedwars\libs\scoreboard\ScoreAPI;
use bofoiii\bedwars\provider\YamlProvider;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\event\Listener;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\World;

class BedWars extends PluginBase implements Listener
{

    /** @var string $prefix */
    public string $prefix = "§o§l§bNew§cBed§fWars§r§7 > ";

    /** @var BedWars $instance */
    protected static BedWars $instance;

    /** @var ScoreAPI $score */
    protected static ScoreAPI $score;

    /** @var Game[] $arenas */
    public array $arenas = [];

    /** @var Game[] $setters */
    public array $setters = [];

    /** @var YamlProvider $dataProvider */
    public YamlProvider $dataProvider;

    public function onEnable(): void
    {
        if (!PacketHooker::isRegistered()) {
            PacketHooker::register($this);
        }
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
        self::$score = new ScoreAPI($this);
        $this->registerEntity();
        $this->registerItems();
        $this->fixGUI();
        $this->dataProvider = new YamlProvider($this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register("newbedwars", new MainCommand($this, "newbedwars", "NewBedWars Commands", ["nbw", "bedwars", "bw"]));
        $this->dataProvider->loadArenas();
    }

    public function onLoad(): void
    {
        self::$instance = $this;
    }

    public function onDisable(): void
    {
        $this->dataProvider->saveArenas();
    }

    public static function getInstance(): BedWars
    {
        return self::$instance;
    }

    public static function getScore(): ScoreAPI
    {
        return self::$score;
    }

    public function registerEntity(): void
    {
        EntityFactory::getInstance()->register(Generator::class, function (World $world, CompoundTag $nbt): Human {
            return new Generator(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ["Generator"]);
        EntityFactory::getInstance()->register(ShopVillager::class, function (World $world, CompoundTag $nbt): ShopVillager {
            return new ShopVillager(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["ShopVillager"]);
        EntityFactory::getInstance()->register(UpgradeVillager::class, function (World $world, CompoundTag $nbt): UpgradeVillager {
            return new UpgradeVillager(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["UpgradeVillager"]);
        EntityFactory::getInstance()->register(CustomTNT::class, function (World $world, CompoundTag $nbt): CustomTNT {
            return new CustomTNT(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["TNTBW"]);
        EntityFactory::getInstance()->register(FireBall::class, function (World $world, CompoundTag $nbt): FireBall {
            return new FireBall(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Fireball"]);
        EntityFactory::getInstance()->register(Egg::class, function (World $world, CompoundTag $nbt): Egg {
            return new Egg(EntityDataHelper::parseLocation($nbt, $world), $nbt, null);
        }, ["Egg Bridge"]);
        EntityFactory::getInstance()->register(EnderDragon::class, function(World $world, CompoundTag $nbt) : EnderDragon{
            return new EnderDragon(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["DragonNBW"]);
        EntityFactory::getInstance()->register(SilverFish::class, function(World $world, CompoundTag $nbt) : SilverFish {
            return new SilverFish(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['BedBug']);
        EntityFactory::getInstance()->register(IronGolem::class, function(World $world, CompoundTag $nbt) : IronGolem {
            return new IronGolem(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['DreamDefender']);

    }

    private function registerItems(): void
    {
        ItemFactory::registerItems();
        $this->getServer()->getAsyncPool()->addWorkerStartHook(function (int $worker): void {
            $this->getServer()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask
            {
                public function onRun(): void
                {
                    ItemFactory::registerItems();
                }
            }, $worker);
        });
    }

    /*
     * Credit: Muqsit (https://github.com/Muqsit/InvCrashFix/blob/api-4.0/src/muqsit/invcrashfix/Loader.php#L14)
     */
    public function fixGUI(): void
    {
        static $send = false;
        SimplePacketHandler::createInterceptor($this)
            ->interceptIncoming(static function (ContainerClosePacket $packet, NetworkSession $session) use (&$send): bool {
                $send = true;
                $session->sendDataPacket($packet);
                $send = false;
                return true;
            })
            ->interceptOutgoing(static function (ContainerClosePacket $packet, NetworkSession $session) use (&$send): bool {
                return $send;
            });
    }
}
