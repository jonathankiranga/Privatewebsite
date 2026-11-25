<?php
require dirname(__DIR__) . '/src/customer-bootstrap.php';

$providers = [
    'google' => [
        'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token',
        'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'client_id_env' => 'SMARTNOW_GOOGLE_CLIENT_ID',
        'client_secret_env' => 'SMARTNOW_GOOGLE_CLIENT_SECRET'
    ],
    'yahoo' => [
        'authorize' => 'https://api.login.yahoo.com/oauth2/request_auth',
        'token' => 'https://api.login.yahoo.com/oauth2/get_token',
        'userinfo' => 'https://api.login.yahoo.com/openid/v1/userinfo',
        'client_id_env' => 'SMARTNOW_YAHOO_CLIENT_ID',
        'client_secret_env' => 'SMARTNOW_YAHOO_CLIENT_SECRET'
    ]
];

function customer_oauth_fail(): void
{
    customer_redirect('customer-login.php?social=unavailable');
}
function customer_oauth_request(string $url, array $options = []): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('cURL is required for social sign-in.');
    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        error_log('Customer OAuth HTTP request failed: '.curl_error($ch));
        curl_close($ch);
        throw new RuntimeException('Social sign-in provider request failed.');
    }
    curl_close($ch);
    $data = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        error_log('Customer OAuth provider returned HTTP '.$status.'.');
        throw new RuntimeException('Social sign-in provider response was invalid.');
    }
    return $data;
}

$provider = strtolower((string)($_GET['provider'] ?? ''));
if (!isset($providers[$provider])) customer_oauth_fail();
$config = $providers[$provider];
$clientId = (string)getenv($config['client_id_env']);
$clientSecret = (string)getenv($config['client_secret_env']);
$portal = rtrim((string)getenv('SMARTNOW_CUSTOMER_PORTAL_URL'), '/');
if (!$clientId || !$clientSecret || !$portal || strtolower((string)parse_url($portal, PHP_URL_SCHEME)) !== 'https') {
    error_log('Customer social sign-in is not configured for '.$provider.'.');
    customer_oauth_fail();
}
$redirectUri = $portal.'/customer-oauth.php?provider='.rawurlencode($provider);

if (isset($_GET['error'])) customer_oauth_fail();

