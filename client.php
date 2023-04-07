<?php
/**
 * Plugin Name: TrustedLogin Client Test
 */
/**
 * Autoloader for the TrustedLogin Client
 *
 * @param string $class The fully-qualified class name.
 * @see https://www.php-fig.org/psr/psr-4/examples/
 * @return void
 */
spl_autoload_register(function ($class) {
    $prefix = 'TrustedLogin\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
/**
 * Configuration for TrustedLogin Client
 *
 *
 * @see https://docs.trustedlogin.com/Client/configuration
 */
$public_key = '90bd9d918670ea15';
$config = [
    'auth' => [
        'api_key' => $public_key,
    ],
    'vendor' => [
        'namespace' => 'pro-block-builder',
        'title' => 'Pro Block Builder',
        'email' => 'support@example.com',
        'website' => 'https://example.com',
        'support_url' => 'https://help.example.com',
    ],
    'role' => 'editor',
	'caps' => [
		'add' => [
			'gf_full_access' => 'Support will need to see and edit the forms, entries, and Gravity Forms settings on your site.'
		],
	],
	'message_option' => true,
];
$config = new \TrustedLogin\Config( $config );
try {
	new \TrustedLogin\Client(
		$config
	);
} catch ( \Exception $exception ) {
    error_log( $exception->getMessage() );
}
