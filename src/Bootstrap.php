<?php
namespace slightlydiff\xero;

use Yii;
use yii\base\BootstrapInterface;

/**
 * Hook with application bootstrap stage
 * @author Stephen Smith <stephen@slightlydifferent.co.nz>
 * @since 1.0.0
 */
class Bootstrap implements BootstrapInterface {

    /**
     * Initial application compoments and modules need for extension
     * @param \yii\base\Application $app The application currently running
     * @return void
     */
    public function bootstrap($app) {
        // Set alias for extension source
        Yii::setAlias("@xero", __DIR__);

        // Setup i18n compoment for translate all category xero*
        if (!isset(Yii::$app->get('i18n')->translations['xero*'])) {
            Yii::$app->get('i18n')->translations['xero*'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => __DIR__ . '/messages',
            ];
        }
    }

}
