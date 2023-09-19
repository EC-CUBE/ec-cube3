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
            $tags = array('apply','block','deprecated','embed','extends','flush','for','if','set','spaceless','verbatim','with','form_theme','stopwatch','trans','trans_default_domain');
            $filters = array('abs','batch','capitalize','column','convert_encoding','country_name','currency_name','currency_symbol','date','date_modify','default','escape','first','format','format_currency','format_date','format_datetime','format_number','format_time','join','json_encode','keys','language_name','last','length','locale_name','lower','merge','nl2br','number_format','replace','reverse','round','slice','spaceless','split','striptags','timezone_name','title','trim','upper','url_encode','abbr_class','abbr_method','file_link','file_relative','format_args','format_args_as_text','humanize','serialize','trans','yaml_dump','yaml_encode','currency_symbol','date_day','date_day_with_weekday','date_format','date_min','date_sec','doctrine_format_sql','doctrine_prettify_sql','doctrine_pretty_query','doctrine_replace_query_parameters','e','ellipsis','file_ext_icon','form_encode_currency','format_*_number','format_log_message','no_image_product','price','purify','time_ago');
            $methods = array();
            $properties = array();
            $functions = array('dump','cycle','date','max','min','random','range','country_timezones','absolute_url','asset','asset_version','csrf_token','form_parent','fragment_uri','impersonation_exit_path','impersonation_exit_url','is_granted','logout_path','logout_url','path','relative_path','t','url','active_menus','class_categories_as_json','country_names','csrf_token_for_anchor','currency_names','currency_symbol','field_choices','field_errors','field_help','field_label','field_name','field_value','get_all_carts','get_cart','get_carts_total_price','get_carts_total_quantity','has_errors','is_reduced_tax_rate','language_names','product','workflow_can','workflow_has_marked_place','workflow_marked_places','workflow_metadata','workflow_transition','workflow_transition_blockers','workflow_transitions');

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
