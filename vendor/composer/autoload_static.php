<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf46cccc817a237b054437bfb7901d7b2
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Fragen\\Git_Updater\\Gitea\\' => 25,
            'Fragen\\Git_Updater\\API\\' => 23,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Fragen\\Git_Updater\\Gitea\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Fragen\\Git_Updater\\API\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/Gitea',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf46cccc817a237b054437bfb7901d7b2::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf46cccc817a237b054437bfb7901d7b2::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitf46cccc817a237b054437bfb7901d7b2::$classMap;

        }, null, ClassLoader::class);
    }
}
