<?php
namespace slightlydiff\xero;

use Yii;
use yii\base\UserException;
use yii\base\Component;

/**
 * Xero Api main component
 * @author Stephen Smith <stephen@slightlydifferent.co.nz>
 * @since 1.0.0
 */
class XeroApi extends Component {

    const ENDPOINT = 'https://api.xero.com/api.xro/2.0/';

    public $consumer_key;
    public $shared_secret;
    public $rsa_public_key = '@app/config/certs/xero_publickey.cer';
    public $rsa_private_key = '@app/config/certs/xero_privatekey.pem';
    public $useragent = "XeroOAuth-PHP";
    public $format = 'xml';

    private $persist_oauth_session_file = '@runtime/cache/oauth_session';

    private $consumer;
    private $token;
    private $signature_method;

    public function __construct($consumer_key = false, $shared_secret = false, $rsa_public_key = false, $rsa_private_key = false, $format = 'xml', $config = []) {
        
        parent::__construct($config);
        
        if ($consumer_key) $this->consumer_key = $consumer_key;
        if ($shared_secret) $this->shared_secret = $shared_secret;
        if ($rsa_public_key) $this->rsa_public_key = $rsa_public_key;
        if ($rsa_private_key) $this->rsa_private_key = $rsa_private_key;
        if (!($this->consumer_key) || !($this->shared_secret) || !($this->rsa_public_key) || !($this->rsa_private_key)) {
            throw new UserException(\Yii::t('xero', 'A valid consumer key, shared secret and public / private key pair must be provided'));
        }
        if (!file_exists(Yii::getAlias($this->rsa_public_key)) || !file_exists(Yii::getAlias($this->rsa_private_key))) {
            throw new UserException(\Yii::t('xero', 'A valid public / private key pair must be provided'));
        }
        if ($format) $this->format = ( in_array($format, array('xml', 'json', 'pdf'))) ? $format : 'xml';
        
    }

    public function init() {
        parent::init();
        $this->registerTranslations();
    }

    public function registerTranslations() {
        Yii::$app->i18n->translations['modules/users/*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@app/modules/users/messages',
            'fileMap' => [
                'modules/users/validation' => 'validation.php',
                'modules/users/form' => 'form.php'
            ],
        ];
    }

