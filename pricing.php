<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $_database, $languageService;

// Style-Klasse laden
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'pricing'
];
echo $tpl->loadTemplate("pricing", "head", $data_array, 'plugin');

function pricing_ensure_schema(mysqli $database): void
{
    static $done = false;
    if ($done) {
        return;
    }

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

    $database->query("UPDATE plugins_pricing_plans
        SET
            title_de = IF(title_de = '', title, title_de),
            title_en = IF(title_en = '', title, title_en),
            title_it = IF(title_it = '', title, title_it),
            price_unit_de = IF(price_unit_de = '', price_unit, price_unit_de),
            price_unit_en = IF(price_unit_en = '', price_unit, price_unit_en),
            price_unit_it = IF(price_unit_it = '', price_unit, price_unit_it)");

    $database->query("UPDATE plugins_pricing_features
        SET
            feature_text_de = IF(feature_text_de = '', feature_text, feature_text_de),
            feature_text_en = IF(feature_text_en = '', feature_text, feature_text_en),
            feature_text_it = IF(feature_text_it = '', feature_text, feature_text_it)");

    $done = true;
}

function pricing_current_language(LanguageService $languageService): string
{
    $lang = strtolower((string) $languageService->detectLanguage());
    return in_array($lang, ['de', 'en', 'it'], true) ? $lang : 'en';
}

function pricing_localized_value(array $row, string $baseKey, string $lang): string
{
    $candidates = [$baseKey . '_' . $lang, $baseKey . '_en', $baseKey . '_de', $baseKey . '_it', $baseKey];
    foreach ($candidates as $candidate) {
        $value = trim((string) ($row[$candidate] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function pricing_build_plan_url(int $planId): string
{
    return SeoUrlHandler::convertToSeoUrl('index.php?site=pricing&plan=' . $planId);
}

function pricing_build_contact_url(int $planId): string
{
    return SeoUrlHandler::convertToSeoUrl('index.php?site=contact&pricing_plan=' . $planId);
}

function pricing_build_todo_url(int $planId): string
{
    return SeoUrlHandler::convertToSeoUrl('index.php?site=pricing&todo=' . $planId);
}

pricing_ensure_schema($_database);
$currentLang = pricing_current_language($languageService);

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars((string) ($config['selected_style'] ?? ''), ENT_QUOTES, 'UTF-8');

$plans = [];
$res = $_database->query("SELECT * FROM plugins_pricing_plans ORDER BY sort_order ASC, id ASC");
while ($res && ($plan = $res->fetch_assoc())) {
    $planId = (int) ($plan['id'] ?? 0);
    $plans[$planId] = $plan;
    $plans[$planId]['features'] = [];
}

$res2 = $_database->query("SELECT * FROM plugins_pricing_features ORDER BY plan_id ASC, id ASC");
while ($res2 && ($feat = $res2->fetch_assoc())) {
    $planId = (int) ($feat['plan_id'] ?? 0);
    if (isset($plans[$planId])) {
        $plans[$planId]['features'][] = $feat;
    }
}

$selectedPlanId = isset($_GET['plan']) ? (int) $_GET['plan'] : 0;
$selectedPlan = ($selectedPlanId > 0 && isset($plans[$selectedPlanId])) ? $plans[$selectedPlanId] : null;
$todoPlanId = isset($_GET['todo']) ? (int) $_GET['todo'] : 0;
$todoPlan = ($todoPlanId > 0 && isset($plans[$todoPlanId])) ? $plans[$todoPlanId] : null;
?>
<div class="pricing-page-card">
        <?php if ($todoPlan !== null): ?>
            <?php
            $todoTitle = pricing_localized_value($todoPlan, 'title', $currentLang);
            $todoUnit = pricing_localized_value($todoPlan, 'price_unit', $currentLang);
            $todoButtonUrl = pricing_build_plan_url((int) $todoPlan['id']);
            ?>
            <section class="pricing-detail pricing-todo-view">
                <div class="pricing-detail-topbar">
                    <a href="<?= htmlspecialchars($todoButtonUrl, ENT_QUOTES, 'UTF-8') ?>" class="pricing-backlink">
                        <i class="bi bi-arrow-left"></i>
                        <span><?= htmlspecialchars($languageService->get('back_to_plan'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </div>
                <div class="row g-4 align-items-start pricing-todo-grid">
                    <div class="col-12 col-xl-5">
                        <div class="box<?= !empty($todoPlan['is_featured']) ? ' featured' : '' ?> pricing-detail-card pricing-todo-card h-100">
                            <?php if (!empty($todoPlan['is_advanced'])): ?>
                                <span class="advanced"><?= htmlspecialchars($languageService->get('feature_advanced'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <span class="pricing-todo-badge">In Vorbereitung</span>
                            <div class="pricing-todo-plan-label"><?= htmlspecialchars($todoTitle, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="pricing-todo-price" aria-label="Price">
                                <span class="pricing-todo-currency">EUR</span>
                                <span class="pricing-todo-amount"><?= htmlspecialchars(trim((string) ($todoPlan['price'] ?? '0')), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="pricing-todo-unit"><?= htmlspecialchars($todoUnit, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="pricing-detail-note"><?= htmlspecialchars($languageService->get('todo_intro'), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="pricing-detail-actions">
                                <a href="<?= htmlspecialchars($todoButtonUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-buy"><?= htmlspecialchars($languageService->get('btn_back_to_plan'), ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="pricing-detail-panel pricing-todo-panel">
                            <div class="pricing-todo-panel-head">
                                <h2><?= htmlspecialchars($languageService->get('todo_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="pricing-todo-copy"><?= htmlspecialchars($languageService->get('todo_description'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <ul class="pricing-detail-list pricing-detail-list-positive">
                                <li><?= htmlspecialchars($languageService->get('todo_step_1'), ENT_QUOTES, 'UTF-8') ?></li>
                                <li><?= htmlspecialchars($languageService->get('todo_step_2'), ENT_QUOTES, 'UTF-8') ?></li>
                                <li><?= htmlspecialchars($languageService->get('todo_step_3'), ENT_QUOTES, 'UTF-8') ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        <?php elseif ($selectedPlan !== null): ?>
            <?php
            $priceRaw = trim((string) ($selectedPlan['price'] ?? '0'));
            $localizedTitle = pricing_localized_value($selectedPlan, 'title', $currentLang);
            $localizedUnit = pricing_localized_value($selectedPlan, 'price_unit', $currentLang);
            $targetUrl = trim((string) ($selectedPlan['target_url'] ?? ''));
            $ctaUrl = $targetUrl !== '' ? $targetUrl : pricing_build_todo_url((int) $selectedPlan['id']);
            $buttonText = pricing_localized_value($selectedPlan, 'button_text', $currentLang);
            if ($buttonText === '') {
                $buttonText = $targetUrl !== '' ? $languageService->get('btn_buy_now') : $languageService->get('btn_contact_now');
            }
            $availableFeatures = [];
            $unavailableFeatures = [];
            foreach (($selectedPlan['features'] ?? []) as $feature) {
                $featureText = pricing_localized_value($feature, 'feature_text', $currentLang);
                if ($featureText === '') {
                    continue;
                }
                if ((int) ($feature['available'] ?? 0) === 1) {
                    $availableFeatures[] = $featureText;
                } else {
                    $unavailableFeatures[] = $featureText;
                }
            }
            ?>
            <section class="pricing-detail">
                <div class="pricing-detail-topbar">
                    <a href="<?= htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=pricing'), ENT_QUOTES, 'UTF-8') ?>" class="pricing-backlink">
                        <i class="bi bi-arrow-left"></i>
                        <span><?= htmlspecialchars($languageService->get('back_to_overview'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </div>
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-xl-4">
                        <div class="box<?= !empty($selectedPlan['is_featured']) ? ' featured' : '' ?> pricing-detail-card pricing-main-card h-100">
                            <?php if (!empty($selectedPlan['is_advanced'])): ?>
                                <span class="advanced"><?= htmlspecialchars($languageService->get('feature_advanced'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <div class="pricing-main-plan-label"><?= htmlspecialchars($localizedTitle, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="pricing-main-price" aria-label="Price">
                                <span class="pricing-main-currency">EUR</span>
                                <span class="pricing-main-amount"><?= htmlspecialchars($priceRaw, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="pricing-main-unit"><?= htmlspecialchars($localizedUnit, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php if ($targetUrl === ''): ?>
                                <p class="pricing-detail-note"><?= htmlspecialchars($languageService->get('info_contact_fallback'), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <div class="pricing-detail-actions">
                                <a href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-buy"><?= htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-8">
                        <div class="pricing-detail-panel pricing-main-panel">
                            <h2><?= htmlspecialchars($languageService->get('detail_included'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php if (!empty($availableFeatures)): ?>
                                <ul class="pricing-detail-list pricing-detail-list-positive"><?php foreach ($availableFeatures as $featureText): ?><li><?= htmlspecialchars($featureText, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
                            <?php else: ?>
                                <p class="text-muted mb-0"><?= htmlspecialchars($languageService->get('info_no_features'), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($unavailableFeatures)): ?>
                            <div class="pricing-detail-panel pricing-detail-panel-muted mt-4">
                                <h2><?= htmlspecialchars($languageService->get('detail_not_included'), ENT_QUOTES, 'UTF-8') ?></h2>
                                <ul class="pricing-detail-list pricing-detail-list-negative"><?php foreach ($unavailableFeatures as $featureText): ?><li><?= htmlspecialchars($featureText, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section id="pricing" class="pricing">
                <?php if (empty($plans)): ?>
                    <div class="alert alert-info mb-0" role="alert"><?= htmlspecialchars($languageService->get('no_pricing'), ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php $delay = 0; foreach ($plans as $plan):
                            $featuredClass = !empty($plan['is_featured']) ? ' featured' : '';
                            $advancedLabel = !empty($plan['is_advanced']) ? '<span class="advanced">' . htmlspecialchars($languageService->get('feature_advanced'), ENT_QUOTES, 'UTF-8') . '</span>' : '';
                            $priceRaw = trim((string) ($plan['price'] ?? '0'));
                            $localizedTitle = pricing_localized_value($plan, 'title', $currentLang);
                            $localizedUnit = pricing_localized_value($plan, 'price_unit', $currentLang);
                            $detailUrl = pricing_build_plan_url((int) $plan['id']);
                            $availableFeatures = [];
                            $unavailableFeatures = [];
                            foreach (($plan['features'] ?? []) as $feature) {
                                $featureText = pricing_localized_value($feature, 'feature_text', $currentLang);
                                if ($featureText === '') {
                                    continue;
                                }
                                if ((int) ($feature['available'] ?? 0) === 1) {
                                    $availableFeatures[] = $featureText;
                                } else {
                                    $unavailableFeatures[] = $featureText;
                                }
                            }
                        ?>
                        <div class="col-12 col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                            <div class="box<?= $featuredClass ?> h-100">
                                <?= $advancedLabel ?>
                                <h3><?= htmlspecialchars($localizedTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                                <h4><sup>EUR</sup><?= htmlspecialchars($priceRaw, ENT_QUOTES, 'UTF-8') ?><span><?= htmlspecialchars($localizedUnit, ENT_QUOTES, 'UTF-8') ?></span></h4>
                                <ul><?php foreach ($availableFeatures as $featureText): ?><li><?= htmlspecialchars($featureText, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?><?php foreach ($unavailableFeatures as $featureText): ?><li class="na"><?= htmlspecialchars($featureText, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
                                <div class="btn-wrap mt-auto"><a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-buy"><?= htmlspecialchars($languageService->get('btn_view_details'), ENT_QUOTES, 'UTF-8') ?></a></div>
                            </div>
                        </div>
                        <?php $delay += 100; endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
</div>
