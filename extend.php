<?php

use FoF\OAuth\Extend\RegisterProvider;
use forumaker\Yandex\Providers\Yandex;
use Flarum\Extend;

return [
    new Extend\Locales(__DIR__ . '/resources/locale'),
    new RegisterProvider(Yandex::class),

    (new Extend\Frontend('forum'))
        ->css(__DIR__ . '/resources/less/forum.less'),
];