<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/

$route["default_controller"]        = "api/Home";
$route["token"]                     = "api/Token";

// CLIENT
$route["client/login"]                  = "api/Client_login";
$route["client/registration"]           = "api/Client_registration";

$route["client/cash-in/request"]        = "api/Client_cash_in/request";

$route["client/gift"]                   = "api/Client_gift/send";

$route["client/lookup/pastor-list"]     = "api/Client_lookup/pastor_list";
$route["client/lookup/qr-code"]         = "api/Client_lookup/qr_code";
$route["client/lookup/tx"]              = "api/Client_lookup/tx";

$route["client/balance"]                = "api/Client/balance";
$route["client/tx-history"]             = "api/Client_tx/history";

// MERCHANT
$route["merchant/login"]                  = "api/Pastor_login";
$route["merchant/registration"]           = "api/Pastor_registration";

$route["merchant/cash-in/accept"]         = "api/Pastor_cash_in/accept";

$route["merchant/top-up/request"]         = "api/Pastor_top_up/request";

$route["merchant/lookup/tx"]              = "api/Pastor_lookup/tx";
$route["merchant/tx-history"]             = "api/Pastor_tx/history";

$route["merchant/balance"]                = "api/Pastor/balance";

$route["lookup/merchant-list"]          = "api/Lookup/pastor_list";
$route["lookup/branches"]        = "api/Lookup/church_branches";
$route["lookup/countries"]              = "api/Lookup/countries";
$route["lookup/provinces/(:num)"]       = "api/Lookup/provinces/$1";

// OTP
$route["otp/client/submit"]             = "api/Otp_client_registration/submit";
$route["otp/client/resend"]             = "api/Otp_client_registration/resend";

// QR Code
$route["qr-code/client/(:any)"]         = "public/Qr_code/client_accounts/$1";

$route['404_override'] = 'api/Error_404';
$route['translate_uri_dashes'] = FALSE;

























