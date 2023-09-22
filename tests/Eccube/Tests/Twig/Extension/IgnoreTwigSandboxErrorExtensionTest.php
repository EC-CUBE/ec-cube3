<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Tests\Twig\Extension;

use Eccube\Tests\Web\AbstractWebTestCase;

class IgnoreTwigSandboxErrorExtensionTest extends AbstractWebTestCase
{
    protected $app;


    public function setUp()
    {
        parent::setUp();
        $this->app = \Eccube\Application::getInstance();
        $this->app['debug'] = false;
    }


    public function tearDown()
    {
        $this->app['debug'] = true;
        parent::tearDown();
    }

    /**
     * @dataProvider twigSnippetsProvider
     */
    public function testFreeArea($snippet, $whitelisted)
    {
        $Product = $this->createProduct();
        $Product->setFreeArea('__RENDERED__'.$snippet);
        $this->app['orm.em']->flush();
        
        $crawler = $this->client->request('GET', $this->app['url_generator']->generate('product_detail', array('id' => $Product->getId())));
        $text = $crawler->text();

        // $snippetがsandboxで制限された場合はフリーエリアは空で出力されるため、__RENDERED__の出力有無で結果を確認する
        if ($whitelisted) {
            self::assertContains('__RENDERED__', $text);
        } else {
            self::assertNotContains('__RENDERED__', $text);
        }
    }

    public function twigSnippetsProvider()
    {
        // 0: twigスニペット, 1: ホワイトリスト対象かどうか
        return array(
            // タグ・フィルター・関数
            array('{% set foo = "bar" %}', true),
            array('{% with %} test {% endwith %}', true),
            array('{% if true %} <p>test</p> {% endif %}', true),
            array('{% autoescape %} test {% endautoescape %}', false),
            array('{% macro input(name, value, type = "text", size = 20) %}<input type="{{ type }}" name="{{ name }}" value="{{ value|e }}" size="{{ size }}"/>{% endmacro %}', false),
            array('{% include "index.twig" %}', false),
            array('{{ "-5"|abs }}', true),
            array('{{ "2020/02/01"|date }}', true),
            array('{{ [1, 2, 3, 4]|first }}', true),
            array('{{ [1, 2, 3]|sort }}', false),
            array('{{ "<p> <strong>test</strong> </p>" |raw }}', false),
            array('{{ url("homepage") }}', true),
            array('{{ random(1, 100) }}', true),
            array('{% for i in range(3, 0) %} {{ i }}, {% endfor %}', true),
            array('{{ source("index.twig") }}', false),
            array('{{ form_start(form) }} <div>test </div> {{ form_end(form) }}', false),
            array('{{ include(template_from_string("Hello")) }}', false),
            // 変数
            array('{{ Product }}', true),
            array('{{ app.session }}', false),
            array('{{ app.security }}', false),
            array('{{ app.request.cookies }}', false),
        );
    }
}
