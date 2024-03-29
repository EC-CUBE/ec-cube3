<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Form\Type\Admin;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Application;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ProductType.
 */
class ProductType extends AbstractType
{
    /**
     * @var Application
     */
    public $app;

    /**
     * ProductType constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /**
         * @var ArrayCollection $arrCategory array of category
         */
        $arrCategory = $this->app['eccube.repository.category']->getList(null, true);

        $builder
            // 商品規格情報
            ->add('class', 'admin_product_class', array(
                'mapped' => false,
            ))
            // 基本情報
            ->add('name', 'text', array(
                'label' => '商品名',
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->add('product_image', 'file', array(
                'label' => '商品画像',
                'multiple' => true,
                'required' => false,
                'mapped' => false,
            ))
            ->add('description_detail', 'textarea', array(
                'label' => '商品説明',
            ))
            ->add('description_list', 'textarea', array(
                'label' => '商品説明(一覧)',
                'required' => false,
            ))
            ->add('Category', 'entity', array(
                'class' => 'Eccube\Entity\Category',
                'property' => 'NameWithLevel',
                'label' => '商品カテゴリ',
                'multiple' => true,
                'mapped' => false,
                // Choices list (overdrive mapped)
                'choices' => $arrCategory,
            ))

            // 詳細な説明
            ->add('Tag', 'tag', array(
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
            ))
            ->add('search_word', 'textarea', array(
                'label' => "検索ワード",
                'required' => false,
            ))
            // サブ情報
            ->add('free_area', 'textarea', array(
                'label' => 'サブ情報',
                'required' => false,
            ))

            // 右ブロック
            ->add('Status', 'disp', array(
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->add('note', 'textarea', array(
                'label' => 'ショップ用メモ帳',
                'required' => false,
            ))

            // タグ
            ->add('tags', 'collection', array(
                'type' => 'hidden',
                'prototype' => true,
                'mapped' => false,
                'allow_add' => true,
                'allow_delete' => true,
            ))
            // 画像
            ->add('images', 'collection', array(
                'type' => 'hidden',
                'prototype' => true,
                'mapped' => false,
                'allow_add' => true,
                'allow_delete' => true,
            ))
            ->add('add_images', 'collection', array(
                'type' => 'hidden',
                'prototype' => true,
                'mapped' => false,
                'allow_add' => true,
                'allow_delete' => true,
            ))
            ->add('delete_images', 'collection', array(
                'type' => 'hidden',
                'prototype' => true,
                'mapped' => false,
                'allow_add' => true,
                'allow_delete' => true,
            ))
        ;

        $that = $this;
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($that) {
            /** @var FormInterface $form */
            $form = $event->getForm();
            $saveImgDir = $that->app['config']['image_save_realdir'];
            $tempImgDir = $that->app['config']['image_temp_realdir'];
            $that->validateFilePath($form->get('delete_images'), array($saveImgDir, $tempImgDir));
            $that->validateFilePath($form->get('add_images'), array($tempImgDir));
        });
    }

    /**
     * 指定された複数ディレクトリのうち、いずれかのディレクトリ以下にファイルが存在するかを確認。
     *
     * @param $form FormInterface
     * @param $dirs array
     */
    private function validateFilePath($form, $dirs)
    {
        foreach ($form->getData() as $fileName) {
            if (strpos($fileName, '..') !== false) {
                $formRoot = $form->getRoot();
                $formRoot['product_image']->addError(new FormError('画像のパスが不正です。'));
                break;
            }
            $fileInDir = array_filter($dirs, function ($dir) use ($fileName) {
                $filePath = realpath($dir.'/'.$fileName);
                $topDirPath = realpath($dir);
                return strpos($filePath, $topDirPath) === 0 && $filePath !== $topDirPath;
            });
            if (!$fileInDir) {
                $formRoot = $form->getRoot();
                $formRoot['product_image']->addError(new FormError('画像のパスが不正です。'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'admin_product';
    }
}
