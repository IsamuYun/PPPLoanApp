<?php
// ds_config.php
// 
// DocuSign configuration settings
$DS_CONFIG = [
    'quickstart' => 'true',
    'ds_client_id' => '6fae2115-95c9-4165-a4e7-4753652956c2',  // The app's DocuSign integration key
    'ds_client_secret' => 'db3e3746-f2ab-422e-87ee-5aa0b31b8181', // The app's DocuSign integration key's secret
    'signer_email' => 'yun@rmdslab.com',
    'signer_name' => 'Isamu Yun',
    'app_url' => 'http://localhost:8080/public', // The url of the application.
    // Ie, the user enters  app_url in their browser to bring up the app's home page
    // Eg http://localhost/code-examples-php/public if the app is installed in a
    // development directory that is accessible via web server.
    // NOTE => You must add a Redirect URI of app_url/index.php?page=ds_callback to your Integration Key.
    'authorization_server' => 'https://account-d.docusign.com',
    'session_secret' => '{SESSION_SECRET}', // Secret for encrypting session cookie content
    'allow_silent_authentication' => true, // a user can be silently authenticated if they have an
    // active login session on another tab of the same browser
    'target_account_id' => false, // Set if you want a specific DocuSign AccountId, If false, the user's default account will be used.
    'demo_doc_path' => 'demo_documents',
    'doc_docx' => 'World_Wide_Corp_Battle_Plan_Trafalgar.docx',
    'doc_pdf' =>  'World_Wide_Corp_lorem.pdf',
    // Payment gateway information is optional
    'gateway_account_id' => '{GATEWAY_ACCOUNT_ID}',
    'gateway_name' => "stripe",
    'gateway_display_name' => "Stripe",
    'github_example_url' => 'https://github.com/docusign/code-examples-php/tree/master/src/Controllers/Templates',
    'documentation' => false,
];

$JWT_CONFIG = [
    'ds_client_id' => '6fae2115-95c9-4165-a4e7-4753652956c2', // The app's DocuSign integration key
    'authorization_server' => 'account-d.docusign.com',
    "ds_impersonated_user_id" => 'ddacd29b-a870-41f5-b198-55ef2cb34930',  // the id of the user
    "jwt_scope" => "signature impersonation",
    "private_key_file" => "../private.key", // path to private key file

];

$GLOBALS['DS_CONFIG'] = $DS_CONFIG;
$GLOBALS['JWT_CONFIG'] = $JWT_CONFIG;
