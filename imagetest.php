<?php
/**
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Imagetest extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'imagetest';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Egor';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Image checker');
        $this->description = $this->l('Image checker - duplicate or delete');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('IMAGETEST_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('IMAGETEST_LIVE_MODE');

        return parent::uninstall();
    }


    protected function deleteAllImg()
    {
        $dir = _PS_ROOT_DIR_;
        $dh = @opendir($dir);
        if ($dh) {
            $fullpath = $dir . '/img/';
            $this->addtext .= '<span style="font-weight:bold;">Path to img</span> ' . $fullpath . '<br>';

            $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'image';
            $result = Db::getInstance()->ExecuteS($query);
            //"DELETE FROM 'ps_image' WHERE 'ps_image'.'id_image' = 413"
            //Db::getInstance()->delete($table, $field﻿ .' = '. $delete, 1, $use_cache = false);

            /* Выборка результатов запроса */
            foreach ($result AS $k => $address) {
                if ($k == 100) break;

                $this->addtext .= '<span style="font-weight:bold;">ID товара: ' . $address["id_product"] . ', id_img: ' . $address["id_image"] . '</span><br>';
                $split_img = str_split($address["id_image"]);
                $split_img = implode("/", $split_img);
                $imgpath_del = $fullpath . 'p/' . $split_img . '/';
                if (file_exists($imgpath_del)) {
                    foreach (glob($imgpath_del.'/*') as $file) {
                        unlink($file);
                    }
                }

                $this->addtext .= 'Delete img from ftp: '.$imgpath_del.'';

                # delete from ps_image table
                $sql = 'DELETE FROM '._DB_PREFIX_.'image WHERE id_product = '. $address["id_product"].' AND id_image = '.$address["id_image"];
                if (!Db::getInstance()->ExecuteS($sql))
                die('Error MYSQL delete from ps_image');
                # delete from ps_image_lang table
                $sql = 'DELETE FROM '._DB_PREFIX_.'image_lang WHERE id_image = '.$address["id_image"];
                if (!Db::getInstance()->ExecuteS($sql))
                    die('Error MYSQL delete from ps_image_lang');
                # delete from ps_image_shop table
                $sql = 'DELETE FROM '._DB_PREFIX_.'image_shop WHERE id_product = '. $address["id_product"].' AND id_image = '.$address["id_image"];
                if (!Db::getInstance()->ExecuteS($sql))
                    die('Error MYSQL delete from ps_image_shop');
                $this->addtext .= ' | Delete mysql: ps_image,ps_image_lang,ps_image_shop<br>';

            }

            closedir($dh);
        }
    }


    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $this->addtext = '';

        if (((bool)Tools::isSubmit('submitImagetestModule')) == true) {
            $this->postProcess();
        }

        if (Tools::isSubmit('submitChangePermissions')) {
            $this->deleteAllImg();
        }
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('addtext', $this->addtext);
        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitImagetestModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'submit' => array(
                    'title' => $this->l('Delete all image'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitChangePermissions',
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'IMAGETEST_LIVE_MODE' => Configuration::get('IMAGETEST_LIVE_MODE', true),
            'IMAGETEST_ACCOUNT_EMAIL' => Configuration::get('IMAGETEST_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'IMAGETEST_ACCOUNT_PASSWORD' => Configuration::get('IMAGETEST_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }
}
