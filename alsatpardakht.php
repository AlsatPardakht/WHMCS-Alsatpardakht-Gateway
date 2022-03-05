<?php
/**
 * @author Həmid Musəvi <w1w@yahoo.com>
 * @date   2/20/2022
 * https://kratos.ir
 */

use WHMCS\Database\Capsule;

if (isset($_GET['invoice']) && is_numeric($_GET['invoice'])) {
    require_once __DIR__.'/../../init.php';
    require_once __DIR__.'/../../includes/gatewayfunctions.php';
    require_once __DIR__.'/../../includes/invoicefunctions.php';
    $gatewayParams = getGatewayVariables('alsatpardakht');
    if (isset($_GET['invoice']) && isset($_GET['callback']) && $_GET['callback'] == 1) {

        $invoice = Capsule::table('tblinvoices')->where('id', $_GET['invoice'])->where('status', 'Unpaid')->first();
        if (!$invoice) {
            die("Invoice not found");
        }
        $amount = ceil($invoice->total * ($gatewayParams['currencyType'] == 'IRR' ? 1 : 10));
        if ($gatewayParams['feeFromClient'] == 'on') {
            $fee = 1 * $amount / 100;
            if ($fee >= 25000) {
                $fee = 25000;
            }
            $amount += $fee;
        }

        if (isset($gatewayParams['gatewayType'])) {
            if ($gatewayParams['gatewayType'] === 'direct') {
                $data = [
                    'Api' => $gatewayParams['apiKey'],
                    'tref' => $_GET['tref'],
                    'iN' => $_GET['iN'],
                    'iD' => $_GET['iD'],
                ];
                $result = post_to_alsatpardakht('https://www.alsatpardakht.com/API_V1/callback.php', $data);
            } else {
                $data = [
                    'Api' => $gatewayParams['apiKey'],
                    'tref' => $_GET['tref'],
                    'iN' => $_GET['iN'],
                    'iD' => $_GET['iD'],
                ];
                $result = post_to_alsatpardakht('https://www.alsatpardakht.com/IPGAPI/Api22/VerifyTransaction.php',
                    $data);
            }
        } else {
            die("تنظیمات درگاه را بررسی نمائید.");
        }

        if ($result && isset($result->VERIFY) && $result->VERIFY->IsSuccess && $result->PSP->Amount === (int) $amount) {
//             checkCbTransID($result->refNumber);
            logTransaction($gatewayParams['name'], ['callback' => $_GET, 'result' => json_encode($result)], 'Success');
            addInvoicePayment(
                $invoice->id,
                $result->PSP->TransactionReferenceID,
                $invoice->total,
                0,
                'AlsatPardakht'
            );
        } else {
            logTransaction($gatewayParams['name'], array(
                'Code' => 'AlsatPardakht Result',
                'Message' => json_encode($result),
                'Transaction' => isset($_GET['invoice']) ? $_GET['invoice'] : '',
                'Invoice' => $invoice->id,
                'Amount' => $invoice->total,
            ), 'Failure');
        }
        header('Location: '.$gatewayParams['systemurl'].'/viewinvoice.php?id='.$invoice->id);
    } else {
        if (isset($_SESSION['uid'])) {
            $invoice = Capsule::table('tblinvoices')->where('id', $_GET['invoice'])->where('status',
                'Unpaid')->where('userid', $_SESSION['uid'])->first();
            if (!$invoice) {
                die("Invoice not found");
            }
            $client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();
            $amount = ceil($invoice->total * ($gatewayParams['currencyType'] == 'IRR' ? 1 : 10));
            if ($gatewayParams['feeFromClient'] == 'on') {
                $fee = 1 * $amount / 100;
                if ($fee >= 25000) {
                    $fee = 25000;
                }
                $amount += $fee;
            }

            if (isset($gatewayParams['gatewayType'])) {
                if ($gatewayParams['gatewayType'] === 'direct') {
                    $data = array(
                        'Api' => $gatewayParams['apiKey'],
                        'Amount' => "$amount",
                        'InvoiceNumber' => $invoice->id,
                        'RedirectAddress' => $gatewayParams['systemurl'].'/modules/gateways/alsatpardakht.php?invoice='.$invoice->id.'&callback=1',
                    );

                    $result = post_to_alsatpardakht('https://www.alsatpardakht.com/API_V1/sign.php', $data);
                } else {
                    $Tashim[] = [];

                    $Tashim = json_encode($Tashim, JSON_UNESCAPED_UNICODE);

                    $data = array(
                        'ApiKey' => $gatewayParams['apiKey'],
                        'Amount' => "$amount",
                        'Tashim' => $Tashim,
                        'RedirectAddressPage' => $gatewayParams['systemurl'].'/modules/gateways/alsatpardakht.php?invoice='.$invoice->id.'&callback=1',
                    );
                    $result = post_to_alsatpardakht('https://www.alsatpardakht.com/IPGAPI/Api22/send.php', $data);
                }
            } else {
                die("تنظیمات درگاه را بررسی نمائید.");
            }
            if (isset($result)) {
                if ($result->Token) {
                    header("location:https://www.alsatpardakht.com/API_V1/Go.php?Token=$result->Token");
                } else {
                    echo "اتصال به درگاه امکان پذیر نیست: <br>";
                    if ($result->Report) {
                        foreach ($result->Report as $report) {
                            if (gettype($report) === "object") {
                                foreach ($report as $key => $value) {
                                    echo "$value $key<br>";
                                }
                            }
                        }
                    } else {
                        echo "$result->Error";
                    }
                }
            }
        }
    }
    return;
}

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function post_to_alsatpardakht($url, $data = false)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } else {
        return false;
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return false;
    }
    curl_close($ch);
    return !empty($result) ? json_decode($result) : false;
}

function alsatpardakht_MetaData()
{
    return array(
        'DisplayName' => 'ماژول پرداخت آل‌سات پرداخت',
        'APIVersion' => '1.0',
    );
}

function alsatpardakht_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'آل‌سات پرداخت',
        ),
        'currencyType' => array(
            'FriendlyName' => 'نوع ارز',
            'Type' => 'dropdown',
            'Options' => array(
                'IRR' => 'ریال',
                'IRT' => 'تومان',
            ),
        ),
        'gatewayType' => array(
            'FriendlyName' => 'نوع درگاه',
            'Type' => 'dropdown',
            'Options' => array(
                'vaset' => 'واسط',
                'direct' => 'مستقیم',
            ),
        ),
        'apiKey' => array(
            'FriendlyName' => 'کد درگاه (API)',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'کلید دریافتی از سایت آل‌سات پرداخت',
        ),
        'feeFromClient' => array(
            'FriendlyName' => 'دریافت کارمزد از کاربر',
            'Type' => 'yesno',
            'Description' => 'برای دریافت کارمزد از کاربر تیک بزنید',
        ),
    );
}

function alsatpardakht_link($params)
{
    $htmlOutput = '<form method="GET" action="modules/gateways/alsatpardakht.php">';
    $htmlOutput .= '<input type="hidden" name="invoice" value="'.$params['invoiceid'].'">';
    $htmlOutput .= '<input type="submit" value="'.$params['langpaynow'].'" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}
