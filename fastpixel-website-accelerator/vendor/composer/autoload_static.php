<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticIniteb684a0ef26501971a33e40757279196
{
    public static $files = array (
        '0509b34a4bd7aebefeac629c9dc8a978' => __DIR__ . '/..' . '/wpdesk/wp-notice/src/WPDesk/notice-functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WPDesk\\PluginBuilder\\' => 21,
            'WPDesk\\Notice\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WPDesk\\PluginBuilder\\' => 
        array (
            0 => __DIR__ . '/..' . '/wpdesk/wp-builder/src',
        ),
        'WPDesk\\Notice\\' => 
        array (
            0 => __DIR__ . '/..' . '/wpdesk/wp-notice/src/WPDesk/Notice',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'WPDesk\\Notice\\AjaxHandler' => __DIR__ . '/..' . '/wpdesk/wp-notice/src/WPDesk/Notice/AjaxHandler.php',
        'WPDesk\\Notice\\Factory' => __DIR__ . '/..' . '/wpdesk/wp-notice/src/WPDesk/Notice/Factory.php',
        'WPDesk\\Notice\\Notice' => __DIR__ . '/..' . '/wpdesk/wp-notice/src/WPDesk/Notice/Notice.php',
        'WPDesk\\Notice\\PermanentDismissibleNotice' => __DIR__ . '/..' . '/wpdesk/wp-notice/src/WPDesk/Notice/PermanentDismissibleNotice.php',
        'WPDesk\\PluginBuilder\\BuildDirector\\LegacyBuildDirector' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/BuildDirector/LegacyBuildDirector.php',
        'WPDesk\\PluginBuilder\\Builder\\AbstractBuilder' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Builder/AbstractBuilder.php',
        'WPDesk\\PluginBuilder\\Builder\\InfoActivationBuilder' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Builder/InfoActivationBuilder.php',
        'WPDesk\\PluginBuilder\\Builder\\InfoBuilder' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Builder/InfoBuilder.php',
        'WPDesk\\PluginBuilder\\Plugin\\AbstractPlugin' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/AbstractPlugin.php',
        'WPDesk\\PluginBuilder\\Plugin\\Activateable' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/Activateable.php',
        'WPDesk\\PluginBuilder\\Plugin\\ActivationAware' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/ActivationAware.php',
        'WPDesk\\PluginBuilder\\Plugin\\ActivationTracker' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/ActivationTracker.php',
        'WPDesk\\PluginBuilder\\Plugin\\Conditional' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/Conditional.php',
        'WPDesk\\PluginBuilder\\Plugin\\Deactivateable' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/Deactivateable.php',
        'WPDesk\\PluginBuilder\\Plugin\\Hookable' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/Hookable.php',
        'WPDesk\\PluginBuilder\\Plugin\\HookableCollection' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/HookableCollection.php',
        'WPDesk\\PluginBuilder\\Plugin\\HookableParent' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/HookableParent.php',
        'WPDesk\\PluginBuilder\\Plugin\\HookablePluginDependant' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/HookablePluginDependant.php',
        'WPDesk\\PluginBuilder\\Plugin\\PluginAccess' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/PluginAccess.php',
        'WPDesk\\PluginBuilder\\Plugin\\SlimPlugin' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/SlimPlugin.php',
        'WPDesk\\PluginBuilder\\Plugin\\TemplateLoad' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/TemplateLoad.php',
        'WPDesk\\PluginBuilder\\Storage\\Exception\\ClassAlreadyExists' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Storage/Exception/ClassAlreadyExists.php',
        'WPDesk\\PluginBuilder\\Storage\\Exception\\ClassNotExists' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Storage/Exception/ClassNotExists.php',
        'WPDesk\\PluginBuilder\\Storage\\PluginStorage' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Storage/PluginStorage.php',
        'WPDesk\\PluginBuilder\\Storage\\StaticStorage' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Storage/StaticStorage.php',
        'WPDesk\\PluginBuilder\\Storage\\StorageFactory' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Storage/StorageFactory.php',
        'WPDesk\\PluginBuilder\\Storage\\WordpressFilterStorage' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Storage/WordpressFilterStorage.php',
        'WPDesk_Buildable' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/WithoutNamespace/Buildable.php',
        'WPDesk_Has_Plugin_Info' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/WithoutNamespace/Has_Plugin_Info.php',
        'WPDesk_Plugin_Info' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/WithoutNamespace/Plugin_Info.php',
        'WPDesk_Translable' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/WithoutNamespace/Translable.php',
        'WPDesk_Translatable' => __DIR__ . '/..' . '/wpdesk/wp-builder/src/Plugin/WithoutNamespace/Translatable.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticIniteb684a0ef26501971a33e40757279196::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticIniteb684a0ef26501971a33e40757279196::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticIniteb684a0ef26501971a33e40757279196::$classMap;

        }, null, ClassLoader::class);
    }
}
