<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $_database, $plugin;

$modulname = 'pricing';
$version = isset($plugin['version']) ? (string)$plugin['version'] : ($version ?? '1.0.3.3');
$pluginName = 'Pricing';
$pluginPath = 'includes/plugins/pricing/';

if (!function_exists('pricing_sql')) {
    function pricing_sql($value): string
    {
        return escape((string)$value);
    }
}

if (!function_exists('pricing_schema_ensure_columns')) {
    function pricing_schema_ensure_columns(mysqli $database): void
    {
        $requiredColumns = [
            'title_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_de VARCHAR(100) NOT NULL DEFAULT '' AFTER title",
            'title_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_en VARCHAR(100) NOT NULL DEFAULT '' AFTER title_de",
            'title_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_it VARCHAR(100) NOT NULL DEFAULT '' AFTER title_en",
            'price_unit_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_de VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit",
            'price_unit_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_en VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit_de",
            'price_unit_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_it VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit_en",
            'button_text_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_de VARCHAR(100) NOT NULL DEFAULT '' AFTER price_unit_it",
            'button_text_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_en VARCHAR(100) NOT NULL DEFAULT '' AFTER button_text_de",
            'button_text_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_it VARCHAR(100) NOT NULL DEFAULT '' AFTER button_text_en",
            'feature_text_de' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_de VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text",
            'feature_text_en' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_en VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text_de",
            'feature_text_it' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_it VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text_en",
        ];

        foreach ($requiredColumns as $column => $sql) {
            $table = str_starts_with($column, 'feature_') ? 'plugins_pricing_features' : 'plugins_pricing_plans';
            $check = $database->query("SHOW COLUMNS FROM {$table} LIKE '" . $database->real_escape_string($column) . "'");
            if ($check instanceof mysqli_result && $check->num_rows === 0) {
                $database->query($sql);
            }
        }
    }
}

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

pricing_schema_ensure_columns($_database);

safe_query("UPDATE plugins_pricing_plans SET title_de = IF(title_de = '', title, title_de), title_en = IF(title_en = '', title, title_en), title_it = IF(title_it = '', title, title_it), price_unit_de = IF(price_unit_de = '', price_unit, price_unit_de), price_unit_en = IF(price_unit_en = '', price_unit, price_unit_en), price_unit_it = IF(price_unit_it = '', price_unit, price_unit_it)");
safe_query("UPDATE plugins_pricing_features SET feature_text_de = IF(feature_text_de = '', feature_text, feature_text_de), feature_text_en = IF(feature_text_en = '', feature_text, feature_text_en), feature_text_it = IF(feature_text_it = '', feature_text, feature_text_it)");

