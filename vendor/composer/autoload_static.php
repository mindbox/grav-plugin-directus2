<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3cf048609369253be808d8742e9b689d
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Grav\\Plugin\\Directus2\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Grav\\Plugin\\Directus2\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Grav\\Plugin\\Directus2Plugin' => __DIR__ . '/../..' . '/directus2.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3cf048609369253be808d8742e9b689d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3cf048609369253be808d8742e9b689d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3cf048609369253be808d8742e9b689d::$classMap;

        }, null, ClassLoader::class);
    }
}