if (!isset($_GET['code'])) {
    if (customer_current()) customer_redirect('customer-dashboard.php');
    $state = bin2hex(random_bytes(32));
    $_SESSION['customer_oauth_state'] = $state;
    $_SESSION['customer_oauth_provider'] = $provider;
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state
    ];
    customer_redirect($config['authorize'].'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

$state = (string)($_GET['state'] ?? '');
$expectedState = (string)($_SESSION['customer_oauth_state'] ?? '');
$expectedProvider = (string)($_SESSION['customer_oauth_provider'] ?? '');
unset($_SESSION['customer_oauth_state'], $_SESSION['customer_oauth_provider']);
if (!$state || !$expectedState || !hash_equals($expectedState, $state) || $expectedProvider !== $provider || isset($_GET['error'])) {
    customer_oauth_fail();
}

try {
    $tokenOptions = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => (string)$_GET['code'],
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ]),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']
    ];
    if ($provider === 'yahoo') {
        $tokenOptions[CURLOPT_HTTPHEADER] = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret)
        ];
        $tokenOptions[CURLOPT_POSTFIELDS] = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => (string)$_GET['code'],
            'redirect_uri' => $redirectUri
        ]);
    }
    $token = customer_oauth_request($config['token'], $tokenOptions);
    if (empty($token['access_token'])) throw new RuntimeException('OAuth access token missing.');
    $profile = customer_oauth_request($config['userinfo'], [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer '.$token['access_token']]
    ]);
    $subject = trim((string)($profile['sub'] ?? ''));
    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $emailVerified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($subject === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
        throw new RuntimeException('Provider did not supply a verified email identity.');
    }

    $db = customer_db();
    $displayName = trim((string)($profile['name'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string)($profile['given_name'] ?? '').' '.(string)($profile['family_name'] ?? ''));
    }
    if ($displayName === '') $displayName = strstr($email, '@', true) ?: 'Customer';
    $displayName = substr($displayName, 0, 160);

    /*
     * Provider-verified email is sufficient proof of mailbox ownership.
     * Create or attach the customer record and provider identity atomically.
     * A random password hash is stored because password_hash is NOT NULL;
     * the random password itself is discarded and cannot be used to sign in.
     */
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT a.id,a.customer_id,a.email_verified_at,a.updated_at,c.email,c.contact_name,c.organisation_name,c.status FROM platform_customer_identities i INNER JOIN platform_customer_accounts a ON a.id=i.account_id INNER JOIN platform_customers c ON c.id=a.customer_id WHERE i.provider=? AND i.provider_subject=? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('ss', $provider, $subject);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        if (!$account) {
            $stmt = $db->prepare('SELECT id,status,contact_name,organisation_name FROM platform_customers WHERE email=? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();

            if ($customer && in_array($customer['status'], ['suspended', 'closed'], true)) {
                throw new RuntimeException('The matching customer record is suspended or closed.');
            }

            if (!$customer) {
                $status = 'lead';
                $emptyPhone = '';
                $emptyCountry = '';
                $emptyOrganisation = '';
                $stmt = $db->prepare('INSERT INTO platform_customers (email,contact_name,phone,country_code,organisation_name,status) VALUES (?,?,?,?,?,?)');
                $stmt->bind_param('ssssss', $email, $displayName, $emptyPhone, $emptyCountry, $emptyOrganisation, $status);
                $stmt->execute();
                $customerId = (int)$db->insert_id;
            } else {
                $customerId = (int)$customer['id'];
                if (trim((string)$customer['contact_name']) === '') {
                    $stmt = $db->prepare('UPDATE platform_customers SET contact_name=? WHERE id=?');
                    $stmt->bind_param('si', $displayName, $customerId);
                    $stmt->execute();
                }
            }

            $stmt = $db->prepare('SELECT id FROM platform_customer_accounts WHERE customer_id=? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('i', $customerId);
            $stmt->execute();
            $existingAccount = $stmt->get_result()->fetch_assoc();

            if ($existingAccount) {
                $accountId = (int)$existingAccount['id'];
                // The provider has independently verified this exact email.
                $stmt = $db->prepare('UPDATE platform_customer_accounts SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?');
                $stmt->bind_param('i', $accountId);
                $stmt->execute();
            } else {
                $randomPassword = bin2hex(random_bytes(48));
                $unusablePasswordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
                unset($randomPassword);
                $stmt = $db->prepare('INSERT INTO platform_customer_accounts (customer_id,password_hash,email_verified_at) VALUES (?,?,NOW())');
                $stmt->bind_param('is', $customerId, $unusablePasswordHash);
                $stmt->execute();
                $accountId = (int)$db->insert_id;
            }

            $stmt = $db->prepare('SELECT a.id,a.customer_id,a.email_verified_at,a.updated_at,c.email,c.contact_name,c.organisation_name,c.status FROM platform_customer_accounts a INNER JOIN platform_customers c ON c.id=a.customer_id WHERE a.id=? LIMIT 1');
            $stmt->bind_param('i', $accountId);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            if (!$account) throw new RuntimeException('The customer account could not be loaded after registration.');

            $stmt = $db->prepare('INSERT INTO platform_customer_identities (account_id,provider,provider_subject,provider_email,last_login_at) VALUES (?,?,?,?,NOW())');
            $stmt->bind_param('isss', $accountId, $provider, $subject, $email);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('UPDATE platform_customer_identities SET provider_email=?,last_login_at=NOW() WHERE provider=? AND provider_subject=?');
            $stmt->bind_param('sss', $email, $provider, $subject);
            $stmt->execute();
        }

        if (!$account['email_verified_at'] || in_array($account['status'], ['suspended', 'closed'], true)) {
            throw new RuntimeException('Customer account is unavailable.');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    customer_establish_login($account, true);
    $accountId = (int)$account['id'];
    $stmt = $db->prepare('UPDATE platform_customer_accounts SET last_login_at=NOW() WHERE id=?');
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    customer_redirect('customer-dashboard.php');
} catch (Throwable $e) {
    error_log('Customer social sign-in failed for '.$provider.': '.$e->getMessage());
    customer_oauth_fail();
}
