<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $_database, $str, $modulname, $version, $plugin;

$modulname = 'pricing';
$version = isset($plugin['version']) ? (string)$plugin['version'] : ($version ?? '0.0.0');
$str = 'Pricing';

require __DIR__ . '/install.php';

if (!function_exists('pricing_schema_ensure_columns')) {
    function pricing_schema_ensure_columns(mysqli $database): void
    {
        $plansColumns = [
            'title_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_de VARCHAR(100) NOT NULL DEFAULT '' AFTER title",
            'title_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_en VARCHAR(100) NOT NULL DEFAULT '' AFTER title_de",
            'title_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_it VARCHAR(100) NOT NULL DEFAULT '' AFTER title_en",
            'price_unit_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_de VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit",
            'price_unit_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_en VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit_de",
            'price_unit_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_it VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit_en",
            'button_text_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_de VARCHAR(100) NOT NULL DEFAULT '' AFTER price_unit_it",
            'button_text_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_en VARCHAR(100) NOT NULL DEFAULT '' AFTER button_text_de",
            'button_text_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_it VARCHAR(100) NOT NULL DEFAULT '' AFTER button_text_en"
        ];

        foreach ($plansColumns as $column => $sql) {
            $result = safe_query("SHOW COLUMNS FROM plugins_pricing_plans LIKE '" . mysqli_real_escape_string($database, $column) . "'");
            if (!$result || mysqli_num_rows($result) === 0) {
                safe_query($sql);
            }
        }

        $featuresColumns = [
            'feature_text_de' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_de VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text",
            'feature_text_en' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_en VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text_de",
            'feature_text_it' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_it VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text_en"
        ];

        foreach ($featuresColumns as $column => $sql) {
            $result = safe_query("SHOW COLUMNS FROM plugins_pricing_features LIKE '" . mysqli_real_escape_string($database, $column) . "'");
            if (!$result || mysqli_num_rows($result) === 0) {
                safe_query($sql);
            }
        }
    }
}

pricing_schema_ensure_columns($_database);

safe_query("UPDATE plugins_pricing_plans SET title_de = IF(title_de = '', title, title_de), title_en = IF(title_en = '', title, title_en), title_it = IF(title_it = '', title, title_it), price_unit_de = IF(price_unit_de = '', price_unit, price_unit_de), price_unit_en = IF(price_unit_en = '', price_unit, price_unit_en), price_unit_it = IF(price_unit_it = '', price_unit, price_unit_it)");
safe_query("UPDATE plugins_pricing_features SET feature_text_de = IF(feature_text_de = '', feature_text, feature_text_de), feature_text_en = IF(feature_text_en = '', feature_text, feature_text_en), feature_text_it = IF(feature_text_it = '', feature_text, feature_text_it)");
