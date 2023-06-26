<?php

namespace bofoiii\bedwars\task;

use bofoiii\bedwars\BedWars;
use pocketmine\scheduler\AsyncTask;

class IMGBBAsyncTask extends AsyncTask
{

    /** @var string $image */
    protected string $image;

    /** @var string $fileName */
    protected string $fileName;

    public function __construct(string $image, string $fileName)
    {
        $this->image = $image;
        $this->fileName = $fileName;
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        $API_KEY = '9ab5fa259a798be5efbcecbdae604307';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data = array('image' => base64_encode(file_get_contents($this->image)), 'name' => $this->fileName);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            BedWars::getInstance()->getLogger()->error(curl_error($ch));
        } else {
            $this->setResult(json_decode($result));
        }
        unlink($this->image);
        curl_close($ch);
    }

    public function onCompletion(): void
    {
        $result = $this->getResult();
        if (!isset($result->error)) {
            BedWars::getInstance()->getLogger()->info($result->data->image->url);
        }
        BedWars::getInstance()->urlSkin[$this->fileName] = $result->data->image->url;
    }
}
