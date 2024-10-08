<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9eb86b40a85e73c6cb7d87768cf3103e
{
    public static $files = array (
        '3109cb1a231dcd04bee1f9f620d46975' => __DIR__ . '/..' . '/paragonie/sodium_compat/autoload.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Pusher\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Pusher\\' => 
        array (
            0 => __DIR__ . '/..' . '/pusher/pusher-php-server/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Pusher\\ApiErrorException' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/ApiErrorException.php',
        'Pusher\\Pusher' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/Pusher.php',
        'Pusher\\PusherCrypto' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/PusherCrypto.php',
        'Pusher\\PusherException' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/PusherException.php',
        'Pusher\\PusherInstance' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/PusherInstance.php',
        'Pusher\\PusherInterface' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/PusherInterface.php',
        'Pusher\\Webhook' => __DIR__ . '/..' . '/pusher/pusher-php-server/src/Webhook.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9eb86b40a85e73c6cb7d87768cf3103e::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9eb86b40a85e73c6cb7d87768cf3103e::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit9eb86b40a85e73c6cb7d87768cf3103e::$classMap;

        }, null, ClassLoader::class);
    }
}
