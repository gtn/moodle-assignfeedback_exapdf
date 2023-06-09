<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitbfdb5ff5aa8695acec472a51e8a29ea3
{
    public static $prefixLengthsPsr4 = array (
        's' => 
        array (
            'setasign\\Fpdi\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'setasign\\Fpdi\\' => 
        array (
            0 => __DIR__ . '/..' . '/setasign/fpdi/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'FPDF' => __DIR__ . '/..' . '/setasign/fpdf/fpdf.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitbfdb5ff5aa8695acec472a51e8a29ea3::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitbfdb5ff5aa8695acec472a51e8a29ea3::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitbfdb5ff5aa8695acec472a51e8a29ea3::$classMap;

        }, null, ClassLoader::class);
    }
}
