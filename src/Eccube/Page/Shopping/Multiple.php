<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2014 LOCKON CO.,LTD. All Rights Reserved.
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Page\Shopping;

use Eccube\Application;
use Eccube\Page\AbstractPage;
use Eccube\Framework\Customer;
use Eccube\Framework\CartSession;
use Eccube\Framework\SiteSession;
use Eccube\Framework\FormParam;
use Eccube\Framework\Response;
use Eccube\Framework\Helper\AddressHelper;
use Eccube\Framework\Helper\PurchaseHelper;
use Eccube\Framework\DB\MasterData;
use Eccube\Framework\Util\Utils;

/**
 * お届け先の複数指定 のページクラス.
 *
 * @package Page
 * @author LOCKON CO.,LTD.
 */
class Multiple extends AbstractPage
{
    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->tpl_title = 'お届け先の複数指定';
        $this->httpCacheControl('nocache');
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    public function process()
    {
        parent::process();
        $this->action();
        $this->sendResponse();
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    public function action()
    {
        //決済処理中ステータスのロールバック
        /* @var $objPurchase PurchaseHelper */
        $objPurchase = Application::alias('eccube.helper.purchase');
        $objPurchase->cancelPendingOrder(PENDING_ORDER_CANCEL_FLAG);

        /* @var $objSiteSess SiteSession */
        $objSiteSess = Application::alias('eccube.site_session');
        /* @var $objCartSess CartSession */
        $objCartSess = Application::alias('eccube.cart_session');
        /* @var $objCustomer Customer */
        $objCustomer = Application::alias('eccube.customer');
        /* @var $objFormParam FormParam */
        $objFormParam = Application::alias('eccube.form_param');
        /* @var $objAddress AddressHelper */
        $objAddress = Application::alias('eccube.helper.address');

        // 複数配送先指定が無効な場合はエラー
        if (USE_MULTIPLE_SHIPPING === false) {
            Utils::sfDispSiteError(PAGE_ERROR, '', true);
            Application::alias('eccube.response')->actionExit();
        }

        $this->tpl_uniqid = $objSiteSess->getUniqId();

        $this->addrs = $this->getDelivAddrs($objCustomer, $objPurchase, $objAddress);
        $this->tpl_addrmax = count($this->addrs) - 2; // 「選択してください」と会員の住所をカウントしない
        $this->lfInitParam($objFormParam);

        $objPurchase->verifyChangeCart($this->tpl_uniqid, $objCartSess);

        switch ($this->getMode()) {
            case 'confirm':
                $objFormParam->setParam($_POST);
                $this->arrErr = $this->lfCheckError($objFormParam);
                if (Utils::isBlank($this->arrErr)) {
                    // フォームの情報を一時保存しておく
                    $_SESSION['multiple_temp'] = $objFormParam->getHashArray();
                    $this->saveMultipleShippings($this->tpl_uniqid, $objFormParam,
                                                 $objCustomer, $objPurchase,
                                                 $objAddress);
                    $objSiteSess->setRegistFlag();

                    Application::alias('eccube.response')->sendRedirect('payment.php');
                    Application::alias('eccube.response')->actionExit();
                }
                break;

            default:
                $this->setParamToSplitItems($objFormParam, $objCartSess);
        }

        // 前のページから戻ってきた場合
        if ($_GET['from'] == 'multiple') {
            $objFormParam->setParam($_SESSION['multiple_temp']);
        }
        $this->arrForm = $objFormParam->getFormParamList();
    }

    /**
     * フォームを初期化する.
     *
     * @param  FormParam $objFormParam FormParam インスタンス
     * @return void
     */
    public function lfInitParam(FormParam &$objFormParam)
    {
        $objFormParam->addParam('商品規格ID', 'product_class_id', INT_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objFormParam->addParam('商品名', 'name');
        $objFormParam->addParam('規格1', 'class_name1');
        $objFormParam->addParam('規格2', 'class_name2');
        $objFormParam->addParam('規格分類1', 'classcategory_name1');
        $objFormParam->addParam('規格分類2', 'classcategory_name2');
        $objFormParam->addParam('メイン画像', 'main_image');
        $objFormParam->addParam('メイン一覧画像', 'main_list_image');
        $objFormParam->addParam(SALE_PRICE_TITLE, 'price');
        $objFormParam->addParam(SALE_PRICE_TITLE . '(税込)', 'price_inctax');
        $objFormParam->addParam('数量', 'quantity', INT_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'), 1);
        $objFormParam->addParam('お届け先', 'shipping', INT_LEN, 'n', array('MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objFormParam->addParam('カート番号', 'cart_no', INT_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
        $objFormParam->addParam('行数', 'line_of_num', INT_LEN, 'n', array('EXIST_CHECK', 'MAX_LENGTH_CHECK', 'NUM_CHECK'));
    }

    /**
     * カートの商品を数量ごとに分割し, フォームに設定する.
     *
     * @param   FormParam   $objFormParam FormParam インスタンス
     * @param   CartSession $objCartSess  CartSession インスタンス
     * @return  void
     */
    public function setParamToSplitItems(FormParam &$objFormParam, CartSession &$objCartSess)
    {
        $cartLists =& $objCartSess->getCartList($objCartSess->getKey());
        $arrItems = array();
        $index = 0;
        foreach (array_keys($cartLists) as $key) {
            $arrProductsClass = $cartLists[$key]['productsClass'];
            $quantity = (int) $cartLists[$key]['quantity'];
            for ($i = 0; $i < $quantity; $i++) {
                foreach ($arrProductsClass as $key2 => $val) {
                    $arrItems[$key2][$index] = $val;
                }
                $arrItems['quantity'][$index] = 1;
                $arrItems['price'][$index] = $cartLists[$key]['price'];
                $arrItems['price_inctax'][$index] = $cartLists[$key]['price_inctax'];
                $index++;
            }
        }
        $objFormParam->setParam($arrItems);
        $objFormParam->setValue('line_of_num', $index);
    }

    /**
     * 配送住所のプルダウン用連想配列を取得する.
     *
     * 会員ログイン済みの場合は, 会員登録住所及び追加登録住所を取得する.
     * 非会員の場合は, 「お届け先の指定」画面で入力した住所を取得する.
     *
     * @param  Customer        $objCustomer Customer インスタンス
     * @param  PurchaseHelper $objPurchase PurchaseHelper インスタンス
     * @param AddressHelper $objAddress
     * @return array              配送住所のプルダウン用連想配列
     */
    public function getDelivAddrs(Customer &$objCustomer, PurchaseHelper &$objPurchase, AddressHelper &$objAddress)
    {
        $masterData = Application::alias('eccube.db.master_data');
        $arrPref = $masterData->getMasterData('mtb_pref');

        $arrResults = array('' => '選択してください');
        // 会員ログイン時
        if ($objCustomer->isLoginSuccess(true)) {
            $addr = array(
                array(
                    'other_deliv_id'    => NULL,
                    'customer_id'       => $objCustomer->getValue('customer_id'),
                    'name01'            => $objCustomer->getValue('name01'),
                    'name02'            => $objCustomer->getValue('name02'),
                    'kana01'            => $objCustomer->getValue('kana01'),
                    'kana02'            => $objCustomer->getValue('kana02'),
                    'zip01'             => $objCustomer->getValue('zip01'),
                    'zip02'             => $objCustomer->getValue('zip02'),
                    'pref'              => $objCustomer->getValue('pref'),
                    'addr01'            => $objCustomer->getValue('addr01'),
                    'addr02'            => $objCustomer->getValue('addr02'),
                    'tel01'             => $objCustomer->getValue('tel01'),
                    'tel02'             => $objCustomer->getValue('tel02'),
                    'tel03'             => $objCustomer->getValue('tel03'),
                )
            );
            $arrAddrs = array_merge($addr, $objAddress->getList($objCustomer->getValue('customer_id')));
            foreach ($arrAddrs as $val) {
                $other_deliv_id = Utils::isBlank($val['other_deliv_id']) ? 0 : $val['other_deliv_id'];
                $arrResults[$other_deliv_id] = $val['name01'] . $val['name02']
                    . ' ' . $arrPref[$val['pref']] . $val['addr01'] . $val['addr02'];
            }
        // 非会員
        } else {
            $arrShippings = $objPurchase->getShippingTemp();
            foreach ($arrShippings as $shipping_id => $val) {
                $arrResults[$shipping_id] = $val['shipping_name01'] . $val['shipping_name02']
                    . ' ' . $arrPref[$val['shipping_pref']]
                    . $val['shipping_addr01'] . $val['shipping_addr02'];
            }
        }

        return $arrResults;
    }

    /**
     * 入力チェックを行う.
     *
     * @param   FormParam   $objFormParam FormParam インスタンス
     * @return  array       エラー情報の配列
     */
    public function lfCheckError(FormParam &$objFormParam)
    {
        /* @var $objCartSess CartSession */
        $objCartSess = Application::alias('eccube.cart_session');

        $objFormParam->convParam();
        // 数量未入力は0に置換
        $objFormParam->setValue('quantity', $objFormParam->getValue('quantity', 0));

        $arrErr = $objFormParam->checkError();

        $arrParams = $objFormParam->getSwapArray();

        if (empty($arrErr)) {
            foreach ($arrParams as $index => $arrParam) {
                // 数量0で、お届け先を選択している場合
                if ($arrParam['quantity'] == 0 && !Utils::isBlank($arrParam['shipping'])) {
                    $arrErr['shipping'][$index] = '※ 数量が0の場合、お届け先を入力できません。<br />';;
                }
                // 数量の入力があり、お届け先を選択していない場合
                if ($arrParam['quantity'] > 0 && Utils::isBlank($arrParam['shipping'])) {
                    $arrErr['shipping'][$index] = '※ お届け先が入力されていません。<br />';
                }
            }
        }

        // 入力エラーが無い場合、カゴの中身との数量の整合を確認
        if (empty($arrErr)) {
            $arrQuantity = array();
            // 入力内容を集計
            foreach ($arrParams as $arrParam) {
                $product_class_id = $arrParam['product_class_id'];
                $arrQuantity[$product_class_id] += $arrParam['quantity'];
            }
            // カゴの中身と突き合わせ
            $cartLists =& $objCartSess->getCartList($objCartSess->getKey());
            foreach ($cartLists as $arrCartRow) {
                $product_class_id = $arrCartRow['id'];
                // 差異がある場合、エラーを記録
                if ($arrCartRow['quantity'] != $arrQuantity[$product_class_id]) {
                    foreach ($arrParams as $index => $arrParam) {
                        if ($arrParam['product_class_id'] == $product_class_id) {
                            $arrErr['quantity'][$index] = '※ 数量合計を「' . $arrCartRow['quantity'] .'」にしてください。<br />';
                        }
                    }
                }
            }
        }

        return $arrErr;
    }

    /**
     * 複数配送情報を一時保存する.
     *
     * 会員ログインしている場合は, その他のお届け先から住所情報を取得する.
     *
     * @param   integer         $uniqid       一時受注テーブルのユニークID
     * @param   FormParam       $objFormParam FormParam インスタンス
     * @param   Customer        $objCustomer  Customer インスタンス
     * @param   PurchaseHelper  $objPurchase  PurchaseHelper インスタンス
     * @param   AddressHelper   $objAddress
     * @return  void
     */
    public function saveMultipleShippings($uniqid, FormParam &$objFormParam, Customer &$objCustomer, PurchaseHelper &$objPurchase, AddressHelper &$objAddress)
    {
        $arrParams = $objFormParam->getSwapArray();

        foreach ($arrParams as $arrParam) {
            $other_deliv_id = $arrParam['shipping'];

            if ($objCustomer->isLoginSuccess(true)) {
                if ($other_deliv_id != 0) {
                    $otherDeliv = $objAddress->getAddress($other_deliv_id, $objCustomer->getValue('customer_id'));

                    if (!$otherDeliv) {
                        Utils::sfDispSiteError(FREE_ERROR_MSG, '', false, "入力値が不正です。<br />正しい値を入力してください。");
                        Application::alias('eccube.response')->actionExit();
                    }

                    foreach ($otherDeliv as $key => $val) {
                        $arrValues[$other_deliv_id]['shipping_' . $key] = $val;
                    }
                } else {
                    $objPurchase->copyFromCustomer($arrValues[0], $objCustomer,
                                                   'shipping');
                }
            } else {
                $arrValues = $objPurchase->getShippingTemp();
            }
            $arrItemTemp[$other_deliv_id][$arrParam['product_class_id']] += $arrParam['quantity'];
        }

        $objPurchase->clearShipmentItemTemp();

        foreach ($arrValues as $shipping_id => $arrVal) {
            $objPurchase->saveShippingTemp($arrVal, $shipping_id);
        }

        foreach ($arrItemTemp as $other_deliv_id => $arrProductClassIds) {
            foreach ($arrProductClassIds as $product_class_id => $quantity) {
                if ($quantity == 0) continue;
                $objPurchase->setShipmentItemTemp($other_deliv_id,
                                                  $product_class_id,
                                                  $quantity);
            }
        }

        //不必要な配送先を削除
        foreach ($_SESSION['shipping'] as $id=>$arrShipping) {
            if (!isset($arrShipping['shipment_item'])) {
                $objPurchase->unsetOneShippingTemp($id);
            }
        }

        // $arrValues[0] には, 購入者の情報が格納されている
        $objPurchase->saveOrderTemp($uniqid, $arrValues[0], $objCustomer);
    }
}