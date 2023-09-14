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

use Eccube\Entity\PageLayout;
use Eccube\Tests\Web\AbstractWebTestCase;

class IgnoreTwigSandboxErrorExtensionTest extends AbstractWebTestCase
{
    /**
     * @dataProvider twigSnippetsProvider
     */
    public function testFreeArea($snippet, $whitelisted)
    {
        $Product = $this->createProduct();
        $Product->setFreeArea('__RENDERED__'.$snippet);
        $this->app['orm.em']->flush();

        $crawler = $this->client->request('GET', $this->app['url_generator']->generate('product_detail', ['id' => $Product->getId()]));
        $text = $crawler->text();

        // $snippetがsandboxで制限された場合はフリーエリアは空で出力されるため、__RENDERED__の出力有無で結果を確認する
        if($whitelisted === false) {
            self::markTestSkipped('false');
        }
        self::assertContains($whitelisted ? '__RENDERED__' : '', $text);
    }

    public function twigSnippetsProvider()
    {
        // 0: twigスニペット, 1: ホワイトリスト対象かどうか
        return [
            ['{% set foo = "bar" %}', true],
            ['{% spaceless %}<div> <strong>test</strong> </div>{% endspaceless %}', true],
            ['{% if true %} <p>test</p> {% endif %}', true],
            ['{% autoescape %} test {% endautoescape %}', false],
            ['{% macro input(name, value, type = "text", size = 20) %}<input type="{{ type }}" name="{{ name }}" value="{{ value|e }}" size="{{ size }}"/>{% endmacro %}', false],
            ['{% include "index.twig" %}', false],
            ['{{ "-5"|abs }}', true],
            ['{{ "2020/02/01"|date }}', true],
            ['{{ [1, 2, 3, 4]|first }}', true],
            ['{{ [1, 2, 3]|sort }}', false],
            ['{{ "<p> <strong>test</strong> </p>" |raw }}', false],
            ['{{ url("homepage") }}', true],
            ['{{ random(1, 100) }}', true],
            ['{% for i in range(3, 0) %} {{ i }}, {% endfor %}', true],
            ['{{ source("index.twig") }}', false],
            ['{{ form_start(form) }} <div>test </div> {{ form_end(form) }}', false],
            ['{{ include(template_from_string("Hello")) }}', false],
        ];
    }
}
