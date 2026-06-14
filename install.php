<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $plugin;

PluginInstallerHelper::install([

    'modulname'  => 'pricing',
    'name'       => 'Pricing',
    'version'    => 'version'    => (string)($plugin['version'] ?? '0.0.0'),
    'author'     => 'T-Seven',
    'website'    => 'https://www.nexpell.de',
    'path'       => 'includes/plugins/pricing/',

    'admin_file' => 'admin_pricing',
    'index_link' => 'pricing',
    'sidebar'    => 'deactivated',

    'languages' => [
        'plugin_info_pricing' => [
            'de' => 'Mehrsprachige Pricing-Seiten mit Adminverwaltung.',
            'en' => 'Multilingual pricing pages with admin management.',
            'it' => 'Pagine pricing multilingua con gestione admin.'
        ]
    ],

    'permissions' => [
        'pricing'
    ],

    'widgets' => [
        [
            'widget_key'    => 'widget_pricing_content',
            'title'         => 'Pricing Content Widget',
            'description'   => 'Pricing plans overview widget.',
            'allowed_zones' => 'maintop,mainbottom'
        ]
    ],

    'admin_navigation' => [
        [
            'url'   => 'admincenter.php?site=admin_pricing',
            'catID' => 8,
            'sort'  => 1,
            'labels' => [
                'de' => 'Preise & Tarife',
                'en' => 'Pricing',
                'it' => 'Pricing'
            ]
        ]
    ],

    'website_navigation' => [
        [
            'url'        => 'index.php?site=pricing',
            'mnavID'     => 1,
            'sort'       => 1,
            'indropdown' => 1,
            'labels' => [
                'de' => 'Preise & Tarife',
                'en' => 'Pricing',
                'it' => 'Pricing'
            ]
        ]
    ]

]);

safe_query("CREATE TABLE IF NOT EXISTS plugins_pricing_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100),
  title_de VARCHAR(100) NOT NULL DEFAULT '',
  title_en VARCHAR(100) NOT NULL DEFAULT '',
  title_it VARCHAR(100) NOT NULL DEFAULT '',
  target_url VARCHAR(255) NOT NULL DEFAULT '',
  price DECIMAL(10,2),
  price_unit VARCHAR(50) DEFAULT '/ month',
  price_unit_de VARCHAR(50) NOT NULL DEFAULT '',
  price_unit_en VARCHAR(50) NOT NULL DEFAULT '',
  price_unit_it VARCHAR(50) NOT NULL DEFAULT '',
  button_text_de VARCHAR(100) NOT NULL DEFAULT '',
  button_text_en VARCHAR(100) NOT NULL DEFAULT '',
  button_text_it VARCHAR(100) NOT NULL DEFAULT '',
  is_featured TINYINT(1) DEFAULT 0,
  is_advanced TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0
);");

safe_query("CREATE TABLE IF NOT EXISTS plugins_pricing_features (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  feature_text VARCHAR(255) NOT NULL,
  feature_text_de VARCHAR(255) NOT NULL DEFAULT '',
  feature_text_en VARCHAR(255) NOT NULL DEFAULT '',
  feature_text_it VARCHAR(255) NOT NULL DEFAULT '',
  available TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (plan_id) REFERENCES plugins_pricing_plans(id) ON DELETE CASCADE
);");
