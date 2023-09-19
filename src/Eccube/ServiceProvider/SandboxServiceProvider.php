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
            $tags = array('block','extends','for','if','set','spaceless','verbatim','with','form_theme','stopwatch','trans','trans_default_domain');
            $filters = array('abs','batch','capitalize','date','default','doctrine_format_sql','doctrine_prettify_sql','doctrine_pretty_query','doctrine_replace_query_parameters','escape','first','format','abbr_class','abbr_method','file_link','file_relative','format_args','format_args_as_text','humanize','json_encode','keys','last','length','lower','merge','replace','round','split','striptags','title','trim','no_image_product','date_format','price','no_image_product','date_format','price','ellipsis','time_ago');
            $methods = array();
            $properties = array();
            $functions = array('cycle','max','min','random','range','min','random','range','template_from_string','absolute_url','asset','asset_version','csrf_token','form_parent','fragment_uri','impersonation_exit_path','impersonation_exit_url','is_granted','logout_path','logout_url','path','relative_path','t','url','calc_inc_tax','active_menus','csrf_token_for_anchor','url','path','is_object','get_product');

            $policy = new \Twig\Sandbox\SecurityPolicy($tags, $filters, $methods, $properties, $functions);
            $sandbox = new \Twig\Extension\SandboxExtension($policy);

            $twig->addExtension($sandbox);
            $twig->addExtension(new IgnoreTwigSandboxErrorExtension());

            return $twig;
        }));
    }

    public function boot(Application $app)
    {
        // TODO: Implement boot() method.
    }
}