    public function __call($command, $arguments) {
        $command = strtolower($command);
        $api_commands = [
                            'attachments'           => ['slug' => 'Attachments', 'supports' => 'GET,PUT,POST'],
                            'accounts'              => ['slug' => 'Accounts', 'supports' => 'GET,PUT,POST,DELETE'],
                            'banktransactions'      => ['slug' => 'BankTransactions', 'supports' => 'GET,PUT,POST'],
                            'banktransfers'         => ['slug' => 'BankTransfers', 'supports' => 'GET,PUT'],
                            'brandingthemes'        => ['slug' => 'BrandingThemes', 'supports' => 'GET'],
                            'contacts'              => ['slug' => 'Contacts', 'supports' => 'GET,PUT,POST'],
                            'contactgroups'         => ['slug' => 'ContactGroups', 'supports' => 'GET,PUT,POST,DELETE'],
                            'creditnotes'           => ['slug' => 'CreditNotes', 'supports' => 'GET,PUT,POST'],
                            'currencies'            => ['slug' => 'Currencies', 'supports' => 'GET'],
                            'employees'             => ['slug' => 'Employees', 'supports' => 'GET,PUT,POST,'],
                            'expenseclaims'         => ['slug' => 'ExpenseClaims', 'supports' => 'GET,PUT,POST'],
                            'invoices'              => ['slug' => 'Invoices', 'supports' => 'GET,PUT,POST,'],
                            'invoicereminders'      => ['slug' => 'InvoiceReminders', 'supports' => 'GET'],
                            'items'                 => ['slug' => 'Items', 'supports' => 'GET,PUT,POST,DELETE'],
                            'journals'              => ['slug' => 'Journals', 'supports' => 'GET'],
                            'linkedtransactions'    => ['slug' => 'LinkedTransactions', 'supports' => 'GET,PUT,POST,DELETE'],
                            'manualjournals'        => ['slug' => 'ManualJournals', 'supports' => 'GET,PUT,POST'],
                            'organisation'          => ['slug' => 'Organisation', 'supports' => 'GET'],
                            'overpayments'          => ['slug' => 'Overpayments', 'supports' => 'GET,PUT'],
                            'payments'              => ['slug' => 'Payments', 'supports' => 'GET,PUT,POST'],
                            'prepayments'           => ['slug' => 'Prepayments', 'supports' => 'GET,PUT'],
                            'purchaseorders'        => ['slug' => 'PurchaseOrders', 'supports' => 'GET,PUT,POST'],
                            'receipts'              => ['slug' => 'Receipts', 'supports' => 'GET,PUT,POST'],
                            'repeatinginvoices'     => ['slug' => 'RepeatingInvoices', 'supports' => 'GET'],
                            'reports'               => ['slug' => 'Reports', 'supports' => 'GET'],
                            'taxrates'              => ['slug' => 'TaxRates', 'supports' => 'GET,PUT,POST'],
                            'trackingcategories'    => ['slug' => 'TrackingCategories', 'supports' => 'GET,PUT,POST,DELETE'],
                            'users'                 => ['slug' => 'Users', 'supports' => 'GET']
                            ];
        if (!array_key_exists($command, $api_commands)) {
            throw new UserException(\Yii::t('xero', 'The command "{command}" is not valid for the Xero API', ['command' => $command]));
        }

        $api_command = $api_commands[$command];
        $supported_methods = explode(',', $api_command['supports']);
        
        // First argument must always be the method
        if ((count($arguments) == 0)) {
            throw new UserException(\Yii::t('xero', 'At least one argument must be supplied with the HTTP Method when calling "{command}"', ['command' => $command]));
        }
        $method = strip_tags(strval($arguments[0]));
        if (!in_array($method, $supported_methods)) {
            throw new UserException(\Yii::t('xero', 'The Xero API command "{command}" does not support the method "{method}"', ['command' => $command, 'method' => $method]));
        }
        
        $post_body = null;
        $xero_id = $modified_since = false;
        $filters = [];
        
        if ($method == 'GET') {
            
            // Parameter 1 is for getting an object by ID
            $xero_id = (count($arguments) > 1) ? strip_tags(strval($arguments[1])) : false;
            
            // Parameter 2 is for getting all object modified since date
            $modified_since = (count($arguments) > 2) ? str_replace('X', 'T', date('Y-m-dXH:i:s', strtotime($arguments[2]))) : false;
            
            // Parameter 3 is for filters (where and order)
            $filters = (count($arguments) > 3) ? $arguments[3] : [];
            
        } elseif ($method == 'POST' || $method == 'PUT') {
            
            if ((count($arguments) == 2) && (is_array($arguments[1]) || is_a($arguments[1], 'SimpleXMLElement'))) {
                
                if (is_a($arguments[1], 'SimpleXMLElement')) {
                    $post_body = $arguments[1]->asXML();
                } elseif (is_array($arguments[1])) {
                    $post_body = ArrayToXml::toXML($arguments[1], $rootNodeName = $api_command['slug']);
                }
                $post_body = trim(substr($post_body, (stripos($post_body, ">") + 1)));
            } else {
                throw new UserException(\Yii::t('xero', 'Two parameters are required for method "{method}", the method and the XML data', ['method' => $method]));
            }
            
        }

        $signatures = [
                        'consumer_key' => $this->consumer_key, 
                        'shared_secret' => $this->shared_secret, 
                        'rsa_private_key' => Yii::getAlias($this->rsa_private_key), 
                        'rsa_public_key' => Yii::getAlias($this->rsa_public_key), 
                        'core_version' => '2.0'
                        ];

        $XeroOAuth = new \XeroOAuth(array_merge(['application_type' => "Private", 'oauth_callback' => "oob", 'user_agent' => $this->useragent], $signatures));
        $initial_check = $XeroOAuth->diagnostics();

        if (count($initial_check) > 0) {
            $errors = '';
            foreach ($initial_check as $check) {
                $errors .= ' - ' . $check . PHP_EOL;
                
            }
            throw new UserException(\Yii::t('xero', 'The Xero OAuth call has returned the following errors:{errors}', ['errors' => PHP_EOL . $errors]));
        }

        $session = $this->persistSession([
                            'oauth_token' => $XeroOAuth->config['consumer_key'], 
                            'oauth_token_secret' => $XeroOAuth->config['shared_secret'], 
                            'oauth_session_handle' => ''
                            ]);
        $oauthSession = $this->retrieveSession();

        if (isset($oauthSession['oauth_token'])) {
            $XeroOAuth->config['access_token'] = $oauthSession['oauth_token'];
            $XeroOAuth->config['access_token_secret'] = $oauthSession['oauth_token_secret'];
            $XeroOAuth->config['session_handle'] = $oauthSession['oauth_session_handle'];

            $url = $XeroOAuth->url($api_command['slug'], 'core');
            $data = null;
            
            if ($method == 'GET') {
                if ($xero_id) {
                    $url = $XeroOAuth->url($api_command['slug'] . '/' . $xero_id, 'core');
                    $filters = [];
                } elseif ($modified_since) {
                    if (empty($filters) || !is_array($filters)) {
                        $filters = ['If-Modified-Since' => $modified_since];
                    } else {
                        $filters['If-Modified-Since'] = $modified_since;
                    }
                }
                $post_body = null;
            } else {
                $filters = [];
            }

            $response = $XeroOAuth->request(
                                            $method,        // HTML Method (GET, POST, PUT or DELETE)
                                            $url,           // URL built using XeroOAuth->url
                                            $filters,       // Filters (where, order, since)
                                            $post_body,     // xml data for POST or PUT
                                            $this->format   // response format
                                            );
                                            
            if ($XeroOAuth->response['code'] == 200) {
	            $data = $XeroOAuth->parseResponse( $XeroOAuth->response['response'], $XeroOAuth->response['format'] );
            } elseif ($command == 'contacts') {
	            //$data = $XeroOAuth->parseResponse( $XeroOAuth->response['response'], $XeroOAuth->response['format'] );
				//if ($data->ApiException->Elements[0]->DataContractBase->ValidationErrors[0]->ValidationError->Message == 'The specified contact details matched an archived contact. Archived contacts cannot currently be edited via the API.') {
					// ignore and carry on
				//} else {
				//	throw new UserException(\Yii::t('xero', 'The Xero Api has returned the following error:{errors}', ['errors' => PHP_EOL . $XeroOAuth->response['response']]));
				//}
            } else {
                throw new UserException(\Yii::t('xero', 'The Xero Api has returned the following error:{errors}', ['errors' => PHP_EOL . $XeroOAuth->response['response']]));
            }
        }
        return $data;
    }

    public function __get($command) {
        return $this->$command();
    }

    private function persistSession($response) {
        $allowed_keys = array('oauth_token', 'oauth_token_secret', 'oauth_session_handle');
        if (isset($response)) {
            foreach ($response as $key => $value) {
                if (!in_array($key, $allowed_keys)) {
                    unset($response[$key]);
                }
            }
            file_put_contents(Yii::getAlias($this->persist_oauth_session_file), serialize($response));
        } else {
            return false;
        }

    }

    private function retrieveSession() {
        $oauth_session = file_get_contents(Yii::getAlias($this->persist_oauth_session_file));
        $oauth_session = unserialize($oauth_session);
        if ($oauth_session !== FALSE && isset($oauth_session['oauth_token'])) {
            $response['oauth_token'] = $oauth_session['oauth_token'];
            $response['oauth_token_secret'] = $oauth_session['oauth_token_secret'];
            $response['oauth_session_handle'] = $oauth_session['oauth_session_handle'];
            return $response;
        } else {
            return false;
        }
    }

}
