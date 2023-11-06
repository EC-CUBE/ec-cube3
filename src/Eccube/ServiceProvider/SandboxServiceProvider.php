<?php

namespace Eccube\ServiceProvider;

use Eccube\EventListener\LogListener;
use Eccube\Log\Logger;
use Eccube\Log\Monolog\Helper\LogHelper;
use Eccube\Twig\Extension\IgnoreTwigSandboxErrorExtension;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Bridge\Twig\Extension\DumpExtension;

/**
 * Class LogServiceProvider
 *
 * @package Eccube\ServiceProvider
 */
class SandboxServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {

            // ホワイトリストの設定
            $twig_sandbox_list = $app['config']['twig_sandbox'];

            $tags = $twig_sandbox_list['allowed_tags'];
            $filters = $twig_sandbox_list['allowed_filters'];
            $methods = $twig_sandbox_list['allowed_methods'];
            $properties =  $twig_sandbox_list['allowed_properties'];
            $functions = $twig_sandbox_list['allowed_functions'];

            $policy = new \Twig\Sandbox\SecurityPolicy($tags, $filters, $methods, $properties, $functions);
            $sandbox = new \Twig\Extension\SandboxExtension($policy);

            $twig->addExtension($sandbox);
            $twig->addExtension(new IgnoreTwigSandboxErrorExtension());

            return $twig;
        }));
    }

    public function boot(Application $app)
    {
    }
}