$pluginRes = safe_query("SELECT pluginID FROM settings_plugins WHERE modulname = 'pricing' LIMIT 1");
if ($pluginRes && ($pluginRow = mysqli_fetch_assoc($pluginRes))) {
    safe_query("UPDATE settings_plugins SET
        admin_file = 'admin_pricing',
        activate = 1,
        author = 'T-Seven',
        website = 'https://www.nexpell.de',
        index_link = 'pricing',
        hiddenfiles = '',
        version = '" . pricing_sql($version) . "',
        path = '" . pricing_sql($pluginPath) . "',
        status_display = 1,
        plugin_display = 1,
        widget_display = 1,
        delete_display = 1,
        sidebar = 'deactivated'
        WHERE pluginID = " . (int)$pluginRow['pluginID'] . "
    ");
} else {
    safe_query("INSERT INTO settings_plugins
        (modulname, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar)
    VALUES
        ('pricing', 'admin_pricing', 1, 'T-Seven', 'https://www.nexpell.de', 'pricing', '', '" . pricing_sql($version) . "', '" . pricing_sql($pluginPath) . "', 1, 1, 1, 1, 'deactivated')
    ");
}

safe_query("
    INSERT INTO settings_widgets
        (widget_key, title, modulname, plugin, description, allowed_zones, active, version, created_at)
    VALUES
        ('widget_pricing_content', 'Pricing Content Widget', 'pricing', 'pricing', 'Pricing plans overview widget.', 'maintop,mainbottom', 1, '" . pricing_sql($version) . "', NOW())
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        modulname = VALUES(modulname),
        plugin = VALUES(plugin),
        description = VALUES(description),
        allowed_zones = VALUES(allowed_zones),
        active = VALUES(active),
        version = VALUES(version)
");

safe_query("
    INSERT INTO settings_plugins_lang
        (content_key, language, content, modulname, updated_at)
    VALUES
        ('plugin_name_pricing', 'de', 'Pricing', 'pricing', NOW()),
        ('plugin_name_pricing', 'en', 'Pricing', 'pricing', NOW()),
        ('plugin_name_pricing', 'it', 'Pricing', 'pricing', NOW()),
        ('plugin_info_pricing', 'de', 'Mehrsprachige Pricing-Seiten mit Adminverwaltung.', 'pricing', NOW()),
        ('plugin_info_pricing', 'en', 'Multilingual pricing pages with admin management.', 'pricing', NOW()),
        ('plugin_info_pricing', 'it', 'Pagine pricing multilingua con gestione admin.', 'pricing', NOW())
    ON DUPLICATE KEY UPDATE
        content = VALUES(content),
        modulname = VALUES(modulname),
        updated_at = VALUES(updated_at)
");

safe_query("
    INSERT INTO settings_plugins_installed
        (name, modulname, description, version, author, url, folder, installed_date)
    VALUES
        ('Pricing', 'pricing', 'Multilingual pricing pages with admin management.', '" . pricing_sql($version) . "', 'nexpell-team', 'https://www.nexpell.de', 'pricing', NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        version = VALUES(version),
        author = VALUES(author),
        url = VALUES(url),
        folder = VALUES(folder),
        installed_date = NOW()
");

$linkID = 0;
$linkRes = safe_query("
    SELECT linkID FROM navigation_dashboard_links
    WHERE modulname = 'pricing'
    ORDER BY linkID ASC LIMIT 1
");
if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
    $linkID = (int)($linkRow['linkID'] ?? 0);
    safe_query("
        UPDATE navigation_dashboard_links SET
            catID = 8,
            url = 'admincenter.php?site=admin_pricing',
            sort = 1
        WHERE linkID = " . $linkID . "
    ");
} else {
    safe_query("
        INSERT INTO navigation_dashboard_links
            (catID, modulname, url, sort)
        VALUES
            (8, 'pricing', 'admincenter.php?site=admin_pricing', 1)
    ");
    $linkID = (int)mysqli_insert_id($_database);
}

if ($linkID > 0) {
    safe_query("
        INSERT INTO navigation_dashboard_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_link_{$linkID}', 'de', 'Preise & Tarife', 'pricing', NOW()),
            ('nav_link_{$linkID}', 'en', 'Pricing', 'pricing', NOW()),
            ('nav_link_{$linkID}', 'it', 'Pricing', 'pricing', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

$snavID = 0;
$snavRes = safe_query("
    SELECT snavID FROM navigation_website_sub
    WHERE modulname = 'pricing'
    ORDER BY snavID ASC LIMIT 1
");
if ($snavRes && ($snavRow = mysqli_fetch_assoc($snavRes))) {
    $snavID = (int)($snavRow['snavID'] ?? 0);
    safe_query("
        UPDATE navigation_website_sub SET
            mnavID = 1,
            url = 'index.php?site=pricing',
            sort = 1,
            indropdown = 1,
            last_modified = NOW()
        WHERE snavID = " . $snavID . "
    ");
} else {
    safe_query("
        INSERT INTO navigation_website_sub
            (mnavID, modulname, url, sort, indropdown, last_modified)
        VALUES
            (1, 'pricing', 'index.php?site=pricing', 1, 1, NOW())
    ");
    $snavID = (int)mysqli_insert_id($_database);
}

if ($snavID > 0) {
    safe_query("
        INSERT INTO navigation_website_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_sub_{$snavID}', 'de', 'Preise & Tarife', 'pricing', NOW()),
            ('nav_sub_{$snavID}', 'en', 'Pricing', 'pricing', NOW()),
            ('nav_sub_{$snavID}', 'it', 'Pricing', 'pricing', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

safe_query("
    INSERT IGNORE INTO user_role_admin_navi_rights
        (id, roleID, type, modulname)
    VALUES
        ('', 1, 'link', 'pricing')
");
