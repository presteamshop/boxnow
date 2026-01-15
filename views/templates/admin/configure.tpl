{*
 * 2007-2024 PrestaShop
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2024 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 *}
<style>
    .boxnow-settings-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
    }
    .boxnow-settings-grid > form {
        flex: 1 1 calc(50% - 24px);
        min-width: 320px;
    }
    .boxnow-settings-grid .panel {
        border: 1px solid #e6e9ef;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(25, 38, 60, 0.08);
        padding: 16px 20px;
        background: #ffffff;
    }
    .boxnow-settings-grid .panel-heading,
    .boxnow-settings-grid h3 {
        font-weight: 600;
        letter-spacing: 0.2px;
    }
    .boxnow-settings-grid .form-group {
        margin-bottom: 16px;
    }
    .boxnow-settings-grid .help-block {
        color: #6b7280;
    }
    @media (max-width: 991px) {
        .boxnow-settings-grid > form {
            flex-basis: 100%;
        }
    }

    .ps-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .ps-col {
        display: flex;
        align-items: center;
    }

    #payment-logo {
        height: 70px;
        width: auto;
        max-width: 260px;
    }

    .panel .boxnow-title {
        font-size: 24px;
        margin-right: 8px;
        display: flex;
        align-items: center;
    }

    .panel .boxnow-title i {
        margin-right: 8px;
        margin-left: 8px;
    }
</style>

<div class="panel" style="overflow: hidden;">

    <div class="ps-row">
        <div class="ps-col">
            <h3 class="boxnow-title">
                <i class="icon icon-truck"></i>
                {l s='BOX NOW Delivery' mod='boxnow'}
            </h3>
        </div>
        <div class="ps-col">
            <img src="{$module_dir|escape:'html':'UTF-8'}/logo.png" id="payment-logo"/>
        </div>
    </div>

    <p>
        This module integrates BOX NOW locker delivery into your PrestaShop store.
        If you encounter any issues or bugs, please contact us at <a href="mailto:ict@boxnow.gr">ict@boxnow.gr</a>.
    </p>
    <p><i>Version 2.4.3</i></p>
</div>
